<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Statut;
use App\Support\Acces;
use Illuminate\Http\Request;

class DoleanceAdminController extends Controller
{
    /**
     * GET /api/admin/statuts
     * Liste des statuts (filtres de la liste, menu « changer le statut »).
     */
    public function statuts()
    {
        return response()->json(
            Statut::orderBy('id_statut')->get(['id_statut', 'libelle', 'couleur'])
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

        $filtres = $request->validate([
            'statut'  => ['nullable', 'integer'],
            'service' => ['nullable', 'integer'],
            'q'       => ['nullable', 'string', 'max:100'],
            'page'    => ['nullable', 'integer', 'min:1'],
        ]);

        $query = Acces::doleancesVisibles($utilisateur);

        // Filtre par service : réservé au Super administrateur.
        if (! empty($filtres['service']) && $utilisateur->estSuperAdmin()) {
            $query->where('id_service', $filtres['service']);
        }

        if (! empty($filtres['q'])) {
            $texte = '%'.addcslashes($filtres['q'], '%_').'%';
            $query->where(function ($w) use ($texte) {
                $w->where('reference', 'like', $texte)
                  ->orWhere('nom', 'like', $texte)
                  ->orWhere('prenom', 'like', $texte)
                  ->orWhere('objet', 'like', $texte);
            });
        }

        // Compteurs calculés AVANT le filtre de statut : chaque pastille garde son total.
        $compteurs = (clone $query)
            ->selectRaw('id_statut, COUNT(*) AS total')
            ->groupBy('id_statut')
            ->pluck('total', 'id_statut');

        if (! empty($filtres['statut'])) {
            $query->where('id_statut', $filtres['statut']);
        }

        $page = $query
            ->with([
                'statut:id_statut,libelle,couleur',
                'service:id_service,nom_service',
                'responsable:id_utilisateur,nom,prenom',
            ])
            ->orderByDesc('date_depot')
            ->paginate(15, [
                'id_doleance', 'reference', 'nom', 'prenom', 'objet', 'wilaya',
                'date_depot', 'id_statut', 'id_service', 'id_responsable',
            ]);

        return response()->json([
            'doleances' => $page,
            'compteurs' => $compteurs,
        ]);
    }

    /**
     * GET /api/admin/doleances/{reference}
     * Détail complet d'une doléance. Répond 404 si elle n'existe pas OU si
     * l'utilisateur n'y a pas accès (on ne révèle pas son existence).
     */
    public function show(Request $request, string $reference)
    {
        $doleance = Acces::doleancesVisibles($request->user())
            ->where('reference', strtoupper($reference))
            ->with([
                'statut', 'service', 'nature', 'qualite',
                'responsable:id_utilisateur,nom,prenom,email',
                'doleanceInitiale:id_doleance,reference',
                'piecesJointes',
                'complements' => fn ($q) => $q->orderByDesc('date_demande')
                    ->with('auteur:id_utilisateur,nom,prenom'),
                'reponses' => fn ($q) => $q->orderByDesc('date_publication')
                    ->with('auteur:id_utilisateur,nom,prenom'),
                'notesInternes' => fn ($q) => $q->orderByDesc('date_creation')
                    ->with('auteur:id_utilisateur,nom,prenom'),
                'historique' => fn ($q) => $q->with([
                    'utilisateur:id_utilisateur,nom,prenom',
                    'statutAvant:id_statut,libelle',
                    'statutApres:id_statut,libelle',
                ]),
                'notifications' => fn ($q) => $q->orderByDesc('id_notification'),
            ])
            ->first();

        if (! $doleance) {
            return response()->json(['message' => 'Doléance introuvable.'], 404);
        }

        return response()->json($doleance);
    }
}
