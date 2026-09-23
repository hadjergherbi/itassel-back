<?php

namespace App\Services;

use App\Mail\ChangementStatutMail;
use App\Mail\ComplementAnnuleMail;
use App\Mail\ConfirmationDepotMail;
use App\Mail\DemandeComplementMail;
use App\Mail\NotificationInterneMail;
use App\Mail\ReponseServiceMail;
use App\Models\Doleance;
use App\Models\NotificationApp;
use App\Models\ParametreNotification;
use App\Models\Utilisateur;
use App\Support\Acces;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;

class NotificationDispatcher
{
    public static function emettre(
        string $evenement,
        Doleance $doleance,
        array $contexte = [],
        ?Utilisateur $acteur = null,
        ?int $idEvenement = null,
        array $options = [],
    ): array {
        $emailDemandeur = null;
        $emailResponsable = null;

        try {
            $parametres = ParametreNotification::where('evenement', $evenement)->get();
            $doleance->loadMissing(['responsable', 'service.responsable', 'statut', 'complements', 'reponses']);
            $internes = [];

            foreach ($parametres as $parametre) {
                if ($parametre->destinataire === 'demandeur') {
                    $emailDemandeur = static::envoyerCitoyen($evenement, $doleance, $parametre, $contexte, $idEvenement, $options);
                    continue;
                }

                foreach (static::destinatairesInternes($parametre->destinataire, $doleance, $contexte) as $utilisateur) {
                    if ($acteur && (int) $utilisateur->id_utilisateur === (int) $acteur->id_utilisateur) {
                        continue;
                    }
                    if (! $utilisateur->actif) {
                        continue;
                    }
                    if (! Acces::doleancesVisibles($utilisateur)->whereKey($doleance->id_doleance)->exists()
                        && ! in_array($parametre->destinataire, ['demandeur_reaffectation', 'utilisateur_designe'], true)) {
                        continue;
                    }

                    $id = $utilisateur->id_utilisateur;
                    if (! isset($internes[$id])) {
                        $internes[$id] = ['utilisateur' => $utilisateur, 'canal_app' => false, 'canal_email' => false, 'destinataire' => $parametre->destinataire];
                    }
                    $internes[$id]['canal_app'] = $internes[$id]['canal_app'] || $parametre->canal_app;
                    $internes[$id]['canal_email'] = $internes[$id]['canal_email'] || $parametre->canal_email;
                    if ($parametre->destinataire === 'responsable') {
                        $internes[$id]['destinataire'] = 'responsable';
                    }
                }
            }

            foreach ($internes as $ligne) {
                $virtuel = new ParametreNotification([
                    'evenement'    => $evenement,
                    'destinataire' => $ligne['destinataire'],
                    'canal_app'    => $ligne['canal_app'],
                    'canal_email'  => $ligne['canal_email'],
                    'modifiable'   => true,
                ]);
                $envoye = static::notifierInterne($evenement, $doleance, $ligne['utilisateur'], $virtuel, $contexte, $idEvenement, $options);
                if ($ligne['destinataire'] === 'responsable') {
                    $emailResponsable = $envoye;
                }
            }
        } catch (\Throwable $e) {
            Log::error("NotificationDispatcher « {$evenement} » : ".$e->getMessage());
        }

        return [
            'email_demandeur'    => $emailDemandeur,
            'email_responsable'  => $emailResponsable,
        ];
    }

    private static function envoyerCitoyen(
        string $evenement,
        Doleance $doleance,
        ParametreNotification $parametre,
        array $contexte,
        ?int $idEvenement,
        array $options,
    ): ?bool {
        $forcerOff = ($options['notifier_demandeur'] ?? true) === false;
        if ($forcerOff && $parametre->modifiable) {
            return null;
        }
        if (! $parametre->canal_email) {
            return null;
        }

        $mail = static::mailCitoyen($evenement, $doleance, $contexte);
        if (! $mail) {
            return null;
        }

        $type = match ($evenement) {
            'doleance_deposee'   => 'depot',
            'changement_statut'  => 'changement_statut',
            'complement_demande' => 'complement_demande',
            'complement_recu'    => 'complement_recu',
            'complement_annule'  => 'complement_annule',
            'reponse_publiee'    => 'reponse',
            default              => $evenement,
        };

        return NotificationService::envoyer($doleance, $type, $mail, $idEvenement);
    }

    private static function notifierInterne(
        string $evenement,
        Doleance $doleance,
        Utilisateur $destinataire,
        ParametreNotification $parametre,
        array $contexte,
        ?int $idEvenement,
        array $options,
    ): ?bool {
        $titre = $contexte['titre'] ?? "ITASSEL — {$doleance->reference}";
        $texte = $contexte['texte'] ?? "Mise à jour du dossier {$doleance->reference}.";

        if ($parametre->canal_app) {
            NotificationApp::create([
                'id_utilisateur' => $destinataire->id_utilisateur,
                'evenement'      => $evenement,
                'titre'          => mb_substr($titre, 0, 150),
                'message'        => $texte,
                'id_doleance'    => $doleance->id_doleance,
            ]);
        }

        $estResponsable = $parametre->destinataire === 'responsable';
        $forcerOff = $estResponsable
            && array_key_exists('notifier_responsable', $options)
            && $options['notifier_responsable'] === false;
        $forcerOn = $estResponsable && ($options['notifier_responsable'] ?? false) === true;

        if ($forcerOff) {
            return false;
        }

        if ($parametre->canal_email || $forcerOn) {
            return NotificationService::envoyerA(
                $destinataire->email,
                $doleance,
                'interne_'.$evenement,
                new NotificationInterneMail($doleance, $titre, $texte),
                $idEvenement,
            );
        }

        return $estResponsable ? false : null;
    }

    private static function destinatairesInternes(string $type, Doleance $doleance, array $contexte)
    {
        return match ($type) {
            'responsable' => collect([
                $doleance->responsable ?? $doleance->service?->responsable,
            ])->filter(),
            'admins_service' => Utilisateur::query()
                ->where('role', 'admin_service')
                ->where('actif', true)
                ->where('id_service', $doleance->id_service)
                ->get(),
            'super_admins' => Utilisateur::query()
                ->where('role', 'super_admin')
                ->where('actif', true)
                ->get(),
            'demandeur_reaffectation' => collect([$contexte['demandeur_reaffectation'] ?? null])->filter(),
            'utilisateur_designe' => collect([$contexte['utilisateur_designe'] ?? null])->filter(),
            default => collect(),
        };
    }

    private static function mailCitoyen(string $evenement, Doleance $doleance, array $contexte): ?Mailable
    {
        return match ($evenement) {
            'doleance_deposee' => new ConfirmationDepotMail($doleance),
            'changement_statut' => isset($contexte['statut'])
                ? new ChangementStatutMail($doleance, $contexte['statut'], $contexte['message'] ?? null)
                : null,
            'complement_demande' => isset($contexte['complement'])
                ? new DemandeComplementMail($doleance, $contexte['complement'])
                : null,
            'complement_recu' => new \App\Mail\AccuseComplementMail($doleance),
            'complement_annule' => new ComplementAnnuleMail($doleance),
            'reponse_publiee' => isset($contexte['reponse'])
                ? new ReponseServiceMail($doleance, $contexte['reponse'])
                : null,
            default => null,
        };
    }
}
