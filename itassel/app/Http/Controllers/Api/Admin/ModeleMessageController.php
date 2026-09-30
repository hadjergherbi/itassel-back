<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ModeleMessage;
use Illuminate\Http\Request;

class ModeleMessageController extends Controller
{
    /**
     * GET /api/admin/modeles-message?usage=reponse&statut=en_cours
     * Textes prédéfinis pour répondre au demandeur ou demander un complément.
     * Aucune validation stricte sur les filtres : un usage inconnu renvoie [].
     */
    public function index(Request $request)
    {
        $query = ModeleMessage::query()->orderBy('titre');
        ModeleMessage::appliquerFiltres($query, $request);

        return response()->json(
            $query->get()->map->versApi()->values()
        );
    }

    /**
     * GET /api/admin/messages-predefinis/usages
     * Catalogue des types d'usage (config), pour les filtres et formulaires.
     */
    public function usages()
    {
        $liste = collect(config('itassel.messages_usages', []))
            ->map(fn (array $def, string $code) => [
                'code' => $code,
                'libelle' => $def['libelle'],
                'statuts' => $def['statuts'] ?? [],
            ])
            ->values();

        return response()->json($liste);
    }
}
