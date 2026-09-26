<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Doleance;
use App\Models\Journal;
use App\Models\Service;
use App\Models\Statut;
use App\Models\Utilisateur;
use App\Services\JournalService;
use App\Services\StatistiqueService;
use App\Support\ExportPdf;
use App\Support\GraphiqueCirculaire;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;

class JournalAdminController extends Controller
{
    /** @var array<string, Model> */
    private array $cibles = [];

    /** @var array<string, string>|null */
    private ?array $libellesStatuts = null;

    public function index(Request $request)
    {
        $filtres = $this->valider($request);
        $query = $this->filtrer(Journal::query()->with(['utilisateur.service', 'utilisateur.roleModele']), $filtres);

        $page = $query->orderByDesc('date_action')->paginate(25);
        $this->preparerContexte($page->getCollection());

        return response()->json($page->through(fn (Journal $j) => $this->ligne($j)));
    }

    public function export(Request $request)
    {
        $filtres = $this->valider($request);
        $format = $filtres['format'] ?? 'csv';
        $requete = $this->filtrer(Journal::query(), $filtres);
        $total = (clone $requete)->count();

        if ($format === 'pdf' && $total > 5000) {
            return response()->json([
                'message' => "Trop de lignes pour un PDF ({$total}). Affinez les filtres ou exportez en CSV.",
                'code'    => 'export_trop_volumineux',
            ], 422);
        }

        $lignes = (clone $requete)
            ->with(['utilisateur.service', 'utilisateur.roleModele'])
            ->orderByDesc('date_action')
            ->get();

        if ($format === 'pdf') {
            return $this->exportPdf($request, $filtres, $lignes, clone $requete, $total);
        }

        $this->preparerContexte($lignes);

        $chemin = tmpfile();
        fwrite($chemin, "\xEF\xBB\xBF");
        fputcsv($chemin, [
            'Date', 'Action', 'Catégorie', 'Détail', 'IP', 'Résultat', 'Compte', 'Utilisateur', 'Cible', 'Sensible',
        ], ';');

        foreach ($lignes as $journal) {
            $ligne = $this->ligne($journal);
            fputcsv($chemin, [
                optional($journal->date_action)->format('d/m/Y H:i'),
                $ligne['action_libelle'],
                $journal->categorie,
                $journal->detail,
                $journal->adresse_ip,
                $journal->resultat,
                $journal->compte,
                $journal->utilisateur
                    ? trim($journal->utilisateur->prenom.' '.$journal->utilisateur->nom)
                    : '',
                $ligne['cible']['libelle'] ?? '',
                $ligne['sensible'] ? 'Oui' : 'Non',
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

    public function tableauDeBord(Request $request)
    {
        $data = $request->validate([
            'periode' => ['nullable', 'string', 'in:aujourdhui,7j,30j'],
        ]);

        return response()->json(StatistiqueService::activite($data['periode'] ?? 'aujourdhui'));
    }

    public function show(int $id)
    {
        $journal = Journal::with(['utilisateur.service', 'utilisateur.roleModele'])->find($id);

        if (! $journal) {
            return response()->json(['message' => 'Entrée de journal introuvable.'], 404);
        }

        $autres = Journal::query()
            ->with(['utilisateur.service', 'utilisateur.roleModele'])
            ->where('compte', $journal->compte)
            ->where('id_journal', '!=', $journal->id_journal)
            ->when($journal->date_action, fn ($q) => $q->whereDate('date_action', $journal->date_action))
            ->orderByDesc('date_action')
            ->limit(10)
            ->get();

        $this->preparerContexte(collect([$journal])->merge($autres));

        return response()->json(array_merge($this->ligne($journal), [
            'autres_actions' => $autres->map(fn (Journal $j) => $this->ligne($j))->values(),
        ]));
    }

    private function valider(Request $request): array
    {
        $data = $request->validate([
            'categorie'   => ['nullable', 'string'],
            'action'      => ['nullable', 'string', 'max:60'],
            'sensible'    => ['nullable', 'boolean'],
            'utilisateur' => ['nullable', 'integer'],
            'periode'     => ['nullable', 'string', 'in:aujourdhui,7j,30j,3m'],
            'date_debut'  => ['nullable', 'date'],
            'date_fin'    => ['nullable', 'date'],
            'resultat'    => ['nullable', 'string', 'in:succes,echec'],
            'q'           => ['nullable', 'string', 'max:100'],
            'format'      => ['nullable', 'in:csv,pdf'],
        ]);

        if ($request->exists('sensible')) {
            $data['sensible'] = $request->boolean('sensible');
        }

        return $data;
    }

    private function filtrer($query, array $filtres)
    {
        if (! empty($filtres['categorie'])) {
            $categories = array_values(array_filter(array_map(
                static fn (string $categorie) => trim($categorie),
                explode(',', $filtres['categorie'])
            )));
            if (count($categories) === 1) {
                $query->where('categorie', $categories[0]);
            } elseif ($categories !== []) {
                $query->whereIn('categorie', $categories);
            }
        }
        if (! empty($filtres['action'])) {
            $query->where('action', $filtres['action']);
        }
        if (array_key_exists('sensible', $filtres) && is_bool($filtres['sensible'])) {
            $codes = config('itassel.journal.sensibles', []);
            $prefixe = "action LIKE 'suppression\\_%' ESCAPE '\\'";
            if ($filtres['sensible']) {
                $query->where(function ($q) use ($codes, $prefixe) {
                    $q->whereRaw($prefixe)->orWhereIn('action', $codes);
                });
            } else {
                $query->whereRaw('NOT ('.$prefixe.')')->whereNotIn('action', $codes);
            }
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
            'detail_lisible' => $this->detailLisible($j),
            'sensible'       => $this->estSensible($j->action),
            'cible'          => $this->cibleDe($j),
            'adresse_ip'     => $j->adresse_ip,
            'resultat'       => $j->resultat,
            'compte'         => $j->compte,
            'utilisateur'    => $utilisateur,
        ];
    }

    /**
     * @param  iterable<Journal>  $journaux
     */
    private function preparerContexte(iterable $journaux): void
    {
        $ids = [];
        foreach ($journaux as $journal) {
            if ($journal->cible_type && $journal->cible_id) {
                $ids[$journal->cible_type][] = $journal->cible_id;
            }
        }

        $this->cibles = [];
        foreach ($ids as $classe => $liste) {
            if (! in_array($classe, [Doleance::class, Utilisateur::class, Service::class], true)) {
                continue;
            }

            $requete = $classe::query();
            if (in_array(SoftDeletes::class, class_uses_recursive($classe), true)) {
                $requete->withTrashed();
            }

            $requete->whereIn((new $classe)->getKeyName(), array_values(array_unique($liste)))
                ->get()
                ->each(function (Model $modele) use ($classe) {
                    $this->cibles[$classe.':'.$modele->getKey()] = $modele;
                });
        }

        $this->libellesStatuts ??= Statut::query()->pluck('libelle', 'code')->all();
    }

    private function cibleDe(Journal $journal): ?array
    {
        if (! $journal->cible_type || ! $journal->cible_id) {
            return null;
        }

        $modele = $this->cibles[$journal->cible_type.':'.$journal->cible_id] ?? null;
        if (! $modele) {
            return null;
        }

        return match ($journal->cible_type) {
            Doleance::class => [
                'type'    => 'doleance',
                'id'      => $modele->getKey(),
                'libelle' => $modele->reference,
                'lien'    => '/admin/doleances/'.$modele->reference,
            ],
            Utilisateur::class => [
                'type'    => 'utilisateur',
                'id'      => $modele->getKey(),
                'libelle' => $modele->nomComplet(),
                'lien'    => $modele->trashed()
                    ? null
                    : '/admin/utilisateurs?id='.$modele->getKey(),
            ],
            Service::class => [
                'type'    => 'service',
                'id'      => $modele->getKey(),
                'libelle' => $modele->nom_service,
                'lien'    => '/admin/services',
            ],
            default => null,
        };
    }

    private function estSensible(string $action): bool
    {
        return str_starts_with($action, 'suppression_')
            || in_array($action, config('itassel.journal.sensibles', []), true);
    }

    private function detailLisible(Journal $journal): ?string
    {
        if ($journal->action !== 'changement_statut' || ! is_string($journal->detail) || $journal->detail === '') {
            return null;
        }

        $libelles = $this->libellesStatuts ?? Statut::query()->pluck('libelle', 'code')->all();
        $this->libellesStatuts = $libelles;
        if ($libelles === []) {
            return $journal->detail;
        }

        $codes = array_keys($libelles);
        usort($codes, fn (string $a, string $b) => mb_strlen($b) <=> mb_strlen($a));
        $motif = '/\b('.implode('|', array_map(static fn (string $code) => preg_quote($code, '/'), $codes)).')\b/u';

        $texte = preg_replace_callback(
            $motif,
            fn (array $correspondance) => $libelles[$correspondance[1]] ?? $correspondance[0],
            $journal->detail
        );

        return is_string($texte) ? $texte : $journal->detail;
    }

    private function exportPdf(Request $request, array $filtres, $lignes, $requete, int $total)
    {
        $sql = $total > 2000;
        [$debut, $fin] = $this->bornesJournal($filtres, $lignes);
        $nomFichier = 'journal_'.$debut.'_'.$fin.'.pdf';

        $pdf = Pdf::loadView('exports.journal', [
            'lignes'             => $lignes,
            'total'              => $total,
            'echecs_connexion'   => $this->compterEchecsConnexion($lignes, $requete, $sql),
            'actions_sensibles'  => $this->compterSensibles($lignes, $requete, $sql),
            'graphiques'         => $this->graphiquesJournal($lignes, $requete, $sql),
            'filtres_lisibles'   => $this->filtresLisibles($filtres),
            'libelles'           => collect(config('itassel.journal.actions', []))
                ->map(fn ($meta) => is_array($meta) ? ($meta[0] ?? '') : $meta)
                ->all(),
            'genere_le'          => now()->format('d/m/Y H:i'),
            'agent'              => $request->user()->nomComplet(),
            'titre_document'     => 'Journal des actions',
            'reference'          => 'JRN-'.$fin,
        ])->setPaper('a4', 'landscape');

        ExportPdf::preparer($pdf);
        $dompdf = $pdf->getDomPDF();
        $dompdf->render();

        JournalService::action($request, $request->user(), 'export_journal', "PDF — {$total} ligne(s)");

        return response($dompdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$nomFichier.'"',
        ]);
    }

    private function compterEchecsConnexion($lignes, $requete, bool $sql): int
    {
        if ($sql) {
            return $this->echecsConnexion(clone $requete)->count();
        }

        return $lignes->filter(fn (Journal $journal) => $this->estEchecConnexion($journal))->count();
    }

    private function compterSensibles($lignes, $requete, bool $sql): int
    {
        if ($sql) {
            $codes = config('itassel.journal.sensibles', []);
            $prefixe = "action LIKE 'suppression\\_%' ESCAPE '\\'";

            return (clone $requete)->where(function ($q) use ($codes, $prefixe) {
                $q->whereRaw($prefixe)->orWhereIn('action', $codes);
            })->count();
        }

        return $lignes->filter(fn (Journal $journal) => $this->estSensible($journal->action))->count();
    }

    private function estEchecConnexion(Journal $journal): bool
    {
        return in_array($journal->action, ['connexion_echec', 'compte_verrouille'], true)
            || ($journal->action === 'connexion' && $journal->resultat === 'echec');
    }

    private function echecsConnexion($query)
    {
        return $query->where(function ($q) {
            $q->whereIn('action', ['connexion_echec', 'compte_verrouille'])
                ->orWhere(fn ($q) => $q->where('action', 'connexion')->where('resultat', 'echec'));
        });
    }

    /**
     * @return list<array{titre: string, image: ?string, legende: list<array<string, mixed>>}>
     */
    private function graphiquesJournal($lignes, $requete, bool $sql): array
    {
        if ($sql) {
            $categories = (clone $requete)
                ->reorder()
                ->selectRaw("COALESCE(categorie, 'sans_categorie') as libelle, COUNT(*) as total")
                ->groupBy('libelle')
                ->get()
                ->map(fn ($ligne) => [
                    'libelle' => $this->libelleCategorie((string) $ligne->libelle),
                    'valeur'  => (int) $ligne->total,
                ])
                ->all();

            $succes = (clone $requete)->where('resultat', 'succes')->count();
            $echecs = (clone $requete)->where('resultat', 'echec')->count();

            $utilisateurs = (clone $requete)
                ->reorder()
                ->selectRaw('id_utilisateur, MAX(compte) as compte, COUNT(*) as total')
                ->groupBy('id_utilisateur')
                ->orderByDesc('total')
                ->get();
        } else {
            $categories = $lignes
                ->groupBy(fn (Journal $journal) => $journal->categorie ?: 'sans_categorie')
                ->map(fn ($groupe, $libelle) => [
                    'libelle' => $this->libelleCategorie((string) $libelle),
                    'valeur'  => $groupe->count(),
                ])
                ->values()->all();

            $succes = $lignes->where('resultat', 'succes')->count();
            $echecs = $lignes->where('resultat', 'echec')->count();

            $utilisateurs = $lignes
                ->groupBy(fn (Journal $journal) => $journal->id_utilisateur ?: 'compte:'.$journal->compte)
                ->map(fn ($groupe) => [
                    'id_utilisateur' => $groupe->first()->id_utilisateur,
                    'compte'         => $groupe->first()->compte,
                    'total'          => $groupe->count(),
                    'nom'            => $groupe->first()->utilisateur?->nomComplet(),
                ])
                ->sortByDesc('total')
                ->values();
        }

        $partsUtilisateurs = $this->partsUtilisateurs($utilisateurs);

        return [
            $this->graphiqueJournal('Par catégorie', $categories),
            $this->graphiqueJournal('Succès et échecs', [
                ['libelle' => 'Succès', 'valeur' => $succes, 'couleur' => '#006b3f'],
                ['libelle' => 'Échecs', 'valeur' => $echecs, 'couleur' => '#b42318'],
            ]),
            $this->graphiqueJournal('Utilisateurs les plus actifs', $partsUtilisateurs),
        ];
    }

    private function partsUtilisateurs($lignes): array
    {
        $tries = collect($lignes)->sortByDesc(fn ($ligne) => (int) (is_array($ligne) ? $ligne['total'] : $ligne->total))->values();
        $ids = $tries->pluck('id_utilisateur')->filter()->unique()->all();
        $noms = $ids === []
            ? collect()
            : Utilisateur::withTrashed()->whereIn('id_utilisateur', $ids)->get()->keyBy('id_utilisateur');

        $parts = [];
        foreach ($tries->take(5) as $ligne) {
            $id = is_array($ligne) ? ($ligne['id_utilisateur'] ?? null) : $ligne->id_utilisateur;
            $compte = is_array($ligne) ? ($ligne['compte'] ?? '') : $ligne->compte;
            $total = (int) (is_array($ligne) ? $ligne['total'] : $ligne->total);
            $nom = is_array($ligne) ? ($ligne['nom'] ?? null) : null;
            if (! $nom && $id && $noms->has($id)) {
                $nom = $noms[$id]->nomComplet();
            }
            $parts[] = ['libelle' => $nom ?: ($compte ?: 'Inconnu'), 'valeur' => $total];
        }

        $reste = (int) $tries->slice(5)->sum(fn ($ligne) => (int) (is_array($ligne) ? $ligne['total'] : $ligne->total));
        if ($reste > 0) {
            $parts[] = ['libelle' => 'Autres', 'valeur' => $reste];
        }

        return $parts;
    }

    /**
     * @param  list<array{libelle: string, valeur: int, couleur?: string|null}>  $parts
     * @return array{titre: string, image: ?string, legende: list<array<string, mixed>>}
     */
    private function graphiqueJournal(string $titre, array $parts): array
    {
        $conserver = collect($parts)->contains(fn (array $part) => ! empty($part['couleur']));
        $serie = GraphiqueCirculaire::serie($parts);
        if (! $conserver) {
            foreach ($serie as $index => &$part) {
                $part['couleur'] = GraphiqueCirculaire::PALETTE_SOBRE[$index] ?? '#8A9A91';
            }
            unset($part);
        }

        return [
            'titre'   => $titre,
            'image'   => null,
            'legende' => GraphiqueCirculaire::legende($serie),
        ];
    }

    private function libelleCategorie(string $code): string
    {
        $map = config('itassel.journal.categories_libelles', []);

        return $map[$code] ?? ucfirst(str_replace('_', ' ', $code));
    }

    private function libelleCategoriesFiltre(?string $valeur): string
    {
        if ($valeur === null || $valeur === '') {
            return 'Toutes';
        }

        return collect(explode(',', $valeur))
            ->map(fn (string $code) => $this->libelleCategorie(trim($code)))
            ->implode(', ');
    }

    /**
     * @return array{periode: string, categorie: string, utilisateur: string, resultat: string}
     */
    private function filtresLisibles(array $filtres): array
    {
        if (! empty($filtres['date_debut']) || ! empty($filtres['date_fin'])) {
            $periode = ($filtres['date_debut'] ?? '…').' → '.($filtres['date_fin'] ?? '…');
        } else {
            $periode = match ($filtres['periode'] ?? null) {
                'aujourdhui' => "Aujourd'hui",
                '7j'         => '7 derniers jours',
                '30j'        => '30 derniers jours',
                '3m'         => '3 derniers mois',
                default      => 'Toutes les dates',
            };
        }

        $utilisateur = 'Tous';
        if (! empty($filtres['utilisateur'])) {
            $compte = Utilisateur::withTrashed()->find($filtres['utilisateur']);
            $utilisateur = $compte ? $compte->nomComplet() : 'Utilisateur #'.$filtres['utilisateur'];
        }

        return [
            'periode'     => $periode,
            'categorie'   => $this->libelleCategoriesFiltre($filtres['categorie'] ?? null),
            'utilisateur' => $utilisateur,
            'resultat'    => match ($filtres['resultat'] ?? null) {
                'succes' => 'Succès',
                'echec'  => 'Échec',
                default  => 'Tous',
            },
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function bornesJournal(array $filtres, $lignes): array
    {
        if (! empty($filtres['date_debut']) || ! empty($filtres['date_fin'])) {
            return [
                $filtres['date_debut'] ?? now()->toDateString(),
                $filtres['date_fin'] ?? now()->toDateString(),
            ];
        }

        $fin = now();
        $debut = match ($filtres['periode'] ?? null) {
            'aujourdhui' => now()->startOfDay(),
            '7j'         => now()->subDays(7)->startOfDay(),
            '30j'        => now()->subDays(30)->startOfDay(),
            '3m'         => now()->subMonths(3)->startOfDay(),
            default      => null,
        };

        if ($debut) {
            return [$debut->toDateString(), $fin->toDateString()];
        }

        $dates = $lignes->pluck('date_action')->filter();
        if ($dates->isEmpty()) {
            return [now()->toDateString(), now()->toDateString()];
        }

        return [
            $dates->min()->toDateString(),
            $dates->max()->toDateString(),
        ];
    }

    private function libelleAction(string $action): string
    {
        $meta = config("itassel.journal.actions.{$action}", [$action]);

        return is_array($meta) ? ($meta[0] ?? $action) : $action;
    }
}
