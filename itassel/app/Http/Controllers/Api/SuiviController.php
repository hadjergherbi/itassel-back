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
    // Limites de sécurité, reprises du script SQL et des maquettes.
    private const MAX_DEMANDES_CODE = 3;   // par doléance, par heure
    private const MAX_ESSAIS_CODE   = 5;   // par code
    private const DUREE_VALIDITE_MIN = 10; // minutes

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

        $doleance = Doleance::where('reference', $data['reference'])->first();

        if ($doleance) {
            $cleLimite = "suivi:demandes:{$doleance->id_doleance}";
            $demandes = Cache::get($cleLimite, 0);

            if ($demandes < self::MAX_DEMANDES_CODE) {
                $codeEnClair = (string) random_int(100000, 999999);

                CodeVerification::create([
                    'code_hash'       => hash('sha256', $codeEnClair),
                    'date_creation'   => now(),
                    'date_expiration' => now()->addMinutes(self::DUREE_VALIDITE_MIN),
                    'nombre_essais'   => 0,
                    'utilise'         => false,
                    'id_doleance'     => $doleance->id_doleance,
                ]);

                Cache::put($cleLimite, $demandes + 1, now()->addHour());

                NotificationService::envoyer(
                    $doleance,
                    'code_verification',
                    new CodeVerificationMail($codeEnClair, $doleance->reference, self::DUREE_VALIDITE_MIN),
                );

                // Aide au développement : le code n'est écrit dans le journal que si
                // APP_DEBUG=true. En production, APP_DEBUG doit valoir false.
                if (config('app.debug')) {
                    Log::info("Code de vérification (TEST) pour {$doleance->reference} : {$codeEnClair}");
                }
            }
        }

        return response()->json([
            'message' => "Si cette référence existe, un code à usage unique a été envoyé "
                       . "à l'adresse email du dossier. Il expire dans ".self::DUREE_VALIDITE_MIN." minutes.",
        ]);
    }

    /**
     * POST /api/suivi/verifier-code
     */
    public function verifierCode(Request $request)
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:20'],
            'code'      => ['required', 'digits:6'],
        ]);

        $doleance = Doleance::where('reference', $data['reference'])->first();
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
            'jeton_session'   => $jeton,
            'expire_dans_min' => 30,
        ]);
    }

    /**
     * GET /api/suivi/dossier
     */
    public function consulterDossier(Request $request)
    {
        $request->validate(['jeton_session' => ['required', 'string']]);

        $idDoleance = Cache::get("suivi:session:{$request->jeton_session}");

        if (! $idDoleance) {
            return response()->json(['message' => 'Session expirée ou invalide.'], 401);
        }

        $doleance = Doleance::with([
            'statut', 'service',
            // Statut après chaque événement, pour l'afficher dans la frise du demandeur.
            'historique' => fn ($q) => $q->where('visible_demandeur', true)
                ->where('type_evenement', '!=', 'complement_annule_motif')
                ->with('statutApres:id_statut,code,libelle,couleur'),
            'complements' => fn ($q) => $q->where('etat', 'en_attente'),
            'reponses',
        ])->findOrFail($idDoleance);

        $doleance->complements->each->makeHidden(['motif_annulation']);

        return response()->json($doleance);
    }
}
