<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\ConflitMetier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AnnulerComplementRequest;
use App\Http\Requests\Admin\ReclasserDoleanceRequest;
use App\Models\Complement;
use App\Models\Doleance;
use App\Models\Historique;
use App\Models\NotificationItassel;
use App\Models\NoteInterne;
use App\Models\Reponse;
use App\Models\Statut;
use App\Models\Utilisateur;
use App\Services\ComplementService;
use App\Services\JournalService;
use App\Services\NoteInterneService;
use App\Services\NotificationDispatcher;
use App\Services\ReaffectationService;
use App\Services\ReclassementService;
use App\Support\Acces;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DoleanceActionController extends Controller
{
    /**
     * POST /api/admin/doleances/{reference}/statut
     * Corps : id_statut, message, question, description_piece,
     *         notifier_demandeur, notifier_responsable, reference_initiale.
     */
    public function changerStatut(Request $request, string $reference)
    {
        $utilisateur = $request->user();
        $doleance = $this->trouver($utilisateur, $reference);
        if (! $doleance) {
            return $this->introuvable();
        }

        $data = $request->validate([
            'id_statut'             => ['required', 'integer', 'exists:statuts,id_statut'],
            'message'               => ['nullable', 'string', 'max:2000'],
            'question'              => ['nullable', 'string', 'max:1000'],
            'description_piece'     => ['nullable', 'string', 'max:200'],
            'notifier_demandeur'    => ['nullable', 'boolean'],
            'notifier_responsable'  => ['nullable', 'boolean'],
            'reference_initiale'    => ['nullable', 'string', 'max:20'],
            'organisme_competent'   => ['nullable', 'string', 'max:150'],
        ]);

        $doleance->loadMissing('nature');
        $nouveau = Statut::findOrFail($data['id_statut']);
        $codeActuel = $doleance->statut?->code;
        $autorises = $doleance->transitionsAutorisees()->pluck('code')->all();
        $message = isset($data['message']) ? trim((string) $data['message']) : '';
        $question = isset($data['question']) ? trim((string) $data['question']) : '';
        $notifierDemandeur = $request->boolean('notifier_demandeur', true);
        $notifierResponsable = $request->boolean('notifier_responsable', false);

        if ((int) $nouveau->id_statut === (int) $doleance->id_statut) {
            return $this->erreur('id_statut', 'Le dossier a déjà ce statut.');
        }

        if (! in_array($nouveau->code, $autorises, true)) {
            return $this->erreur('id_statut', 'Cette transition de statut n\'est pas autorisée.');
        }

        if ($nouveau->code === Statut::INFORMATION_DEMANDEE) {
            if ($question === '') {
                return $this->erreur('question', 'Indiquez la question à poser au demandeur.');
            }

            return $this->passerEnInformationDemandee(
                $request,
                $doleance,
                $utilisateur,
                $question,
                $data['description_piece'] ?? null,
                $notifierResponsable,
                $nouveau,
            );
        }

        if ($nouveau->code === Statut::EN_COURS && $codeActuel === Statut::INFORMATION_DEMANDEE) {
            if ($message === '') {
                return $this->erreur('message', 'Indiquez le motif d\'annulation du complément en attente.');
            }

            return $this->annulerComplementEnCours($request, $doleance, $utilisateur, $message, $nouveau);
        }

        if (Statut::estIssue($nouveau->code)) {
            $exige = config("itassel.issues.{$nouveau->code}.exige");
            if ($exige === 'message' && $message === '') {
                return $this->erreur('message', 'Un message est obligatoire pour conclure le dossier.');
            }
            if ($doleance->complementAExaminer()) {
                return $this->erreur('id_statut', 'Examinez d\'abord le complément reçu avant de conclure le dossier.');
            }
        }

        $initiale = null;
        if ($nouveau->code === Statut::DOUBLE) {
            if (empty($data['reference_initiale'])) {
                return $this->erreur('reference_initiale', 'Indiquez la référence du dossier initial.');
            }
            $initiale = Acces::doleancesVisibles($utilisateur)
                ->where('reference', strtoupper(trim($data['reference_initiale'])))
                ->first();
            if (! $initiale || (int) $initiale->id_doleance === (int) $doleance->id_doleance) {
                return $this->erreur('reference_initiale', 'Dossier initial introuvable.');
            }
        }

        $evenement = DB::transaction(function () use ($doleance, $nouveau, $initiale, $utilisateur, $message, $data) {
            $statutAvant = $doleance->id_statut;

            $miseAJour = ['id_statut' => $nouveau->id_statut];
            if ($initiale) {
                $miseAJour['id_doleance_initial'] = $initiale->id_doleance;
            }
            if ($nouveau->code === Statut::HORS_COMPETENCE) {
                $miseAJour['organisme_competent'] = isset($data['organisme_competent'])
                    ? (trim((string) $data['organisme_competent']) ?: null)
                    : $doleance->organisme_competent;
            }
            $this->prendreEnCharge($doleance, $utilisateur, $miseAJour);
            $doleance->update($miseAJour);

            if (Statut::estIssue($nouveau->code)) {
                $enAttente = Complement::where('id_doleance', $doleance->id_doleance)
                    ->where('etat', 'en_attente')
                    ->get();
                foreach ($enAttente as $complement) {
                    ComplementService::annuler(
                        $complement,
                        $utilisateur,
                        "Dossier passé au statut « {$nouveau->libelle} ».",
                    );
                }
                ReaffectationService::passerSansSuite($doleance, $utilisateur);
            }

            return Historique::create([
                'date_evenement'    => now(),
                'type_evenement'    => 'changement_statut',
                'detail'            => $message !== '' ? $message : null,
                'visible_demandeur' => true,
                'id_doleance'       => $doleance->id_doleance,
                'id_utilisateur'    => $utilisateur->id_utilisateur,
                'id_statut_avant'   => $statutAvant,
                'id_statut_apres'   => $nouveau->id_statut,
            ]);
        });

        $doleance->refresh();

        $resultat = NotificationDispatcher::emettre(
            'changement_statut',
            $doleance,
            [
                'statut'  => $nouveau,
                'message' => $message !== '' ? $message : null,
                'titre'   => "Changement de statut — {$doleance->reference}",
                'texte'   => "Le dossier {$doleance->reference} est passé au statut « {$nouveau->libelle} ».",
            ],
            $utilisateur,
            $evenement->id_evenement,
            [
                'notifier_demandeur'   => $notifierDemandeur,
                'notifier_responsable' => $notifierResponsable,
            ],
        );

        JournalService::action(
            $request,
            $utilisateur,
            'changement_statut',
            "{$doleance->reference} : {$codeActuel} → {$nouveau->code}",
            $doleance,
        );

        return response()->json([
            'message'      => 'Statut mis à jour.',
            'statut'       => $nouveau->versApi(),
            'email_envoye' => $resultat['email_demandeur'],
        ]);
    }

    /**
     * POST /api/admin/doleances/{reference}/reponses
     * Corps : contenu, notifier (facultatif, vrai par défaut).
     * La réponse est visible du demandeur dans son suivi.
     */
    public function repondre(Request $request, string $reference)
    {
        $utilisateur = $request->user();
        $doleance = $this->trouver($utilisateur, $reference);
        if (! $doleance) {
            return $this->introuvable();
        }

        $data = $request->validate([
            'contenu'  => ['required', 'string', 'max:5000'],
            'notifier' => ['nullable', 'boolean'],
        ]);

        [$reponse, $evenement] = DB::transaction(function () use ($doleance, $utilisateur, $data) {
            $miseAJour = [];
            $this->prendreEnCharge($doleance, $utilisateur, $miseAJour);
            if ($miseAJour) {
                $doleance->update($miseAJour);
            }

            $reponse = Reponse::create([
                'contenu'          => $data['contenu'],
                'date_publication' => now(),
                'id_doleance'      => $doleance->id_doleance,
                'id_auteur'        => $utilisateur->id_utilisateur,
            ]);

            // Trace interne : la réponse elle-même apparaît déjà dans le suivi du demandeur.
            $evenement = Historique::create([
                'date_evenement'    => now(),
                'type_evenement'    => 'reponse',
                'visible_demandeur' => false,
                'id_doleance'       => $doleance->id_doleance,
                'id_utilisateur'    => $utilisateur->id_utilisateur,
            ]);

            return [$reponse, $evenement];
        });

        $resultat = NotificationDispatcher::emettre(
            'reponse_publiee',
            $doleance->fresh(),
            [
                'reponse' => $reponse,
                'titre'   => "Réponse publiée — {$doleance->reference}",
                'texte'   => "Une réponse a été publiée pour le dossier {$doleance->reference}.",
            ],
            $utilisateur,
            $evenement->id_evenement,
            ['notifier_demandeur' => $request->boolean('notifier', true)],
        );

        JournalService::action($request, $utilisateur, 'reponse', $doleance->reference, $doleance);

        return response()->json([
            'message'      => 'Réponse publiée.',
            'reponse'      => $reponse->load('auteur:id_utilisateur,nom,prenom'),
            'email_envoye' => $resultat['email_demandeur'],
        ], 201);
    }

    /**
     * GET /api/admin/doleances/{reference}/mentionnables
     */
    public function mentionnables(Request $request, string $reference)
    {
        $utilisateur = $request->user();
        $doleance = $this->trouver($utilisateur, $reference);
        if (! $doleance) {
            return $this->introuvable();
        }

        $liste = NoteInterneService::mentionnables(
            $doleance,
            $utilisateur,
            (string) $request->query('q', ''),
        )->map(fn (Utilisateur $u) => NoteInterneService::mentionnableVersApi($u))->values();

        return response()->json($liste);
    }

    /**
     * POST /api/admin/doleances/{reference}/notes
     * Corps : contenu, mentions, notifier_email, etiquette. Jamais visible du demandeur.
     */
    public function ajouterNote(Request $request, string $reference)
    {
        $utilisateur = $request->user();
        $doleance = $this->trouver($utilisateur, $reference);
        if (! $doleance) {
            return $this->introuvable();
        }

        $maxMentions = (int) config('itassel.notes.max_mentions', 5);
        $data = $request->validate([
            'contenu'        => ['required', 'string', 'max:2000'],
            'mentions'       => ['sometimes', 'array', 'max:'.$maxMentions],
            'mentions.*'     => ['integer', 'distinct', 'exists:utilisateurs,id_utilisateur'],
            'notifier_email' => ['sometimes', 'boolean'],
            'etiquette'      => ['nullable', 'in:information,a_verifier,urgent'],
        ]);

        $note = DB::transaction(fn () => NoteInterneService::creer(
            $request,
            $doleance,
            $utilisateur,
            $data['contenu'],
            array_map('intval', $data['mentions'] ?? []),
            $request->boolean('notifier_email'),
            $data['etiquette'] ?? null,
        ));

        return response()->json([
            'message' => 'Note ajoutée.',
            'note'    => $note->versApi($utilisateur),
        ], 201);
    }

    /**
     * PUT /api/admin/doleances/{reference}/notes/{id}
     */
    public function modifierNote(Request $request, string $reference, int $id)
    {
        $utilisateur = $request->user();
        $doleance = $this->trouver($utilisateur, $reference);
        if (! $doleance) {
            return $this->introuvable();
        }

        $note = $this->noteDuDossier($doleance, $id);
        if (! $note) {
            return response()->json(['message' => 'Note introuvable.'], 404);
        }

        if ((int) $note->id_auteur !== (int) $utilisateur->id_utilisateur) {
            return response()->json(['message' => 'Vous ne pouvez modifier que vos propres notes.'], 403);
        }

        if (! $note->estEncoreModifiable()) {
            $minutes = (int) config('itassel.notes.modification_minutes', 15);

            return response()->json([
                'message' => 'Le délai de modification de '.$minutes.' minutes est dépassé.',
            ], 409);
        }

        $maxMentions = (int) config('itassel.notes.max_mentions', 5);
        $data = $request->validate([
            'contenu'    => ['required', 'string', 'max:2000'],
            'mentions'   => ['sometimes', 'array', 'max:'.$maxMentions],
            'mentions.*' => ['integer', 'distinct', 'exists:utilisateurs,id_utilisateur'],
            'etiquette'  => ['nullable', 'in:information,a_verifier,urgent'],
        ]);

        $note = DB::transaction(fn () => NoteInterneService::modifier(
            $request,
            $doleance,
            $note,
            $utilisateur,
            $data['contenu'],
            $request->exists('mentions') ? array_map('intval', $data['mentions'] ?? []) : null,
            $data['etiquette'] ?? null,
            $request->exists('etiquette'),
        ));

        return response()->json([
            'message' => 'Note mise à jour.',
            'note'    => $note->versApi($utilisateur),
        ]);
    }

    /**
     * POST /api/admin/doleances/{reference}/notes/{id}/epingler
     */
    public function epinglerNote(Request $request, string $reference, int $id)
    {
        $utilisateur = $request->user();
        $doleance = $this->trouver($utilisateur, $reference);
        if (! $doleance) {
            return $this->introuvable();
        }

        $note = $this->noteDuDossier($doleance, $id);
        if (! $note) {
            return response()->json(['message' => 'Note introuvable.'], 404);
        }

        $note = NoteInterneService::basculerEpinglage($request, $doleance, $note, $utilisateur);

        return response()->json([
            'message' => $note->epinglee ? 'Note épinglée.' : 'Note désépinglée.',
            'note'    => $note->versApi($utilisateur),
        ]);
    }

    /**
     * POST /api/admin/doleances/{reference}/complements
     * Corps : question, description_piece (facultatif : rend la pièce obligatoire).
     */
    public function demanderComplement(Request $request, string $reference)
    {
        $utilisateur = $request->user();
        $doleance = $this->trouver($utilisateur, $reference);
        if (! $doleance) {
            return $this->introuvable();
        }

        $data = $request->validate([
            'question'          => ['required', 'string', 'max:1000'],
            'description_piece' => ['nullable', 'string', 'max:200'],
        ]);

        if (Statut::estFinal($doleance->statut?->code)) {
            return $this->erreur('question', 'Ce dossier est terminé : aucune information ne peut plus être demandée.');
        }

        $miseAJour = [];
        $this->prendreEnCharge($doleance, $utilisateur, $miseAJour);
        if ($miseAJour) {
            $doleance->update($miseAJour);
        }

        try {
            $complement = ComplementService::demander(
                $doleance,
                $utilisateur,
                $data['question'],
                $data['description_piece'] ?? null,
            );
        } catch (RuntimeException $e) {
            return $this->erreur('question', $e->getMessage());
        }

        JournalService::action($request, $utilisateur, 'complement_demande', $doleance->reference, $doleance);

        return response()->json([
            'message'    => 'Demande de complément envoyée au demandeur.',
            'complement' => $complement,
        ], 201);
    }

    /* ------------------------------------------------------------------ */

    public function annulerComplement(Request $request, int $id)
    {
        $complement = Complement::find($id);
        if (! $complement) {
            return $this->introuvable();
        }

        $utilisateur = $request->user();
        $complement->loadMissing('doleance');
        $doleance = Acces::doleancesVisibles($utilisateur)
            ->whereKey($complement->id_doleance)
            ->first();

        if (! $doleance) {
            return Acces::reponseDossierReaffecte($utilisateur, $complement->doleance?->reference ?? '')
                ?? $this->introuvable();
        }

        $data = $request->validate((new AnnulerComplementRequest())->rules());

        try {
            $complement = ComplementService::annuler($complement, $utilisateur, $data['motif']);
        } catch (ConflitMetier $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => $e->codeErreur], 409);
        }

        $emailEnvoye = (bool) $complement->email_envoye;
        $complement->offsetUnset('email_envoye');

        return response()->json([
            'message'      => 'Demande de complément annulée.',
            'complement'   => $this->complementPourApi($complement),
            'email_envoye' => $emailEnvoye,
        ]);
    }

    public function reclasser(Request $request, string $reference)
    {
        $utilisateur = $request->user();
        $doleance = $this->trouver($utilisateur, $reference);
        if (! $doleance) {
            return Acces::reponseDossierReaffecte($utilisateur, $reference) ?? $this->introuvable();
        }

        $data = $request->validate((new ReclasserDoleanceRequest())->rules());

        $cible = Statut::findOrFail($data['id_statut']);

        try {
            $doleance = ReclassementService::reclasser($doleance, $cible, $utilisateur, $data['motif']);
        } catch (ConflitMetier $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => $e->codeErreur], 409);
        } catch (RuntimeException $e) {
            return $this->erreur('id_statut', $e->getMessage());
        }

        JournalService::action(
            $request,
            $utilisateur,
            'reclassement',
            "{$doleance->reference} : {$cible->code}",
            $doleance,
        );

        return response()->json([
            'message'  => 'Dossier reclassé.',
            'doleance' => $doleance,
        ]);
    }

    private function passerEnInformationDemandee(
        Request $request,
        Doleance $doleance,
        Utilisateur $utilisateur,
        string $question,
        ?string $descriptionPiece,
        bool $notifierResponsable,
        Statut $nouveau,
    ) {
        $miseAJour = [];
        $this->prendreEnCharge($doleance, $utilisateur, $miseAJour);
        if ($miseAJour) {
            $doleance->update($miseAJour);
        }

        try {
            ComplementService::demander(
                $doleance,
                $utilisateur,
                $question,
                $descriptionPiece,
                ['notifier_responsable' => $notifierResponsable],
            );
        } catch (RuntimeException $e) {
            return $this->erreur('question', $e->getMessage());
        }

        $doleance->refresh();
        $evenementId = Historique::where('id_doleance', $doleance->id_doleance)
            ->where('type_evenement', 'complement_demande')
            ->latest('id_evenement')
            ->value('id_evenement');

        $emailEnvoye = NotificationItassel::where('id_doleance', $doleance->id_doleance)
            ->where('type_notification', 'complement_demande')
            ->latest('id_notification')
            ->value('etat_envoi') === 'transmis';

        JournalService::action(
            $request,
            $utilisateur,
            'changement_statut',
            "{$doleance->reference} : {$doleance->statut?->code} → {$nouveau->code}",
            $doleance,
        );

        return response()->json([
            'message'      => 'Statut mis à jour.',
            'statut'       => $nouveau->versApi(),
            'email_envoye' => $emailEnvoye,
        ]);
    }

    private function annulerComplementEnCours(
        Request $request,
        Doleance $doleance,
        Utilisateur $utilisateur,
        string $motif,
        Statut $nouveau,
    ) {
        $complement = Complement::where('id_doleance', $doleance->id_doleance)
            ->where('etat', 'en_attente')
            ->latest('date_demande')
            ->first();

        if (! $complement) {
            return $this->erreur('message', 'Aucun complément en attente à annuler.');
        }

        try {
            $complement = ComplementService::annuler($complement, $utilisateur, $motif);
        } catch (ConflitMetier $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => $e->codeErreur], 409);
        }

        $doleance->refresh();

        JournalService::action(
            $request,
            $utilisateur,
            'changement_statut',
            "{$doleance->reference} : information_demandee → {$nouveau->code}",
            $doleance,
        );

        return response()->json([
            'message'      => 'Statut mis à jour.',
            'statut'       => $nouveau->versApi(),
            'email_envoye' => (bool) $complement->email_envoye,
        ]);
    }

    /**
     * POST /api/admin/complements/{id}/examiner
     * Passe un complément « reçu » à « examiné ». Historique interne.
     */
    public function examinerComplement(Request $request, int $id)
    {
        $complement = Complement::find($id);
        if (! $complement) {
            return $this->introuvable();
        }

        $doleance = Acces::doleancesVisibles($request->user())
            ->whereKey($complement->id_doleance)
            ->first();

        if (! $doleance) {
            return $this->introuvable();
        }

        if ($complement->etat !== 'recu') {
            return response()->json([
                'message' => 'Ce complément n\'est pas en attente d\'examen.',
                'code'    => 'etat_invalide',
            ], 409);
        }

        DB::transaction(function () use ($complement, $doleance, $request) {
            $complement->update(['etat' => 'examine']);

            Historique::create([
                'date_evenement'    => now(),
                'type_evenement'    => 'complement_examine',
                'detail'            => 'Complément examiné.',
                'visible_demandeur' => false,
                'id_doleance'       => $doleance->id_doleance,
                'id_utilisateur'    => $request->user()->id_utilisateur,
            ]);
        });

        JournalService::action($request, $request->user(), 'complement_examine', $doleance->reference, $doleance);

        return response()->json([
            'message'    => 'Complément marqué comme examiné.',
            'complement' => $complement->fresh()->load([
                'auteur:id_utilisateur,nom,prenom',
                'annulePar:id_utilisateur,nom,prenom',
                'piecesJointes:id_piece,id_complement,nom_fichier,type,taille,origine',
            ]),
        ]);
    }

    private function trouver(Utilisateur $utilisateur, string $reference): ?Doleance
    {
        return Acces::doleancesVisibles($utilisateur)
            ->with('statut')
            ->where('reference', strtoupper($reference))
            ->first();
    }

    /**
     * Le premier administrateur de service qui agit sur un dossier sans
     * responsable en devient le responsable (« un seul responsable par dossier »).
     */
    private function prendreEnCharge(Doleance $doleance, Utilisateur $utilisateur, array &$miseAJour): void
    {
        if (! $doleance->id_responsable && ! $utilisateur->estSuperAdmin()) {
            $miseAJour['id_responsable'] = $utilisateur->id_utilisateur;
        }
    }

    private function complementPourApi(Complement $complement): array
    {
        $complement->load([
            'auteur:id_utilisateur,nom,prenom',
            'annulePar:id_utilisateur,nom,prenom',
            'piecesJointes:id_piece,id_complement,nom_fichier,type,taille,origine',
        ]);

        $donnees = $complement->toArray();
        $donnees['annule_par'] = $complement->annulePar
            ? $complement->annulePar->only(['id_utilisateur', 'nom', 'prenom'])
            : null;

        return $donnees;
    }

    private function noteDuDossier(Doleance $doleance, int $id): ?NoteInterne
    {
        return NoteInterne::query()
            ->where('id_doleance', $doleance->id_doleance)
            ->whereKey($id)
            ->first();
    }

    private function introuvable()
    {
        return response()->json(['message' => 'Doléance introuvable.'], 404);
    }

    private function erreur(string $champ, string $message)
    {
        return response()->json([
            'message' => $message,
            'errors'  => [$champ => [$message]],
        ], 422);
    }
}
