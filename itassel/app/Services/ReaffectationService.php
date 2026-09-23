<?php

namespace App\Services;

use App\Mail\NotificationInterneMail;
use App\Models\Doleance;
use App\Models\Historique;
use App\Models\Reaffectation;
use App\Models\Service;
use App\Models\Statut;
use App\Models\Utilisateur;
use Illuminate\Support\Collection;

class ReaffectationService
{
    public static function appliquerAuDossier(Doleance $doleance, Service $destination): void
    {
        $doleance->update([
            'id_service'      => $destination->id_service,
            'id_responsable'  => $destination->id_responsable,
        ]);
        $doleance->unsetRelation('service');
        $doleance->unsetRelation('responsable');
    }

    public static function passerSansSuite(Doleance $doleance, Utilisateur $acteur): void
    {
        $enAttente = Reaffectation::where('id_doleance', $doleance->id_doleance)
            ->where('etat', 'en_attente')
            ->get();

        if ($enAttente->isEmpty()) {
            return;
        }

        foreach ($enAttente as $demande) {
            $demande->update([
                'etat'          => 'sans_suite',
                'date_decision' => now(),
            ]);
        }

        Historique::create([
            'date_evenement'    => now(),
            'type_evenement'    => 'reaffectation_sans_suite',
            'detail'            => 'Demande de réaffectation classée sans suite : dossier conclu.',
            'visible_demandeur' => false,
            'id_doleance'       => $doleance->id_doleance,
            'id_utilisateur'    => $acteur->id_utilisateur,
        ]);
    }

    public static function notifierSuperAdmins(Doleance $doleance, string $titre, string $texte, ?int $idEvenement): void
    {
        static::destinatairesSuperAdmins()->each(function (Utilisateur $admin) use ($doleance, $titre, $texte, $idEvenement) {
            NotificationService::envoyerA(
                $admin->email,
                $doleance,
                'interne_reaffectation',
                new NotificationInterneMail($doleance, $titre, $texte),
                $idEvenement,
            );
        });
    }

    public static function notifier(string $destinataire, Doleance $doleance, string $titre, string $texte, ?int $idEvenement): void
    {
        if (trim($destinataire) === '') {
            return;
        }

        NotificationService::envoyerA(
            $destinataire,
            $doleance,
            'interne_reaffectation',
            new NotificationInterneMail($doleance, $titre, $texte),
            $idEvenement,
        );
    }

    public static function destinatairesSuperAdmins(): Collection
    {
        return Utilisateur::query()
            ->where('role', 'super_admin')
            ->where('actif', true)
            ->get();
    }

    public static function dossierConclu(Doleance $doleance): bool
    {
        $doleance->loadMissing('statut');
        $code = $doleance->statut?->code;

        return Statut::estFinal($code);
    }

    public static function codeConflitEtat(string $etat): string
    {
        return match ($etat) {
            'acceptee'   => 'deja_acceptee',
            'refusee'    => 'deja_refusee',
            'annulee'    => 'deja_annulee',
            'sans_suite' => 'sans_suite',
            default      => 'etat_invalide',
        };
    }
}
