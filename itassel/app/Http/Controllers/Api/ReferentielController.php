<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Nature;
use App\Models\Qualite;
use App\Models\Service;

class ReferentielController extends Controller
{
    /**
     * GET /api/referentiels
     * Listes utilisées par le formulaire de dépôt (selects).
     */
    public function index()
    {
        return response()->json([
            'services' => Service::assignables()->orderBy('nom_service')->get(['id_service', 'nom_service']),
            'natures' => Nature::publiques()->orderBy('libelle')->get(['id_nature', 'libelle']),
            'qualites' => Qualite::selectionnables()->get(['id_qualite', 'libelle']),
        ]);
    }
}
