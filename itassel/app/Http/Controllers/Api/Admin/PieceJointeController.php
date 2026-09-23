<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PieceJointe;
use App\Support\Acces;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PieceJointeController extends Controller
{
    /**
     * GET /api/admin/pieces-jointes/{id}/telecharger
     * Seulement si la doléance de la pièce est visible par l'utilisateur.
     */
    public function telecharger(Request $request, int $id)
    {
        $piece = PieceJointe::find($id);

        $autorise = $piece && Acces::doleancesVisibles($request->user())
            ->whereKey($piece->id_doleance)
            ->exists();

        if (! $autorise) {
            return response()->json(['message' => 'Pièce jointe introuvable.'], 404);
        }

        if (! Storage::disk('local')->exists($piece->chemin)) {
            return response()->json(['message' => 'Le fichier est absent du serveur.'], 404);
        }

        return Storage::disk('local')->download($piece->chemin, $piece->nom_fichier);
    }
}
