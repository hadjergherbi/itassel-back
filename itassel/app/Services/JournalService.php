<?php

namespace App\Services;

use App\Models\Journal;
use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class JournalService
{
    public static function ecrire(
        Request $request,
        string $compte,
        string $action,
        string $resultat,
        ?Utilisateur $utilisateur = null,
        ?string $detail = null,
    ): void {
        static::action($request, $utilisateur, $action, $detail, null, $resultat, $compte);
    }

    public static function action(
        Request $request,
        ?Utilisateur $utilisateur,
        string $action,
        ?string $detail = null,
        ?Model $cible = null,
        string $resultat = 'succes',
        ?string $compte = null,
    ): void {
        $meta = config("itassel.journal.actions.{$action}", [null, null]);
        $categorie = is_array($meta) ? ($meta[1] ?? null) : null;

        try {
            Journal::create([
                'date_action' => now(),
                'compte' => mb_substr($compte ?? $utilisateur?->email ?? '', 0, 120),
                'action' => $action,
                'categorie' => $categorie,
                'detail' => $detail,
                'adresse_ip' => $request->ip() ?? '0.0.0.0',
                'resultat' => $resultat,
                'id_utilisateur' => $utilisateur?->id_utilisateur,
                'cible_type' => $cible ? $cible::class : null,
                'cible_id' => $cible?->getKey(),
            ]);
        } catch (\Throwable $e) {
            Log::error("Échec d'écriture dans le journal ({$action}) : ".$e->getMessage());
        }
    }
}
