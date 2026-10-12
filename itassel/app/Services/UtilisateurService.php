<?php

namespace App\Services;

use App\Exceptions\ConflitMetier;
use App\Models\Complement;
use App\Models\Doleance;
use App\Models\Historique;
use App\Models\JetonMotDePasse;
use App\Models\NoteInterne;
use App\Models\Reaffectation;
use App\Models\Reponse;
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
        $cible = DB::transaction(function () use ($cible, $donnees, $acteur, $request) {
            $role = $donnees['role'] ?? $cible->role;

            if ($cible->role === 'super_admin' && $role !== 'super_admin' && static::dernierSuperAdminActif($cible)) {
                throw new ConflitMetier(
                    'dernier_super_admin',
                    'Impossible de retirer le rôle du dernier Super administrateur actif.',
                );
            }

            $anciens = $cible->idsServices();
            $toucheServices = $role !== $cible->role
                || array_key_exists('ids_services', $donnees)
                || array_key_exists('id_service', $donnees)
                || array_key_exists('id_service_principal', $donnees);

            if ($role === 'super_admin') {
                $nouveaux = [];
                $principal = null;
            } elseif ($toucheServices) {
                if (! array_key_exists('ids_services', $donnees)) {
                    $donnees['ids_services'] = $anciens;
                }
                $affectation = static::resoudreAffectation($donnees);
                $nouveaux = $affectation['ids'];
                $principal = $affectation['principal'];
            } else {
                $nouveaux = $anciens;
                $principal = $cible->id_service ? (int) $cible->id_service : null;
            }

            $retires = array_values(array_diff($anciens, $nouveaux));
            if ($retires !== []) {
                static::appliquerRetraitServices($cible, $retires, $donnees['id_remplacant'] ?? null, $acteur);
            }

            $cible->fill([
                'nom' => $donnees['nom'] ?? $cible->nom,
                'prenom' => $donnees['prenom'] ?? $cible->prenom,
                'email' => $donnees['email'] ?? $cible->email,
            ]);
            $cible->role = $role;
            $cible->id_service = $role === 'admin_service' ? $principal : null;
            $cible->save();

            if ($toucheServices) {
                static::synchroniserServices($cible, $nouveaux);
            }

            JournalService::action(
                $request,
                $acteur,
                'modification_utilisateur',
                static::detailServices($cible->nomComplet(), $anciens, $nouveaux),
                $cible,
            );

            return $cible;
        });

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

        $servicesGeres = Service::where('id_responsable', $cible->id_utilisateur)->get();
        if ($remplacant) {
            $references = $servicesGeres->isNotEmpty()
                ? $servicesGeres
                : collect([$cible->service])->filter();
            foreach ($references as $service) {
                static::verifierRemplacant($remplacant, $service);
            }
        }

        if ($cible->role === 'admin_service') {
            static::verifierAutreAdminParService($cible, $remplacant);
        }

        $dossiersTransferes = DB::transaction(function () use ($cible, $acteur, $remplacant, $servicesGeres) {
            foreach ($servicesGeres as $serviceGere) {
                $serviceGere->update(['id_responsable' => $remplacant?->id_utilisateur]);
            }

            $dossiersTransferes = static::reassignerDossiersOuverts($cible, $remplacant, $acteur);

            $cible->actif = false;
            $cible->save();
            $cible->tokens()->delete();

            JetonMotDePasse::where('id_utilisateur', $cible->id_utilisateur)
                ->whereNull('utilise_le')
                ->update(['utilise_le' => now()]);

            return $dossiersTransferes;
        });

        JournalService::action($request, $acteur, 'desactivation_utilisateur', $cible->nomComplet(), $cible);

        return [
            'utilisateur' => $cible->fresh('service'),
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
                'nom' => $cible->nom,
                'prenom' => $cible->prenom,
                'role' => $cible->role,
                'libelle_role' => $cible->libelleRole(),
                'actif' => $cible->actif,
                'service' => $cible->service
                    ? [
                        'id_service' => $cible->service->id_service,
                        'nom_service' => $cible->service->nom_service,
                    ]
                    : null,
            ],
            'est_responsable_service' => $estResponsable,
            'dossiers_service' => $estResponsable
                ? static::compterOuvertes(Doleance::where('id_service', $serviceGere->id_service))
                : null,
            'dossiers_suivis' => static::compterOuvertes(
                Doleance::where('id_responsable', $cible->id_utilisateur)
            ),
            'remplacants_possibles' => static::remplacantsPossibles($cible),
            'a_historique' => static::aHistorique($cible),
            'suppression_possible' => $raison === null,
            'raison_blocage' => $raison,
        ];
    }

    /**
     * @param  array<string, mixed>  $donnees
     * @return array{ids: list<int>, principal: int}
     */
    public static function resoudreAffectation(array $donnees): array
    {
        $ids = array_values(array_unique(array_map('intval', $donnees['ids_services'] ?? [])));

        if ($ids === []) {
            throw ValidationException::withMessages([
                'ids_services' => ['Un administrateur de service doit être rattaché à au moins un service.'],
            ]);
        }

        if (count($ids) > 10) {
            throw ValidationException::withMessages([
                'ids_services' => ['Un administrateur de service ne peut pas gérer plus de 10 services.'],
            ]);
        }

        $principal = array_key_exists('id_service_principal', $donnees) && $donnees['id_service_principal'] !== null
            ? (int) $donnees['id_service_principal']
            : $ids[0];

        if (! in_array($principal, $ids, true)) {
            throw ValidationException::withMessages([
                'id_service_principal' => ['Le service principal doit faire partie des services affectés.'],
            ]);
        }

        return ['ids' => $ids, 'principal' => $principal];
    }

    /**
     * @param  list<int>  $ids
     */
    public static function synchroniserServices(Utilisateur $utilisateur, array $ids): void
    {
        if ($utilisateur->role !== 'admin_service' || $ids === []) {
            $utilisateur->services()->detach(null, false);
        } else {
            $utilisateur->services()->sync(array_map('intval', $ids));
        }

        $utilisateur->oublierIdsServices();
    }

    /**
     * @param  list<int>  $anciens
     * @param  list<int>  $nouveaux
     */
    public static function detailServices(string $nom, array $anciens, array $nouveaux, bool $creation = false): string
    {
        $nouvelleListe = static::libelleServices($nouveaux);

        if ($creation) {
            return "{$nom} — services : {$nouvelleListe}";
        }

        return "{$nom} — services : ".static::libelleServices($anciens)." → {$nouvelleListe}";
    }

    /**
     * @param  list<int>  $idsRetires
     */
    private static function appliquerRetraitServices(Utilisateur $cible, array $idsRetires, ?int $idRemplacant, Utilisateur $acteur): void
    {
        $services = Service::whereIn('id_service', $idsRetires)->get()->keyBy('id_service');
        $bloques = [];

        foreach ($idsRetires as $id) {
            $service = $services->first(fn (Service $service) => (int) $service->id_service === (int) $id);
            if ($service && static::responsableDuService($cible, (int) $id)) {
                $bloques[] = $service;
            }
        }

        if ($bloques === []) {
            return;
        }

        $noms = collect($bloques)->pluck('nom_service')->implode(', ');
        if (! $idRemplacant) {
            throw ValidationException::withMessages([
                'ids_services' => ["Impossible de retirer {$noms} : ce compte en est responsable ou suit des dossiers ouverts de ce service. Désignez un remplaçant."],
            ]);
        }

        $remplacant = Utilisateur::find($idRemplacant);
        if (! $remplacant) {
            throw ValidationException::withMessages([
                'id_remplacant' => ['Le remplaçant est introuvable.'],
            ]);
        }

        foreach ($bloques as $service) {
            static::verifierRemplacant($remplacant, $service);
            if ((int) $service->id_responsable === (int) $cible->id_utilisateur) {
                $service->update(['id_responsable' => $remplacant->id_utilisateur]);
            }
            static::reassignerDossiersOuverts($cible, $remplacant, $acteur, (int) $service->id_service);
        }
    }

    private static function responsableDuService(Utilisateur $cible, int $idService): bool
    {
        $responsable = Service::where('id_service', $idService)
            ->where('id_responsable', $cible->id_utilisateur)
            ->exists();

        if ($responsable) {
            return true;
        }

        return Doleance::where('id_service', $idService)
            ->where('id_responsable', $cible->id_utilisateur)
            ->whereHas('statut', fn ($q) => $q->whereIn('code', Statut::codesOuverts()))
            ->exists();
    }

    private static function reassignerDossiersOuverts(
        Utilisateur $cible,
        ?Utilisateur $remplacant,
        Utilisateur $acteur,
        ?int $idService = null,
    ): int {
        $dossiers = Doleance::where('id_responsable', $cible->id_utilisateur)
            ->when($idService !== null, fn ($q) => $q->where('id_service', $idService))
            ->whereHas('statut', fn ($q) => $q->whereIn('code', Statut::codesOuverts()))
            ->get();

        foreach ($dossiers as $doleance) {
            $doleance->update(['id_responsable' => $remplacant?->id_utilisateur]);

            Historique::create([
                'date_evenement' => now(),
                'type_evenement' => 'changement_responsable',
                'detail' => $remplacant
                    ? "Responsable : {$cible->nomComplet()} → {$remplacant->nomComplet()}"
                    : "Responsable retiré : {$cible->nomComplet()}",
                'visible_demandeur' => false,
                'id_doleance' => $doleance->id_doleance,
                'id_utilisateur' => $acteur->id_utilisateur,
            ]);
        }

        return $dossiers->count();
    }

    /**
     * @param  list<int>  $ids
     */
    private static function libelleServices(array $ids): string
    {
        if ($ids === []) {
            return 'aucun';
        }

        $noms = Service::whereIn('id_service', $ids)->pluck('nom_service', 'id_service');
        $liste = [];
        foreach ($ids as $id) {
            $liste[] = (string) ($noms->get($id) ?? $noms->get((string) $id) ?? $id);
        }

        return implode(', ', $liste);
    }

    private static function dernierSuperAdminActif(Utilisateur $cible): bool
    {
        return ! Utilisateur::where('role', 'super_admin')
            ->where('actif', true)
            ->where('id_utilisateur', '!=', $cible->id_utilisateur)
            ->exists();
    }

    private static function verifierAutreAdminParService(Utilisateur $cible, ?Utilisateur $remplacant): void
    {
        foreach ($cible->idsServices() as $idService) {
            $autre = Utilisateur::query()
                ->where('role', 'admin_service')
                ->where('actif', true)
                ->where('id_utilisateur', '!=', $cible->id_utilisateur)
                ->whereHas('services', fn ($q) => $q->where('services.id_service', $idService))
                ->exists();

            if ($autre) {
                continue;
            }

            if ($remplacant && $remplacant->gereLeService($idService)) {
                continue;
            }

            $nom = Service::whereKey($idService)->value('nom_service') ?? (string) $idService;
            throw ValidationException::withMessages([
                'id_remplacant' => ["Le service {$nom} n'a pas d'autre administrateur. Désignez un remplaçant qui gère ce service."],
            ]);
        }
    }

    private static function verifierRemplacant(Utilisateur $remplacant, Service $service): void
    {
        if (! $remplacant->actif
            || $remplacant->role !== 'admin_service'
            || ! $remplacant->gereLeService((int) $service->id_service)
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
            'total' => $dossiers->count(),
            'nouvelles' => $parCode->get(Statut::NOUVELLE, collect())->count(),
            'en_cours' => $parCode->get(Statut::EN_COURS, collect())->count(),
            'information_demandee' => $parCode->get(Statut::INFORMATION_DEMANDEE, collect())->count(),
        ];
    }

    private static function remplacantsPossibles(Utilisateur $cible): array
    {
        $ids = $cible->idsServices();
        if ($ids === []) {
            return [];
        }

        return Utilisateur::query()
            ->where('role', 'admin_service')
            ->where('actif', true)
            ->whereHas('services', fn ($q) => $q->whereIn('services.id_service', $ids))
            ->whereNotNull('mot_de_passe_defini_le')
            ->where('id_utilisateur', '!=', $cible->id_utilisateur)
            ->orderBy('nom')
            ->get()
            ->map(fn (Utilisateur $u) => [
                'id_utilisateur' => $u->id_utilisateur,
                'nom' => $u->nom,
                'prenom' => $u->prenom,
                'libelle_role' => $u->libelleRole(),
            ])
            ->values()
            ->all();
    }

    private static function aHistorique(Utilisateur $cible): bool
    {
        $id = $cible->id_utilisateur;

        return Historique::where('id_utilisateur', $id)->exists()
            || Reponse::where('id_auteur', $id)->exists()
            || NoteInterne::where('id_auteur', $id)->exists()
            || Complement::where(fn ($q) => $q->where('id_auteur', $id)->orWhere('id_annule_par', $id))->exists()
            || Reaffectation::where(fn ($q) => $q->where('id_demandeur', $id)->orWhere('id_decideur', $id))->exists();
    }
}
