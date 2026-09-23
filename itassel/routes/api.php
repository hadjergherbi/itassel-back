<?php

use App\Http\Controllers\Api\Admin\AuthController;
use App\Http\Controllers\Api\Admin\DoleanceAdminController;
use App\Http\Controllers\Api\DoleanceController;
use App\Http\Controllers\Api\ReferentielController;
use App\Http\Controllers\Api\SuiviComplementController;
use App\Http\Controllers\Api\SuiviController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Espace public (sans compte)
|--------------------------------------------------------------------------
| Chaque route est limitée en nombre de requêtes par minute et par adresse IP
| (throttle:nombre,minutes), pour éviter les abus sur des routes ouvertes.
*/

Route::get('/referentiels', [ReferentielController::class, 'index'])
    ->middleware('throttle:60,1');

Route::post('/doleances', [DoleanceController::class, 'store'])
    ->middleware('throttle:10,1');

Route::prefix('suivi')->middleware('throttle:20,1')->group(function () {
    Route::post('/demander-code', [SuiviController::class, 'demanderCode']);
    Route::post('/verifier-code', [SuiviController::class, 'verifierCode']);
    Route::get('/dossier', [SuiviController::class, 'consulterDossier']);
    Route::post('/repondre-complement', [SuiviComplementController::class, 'repondre']);
});

/*
|--------------------------------------------------------------------------
| Back-office (administrateurs de service et Super administrateur)
|--------------------------------------------------------------------------
| Toutes les routes, sauf la connexion, exigent l'en-tête :
| Authorization: Bearer <jeton reçu à la connexion>
*/

Route::prefix('admin')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1'); // 5 tentatives par minute

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        Route::get('/statuts', [DoleanceAdminController::class, 'statuts']);
        Route::get('/doleances', [DoleanceAdminController::class, 'index']);
        Route::get('/doleances/{reference}', [DoleanceAdminController::class, 'show']);
    });
});