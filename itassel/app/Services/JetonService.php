<?php

namespace App\Services;

use App\Models\JetonMotDePasse;
use App\Models\Utilisateur;
use Illuminate\Support\Str;

class JetonService
{
    public static function emettre(Utilisateur $utilisateur, string $type, ?Utilisateur $createur = null): string
    {
        JetonMotDePasse::where('id_utilisateur', $utilisateur->id_utilisateur)
            ->where('type', $type)
            ->whereNull('utilise_le')
            ->update(['utilise_le' => now()]);

        $clair = Str::random(64);

        $expire = $type === 'invitation'
            ? now()->addHours((int) config('itassel.jetons.invitation_heures', 72))
            : now()->addMinutes((int) config('itassel.jetons.reinitialisation_minutes', 60));

        JetonMotDePasse::create([
            'id_utilisateur' => $utilisateur->id_utilisateur,
            'type'           => $type,
            'jeton_hash'     => hash('sha256', $clair),
            'expire_le'      => $expire,
            'id_createur'    => $createur?->id_utilisateur,
        ]);

        return $clair;
    }

    public static function trouver(string $jeton): ?JetonMotDePasse
    {
        if ($jeton === '') {
            return null;
        }

        return JetonMotDePasse::where('jeton_hash', hash('sha256', $jeton))->first();
    }
}
