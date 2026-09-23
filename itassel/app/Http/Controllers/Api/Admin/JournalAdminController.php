<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Journal;
use App\Services\JournalService;
use App\Services\StatistiqueService;
use Illuminate\Http\Request;

class JournalAdminController extends Controller
{
    public function index(Request $request)
    {
        $filtres = $this->valider($request);
        $query = $this->filtrer(Journal::query()->with(['utilisateur.service', 'utilisateur.roleModele']), $filtres);

        $page = $query->orderByDesc('date_action')->paginate(25)->through(fn (Journal $j) => $this->ligne($j));

        return response()->json($page);
    }

    public function export(Request $request)
    {
        $filtres = $this->valider($request);
        $lignes = $this->filtrer(Journal::query()->with(['utilisateur.service', 'utilisateur.roleModele']), $filtres)
            ->orderByDesc('date_action')
            ->get();

        $chemin = tmpfile();
        fwrite($chemin, "\xEF\xBB\xBF");
        fputcsv($chemin, [
            'Date', 'Action', 'Catégorie', 'Détail', 'IP', 'Résultat', 'Compte', 'Utilisateur',
        ], ';');

        foreach ($lignes as $journal) {
            fputcsv($chemin, [
                optional($journal->date_action)->format('d/m/Y H:i'),
                $this->libelleAction($journal->action),
                $journal->categorie,
                $journal->detail,
                $journal->adresse_ip,
                $journal->resultat,
                $journal->compte,
                $journal->utilisateur
                    ? trim($journal->utilisateur->prenom.' '.$journal->utilisateur->nom)
                    : '',
            ], ';');
        }

        rewind($chemin);
        $contenu = stream_get_contents($chemin);
        fclose($chemin);

        JournalService::action($request, $request->user(), 'export_journal', $lignes->count().' ligne(s)');

        return response($contenu, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="journaux-'.now()->format('Y-m-d').'.csv"',
        ]);
    }

    public function tableauDeBord()
    {
        return response()->json(StatistiqueService::activite());
    }

    private function valider(Request $request): array
    {
        return $request->validate([
            'categorie'  => ['nullable', 'string'],
            'utilisateur'=> ['nullable', 'integer'],
            'periode'    => ['nullable', 'string', 'in:aujourdhui,7j,30j,3m'],
            'date_debut' => ['nullable', 'date'],
            'date_fin'   => ['nullable', 'date'],
            'resultat'   => ['nullable', 'string', 'in:succes,echec'],
            'q'          => ['nullable', 'string', 'max:100'],
        ]);
    }

    private function filtrer($query, array $filtres)
    {
        if (! empty($filtres['categorie'])) {
            $query->where('categorie', $filtres['categorie']);
        }
        if (! empty($filtres['utilisateur'])) {
            $query->where('id_utilisateur', $filtres['utilisateur']);
        }
        if (! empty($filtres['resultat'])) {
            $query->where('resultat', $filtres['resultat']);
        }
        if (! empty($filtres['periode'])) {
            $depuis = match ($filtres['periode']) {
                'aujourdhui' => now()->startOfDay(),
                '7j'         => now()->subDays(7),
                '30j'        => now()->subDays(30),
                '3m'         => now()->subMonths(3),
            };
            $query->where('date_action', '>=', $depuis);
        }
        if (! empty($filtres['date_debut'])) {
            $query->whereDate('date_action', '>=', $filtres['date_debut']);
        }
        if (! empty($filtres['date_fin'])) {
            $query->whereDate('date_action', '<=', $filtres['date_fin']);
        }
        if (! empty($filtres['q'])) {
            $texte = '%'.addcslashes($filtres['q'], '%_').'%';
            $query->where(function ($w) use ($texte) {
                $w->where('compte', 'like', $texte)
                  ->orWhere('detail', 'like', $texte)
                  ->orWhere('adresse_ip', 'like', $texte)
                  ->orWhereHas('utilisateur', fn ($u) => $u
                      ->where('nom', 'like', $texte)
                      ->orWhere('prenom', 'like', $texte));
            });
        }

        return $query;
    }

    private function ligne(Journal $j): array
    {
        $utilisateur = null;
        if ($j->utilisateur && $j->resultat === 'succes') {
            $utilisateur = [
                'nom'          => $j->utilisateur->nom,
                'prenom'       => $j->utilisateur->prenom,
                'libelle_role' => $j->utilisateur->libelleRole(),
                'service'      => $j->utilisateur->service?->nom_service,
            ];
        }

        return [
            'id_journal'     => $j->id_journal,
            'date_action'    => $j->date_action,
            'action'         => $j->action,
            'action_libelle' => $this->libelleAction($j->action),
            'categorie'      => $j->categorie,
            'detail'         => $j->detail,
            'adresse_ip'     => $j->adresse_ip,
            'resultat'       => $j->resultat,
            'compte'         => $j->compte,
            'utilisateur'    => $utilisateur,
        ];
    }

    private function libelleAction(string $action): string
    {
        $meta = config("itassel.journal.actions.{$action}", [$action]);

        return is_array($meta) ? ($meta[0] ?? $action) : $action;
    }
}
