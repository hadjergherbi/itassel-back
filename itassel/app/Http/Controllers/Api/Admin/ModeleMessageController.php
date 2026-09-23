<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ModeleMessage;
use Illuminate\Http\Request;

class ModeleMessageController extends Controller
{
    /**
     * GET /api/admin/modeles-message?type_usage=reponse
     * Textes prédéfinis proposés dans le menu « Modèle de réponse ».
     */
    public function index(Request $request)
    {
        $query = ModeleMessage::orderBy('titre');

        if ($request->filled('type_usage')) {
            $query->where('type_usage', $request->input('type_usage'));
        }

        return response()->json($query->get(['id_modele', 'titre', 'contenu', 'type_usage']));
    }
}
