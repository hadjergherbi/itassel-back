<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Utilisateur;
use App\Services\JournalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

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

        $utilisateur = Utilisateur::with('service')->where('email', $data['email'])->first();

        $motDePasseOk = false;
        if ($utilisateur) {
            try {
                $motDePasseOk = Hash::check($data['mot_de_passe'], $utilisateur->mot_de_passe);
            } catch (\RuntimeException $e) {
                // Mot de passe enregistré en clair (ancien compte de test) : refusé.
                $motDePasseOk = false;
            }
        }

        $ok = $utilisateur && $utilisateur->actif && $motDePasseOk;

        $detailEchec = match (true) {
            ! $utilisateur          => 'Compte inconnu',
            ! $utilisateur->actif   => 'Compte désactivé',
            ! $motDePasseOk         => 'Mot de passe incorrect',
            default                 => null,
        };

        JournalService::ecrire(
            $request,
            $data['email'],
            'connexion',
            $ok ? 'succes' : 'echec',
            $utilisateur,
            $ok ? null : $detailEchec,
        );

        if (! $ok) {
            // Même message dans tous les cas : on ne révèle pas si le compte existe.
            return response()->json(['message' => 'Email ou mot de passe incorrect.'], 422);
        }

        $jeton = $utilisateur->createToken('backoffice', ['*'], now()->addHours(8))->plainTextToken;

        return response()->json([
            'token'       => $jeton,
            'utilisateur' => $this->profil($utilisateur),
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
        return response()->json($this->profil($request->user()->load('service')));
    }

    private function profil(Utilisateur $utilisateur): array
    {
        return [
            'id'      => $utilisateur->id_utilisateur,
            'nom'     => $utilisateur->nom,
            'prenom'  => $utilisateur->prenom,
            'email'   => $utilisateur->email,
            'role'    => $utilisateur->role,
            'service' => $utilisateur->service
                ? ['id' => $utilisateur->service->id_service, 'nom' => $utilisateur->service->nom_service]
                : null,
        ];
    }
}
