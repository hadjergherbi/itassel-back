<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Complement;
use App\Models\Doleance;
use App\Models\Historique;
use App\Models\Statut;
use App\Services\NotificationDispatcher;
use App\Services\PieceJointeService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SuiviComplementController extends Controller
{
    /**
     * POST /api/suivi/repondre-complement
     * Le demandeur répond à la demande de complément en attente.
     */
    public function repondre(Request $request)
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'piece_jointe' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $idDoleance = SuiviController::idDoleanceDepuisEntete($request);

        if (! $idDoleance) {
            return response()->json(['message' => 'Session expirée ou invalide.'], 401);
        }

        $doleance = Doleance::with('statut')->findOrFail($idDoleance);

        $complement = Complement::where('id_doleance', $doleance->id_doleance)
            ->where('etat', 'en_attente')
            ->latest('date_demande')
            ->first();

        if (! $complement) {
            return response()->json([
                'message' => 'Aucune demande de complément en attente pour ce dossier.',
            ], 404);
        }

        if ($doleance->statut?->code !== Statut::INFORMATION_DEMANDEE) {
            return response()->json([
                'message' => 'Ce dossier n\'est plus en attente d\'information. Vous ne pouvez plus répondre à cette demande.',
            ], 409);
        }

        if ($complement->piece_exigee && ! $request->hasFile('piece_jointe')) {
            return response()->json([
                'errors' => ['piece_jointe' => ['Une pièce jointe est obligatoire pour cette demande.']],
            ], 422);
        }

        $fichier = $request->file('piece_jointe');
        if ($fichier instanceof UploadedFile) {
            $extension = strtolower($fichier->getClientOriginalExtension());
            if (in_array($extension, ['jpg', 'jpeg', 'png'], true)
                && ! PieceJointeService::reencoderImageSansExif($fichier, $extension)) {
                throw ValidationException::withMessages([
                    'piece_jointe' => ['Image invalide.'],
                ]);
            }
        }

        $evenement = DB::transaction(function () use ($complement, $doleance, $data, $fichier) {
            $complement->update([
                'reponse' => $data['message'],
                'date_reponse' => now(),
                'etat' => 'recu',
            ]);

            if ($fichier instanceof UploadedFile) {
                PieceJointeService::enregistrer(
                    $fichier,
                    $doleance,
                    'COMPLEMENT',
                    $complement->id_complement,
                );
            }

            // Le dossier repart en traitement : le service doit examiner la réponse.
            $statutAvant = $doleance->id_statut;
            $statutEnCours = Statut::parCode(Statut::EN_COURS);
            $doleance->update(['id_statut' => $statutEnCours->id_statut]);

            return Historique::create([
                'date_evenement' => now(),
                'type_evenement' => 'complement_recu',
                'detail' => 'Réponse du demandeur au complément demandé.',
                'visible_demandeur' => true,
                'id_doleance' => $doleance->id_doleance,
                'id_statut_avant' => $statutAvant,
                'id_statut_apres' => $statutEnCours?->id_statut,
            ]);
        });

        NotificationDispatcher::emettre(
            'complement_recu',
            $doleance->fresh(['responsable', 'service.responsable']),
            [
                'titre' => "Complément reçu — {$doleance->reference}",
                'texte' => "Le demandeur a répondu à une demande de complément pour le dossier {$doleance->reference}.",
            ],
            null,
            $evenement->id_evenement,
        );

        return response()->json([
            'message' => 'Votre réponse a bien été transmise au service concerné.',
        ]);
    }
}
