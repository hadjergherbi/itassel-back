<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsurePermission
{
    /**
     * @param  string  ...$codes  Une seule permission suffit (OU logique).
     */
    public function handle(Request $request, Closure $next, string ...$codes)
    {
        $utilisateur = $request->user();

        if ($utilisateur) {
            foreach ($codes as $code) {
                if ($utilisateur->peut($code)) {
                    return $next($request);
                }
            }
        }

        return response()->json([
            'message' => 'Action non autorisée pour votre rôle.',
            'code' => 'permission_refusee',
        ], 403);
    }
}
