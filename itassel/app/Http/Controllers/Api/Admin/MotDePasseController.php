<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\CompteService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class MotDePasseController extends Controller
{
    public function verifierJeton(Request $request)
    {
        $data = $request->validate([
            'jeton' => ['required', 'string'],
        ]);

        return response()->json(CompteService::verifierJeton($data['jeton']));
    }

    public function definir(Request $request)
    {
        $data = $request->validate([
            'jeton'                     => ['required', 'string'],
            'mot_de_passe'              => ['required', 'confirmed', Password::min(10)->letters()->mixedCase()->numbers()],
            'mot_de_passe_confirmation' => ['required', 'string'],
        ]);

        CompteService::definirMotDePasse($data['jeton'], $data['mot_de_passe'], $request);

        return response()->json(['message' => 'Mot de passe enregistré.']);
    }

    public function oublie(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        CompteService::motDePasseOublie($data['email'], $request);

        return response()->json([
            'message' => 'Si un compte correspond à cette adresse, un lien de réinitialisation a été envoyé.',
        ]);
    }
}
