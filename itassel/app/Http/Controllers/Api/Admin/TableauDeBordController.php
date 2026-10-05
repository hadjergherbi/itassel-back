<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Statut;
use App\Services\JournalService;
use App\Services\StatistiqueService;
use App\Support\ExportPdf;
use App\Support\GraphiqueCirculaire;
use App\Support\Periode;
use Barryvdh\DomPDF\Facade\Pdf;
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

    /**
     * GET /api/admin/tableau-de-bord/export?periode=30j|3m|6m|annee
     * PDF d'agrégats, réservé au Super administrateur. Aucune donnée personnelle.
     */
    public function export(Request $request)
    {
        $data = $request->validate([
            'periode' => ['nullable', 'string', 'in:30j,3m,6m,annee'],
        ]);

        $code = $data['periode'] ?? '6m';
        $periode = Periode::resoudre($code);
        $donnees = StatistiqueService::pourExport($request->user(), $code);
        $indicateurs = $donnees['tableau']['indicateurs'];
        $total = (int) $indicateurs['total'];
        $resolues = (int) $indicateurs['resolues'];
        $taux = $total > 0 ? round($resolues / $total * 100, 1) : 0.0;

        $graphiques = [
            $this->graphique('Répartition par statut', $this->parts($donnees['tableau']['repartition'])),
            $this->graphique('Répartition par nature', $this->parts($donnees['tableau']['repartition_nature'])),
        ];

        JournalService::action(
            $request,
            $request->user(),
            'export_pdf',
            'Statistiques du tableau de bord — période '.$code,
        );

        $pdf = Pdf::loadView('exports.tableau-de-bord', [
            'orientation' => 'portrait',
            'titre_document' => 'Statistiques du tableau de bord',
            'reference' => 'TDB-'.$periode['date_fin'],
            'periode' => $periode,
            'genere_le' => now()->format('d/m/Y H:i'),
            'agent' => $request->user()->nomComplet(),
            'indicateurs' => $indicateurs,
            'taux_resolution' => $taux,
            'par_service' => $donnees['vue_globale']['par_service'],
            'par_mois' => $donnees['tableau']['par_mois'],
            'issues' => $donnees['issues'],
            'issues_libelles' => Statut::query()->pluck('libelle', 'code'),
            'graphiques' => $graphiques,
        ])->setPaper('a4', 'portrait');

        ExportPdf::preparer($pdf);

        return $pdf->download('tableau-de-bord_'.$code.'_'.now()->format('Y-m-d').'.pdf');
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $lignes
     * @return list<array{libelle: string, valeur: int}>
     */
    private function parts(iterable $lignes): array
    {
        $parts = [];
        foreach ($lignes as $ligne) {
            $parts[] = [
                'libelle' => (string) ($ligne['libelle'] ?? ''),
                'valeur' => (int) ($ligne['total'] ?? 0),
            ];
        }

        return $parts;
    }

    /**
     * @param  list<array{libelle: string, valeur: int}>  $parts
     * @return array{titre: string, image: ?string, legende: list<array<string, mixed>>}
     */
    private function graphique(string $titre, array $parts): array
    {
        $serie = GraphiqueCirculaire::serie($parts);
        foreach ($serie as $index => &$part) {
            $part['couleur'] = GraphiqueCirculaire::PALETTE_SOBRE[$index] ?? '#8A9A91';
        }
        unset($part);

        return [
            'titre' => $titre,
            'image' => GraphiqueCirculaire::anneau($serie),
            'legende' => GraphiqueCirculaire::legende($serie),
        ];
    }
}
