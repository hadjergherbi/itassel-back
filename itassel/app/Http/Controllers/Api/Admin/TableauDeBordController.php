<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\StatistiqueService;
use App\Support\Periode;
use Illuminate\Http\Request;

class TableauDeBordController extends Controller
{
    /**
     * GET /api/admin/tableau-de-bord?periode=30j|3m|6m|annee
     * Indicateurs limités aux doléances visibles par l'utilisateur.
     */
    public function index(Request $request)
    {
        $data = $request->validate([
            'periode' => ['nullable', 'string', 'in:30j,3m,6m,annee'],
        ]);

        $code = $data['periode'] ?? '6m';
        Periode::resoudre($code);

        $reponse = StatistiqueService::tableauDeBord($request->user(), $code);

        if ($request->user()->peut('tableau_de_bord.global')) {
            $reponse['vue_globale'] = StatistiqueService::vueGlobale($request->user());
            $reponse['issues'] = StatistiqueService::issues($request->user());
        }

        return response()->json($reponse);
    }
}
