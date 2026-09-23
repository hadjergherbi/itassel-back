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
        ?int $idEvenement = null
    ): bool {
        $destinataire = $doleance->email;

        try {
            Mail::to($destinataire)->send($mail);
            $transmis = true;
        } catch (\Throwable $e) {
            Log::error("Échec d'envoi de l'email « {$type} » pour {$doleance->reference} : ".$e->getMessage());
            $transmis = false;
        }

        NotificationItassel::create([
            'type_notification' => $type,
            // La colonne fait 100 caractères (l'email du dépôt peut en faire 120).
            'destinataire'      => mb_substr($destinataire, 0, 100),
            'etat_envoi'        => $transmis ? 'transmis' : 'non_transmis',
            'date_envoi'        => $transmis ? now() : null,
            'id_doleance'       => $doleance->id_doleance,
            'id_evenement'      => $idEvenement,
        ]);

        return $transmis;
    }
}
