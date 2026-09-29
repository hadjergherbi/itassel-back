<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Complement;
use App\Models\Doleance;
use App\Models\Historique;
use App\Models\Reaffectation;
use App\Models\Service;
use App\Models\Utilisateur;
use App\Services\JournalService;
use App\Services\NotificationDispatcher;
use App\Services\ReaffectationService;
use App\Support\Acces;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReaffectationAdminController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'etat' => ['nullable', 'string', 'in:en_attente,acceptee,refusee,annulee,sans_suite'],
        ]);

        $query = Reaffectation::with([
            'doleance:id_doleance,reference,id_service,id_statut',
            'demandeur:id_utilisateur,nom,prenom',
            'servicePropose:id_service,nom_service',
            'serviceDestination:id_service,nom_service',
        ])->orderByDesc('date_demande');

        if (! empty($data['etat'])) {
            $query->where('etat', $data['etat']);
        }

        return response()->json(
            $query->paginate(25)->through(fn (Reaffectation $r) => $r->versApi())
        );
    }

    /**
     * POST /api/admin/doleances/{reference}/reaffectation
     * Réservé à l'administrateur de service.
     */
    public function demander(Request $request, string $reference)
    {
        $utilisateur = $request->user();

        if ($utilisateur->estSuperAdmin()) {
            return response()->json([
                'message' => 'Le Super administrateur réaffecte le dossier directement.',
            ], 403);
        }

        $doleance = $this->trouverVisible($utilisateur, $reference);
        if (! $doleance) {
            return $this->introuvable();
        }

        $data = $request->validate([
            'id_service_propose' => ['nullable', 'integer', 'exists:services,id_service'],
            'motif' => ['required', 'string', 'max:2000'],
        ]);

        if (ReaffectationService::dossierConclu($doleance)) {
            return $this->conflit('Ce dossier est déjà conclu.', 'dossier_conclu');
        }

        $deja = Reaffectation::where('id_doleance', $doleance->id_doleance)
            ->where('etat', 'en_attente')
            ->exists();

        if ($deja) {
            return $this->conflit('Une demande de réaffectation est déjà en attente.', 'demande_en_attente');
        }

        [$demande, $evenement] = DB::transaction(function () use ($doleance, $utilisateur, $data) {
            $demande = Reaffectation::create([
                'etat' => 'en_attente',
                'motif' => $data['motif'],
                'date_demande' => now(),
                'id_doleance' => $doleance->id_doleance,
                'id_demandeur' => $utilisateur->id_utilisateur,
                'id_service_propose' => $data['id_service_propose'] ?? null,
            ]);

            $evenement = Historique::create([
                'date_evenement' => now(),
                'type_evenement' => 'reaffectation_demande',
                'detail' => $data['motif'],
                'visible_demandeur' => false,
                'id_doleance' => $doleance->id_doleance,
                'id_utilisateur' => $utilisateur->id_utilisateur,
            ]);

            return [$demande, $evenement];
        });

        NotificationDispatcher::emettre(
            'reaffectation_demandee',
            $doleance,
            [
                'titre' => "Demande de réaffectation — {$doleance->reference}",
                'texte' => "Une réaffectation est demandée pour le dossier {$doleance->reference} : {$data['motif']}",
            ],
            $utilisateur,
            $evenement->id_evenement,
        );

        JournalService::action(
            $request,
            $utilisateur,
            'reaffectation_demande',
            "{$doleance->reference} : {$doleance->service?->nom_service} → ".($data['id_service_propose'] ?? '—'),
            $doleance,
        );

        return response()->json([
            'message' => 'Demande de réaffectation enregistrée.',
            'reaffectation' => $demande->fresh()->versApi(),
        ], 201);
    }

    /**
     * POST /api/admin/reaffectations/{id}/annuler
     */
    public function annuler(Request $request, int $id)
    {
        $utilisateur = $request->user();
        $demande = Reaffectation::with('doleance')->find($id);

        if (! $demande || (int) $demande->id_demandeur !== (int) $utilisateur->id_utilisateur) {
            return response()->json(['message' => 'Seul l\'auteur de la demande peut l\'annuler.'], 403);
        }

        $data = $request->validate([
            'motif' => ['required', 'string', 'max:2000'],
        ]);

        if ($demande->etat !== 'en_attente') {
            return $this->conflit('Cette demande ne peut plus être annulée.', ReaffectationService::codeConflitEtat($demande->etat));
        }

        DB::transaction(function () use ($demande, $utilisateur, $data) {
            $demande->update([
                'etat' => 'annulee',
                'date_decision' => now(),
            ]);

            Historique::create([
                'date_evenement' => now(),
                'type_evenement' => 'reaffectation_annulee',
                'detail' => $data['motif'],
                'visible_demandeur' => false,
                'id_doleance' => $demande->id_doleance,
                'id_utilisateur' => $utilisateur->id_utilisateur,
            ]);
        });

        JournalService::action(
            $request,
            $utilisateur,
            'reaffectation_annulee',
            $demande->doleance?->reference,
            $demande->doleance,
        );

        return response()->json([
            'message' => 'Demande de réaffectation annulée.',
            'reaffectation' => $demande->fresh()->versApi(),
        ]);
    }

    /**
     * POST /api/admin/reaffectations/{id}/refuser
     */
    public function refuser(Request $request, int $id)
    {
        $utilisateur = $request->user();

        $demande = Reaffectation::with(['doleance', 'demandeur'])->find($id);
        if (! $demande) {
            return $this->introuvable();
        }

        $data = $request->validate([
            'motif' => ['required', 'string', 'max:2000'],
        ]);

        if ($demande->etat !== 'en_attente') {
            return $this->conflit('Cette demande n\'est plus en attente.', ReaffectationService::codeConflitEtat($demande->etat));
        }

        $evenement = DB::transaction(function () use ($demande, $utilisateur, $data) {
            $demande->update([
                'etat' => 'refusee',
                'date_decision' => now(),
                'id_decideur' => $utilisateur->id_utilisateur,
            ]);

            return Historique::create([
                'date_evenement' => now(),
                'type_evenement' => 'reaffectation_refusee',
                'detail' => $data['motif'],
                'visible_demandeur' => false,
                'id_doleance' => $demande->id_doleance,
                'id_utilisateur' => $utilisateur->id_utilisateur,
            ]);
        });

        if ($demande->doleance && $demande->demandeur) {
            NotificationDispatcher::emettre(
                'reaffectation_decidee',
                $demande->doleance,
                [
                    'demandeur_reaffectation' => $demande->demandeur,
                    'titre' => "Réaffectation refusée — {$demande->doleance->reference}",
                    'texte' => $data['motif'],
                ],
                $utilisateur,
                $evenement->id_evenement,
            );
        }

        JournalService::action(
            $request,
            $utilisateur,
            'reaffectation_refusee',
            $demande->doleance?->reference,
            $demande->doleance,
        );

        return response()->json([
            'message' => 'Demande de réaffectation refusée.',
            'reaffectation' => $demande->fresh()->versApi(),
        ]);
    }

    /**
     * POST /api/admin/reaffectations/{id}/accepter
     */
    public function accepter(Request $request, int $id)
    {
        $utilisateur = $request->user();

        $demande = Reaffectation::with(['doleance.service', 'demandeur'])->find($id);
        if (! $demande || ! $demande->doleance) {
            return $this->introuvable();
        }

        $data = $request->validate([
            'id_service_destination' => ['required', 'integer', 'exists:services,id_service'],
            'motif' => ['nullable', 'string', 'max:2000'],
            'notifier_responsable' => ['nullable', 'boolean'],
        ]);

        if ($demande->etat !== 'en_attente') {
            return $this->conflit('Cette demande n\'est plus en attente.', ReaffectationService::codeConflitEtat($demande->etat));
        }

        $destination = Service::findOrFail($data['id_service_destination']);
        $doleance = $demande->doleance;
        $nomOrigine = $doleance->service?->nom_service ?? '—';

        if ((int) $destination->id_service === (int) $doleance->id_service) {
            return response()->json([
                'message' => 'Le service de destination doit être différent du service actuel.',
                'errors' => ['id_service_destination' => ['Le service de destination doit être différent du service actuel.']],
            ], 422);
        }

        $motif = isset($data['motif']) ? trim((string) $data['motif']) : (string) $demande->motif;
        $notifier = $request->boolean('notifier_responsable', true);
        $resultat = $this->transferer($request, $doleance, $destination, $utilisateur, $demande, $nomOrigine, $motif !== '' ? $motif : null, $notifier);

        return response()->json([
            'message' => 'Doléance réaffectée.',
            'doleance' => [
                'reference' => $doleance->reference,
                'service' => $destination->nom_service,
            ],
            'demande_reglee' => true,
            'email_nouveau_responsable' => $resultat['email_nouveau_responsable'],
        ]);
    }

    /**
     * POST /api/admin/doleances/{reference}/reaffecter
     */
    public function reaffecter(Request $request, string $reference)
    {
        $utilisateur = $request->user();

        $doleance = $this->trouverVisible($utilisateur, $reference);
        if (! $doleance) {
            return $this->introuvable();
        }

        $data = $request->validate([
            'id_service_destination' => ['required', 'integer', 'exists:services,id_service'],
            'motif' => ['required', 'string', 'max:2000'],
            'notifier_responsable' => ['nullable', 'boolean'],
        ]);

        $destination = Service::findOrFail($data['id_service_destination']);
        $doleance->loadMissing('service');
        $nomOrigine = $doleance->service?->nom_service ?? '—';

        if (ReaffectationService::dossierConclu($doleance)) {
            return $this->conflit('Ce dossier est déjà conclu.', 'dossier_conclu');
        }

        if ((int) $destination->id_service === (int) $doleance->id_service) {
            return response()->json([
                'message' => 'Le service de destination doit être différent du service actuel.',
                'errors' => ['id_service_destination' => ['Le service de destination doit être différent du service actuel.']],
            ], 422);
        }

        $demande = Reaffectation::with('demandeur')
            ->where('id_doleance', $doleance->id_doleance)
            ->where('etat', 'en_attente')
            ->first();

        $notifier = $request->boolean('notifier_responsable', true);
        $resultat = $this->transferer(
            $request,
            $doleance,
            $destination,
            $utilisateur,
            $demande,
            $nomOrigine,
            $data['motif'],
            $notifier,
        );

        return response()->json([
            'message' => 'Doléance réaffectée.',
            'doleance' => [
                'reference' => $doleance->reference,
                'service' => $destination->nom_service,
            ],
            'demande_reglee' => $demande !== null,
            'email_nouveau_responsable' => $resultat['email_nouveau_responsable'],
        ]);
    }

    /* ------------------------------------------------------------------ */

    private function transferer(
        Request $request,
        Doleance $doleance,
        Service $destination,
        Utilisateur $decideur,
        ?Reaffectation $demande,
        string $nomOrigine,
        ?string $motifDirect = null,
        bool $notifierResponsable = true,
    ): array {
        $evenement = DB::transaction(function () use ($doleance, $destination, $decideur, $demande, $nomOrigine, $motifDirect) {
            if ($demande) {
                $demande->update([
                    'etat' => 'acceptee',
                    'date_decision' => now(),
                    'id_decideur' => $decideur->id_utilisateur,
                    'id_service_destination' => $destination->id_service,
                ]);
            } else {
                Reaffectation::create([
                    'etat' => 'acceptee',
                    'motif' => $motifDirect,
                    'date_demande' => now(),
                    'date_decision' => now(),
                    'id_doleance' => $doleance->id_doleance,
                    'id_demandeur' => $decideur->id_utilisateur,
                    'id_decideur' => $decideur->id_utilisateur,
                    'id_service_destination' => $destination->id_service,
                ]);
            }

            ReaffectationService::appliquerAuDossier($doleance, $destination);

            $lignes = ["Réaffectée : {$nomOrigine} → {$destination->nom_service}"];
            $motif = trim((string) $motifDirect);
            if ($motif !== '') {
                $lignes[] = $motif;
            }
            $complementConserve = Complement::where('id_doleance', $doleance->id_doleance)
                ->whereIn('etat', ['en_attente', 'recu'])
                ->exists();
            if ($complementConserve) {
                $lignes[] = "Complément conservé ; suivi par le service {$destination->nom_service}";
            }

            return Historique::create([
                'date_evenement' => now(),
                'type_evenement' => 'reaffectation',
                'detail' => implode("\n", $lignes),
                'visible_demandeur' => false,
                'id_doleance' => $doleance->id_doleance,
                'id_utilisateur' => $decideur->id_utilisateur,
            ]);
        });

        $doleance->refresh()->load(['responsable', 'service']);

        $auteur = $demande?->demandeur;
        if ($auteur) {
            NotificationDispatcher::emettre(
                'reaffectation_decidee',
                $doleance,
                [
                    'demandeur_reaffectation' => $auteur,
                    'titre' => "Réaffectation acceptée — {$doleance->reference}",
                    'texte' => "Le dossier {$doleance->reference} a été réaffecté vers {$destination->nom_service}.",
                ],
                $decideur,
                $evenement->id_evenement,
            );
        }

        $notif = NotificationDispatcher::emettre(
            'doleance_reaffectee',
            $doleance,
            [
                'titre' => "Dossier réaffecté — {$doleance->reference}",
                'texte' => "Le dossier {$doleance->reference} est désormais affecté à votre service.",
            ],
            $decideur,
            $evenement->id_evenement,
            ['notifier_responsable' => $notifierResponsable],
        );

        JournalService::action(
            $request,
            $decideur,
            $demande ? 'reaffectation_acceptee' : 'reaffectation',
            "{$doleance->reference} : {$nomOrigine} → {$destination->nom_service}",
            $doleance,
        );

        return [
            'email_nouveau_responsable' => $notif['email_responsable'] ?? null,
        ];
    }

    private function trouverVisible(Utilisateur $utilisateur, string $reference): ?Doleance
    {
        return Acces::doleancesVisibles($utilisateur)
            ->with('statut')
            ->where('reference', strtoupper($reference))
            ->first();
    }

    private function introuvable()
    {
        return response()->json(['message' => 'Doléance introuvable.'], 404);
    }

    private function conflit(string $message, string $code)
    {
        return response()->json(['message' => $message, 'code' => $code], 409);
    }
}
