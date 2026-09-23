<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureCompteActif
{
    public function handle(Request $request, Closure $next)
    {
        $utilisateur = $request->user();

        if ($utilisateur && ! $utilisateur->actif) {
            $utilisateur->currentAccessToken()?->delete();

            return response()->json(['message' => 'Compte désactivé.'], 401);
        }

        return $next($request);
    }
}
