<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $code)
    {
        $utilisateur = $request->user();

        if (! $utilisateur || ! $utilisateur->peut($code)) {
            return response()->json([
                'message' => 'Action non autorisée pour votre rôle.',
                'code' => 'permission_refusee',
            ], 403);
        }

        return $next($request);
    }
}
