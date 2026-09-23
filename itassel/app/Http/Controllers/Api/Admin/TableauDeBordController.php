<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Statut;
use App\Services\StatistiqueService;
use App\Support\Acces;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class TableauDeBordController extends Controller
{
    private const LIBELLES_MOIS = [
        1 => 'Janv.', 2 => 'Févr.', 3 => 'Mars', 4 => 'Avr.',
        5 => 'Mai', 6 => 'Juin', 7 => 'Juil.', 8 => 'Août',
        9 => 'Sept.', 10 => 'Oct.', 11 => 'Nov.', 12 => 'Déc.',
    ];

    /**
     * GET /api/admin/tableau-de-bord
     * Indicateurs limités aux doléances visibles par l'utilisateur.
     */
    public function index(Request $request)
    {
        $visibles = Acces::doleancesVisibles($request->user());

        $statuts = Statut::orderBy('id_statut')->get(['id_statut', 'code', 'libelle', 'couleur']);
        $totauxParStatut = (clone $visibles)
            ->selectRaw('id_statut, COUNT(*) AS total')
            ->groupBy('id_statut')
            ->pluck('total', 'id_statut');

        $totauxParCode = [];
        foreach ($statuts as $statut) {
            $totauxParCode[$statut->code] = (int) ($totauxParStatut[$statut->id_statut] ?? 0);
        }

        $depuis = (clone $visibles)->min('date_depot');

        $reponse = [
            'indicateurs' => [
                'nouvelles' => $totauxParCode[Statut::NOUVELLE] ?? 0,
                'en_cours'  => $totauxParCode[Statut::EN_COURS] ?? 0,
                'resolues'  => $totauxParCode[Statut::RESOLUE] ?? 0,
                'total'     => (clone $visibles)->count(),
                'depuis'    => $depuis
                    ? substr((string) $depuis, 0, 10)
                    : now()->toDateString(),
            ],
            'par_mois'    => $this->parMois(clone $visibles),
            'repartition' => $statuts->map(fn (Statut $statut) => [
                'code'    => $statut->code,
                'libelle' => $statut->libelle,
                'total'   => $totauxParCode[$statut->code] ?? 0,
            ])->values(),
            'dernieres'   => (clone $visibles)
                ->with([
                    'statut:id_statut,code,libelle,couleur',
                    'nature:id_nature,libelle',
                ])
                ->orderByDesc('date_depot')
                ->limit(5)
                ->get(['id_doleance', 'reference', 'nom', 'prenom', 'date_depot', 'id_statut', 'id_nature'])
                ->map(fn ($doleance) => [
                    'reference'  => $doleance->reference,
                    'nom'        => $doleance->nom,
                    'prenom'     => $doleance->prenom,
                    'nature'     => $doleance->nature?->libelle,
                    'date_depot' => $doleance->date_depot,
                    'statut'     => $doleance->statut?->versApi(),
                ])->values(),
        ];

        if ($request->user()->peut('tableau_de_bord.global')) {
            $reponse['vue_globale'] = StatistiqueService::vueGlobale($request->user());
            $reponse['issues'] = StatistiqueService::issues($request->user());
        }

        return response()->json($reponse);
    }

    private function parMois($query): array
    {
        $debut = now()->startOfMonth()->subMonths(5);
        $expression = Schema::getConnection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', date_depot)"
            : "DATE_FORMAT(date_depot, '%Y-%m')";

        $totaux = (clone $query)
            ->where('date_depot', '>=', $debut)
            ->selectRaw("{$expression} AS mois, COUNT(*) AS total")
            ->groupByRaw($expression)
            ->pluck('total', 'mois');

        $parMois = [];
        for ($i = 0; $i < 6; $i++) {
            $mois = $debut->copy()->addMonths($i);
            $cle = $mois->format('Y-m');
            $parMois[] = [
                'mois'    => $cle,
                'libelle' => self::LIBELLES_MOIS[(int) $mois->format('n')],
                'total'   => (int) ($totaux[$cle] ?? 0),
            ];
        }

        return $parMois;
    }
}
