<?php

namespace App\Services;

use App\Models\Journal;
use App\Models\Utilisateur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class JournalService
{
    /**
     * Enregistre une action dans le journal (connexions, exports, etc.).
     * Ne bloque jamais l'action en cas d'échec d'écriture.
     */
    public static function ecrire(
        Request $request,
        string $compte,
        string $action,
        string $resultat,
        ?Utilisateur $utilisateur = null,
        ?string $detail = null,
    ): void {
        try {
            Journal::create([
                'date_action'    => now(),
                'compte'         => mb_substr($compte, 0, 120),
                'action'         => $action,
                'detail'         => $detail,
                'adresse_ip'     => $request->ip() ?? '0.0.0.0',
                'resultat'       => $resultat,
                'id_utilisateur' => $utilisateur?->id_utilisateur,
            ]);
        } catch (\Throwable $e) {
            Log::error("Échec d'écriture dans le journal ({$action}) : ".$e->getMessage());
        }
    }
}
