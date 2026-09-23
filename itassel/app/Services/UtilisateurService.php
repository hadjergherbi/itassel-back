<?php

namespace App\Services;

use App\Exceptions\ConflitMetier;
use App\Models\Doleance;
use App\Models\Historique;
use App\Models\JetonMotDePasse;
use App\Models\Service;
use App\Models\Statut;
use App\Models\Utilisateur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UtilisateurService
{
    public static function modifier(Utilisateur $cible, array $donnees, Utilisateur $acteur, Request $request): Utilisateur
    {
        if (array_key_exists('id_service', $donnees)
            && (int) ($donnees['id_service'] ?? 0) !== (int) ($cible->id_service ?? 0)
            && static::bloqueChangementService($cible)
        ) {
            throw new ConflitMetier(
                'dossiers_en_cours',
                'Ce compte est responsable de dossiers ouverts ou d\'un service : le service ne peut pas être modifié.',
            );
        }

        $cible->fill([
            'nom'    => $donnees['nom'] ?? $cible->nom,
            'prenom' => $donnees['prenom'] ?? $cible->prenom,
            'email'  => $donnees['email'] ?? $cible->email,
        ]);

        if (array_key_exists('id_service', $donnees) && $cible->role === 'admin_service') {
            $cible->id_service = $donnees['id_service'];
        }

        $cible->save();

        JournalService::action($request, $acteur, 'modification_utilisateur', $cible->nomComplet(), $cible);

        return $cible->fresh('service');
    }

    public static function activer(Utilisateur $cible, Utilisateur $acteur, Request $request): Utilisateur
    {
        $cible->actif = true;
        $cible->save();

        JournalService::action($request, $acteur, 'activation_utilisateur', $cible->nomComplet(), $cible);

        return $cible->fresh('service');
    }

    /**
     * @return array{utilisateur: Utilisateur, dossiers_transferes: int}
     */
    public static function desactiver(Utilisateur $cible, Utilisateur $acteur, ?Utilisateur $remplacant, Request $request): array
    {
        if ((int) $cible->id_utilisateur === (int) $acteur->id_utilisateur) {
            throw ValidationException::withMessages([
                'id_utilisateur' => ['Vous ne pouvez pas désactiver votre propre compte.'],
            ]);
        }

        if ($cible->role === 'super_admin' && static::dernierSuperAdminActif($cible)) {
            throw new ConflitMetier('dernier_super_admin', 'Impossible de désactiver le dernier Super administrateur actif.');
        }

        $serviceGere = Service::where('id_responsable', $cible->id_utilisateur)->first();
        $serviceRef = $serviceGere ?? $cible->service;
        if ($remplacant && $serviceRef) {
            static::verifierRemplacant($remplacant, $serviceRef);
        }

        $dossiersTransferes = DB::transaction(function () use ($cible, $acteur, $remplacant, $serviceGere) {
            if ($serviceGere) {
                $serviceGere->update(['id_responsable' => $remplacant?->id_utilisateur]);
            }

            $dossiers = Doleance::where('id_responsable', $cible->id_utilisateur)
                ->whereHas('statut', fn ($q) => $q->whereIn('code', Statut::codesOuverts()))
                ->get();

            foreach ($dossiers as $doleance) {
                $doleance->update(['id_responsable' => $remplacant?->id_utilisateur]);

                Historique::create([
                    'date_evenement'    => now(),
                    'type_evenement'    => 'changement_responsable',
                    'detail'            => $remplacant
                        ? "Responsable : {$cible->nomComplet()} → {$remplacant->nomComplet()}"
                        : "Responsable retiré : {$cible->nomComplet()}",
                    'visible_demandeur' => false,
                    'id_doleance'       => $doleance->id_doleance,
                    'id_utilisateur'    => $acteur->id_utilisateur,
                ]);
            }

            $cible->actif = false;
            $cible->save();
            $cible->tokens()->delete();

            JetonMotDePasse::where('id_utilisateur', $cible->id_utilisateur)
                ->whereNull('utilise_le')
                ->update(['utilise_le' => now()]);

            return $dossiers->count();
        });

        JournalService::action($request, $acteur, 'desactivation_utilisateur', $cible->nomComplet(), $cible);

        return [
            'utilisateur'         => $cible->fresh('service'),
            'dossiers_transferes' => $dossiersTransferes,
        ];
    }

    public static function supprimer(Utilisateur $cible, Utilisateur $acteur, Request $request): void
    {
        if ((int) $cible->id_utilisateur === (int) $acteur->id_utilisateur) {
            throw ValidationException::withMessages([
                'id_utilisateur' => ['Vous ne pouvez pas supprimer votre propre compte.'],
            ]);
        }

        if ($cible->role === 'super_admin' && static::dernierSuperAdminActif($cible)) {
            throw new ConflitMetier('dernier_super_admin', 'Impossible de supprimer le dernier Super administrateur actif.');
        }

        if (static::estEncoreAffecte($cible)) {
            throw new ConflitMetier(
                'utilisateur_affecte',
                'Ce compte est encore responsable : désactivez-le en désignant un remplaçant.',
            );
        }

        $nom = $cible->nomComplet();
        $ancienEmail = $cible->email;

        $cible->tokens()->delete();
        JetonMotDePasse::where('id_utilisateur', $cible->id_utilisateur)
            ->whereNull('utilise_le')
            ->delete();

        $cible->actif = false;
        $cible->email = 'supprime.'.$cible->id_utilisateur.'.'.now()->timestamp.'@itassel.invalid';
        $cible->save();
        $cible->delete();

        JournalService::action($request, $acteur, 'suppression_utilisateur', "{$nom} — {$ancienEmail}");
    }

    public static function impact(Utilisateur $cible, Utilisateur $acteur): array
    {
        $cible->loadMissing(['service', 'roleModele']);
        $serviceGere = Service::where('id_responsable', $cible->id_utilisateur)->first();
        $estResponsable = $serviceGere !== null;
        $raison = static::raisonBlocageSuppression($cible, $acteur);

        return [
            'utilisateur' => [
                'id_utilisateur' => $cible->id_utilisateur,
                'nom'            => $cible->nom,
                'prenom'         => $cible->prenom,
                'role'           => $cible->role,
                'libelle_role'   => $cible->libelleRole(),
                'actif'          => $cible->actif,
                'service'        => $cible->service
                    ? [
                        'id_service'  => $cible->service->id_service,
                        'nom_service' => $cible->service->nom_service,
                    ]
                    : null,
            ],
            'est_responsable_service' => $estResponsable,
            'dossiers_service'        => $estResponsable
                ? static::compterOuvertes(Doleance::where('id_service', $serviceGere->id_service))
                : null,
            'dossiers_suivis'         => static::compterOuvertes(
                Doleance::where('id_responsable', $cible->id_utilisateur)
            ),
            'remplacants_possibles'   => static::remplacantsPossibles($cible),
            'a_historique'            => static::aHistorique($cible),
            'suppression_possible'    => $raison === null,
            'raison_blocage'          => $raison,
        ];
    }

    private static function bloqueChangementService(Utilisateur $cible): bool
    {
        $ouvert = Doleance::where('id_responsable', $cible->id_utilisateur)
            ->whereHas('statut', fn ($q) => $q->whereIn('code', Statut::codesOuverts()))
            ->exists();

        return $ouvert || Service::where('id_responsable', $cible->id_utilisateur)->exists();
    }

    private static function dernierSuperAdminActif(Utilisateur $cible): bool
    {
        return ! Utilisateur::where('role', 'super_admin')
            ->where('actif', true)
            ->where('id_utilisateur', '!=', $cible->id_utilisateur)
            ->exists();
    }

    private static function verifierRemplacant(Utilisateur $remplacant, Service $service): void
    {
        if (! $remplacant->actif
            || $remplacant->role !== 'admin_service'
            || (int) $remplacant->id_service !== (int) $service->id_service
            || $remplacant->mot_de_passe_defini_le === null
        ) {
            throw ValidationException::withMessages([
                'id_remplacant' => ['Le remplaçant doit être un administrateur actif du même service.'],
            ]);
        }
    }

    private static function estEncoreAffecte(Utilisateur $cible): bool
    {
        return Service::where('id_responsable', $cible->id_utilisateur)->exists()
            || Doleance::where('id_responsable', $cible->id_utilisateur)
                ->whereHas('statut', fn ($q) => $q->whereIn('code', Statut::codesOuverts()))
                ->exists();
    }

    private static function raisonBlocageSuppression(Utilisateur $cible, Utilisateur $acteur): ?string
    {
        if ((int) $cible->id_utilisateur === (int) $acteur->id_utilisateur) {
            return 'soi_meme';
        }

        if ($cible->role === 'super_admin' && static::dernierSuperAdminActif($cible)) {
            return 'dernier_super_admin';
        }

        if (static::estEncoreAffecte($cible)) {
            return 'utilisateur_affecte';
        }

        return null;
    }

    private static function compterOuvertes($query): array
    {
        $dossiers = $query->whereHas('statut', fn ($q) => $q->whereIn('code', Statut::codesOuverts()))
            ->with('statut')
            ->get();

        $parCode = $dossiers->groupBy(fn (Doleance $d) => $d->statut?->code);

        return [
            'total'                 => $dossiers->count(),
            'nouvelles'             => $parCode->get(Statut::NOUVELLE, collect())->count(),
            'en_cours'              => $parCode->get(Statut::EN_COURS, collect())->count(),
            'information_demandee'  => $parCode->get(Statut::INFORMATION_DEMANDEE, collect())->count(),
        ];
    }

    private static function remplacantsPossibles(Utilisateur $cible): array
    {
        if (! $cible->id_service) {
            return [];
        }

        return Utilisateur::query()
            ->where('role', 'admin_service')
            ->where('actif', true)
            ->where('id_service', $cible->id_service)
            ->whereNotNull('mot_de_passe_defini_le')
            ->where('id_utilisateur', '!=', $cible->id_utilisateur)
            ->orderBy('nom')
            ->get()
            ->map(fn (Utilisateur $u) => [
                'id_utilisateur' => $u->id_utilisateur,
                'nom'            => $u->nom,
                'prenom'         => $u->prenom,
                'libelle_role'   => $u->libelleRole(),
            ])
            ->values()
            ->all();
    }

    private static function aHistorique(Utilisateur $cible): bool
    {
        $id = $cible->id_utilisateur;

        return Historique::where('id_utilisateur', $id)->exists()
            || \App\Models\Reponse::where('id_auteur', $id)->exists()
            || \App\Models\NoteInterne::where('id_auteur', $id)->exists()
            || \App\Models\Complement::where(fn ($q) => $q->where('id_auteur', $id)->orWhere('id_annule_par', $id))->exists()
            || \App\Models\Reaffectation::where(fn ($q) => $q->where('id_demandeur', $id)->orWhere('id_decideur', $id))->exists();
    }
}
