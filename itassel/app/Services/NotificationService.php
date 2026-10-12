<?php

namespace App\Services;

use App\Jobs\EnvoyerEmailNotification;
use App\Models\Doleance;
use App\Models\NotificationItassel;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotificationService
{
    /**
     * Met l'email du demandeur en file (etat_envoi = en_attente) puis le job
     * le passe à transmis ou non_transmis. true = mis en file, pas encore transmis.
     * Un échec SMTP ne remonte pas à l'appelant.
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
     * Crée la ligne puis dispatch EnvoyerEmailNotification.
     * Le code de suivi part sur la file « prioritaire ».
     * true = mis en file.
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

        $ligne = NotificationItassel::create([
            'type_notification' => $type,
            'destinataire' => mb_substr($destinataire, 0, 100),
            'etat_envoi' => 'en_attente',
            'date_envoi' => null,
            'id_doleance' => $doleance->id_doleance,
            'id_evenement' => $idEvenement,
        ]);

        $job = new EnvoyerEmailNotification(
            $destinataire,
            $mail,
            $ligne->id_notification,
            $doleance->reference,
        );

        if ($type === 'code_verification') {
            $job->onQueue('prioritaire');
        }

        try {
            dispatch($job);
        } catch (Throwable $e) {
            if ($ligne->fresh()?->etat_envoi === 'en_attente') {
                $ligne->update([
                    'etat_envoi' => 'non_transmis',
                    'date_envoi' => null,
                ]);
            }

            Log::warning('Échec de mise en file d\'un email', [
                'type' => $type,
                'exception' => $e::class,
            ]);
            $messageErreur = 'mise en file impossible';

            return false;
        }

        return true;
    }

    /**
     * Renvoi manuel, synchrone : l'agent doit voir le résultat tout de suite.
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
        } catch (Throwable $e) {
            Log::error("Échec du renvoi de l'email « {$notification->type_notification} » #{$notification->id_notification} : ".$e::class);
            $transmis = false;
        }

        $notification->update([
            'etat_envoi' => $transmis ? 'transmis' : 'non_transmis',
            'date_envoi' => $transmis ? now() : null,
        ]);

        return $transmis;
    }
}
