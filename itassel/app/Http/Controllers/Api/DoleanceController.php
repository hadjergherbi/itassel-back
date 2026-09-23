<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Doleance;
use App\Models\Historique;
use App\Models\PieceJointe;
use App\Models\Service;
use App\Models\Statut;
use App\Services\NotificationDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DoleanceController extends Controller
{
    /**
     * POST /api/doleances
     * Dépôt d'une doléance depuis le formulaire public (écran "Déposer une doléance").
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'nom'          => ['required', 'string', 'max:60'],
            'prenom'       => ['required', 'string', 'max:60'],
            'email'        => ['required', 'email', 'max:120'],
            'telephone'    => ['required', 'string', 'max:20'],
            'wilaya'       => ['required', 'string', 'max:40'],
            'objet'        => ['required', 'string', 'max:200'],
            'description'  => ['required', 'string'],
            'id_service'   => ['required', 'exists:services,id_service'],
            'id_nature'    => ['required', 'exists:natures,id_nature'],
            'id_qualite'   => ['required', 'exists:qualites,id_qualite'],
            'piece_jointe' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'], // 5 Mo
        ]);

        [$doleance, $evenement] = DB::transaction(function () use ($data, $request) {
            $statutNouvelle = Statut::parCode(Statut::NOUVELLE);

            $doleance = Doleance::create([
                'reference'   => $this->genererReference(),
                'nom'         => $data['nom'],
                'prenom'      => $data['prenom'],
                'email'       => $data['email'],
                'telephone'   => $data['telephone'],
                'wilaya'      => $data['wilaya'],
                'objet'       => $data['objet'],
                'description' => $data['description'],
                'date_depot'  => now(),
                'id_service'  => $data['id_service'],
                'id_statut'   => $statutNouvelle->id_statut,
                'id_nature'   => $data['id_nature'],
                'id_qualite'  => $data['id_qualite'],
            ]);

            if ($request->hasFile('piece_jointe')) {
                $fichier = $request->file('piece_jointe');
                $chemin = $fichier->store('pieces-jointes/'.$doleance->id_doleance, 'local');
                $extension = strtolower($fichier->getClientOriginalExtension());

                PieceJointe::create([
                    'nom_fichier' => $fichier->getClientOriginalName(),
                    'type'        => $extension === 'jpeg' ? 'jpg' : $extension,
                    'taille'      => $fichier->getSize(),
                    'chemin'      => $chemin,
                    'origine'     => 'DEPOT_INITIAL',
                    'id_doleance' => $doleance->id_doleance,
                ]);
            }

            $evenement = Historique::create([
                'date_evenement'    => now(),
                'type_evenement'    => 'depot',
                'visible_demandeur' => true,
                'id_doleance'       => $doleance->id_doleance,
                'id_statut_apres'   => $statutNouvelle->id_statut,
            ]);

            $nomService = Service::whereKey($doleance->id_service)->value('nom_service') ?? '—';
            Historique::create([
                'date_evenement'    => now(),
                'type_evenement'    => 'affectation',
                'detail'            => "Affectée au service {$nomService}",
                'visible_demandeur' => false,
                'id_doleance'       => $doleance->id_doleance,
                'id_utilisateur'    => null,
            ]);

            return [$doleance, $evenement];
        });

        NotificationDispatcher::emettre(
            'doleance_deposee',
            $doleance->fresh(['service.responsable', 'responsable', 'statut']),
            [
                'titre' => "Nouvelle doléance — {$doleance->reference}",
                'texte' => "Le dossier {$doleance->reference} a été déposé.",
            ],
            null,
            $evenement->id_evenement,
        );

        return response()->json([
            'reference' => $doleance->reference,
            'message'   => 'Votre doléance a bien été enregistrée.',
        ], 201);
    }

    /**
     * Génère une référence unique au format ITS-AAAA-NNNN, comme sur les maquettes.
     */
    private function genererReference(): string
    {
        $annee = now()->year;

        do {
            $numero = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $reference = "ITS-{$annee}-{$numero}";
        } while (Doleance::where('reference', $reference)->exists());

        return $reference;
    }
}
