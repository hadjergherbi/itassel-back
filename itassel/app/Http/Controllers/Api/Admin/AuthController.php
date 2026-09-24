<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Statut;
use App\Models\Utilisateur;
use App\Services\CompteService;
use App\Services\JournalService;
use App\Support\Acces;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    /**
     * POST /api/admin/login
     * Renvoie un jeton (valable 8 heures) à envoyer ensuite dans l'en-tête
     * Authorization: Bearer <jeton>.
     */
    public function login(Request $request)
    {
        $data = $request->validate([
            'email'        => ['required', 'email'],
            'mot_de_passe' => ['required', 'string'],
        ]);

        $cleEchec = $this->cleEchecLogin($data['email']);
        $maxEchecs = (int) config('itassel.securite.login_max_echecs', 5);

        if (RateLimiter::tooManyAttempts($cleEchec, $maxEchecs)) {
            return $this->reponseCompteVerrouille($cleEchec);
        }

        $utilisateur = CompteService::trouverParEmail($data['email'])?->load('service');

        $motDePasseOk = false;
        if ($utilisateur) {
            try {
                $motDePasseOk = Hash::check($data['mot_de_passe'], $utilisateur->mot_de_passe);
            } catch (\RuntimeException $e) {
                // Mot de passe enregistré en clair (ancien compte de test) : refusé.
                $motDePasseOk = false;
            }
        }

        $invitationEnAttente = $utilisateur && $utilisateur->mot_de_passe_defini_le === null;
        $ok = $utilisateur && $utilisateur->actif && ! $invitationEnAttente && $motDePasseOk;

        if (! $ok) {
            $this->journaliserEchecConnexion($request, $data['email'], $utilisateur);

            $minutesBlocage = (int) config('itassel.securite.login_blocage_minutes', 15);
            RateLimiter::hit($cleEchec, $minutesBlocage * 60);

            if (RateLimiter::tooManyAttempts($cleEchec, $maxEchecs)) {
                JournalService::ecrire(
                    $request,
                    $data['email'],
                    'compte_verrouille',
                    'echec',
                    $utilisateur,
                    $this->detailConnexionMasque($request, $data['email']),
                );
            }

            // Même message dans tous les cas : on ne révèle pas si le compte existe.
            return response()->json(['message' => 'Email ou mot de passe incorrect.'], 422);
        }

        RateLimiter::clear($cleEchec);

        JournalService::ecrire(
            $request,
            $data['email'],
            'connexion',
            'succes',
            $utilisateur,
            'Session ouverte',
        );

        $utilisateur->connexion_precedente = $utilisateur->derniere_connexion;
        $utilisateur->derniere_connexion = now();
        $utilisateur->save();

        $minutes = (int) config('sanctum.expiration', 480);
        $expireLe = now()->addMinutes($minutes);
        $jeton = $utilisateur->createToken('backoffice', ['*'], $expireLe)->plainTextToken;

        return response()->json([
            'token'       => $jeton,
            'utilisateur' => $this->profil($utilisateur),
            'expire_le'   => $expireLe->toIso8601String(),
        ]);
    }

    /**
     * POST /api/admin/logout
     */
    public function logout(Request $request)
    {
        $utilisateur = $request->user();
        $utilisateur->currentAccessToken()?->delete();

        JournalService::ecrire($request, $utilisateur->email, 'deconnexion', 'succes', $utilisateur);

        return response()->json(['message' => 'Déconnexion effectuée.']);
    }

    /**
     * GET /api/admin/me
     */
    public function me(Request $request)
    {
        $utilisateur = $request->user()->load('service');

        return response()->json(array_merge($this->profil($utilisateur), [
            'compteur_nouvelles'      => Acces::doleancesVisibles($utilisateur)
                ->whereHas('statut', fn ($q) => $q->where('code', Statut::NOUVELLE))
                ->count(),
            'permissions'             => $utilisateur->permissions(),
            'libelle_role'            => $utilisateur->libelleRole(),
            'derniere_connexion'      => $utilisateur->derniere_connexion,
            'connexion_precedente'    => $utilisateur->connexion_precedente,
            'notifications_non_lues'  => $utilisateur->notificationsApp()->whereNull('lue_le')->count(),
        ]));
    }

    public function changerMotDePasse(Request $request)
    {
        $data = $request->validate([
            'mot_de_passe_actuel'         => ['required', 'string'],
            'mot_de_passe'                => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::min(10)->letters()->mixedCase()->numbers()],
            'mot_de_passe_confirmation'   => ['required', 'string'],
        ]);

        \App\Services\CompteService::changerMotDePasse(
            $request->user(),
            $data['mot_de_passe_actuel'],
            $data['mot_de_passe'],
            $request,
        );

        return response()->json(['message' => 'Mot de passe mis à jour.']);
    }

    private function cleEchecLogin(string $email): string
    {
        return 'login-echec:'.sha1(mb_strtolower(trim($email)));
    }

    private function reponseCompteVerrouille(string $cleEchec): JsonResponse
    {
        $secondes = RateLimiter::availableIn($cleEchec);
        $minutes = max(1, (int) ceil($secondes / 60));

        return response()->json([
            'message'        => "Trop de tentatives. Réessayez dans {$minutes} minutes.",
            'reessayer_dans' => $secondes,
        ], 429);
    }

    private function journaliserEchecConnexion(Request $request, string $email, ?Utilisateur $utilisateur): void
    {
        JournalService::ecrire(
            $request,
            $email,
            'connexion_echec',
            'echec',
            $utilisateur,
            $this->detailConnexionMasque($request, $email),
        );
    }

    private function detailConnexionMasque(Request $request, string $email): string
    {
        $detail = 'Email saisi : '.CompteService::masquerEmail($email);
        $agent = $request->userAgent();

        if (is_string($agent) && $agent !== '') {
            $detail .= ' — '.$agent;
        }

        return $detail;
    }

    private function profil(Utilisateur $utilisateur): array
    {
        return [
            'id'             => $utilisateur->id_utilisateur,
            'id_utilisateur' => $utilisateur->id_utilisateur,
            'nom'            => $utilisateur->nom,
            'prenom'         => $utilisateur->prenom,
            'email'          => $utilisateur->email,
            'role'           => $utilisateur->role,
            'service'        => $utilisateur->service
                ? [
                    'id'          => $utilisateur->service->id_service,
                    'id_service'  => $utilisateur->service->id_service,
                    'nom'         => $utilisateur->service->nom_service,
                    'nom_service' => $utilisateur->service->nom_service,
                ]
                : null,
        ];
    }
}
