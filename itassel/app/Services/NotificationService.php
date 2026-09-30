<?php

namespace App\Services;

use App\Models\Doleance;
use App\Models\NotificationItassel;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    /**
     * Envoie un email au demandeur et enregistre le résultat dans la table
     * notifications_itassel (transmis / non_transmis).
     * Un échec d'envoi ne bloque jamais l'action du demandeur : il est
     * seulement tracé, pour pouvoir le renvoyer depuis le back-office.
     */
    public static function envoyer(
        Doleance $doleance,
        string $type,
        Mailable $mail,
        ?int $idEvenement = null,
        ?string &$messageErreur = null,
    ): bool {
        return static::envoyerA($doleance->email, $doleance, $type, $mail, $idEvenement, $messageErreur);
    }

    /**
     * Envoie un email à un destinataire précis (demandeur ou interne)
     * et enregistre le résultat. Ne lève jamais d'exception.
     */
    public static function envoyerA(
        string $destinataire,
        Doleance $doleance,
        string $type,
        Mailable $mail,
        ?int $idEvenement = null,
        ?string &$messageErreur = null,
    ): bool {
        $destinataire = trim($destinataire);
        $messageErreur = null;

        if ($destinataire === '') {
            $messageErreur = 'destinataire vide';

            return false;
        }

        try {
            Mail::to($destinataire)->send($mail);
            $transmis = true;
        } catch (\Throwable $e) {
            // Pas d'email complet dans le log (référence seulement).
            Log::error("Échec d'envoi de l'email « {$type} » pour {$doleance->reference} : ".$e->getMessage());
            $messageErreur = $e->getMessage();
            $transmis = false;
        }

        NotificationItassel::create([
            'type_notification' => $type,
            // La colonne fait 100 caractères (l'email du dépôt peut en faire 120).
            'destinataire' => mb_substr($destinataire, 0, 100),
            'etat_envoi' => $transmis ? 'transmis' : 'non_transmis',
            'date_envoi' => $transmis ? now() : null,
            'id_doleance' => $doleance->id_doleance,
            'id_evenement' => $idEvenement,
        ]);

        return $transmis;
    }

    /**
     * Renvoie un email déjà tracé : met à jour la ligne existante, n'en crée pas une nouvelle.
     */
    public static function renvoyer(NotificationItassel $notification, Mailable $mail): bool
    {
        $destinataire = trim((string) $notification->destinataire);

        if ($destinataire === '') {
            return false;
        }

        try {
            Mail::to($destinataire)->send($mail);
            $transmis = true;
        } catch (\Throwable $e) {
            Log::error("Échec du renvoi de l'email « {$notification->type_notification} » #{$notification->id_notification} : ".$e->getMessage());
            $transmis = false;
        }

        $notification->update([
            'etat_envoi' => $transmis ? 'transmis' : 'non_transmis',
            'date_envoi' => $transmis ? now() : null,
        ]);

        return $transmis;
    }
}
