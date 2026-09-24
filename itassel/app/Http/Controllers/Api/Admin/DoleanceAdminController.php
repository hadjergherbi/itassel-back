<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Doleance;
use App\Models\Nature;
use App\Models\Reaffectation;
use App\Models\Service;
use App\Models\Statut;
use App\Models\Utilisateur;
use App\Services\JournalService;
use App\Services\ReclassementService;
use App\Support\Acces;
use App\Support\DoleanceFiltre;
use App\Support\Periode;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DoleanceAdminController extends Controller
{
    /**
     * GET /api/admin/statuts
     * Liste des statuts (filtres de la liste, menu « changer le statut »).
     */
    public function statuts()
    {
        return response()->json(
            Statut::orderBy('id_statut')->get(['id_statut', 'code', 'libelle', 'couleur'])
        );
    }

    /**
     * GET /api/admin/doleances?statut=&service=&q=&page=
     * Liste paginée (15 par page) des doléances visibles par l'utilisateur,
     * plus le nombre de doléances par statut (pour les pastilles de filtre).
     */
    public function index(Request $request)
    {
        $utilisateur = $request->user();

        $filtres = DoleanceFiltre::valider($request);
        $query = $this->requeteFiltree($utilisateur, $filtres);

        // Compteurs calculés AVANT le filtre de statut : chaque pastille garde son total.
        $compteurs = (clone $query)
            ->selectRaw('id_statut, COUNT(*) AS total')
            ->groupBy('id_statut')
            ->pluck('total', 'id_statut');

        $aExaminer = (clone $query)
            ->whereHas('complements', fn ($q) => $q->where('etat', 'recu'))
            ->count();

        $resume = [
            'total'                     => (clone $query)->count(),
            'sans_responsable'          => DoleanceFiltre::appliquerSansResponsable(clone $query)->count(),
            'reaffectations_en_attente' => (clone $query)
                ->whereHas('reaffectations', fn ($q) => $q->where('etat', 'en_attente'))
                ->count(),
            'a_examiner'                => $aExaminer,
            'a_reclasser'               => DoleanceFiltre::appliquerAReclasser(clone $query)->count(),
        ];

        DoleanceFiltre::appliquerStatut($query, $filtres);

        if (! empty($filtres['a_examiner'])) {
            $query->whereHas('complements', fn ($q) => $q->where('etat', 'recu'));
        }

        if ($utilisateur->estSuperAdmin() && ($filtres['reaffectation'] ?? null) === 'en_attente') {
            $query->whereHas('reaffectations', fn ($q) => $q->where('etat', 'en_attente'));
        }

        $tri = $filtres['tri'] ?? 'date_depot';
        $sens = ($filtres['sens'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $parPage = min(100, (int) ($filtres['par_page'] ?? 15));

        $page = $query
            ->with([
                'statut:id_statut,code,libelle,couleur',
                'service:id_service,nom_service,id_responsable',
                'service.responsable:id_utilisateur,nom,prenom',
                'nature:id_nature,libelle',
                'responsable:id_utilisateur,nom,prenom',
            ])
            ->withCount('notesInternes as nb_notes')
            ->withExists([
                'complements as complement_a_examiner' => fn ($q) => $q->where('etat', 'recu'),
                'reaffectations as reaffectation_en_attente' => fn ($q) => $q->where('etat', 'en_attente'),
            ])
            ->orderBy($tri, $sens)
            ->paginate($parPage, [
                'id_doleance', 'reference', 'nom', 'prenom', 'objet', 'wilaya',
                'date_depot', 'id_statut', 'id_service', 'id_nature', 'id_responsable',
            ]);

        $page->through(function ($doleance) {
            $doleance->setAttribute('nb_notes', (int) $doleance->nb_notes);
            $doleance->setAttribute('complement_a_examiner', (bool) $doleance->complement_a_examiner);
            $doleance->setAttribute('reaffectation_en_attente', (bool) $doleance->reaffectation_en_attente);
            $doleance->setAttribute('service_sans_responsable', $doleance->service?->id_responsable === null);

            return $doleance;
        });

        $reponse = [
            'doleances'  => $page,
            'compteurs'  => $compteurs,
            'a_examiner' => $aExaminer,
            'resume'     => $resume,
        ];

        if ($utilisateur->estSuperAdmin()) {
            $reponse['reaffectations_en_attente'] = Reaffectation::where('etat', 'en_attente')->count();
        }

        return response()->json($reponse);
    }

    /**
     * GET /api/admin/doleances/{reference}
     * Détail complet d'une doléance. Répond 404 si elle n'existe pas OU si
     * l'utilisateur n'y a pas accès (on ne révèle pas son existence).
     */
    public function show(Request $request, string $reference)
    {
        $utilisateur = $request->user();
        $doleance = Acces::doleancesVisibles($utilisateur)
            ->where('reference', strtoupper($reference))
            ->with([
                'statut', 'service', 'nature', 'qualite',
                'responsable:id_utilisateur,nom,prenom,email',
                'doleanceInitiale:id_doleance,reference',
                'piecesJointes',
                'complements' => fn ($q) => $q->orderByDesc('date_demande')
                    ->with([
                        'auteur:id_utilisateur,nom,prenom',
                        'annulePar:id_utilisateur,nom,prenom',
                        'piecesJointes:id_piece,id_complement,nom_fichier,type,taille,origine',
                    ]),
                'reponses' => fn ($q) => $q->orderByDesc('date_publication')
                    ->with('auteur:id_utilisateur,nom,prenom'),
                'notesInternes' => fn ($q) => $q
                    ->orderByDesc('epinglee')
                    ->orderBy('epinglee_le')
                    ->orderBy('created_at')
                    ->with([
                        'auteur.service',
                        'mentions:id_utilisateur,nom,prenom',
                        'epingleePar:id_utilisateur,nom,prenom',
                    ]),
                'historique' => fn ($q) => $q->with([
                    'utilisateur:id_utilisateur,nom,prenom',
                    'statutAvant:id_statut,code,libelle,couleur',
                    'statutApres:id_statut,code,libelle,couleur',
                    'notifications',
                ]),
                'notifications' => fn ($q) => $q->orderByDesc('id_notification'),
                'reaffectations' => fn ($q) => $q->orderByDesc('date_demande')
                    ->with([
                        'demandeur:id_utilisateur,nom,prenom',
                        'decideur:id_utilisateur,nom,prenom',
                        'servicePropose:id_service,nom_service',
                        'serviceDestination:id_service,nom_service',
                    ]),
            ])
            ->first();

        if (! $doleance) {
            return Acces::reponseDossierReaffecte($utilisateur, $reference)
                ?? response()->json(['message' => 'Doléance introuvable.'], 404);
        }

        $emailsSuperAdmin = Utilisateur::where('role', 'super_admin')->pluck('email');

        $reaffectations = $doleance->reaffectations->map->versApi()->values();

        $donnees = $doleance->toArray();
        $donnees['notes_internes'] = $doleance->notesInternes
            ->sort(function ($a, $b) {
                if ((bool) $a->epinglee !== (bool) $b->epinglee) {
                    return $b->epinglee <=> $a->epinglee;
                }
                if ($a->epinglee) {
                    return ($a->epinglee_le?->timestamp ?? 0) <=> ($b->epinglee_le?->timestamp ?? 0);
                }

                return ($a->dateReference()?->timestamp ?? 0) <=> ($b->dateReference()?->timestamp ?? 0);
            })
            ->values()
            ->map(fn ($note) => $note->versApi($utilisateur))
            ->values();
        $donnees['complements'] = $doleance->complements->map(function ($complement) {
            $ligne = $complement->toArray();
            $ligne['annule_par'] = $complement->annulePar
                ? $complement->annulePar->only(['id_utilisateur', 'nom', 'prenom'])
                : null;
            $ligne['date_annulation'] = $complement->date_annulation;

            return $ligne;
        })->values();

        return response()->json(array_merge($donnees, [
            'organisme_competent'      => $doleance->organisme_competent,
            'a_reclasser'              => ReclassementService::estAReclasser($doleance),
            'reclassements_possibles'  => ReclassementService::ciblesPossibles($doleance)->map->versApi()->values(),
            'transitions_autorisees'   => $doleance->transitionsAutorisees()->map->versApi()->values(),
            'complement_a_examiner'    => $doleance->complementAExaminer(),
            'reaffectations'           => $reaffectations,
            'reaffectation_en_attente' => $doleance->reaffectations
                ->firstWhere('etat', 'en_attente')
                ?->versApi(),
            'historique'               => $doleance->historique->map(fn ($evenement) => [
                'id_evenement'      => $evenement->id_evenement,
                'date_evenement'    => $evenement->date_evenement,
                'type_evenement'    => $evenement->type_evenement,
                'detail'            => $evenement->detail,
                'visible_demandeur' => $evenement->visible_demandeur,
                'utilisateur'       => $evenement->utilisateur
                    ? $evenement->utilisateur->only(['id_utilisateur', 'nom', 'prenom'])
                    : null,
                'statut_avant'      => $evenement->statutAvant?->versApi(),
                'statut_apres'      => $evenement->statutApres?->versApi(),
                'notifications'     => $evenement->notifications
                    ->map(fn ($n) => $n->versApi($doleance->email, $emailsSuperAdmin))
                    ->values(),
            ])->values(),
        ]));
    }

    /**
     * GET /api/admin/doleances/export/apercu
     */
    public function apercuExport(Request $request)
    {
        $utilisateur = $request->user();
        $filtres = DoleanceFiltre::valider($request, false);
        [$dateDebut, $dateFin] = Periode::depuisFiltre($filtres);

        $periode = DoleanceFiltre::appliquer(
            Acces::doleancesVisibles($utilisateur),
            $utilisateur,
            collect($filtres)->except(['nature', 'natures'])->all(),
        );

        $totaux = (clone $periode)
            ->selectRaw('id_nature, COUNT(*) AS total')
            ->groupBy('id_nature')
            ->pluck('total', 'id_nature');

        $parNature = Nature::orderBy('libelle')->get(['id_nature', 'libelle'])
            ->map(fn (Nature $nature) => [
                'id_nature' => $nature->id_nature,
                'libelle'   => $nature->libelle,
                'total'     => (int) ($totaux[$nature->id_nature] ?? 0),
            ])->values();

        $total = $this->requeteFiltree($utilisateur, $filtres)->count();

        return response()->json([
            'total'      => $total,
            'par_nature' => $parNature,
            'date_debut' => $dateDebut,
            'date_fin'   => $dateFin,
        ]);
    }

    /**
     * GET /api/admin/doleances/export
     * Mêmes filtres que la liste, sans pagination. CSV UTF-8 avec BOM, séparateur « ; ».
     */
    public function export(Request $request)
    {
        $utilisateur = $request->user();
        $filtres = DoleanceFiltre::valider($request, false);
        $query = $this->requeteFiltree($utilisateur, $filtres);
        DoleanceFiltre::appliquerStatut($query, $filtres);

        if (! empty($filtres['a_examiner'])) {
            $query->whereHas('complements', fn ($q) => $q->where('etat', 'recu'));
        }

        $doleances = $query
            ->with([
                'statut:id_statut,libelle',
                'service:id_service,nom_service',
                'nature:id_nature,libelle',
                'responsable:id_utilisateur,nom,prenom',
            ])
            ->orderByDesc('date_depot')
            ->get();

        if ($doleances->isEmpty()) {
            return response()->json(['message' => 'Aucune doléance ne correspond à ces critères.'], 422);
        }

        [$dateDebut, $dateFin] = Periode::depuisFiltre($filtres);
        $slugService = $this->slugServiceExport($utilisateur, $filtres);
        $format = $filtres['format'] ?? 'csv';
        $nomFichier = 'doleances_'.$slugService.'_'.$dateDebut.'_'.$dateFin.'.'.$format;

        $detail = 'Liste des doléances';
        if (! $utilisateur->estSuperAdmin() && $utilisateur->service) {
            $detail .= ' — '.$utilisateur->service->nom_service;
        } elseif (! empty($filtres['service'])) {
            $nom = Service::whereKey($filtres['service'])->value('nom_service');
            if ($nom) {
                $detail .= ' — '.$nom;
            }
        }

        if ($format === 'pdf') {
            JournalService::action($request, $utilisateur, 'export_pdf', $detail);

            $natures = $doleances->pluck('nature.libelle')->filter()->unique()->values()->all();
            $libelleService = ! $utilisateur->estSuperAdmin() && $utilisateur->service
                ? $utilisateur->service->nom_service
                : (! empty($filtres['service'])
                    ? (Service::whereKey($filtres['service'])->value('nom_service') ?? 'Tous les services')
                    : 'Tous les services');
            $pdf = Pdf::loadView('exports.doleances', [
                'doleances'   => $doleances,
                'service'     => $libelleService,
                'date_debut'  => $dateDebut,
                'date_fin'    => $dateFin,
                'natures'     => $natures,
                'genere_le'   => now()->format('d/m/Y H:i'),
                'agent'       => $utilisateur->nomComplet(),
            ])->setPaper('a4', 'landscape');

            return $pdf->download($nomFichier);
        }

        $chemin = tmpfile();
        fwrite($chemin, "\xEF\xBB\xBF");
        fputcsv($chemin, [
            'Référence', 'Nom', 'Prénom', 'Catégorie', 'Nature', 'Service',
            'Wilaya', 'Date de dépôt', 'Statut', 'Responsable',
        ], ';');

        foreach ($doleances as $doleance) {
            fputcsv($chemin, [
                $doleance->reference,
                $doleance->nom,
                $doleance->prenom,
                $doleance->nature?->libelle ?? '',
                $doleance->nature?->libelle ?? '',
                $doleance->service?->nom_service ?? '',
                $doleance->wilaya,
                optional($doleance->date_depot)->format('d/m/Y'),
                $doleance->statut?->libelle ?? '',
                trim(($doleance->responsable?->prenom ?? '').' '.($doleance->responsable?->nom ?? '')),
            ], ';');
        }

        rewind($chemin);
        $contenu = stream_get_contents($chemin);
        fclose($chemin);

        JournalService::action($request, $utilisateur, 'export_csv', $detail);

        return response($contenu, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$nomFichier.'"',
        ]);
    }

    private function slugServiceExport(Utilisateur $utilisateur, array $filtres): string
    {
        $nom = null;
        if (! $utilisateur->estSuperAdmin() && $utilisateur->service) {
            $nom = $utilisateur->service->nom_service;
        } elseif (! empty($filtres['service'])) {
            $nom = Service::whereKey($filtres['service'])->value('nom_service');
        }

        $slug = Str::slug((string) ($nom ?: 'tous'), '_');

        return $slug !== '' ? $slug : 'tous';
    }

    private function requeteFiltree(Utilisateur $utilisateur, array $filtres): Builder
    {
        return DoleanceFiltre::appliquer(Acces::doleancesVisibles($utilisateur), $utilisateur, $filtres);
    }
}
