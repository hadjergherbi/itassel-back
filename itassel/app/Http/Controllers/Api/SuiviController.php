<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\CodeVerificationMail;
use App\Models\CodeVerification;
use App\Models\Doleance;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SuiviController extends Controller
{
    /**
     * POST /api/suivi/demander-code
     * Réponse volontairement IDENTIQUE que la référence existe ou non,
     * pour ne jamais révéler si une référence est valide.
     */
    public function demanderCode(Request $request)
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:20'],
        ]);

        $reference = strtoupper(trim($data['reference']));
        $maxDemandes = (int) config('itassel.suivi.max_demandes_code', 3);
        $dureeValidite = (int) config('itassel.suivi.duree_validite_min', 10);

        $doleance = Doleance::where('reference', $reference)->first();

        if (! $doleance) {
            $this->journaliserDemandeCodeLocale('reference_inconnue', $reference);

            return $this->reponseDemandeCode($dureeValidite);
        }

        $cleLimite = "suivi:demandes:{$doleance->id_doleance}";
        $demandes = (int) Cache::get($cleLimite, 0);

        if ($demandes >= $maxDemandes) {
            $this->journaliserDemandeCodeLocale('limite_demandes', $reference, [
                'demandes' => $demandes,
                'max' => $maxDemandes,
            ]);

            return $this->reponseDemandeCode($dureeValidite);
        }

        $codeEnClair = (string) random_int(100000, 999999);

        CodeVerification::create([
            'code_hash' => hash('sha256', $codeEnClair),
            'date_creation' => now(),
            'date_expiration' => now()->addMinutes($dureeValidite),
            'nombre_essais' => 0,
            'utilise' => false,
            'id_doleance' => $doleance->id_doleance,
        ]);

        Cache::put($cleLimite, $demandes + 1, now()->addHour());

        $erreurEnvoi = null;
        // true = mis en file (file « prioritaire »), pas encore transmis.
        // L'échec SMTP est tracé par le job (etat_envoi = non_transmis).
        $misEnFile = NotificationService::envoyer(
            $doleance,
            'code_verification',
            new CodeVerificationMail($codeEnClair, $doleance->reference, $dureeValidite, $doleance->langue ?? 'fr'),
            null,
            $erreurEnvoi,
        );

        if (! $misEnFile) {
            $this->journaliserDemandeCodeLocale('echec_envoi', $reference, [
                'erreur' => $erreurEnvoi ?? 'envoi non transmis',
            ]);
        }

        return $this->reponseDemandeCode($dureeValidite);
    }

    /**
     * POST /api/suivi/verifier-code
     */
    public function verifierCode(Request $request)
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:20'],
            'code' => ['required', 'digits:6'],
        ]);

        $reference = strtoupper(trim($data['reference']));
        $doleance = Doleance::where('reference', $reference)->first();
        $messageEchec = 'Code invalide ou expiré.';

        if (! $doleance) {
            return response()->json(['message' => $messageEchec], 422);
        }

        $code = CodeVerification::where('id_doleance', $doleance->id_doleance)
            ->where('utilise', false)
            ->latest('date_creation')
            ->first();

        if (! $code || ! $code->estValide()) {
            return response()->json(['message' => $messageEchec], 422);
        }

        if (! $code->correspondA($data['code'])) {
            $code->increment('nombre_essais');

            return response()->json(['message' => $messageEchec], 422);
        }

        $code->update(['utilise' => true]);

        $jeton = Str::random(48);
        Cache::put("suivi:session:{$jeton}", $doleance->id_doleance, now()->addMinutes(30));

        return response()->json([
            'jeton_session' => $jeton,
            'expire_dans_min' => 30,
        ]);
    }

    /**
     * GET /api/suivi/dossier
     */
    public static function idDoleanceDepuisEntete(Request $request): ?int
    {
        $jeton = $request->header('X-Suivi-Token');
        if (! is_string($jeton) || $jeton === '') {
            return null;
        }

        $id = Cache::get("suivi:session:{$jeton}");

        return is_numeric($id) ? (int) $id : null;
    }

    public function consulterDossier(Request $request)
    {
        $idDoleance = self::idDoleanceDepuisEntete($request);

        if (! $idDoleance) {
            return response()->json(['message' => 'Session expirée ou invalide.'], 401);
        }

        $doleance = Doleance::with([
            'statut:id_statut,code,libelle',
            'historique' => fn ($q) => $q->where('visible_demandeur', true)
                ->where('type_evenement', '!=', 'complement_annule_motif')
                ->with('statutApres:id_statut,code,libelle'),
            'complements' => fn ($q) => $q->where('etat', 'en_attente'),
            'reponses',
        ])->findOrFail($idDoleance);

        return response()->json([
            'reference' => $doleance->reference,
            'statut' => [
                'code' => $doleance->statut?->code,
                'libelle' => $doleance->statut?->libelle,
            ],
            'historique' => $doleance->historique->map(fn ($evenement) => [
                'type_evenement' => $evenement->type_evenement,
                'detail' => $evenement->detail,
                'statut_apres' => $evenement->statutApres ? [
                    'code' => $evenement->statutApres->code,
                    'libelle' => $evenement->statutApres->libelle,
                ] : null,
                'date_evenement' => $evenement->date_evenement,
            ])->values(),
            'reponses' => $doleance->reponses->map(fn ($reponse) => [
                'contenu' => $reponse->contenu,
                'date_publication' => $reponse->date_publication,
            ])->values(),
            'complements' => $doleance->complements->map(fn ($complement) => [
                'id_complement' => $complement->id_complement,
                'etat' => $complement->etat,
                'piece_exigee' => (bool) $complement->piece_exigee,
                'description_piece' => $complement->description_piece,
                'question' => $complement->question,
            ])->values(),
        ]);
    }

    private function reponseDemandeCode(int $dureeValidite)
    {
        return response()->json([
            'message' => 'Si cette référence existe, un code à usage unique a été envoyé '
                       ."à l'adresse email du dossier. Il expire dans ".$dureeValidite.' minutes.',
        ]);
    }

    /**
     * Diagnostic local uniquement (APP_ENV=local). Jamais de code ni d'email.
     *
     * @param  array<string, mixed>  $contexte
     */
    private function journaliserDemandeCodeLocale(string $raison, string $reference, array $contexte = []): void
    {
        if (! app()->environment('local')) {
            return;
        }

        Log::warning('suivi.demander-code: aucun email envoyé', array_merge([
            'raison' => $raison,
            'reference' => $reference,
        ], $contexte));
    }
}
