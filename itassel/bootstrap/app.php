<?php

use App\Exceptions\ConflitMetier;
use App\Exceptions\ErreurValidation;
use App\Http\Middleware\EnsureCompteActif;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnTetesSecurite;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(EnTetesSecurite::class);
        $middleware->alias([
            'compte.actif' => EnsureCompteActif::class,
            'permission' => EnsurePermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Session expirée. Veuillez vous reconnecter.',
                ], 401);
            }
        });

        $exceptions->render(function (ConflitMetier $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => $e->codeErreur,
            ], 409);
        });

        $exceptions->render(function (ErreurValidation $e) {
            return $e->toResponse();
        });
    })->create();
