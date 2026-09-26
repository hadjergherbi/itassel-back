<?php

use App\Http\Controllers\Api\Admin\AuthController;
use App\Http\Controllers\Api\Admin\DoleanceActionController;
use App\Http\Controllers\Api\Admin\DoleanceAdminController;
use App\Http\Controllers\Api\Admin\JournalAdminController;
use App\Http\Controllers\Api\Admin\ModeleMessageController;
use App\Http\Controllers\Api\Admin\MotDePasseController;
use App\Http\Controllers\Api\Admin\NotificationAdminController;
use App\Http\Controllers\Api\Admin\NotificationAppController;
use App\Http\Controllers\Api\Admin\ParametreAdminController;
use App\Http\Controllers\Api\Admin\PieceJointeController;
use App\Http\Controllers\Api\Admin\ReaffectationAdminController;
use App\Http\Controllers\Api\Admin\RoleAdminController;
use App\Http\Controllers\Api\Admin\ServiceAdminController;
use App\Http\Controllers\Api\Admin\TableauDeBordController;
use App\Http\Controllers\Api\Admin\UtilisateurAdminController;
use App\Http\Controllers\Api\DoleanceController;
use App\Http\Controllers\Api\ReferentielController;
use App\Http\Controllers\Api\SuiviComplementController;
use App\Http\Controllers\Api\SuiviController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Espace public (sans compte)
|--------------------------------------------------------------------------
*/

Route::get('/referentiels', [ReferentielController::class, 'index'])
    ->middleware('throttle:60,1');

Route::get('/formulaire/jeton', [DoleanceController::class, 'jeton'])
    ->middleware('throttle:30,1');

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
| Back-office
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1');

    Route::middleware('throttle:5,1')->group(function () {
        Route::post('/mot-de-passe/verifier-jeton', [MotDePasseController::class, 'verifierJeton']);
        Route::post('/mot-de-passe/definir', [MotDePasseController::class, 'definir']);
        Route::post('/mot-de-passe-oublie', [MotDePasseController::class, 'oublie']);
    });

    Route::middleware(['auth:sanctum', 'compte.actif'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/mot-de-passe', [AuthController::class, 'changerMotDePasse'])
            ->middleware('throttle:5,1');

        Route::get('/mes-notifications', [NotificationAppController::class, 'index']);
        Route::get('/mes-notifications/compteur', [NotificationAppController::class, 'compteur']);
        Route::post('/mes-notifications/{id}/lire', [NotificationAppController::class, 'lire'])->whereNumber('id');
        Route::post('/mes-notifications/lire-tout', [NotificationAppController::class, 'lireTout']);

        Route::get('/statuts', [DoleanceAdminController::class, 'statuts']);
        Route::get('/tableau-de-bord', [TableauDeBordController::class, 'index'])
            ->middleware('permission:tableau_de_bord.voir');

        Route::get('/doleances', [DoleanceAdminController::class, 'index'])
            ->middleware('permission:doleances.voir');
        Route::get('/doleances/export/apercu', [DoleanceAdminController::class, 'apercuExport'])
            ->middleware('permission:doleances.exporter');
        Route::get('/doleances/export', [DoleanceAdminController::class, 'export'])
            ->middleware('permission:doleances.exporter');
        Route::get('/doleances/{reference}', [DoleanceAdminController::class, 'show'])
            ->middleware('permission:doleances.voir');

        Route::post('/doleances/{reference}/statut', [DoleanceActionController::class, 'changerStatut'])
            ->middleware('permission:doleances.changer_statut');
        Route::post('/doleances/{reference}/reponses', [DoleanceActionController::class, 'repondre'])
            ->middleware('permission:doleances.repondre');
        Route::get('/doleances/{reference}/mentionnables', [DoleanceActionController::class, 'mentionnables'])
            ->middleware(['permission:doleances.notes', 'throttle:60,1']);
        Route::post('/doleances/{reference}/notes', [DoleanceActionController::class, 'ajouterNote'])
            ->middleware('permission:doleances.notes');
        Route::put('/doleances/{reference}/notes/{id}', [DoleanceActionController::class, 'modifierNote'])
            ->middleware('permission:doleances.notes')
            ->whereNumber('id');
        Route::post('/doleances/{reference}/notes/{id}/epingler', [DoleanceActionController::class, 'epinglerNote'])
            ->middleware('permission:doleances.notes')
            ->whereNumber('id');
        Route::post('/doleances/{reference}/complements', [DoleanceActionController::class, 'demanderComplement'])
            ->middleware('permission:complements.demander');
        Route::post('/doleances/{reference}/reclasser', [DoleanceActionController::class, 'reclasser'])
            ->middleware('permission:doleances.reclasser');
        Route::post('/doleances/{reference}/reaffectation', [ReaffectationAdminController::class, 'demander'])
            ->middleware('permission:reaffectations.demander');
        Route::post('/doleances/{reference}/reaffecter', [ReaffectationAdminController::class, 'reaffecter'])
            ->middleware('permission:reaffectations.directe');

        Route::post('/complements/{id}/examiner', [DoleanceActionController::class, 'examinerComplement'])
            ->middleware('permission:complements.examiner')
            ->whereNumber('id');
        Route::post('/complements/{id}/annuler', [DoleanceActionController::class, 'annulerComplement'])
            ->middleware('permission:complements.annuler')
            ->whereNumber('id');

        Route::get('/reaffectations', [ReaffectationAdminController::class, 'index'])
            ->middleware('permission:reaffectations.decider');
        Route::post('/reaffectations/{id}/annuler', [ReaffectationAdminController::class, 'annuler'])
            ->whereNumber('id');
        Route::post('/reaffectations/{id}/refuser', [ReaffectationAdminController::class, 'refuser'])
            ->middleware('permission:reaffectations.decider')
            ->whereNumber('id');
        Route::post('/reaffectations/{id}/accepter', [ReaffectationAdminController::class, 'accepter'])
            ->middleware('permission:reaffectations.decider')
            ->whereNumber('id');

        Route::get('/pieces-jointes/{id}/apercu', [PieceJointeController::class, 'apercu'])
            ->middleware('throttle:60,1')
            ->whereNumber('id');
        Route::get('/pieces-jointes/{id}/telecharger', [PieceJointeController::class, 'telecharger'])
            ->middleware('permission:pieces_jointes.telecharger')
            ->whereNumber('id');
        Route::post('/notifications/{id}/renvoyer', [NotificationAdminController::class, 'renvoyer'])
            ->middleware('permission:notifications.renvoyer')
            ->whereNumber('id');
        Route::get('/modeles-message', [ModeleMessageController::class, 'index']);

        Route::get('/utilisateurs', [UtilisateurAdminController::class, 'index'])
            ->middleware('permission:utilisateurs.gerer');
        Route::post('/utilisateurs', [UtilisateurAdminController::class, 'store'])
            ->middleware('permission:utilisateurs.gerer');
        Route::get('/utilisateurs/{utilisateur}/impact', [UtilisateurAdminController::class, 'impact'])
            ->middleware('permission:utilisateurs.gerer');
        Route::get('/utilisateurs/{utilisateur}', [UtilisateurAdminController::class, 'show'])
            ->middleware('permission:utilisateurs.gerer');
        Route::put('/utilisateurs/{utilisateur}', [UtilisateurAdminController::class, 'update'])
            ->middleware('permission:utilisateurs.gerer');
        Route::post('/utilisateurs/{utilisateur}/activer', [UtilisateurAdminController::class, 'activer'])
            ->middleware('permission:utilisateurs.gerer');
        Route::post('/utilisateurs/{utilisateur}/desactiver', [UtilisateurAdminController::class, 'desactiver'])
            ->middleware('permission:utilisateurs.gerer');
        Route::post('/utilisateurs/{utilisateur}/renvoyer-invitation', [UtilisateurAdminController::class, 'renvoyerInvitation'])
            ->middleware('permission:utilisateurs.gerer');
        Route::post('/utilisateurs/{utilisateur}/reinitialiser-mot-de-passe', [UtilisateurAdminController::class, 'reinitialiserMotDePasse'])
            ->middleware('permission:utilisateurs.gerer');
        Route::delete('/utilisateurs/{utilisateur}', [UtilisateurAdminController::class, 'destroy'])
            ->middleware('permission:utilisateurs.gerer');

        Route::get('/roles', [RoleAdminController::class, 'index'])
            ->middleware('permission:roles.gerer');
        Route::get('/permissions', [RoleAdminController::class, 'permissions'])
            ->middleware('permission:roles.gerer');
        Route::put('/roles/{code}/permissions', [RoleAdminController::class, 'mettreAJourPermissions'])
            ->middleware('permission:roles.gerer');

        Route::get('/services', [ServiceAdminController::class, 'index'])
            ->middleware('permission:services.gerer');
        Route::post('/services', [ServiceAdminController::class, 'store'])
            ->middleware('permission:services.gerer');
        Route::put('/services/{service}', [ServiceAdminController::class, 'update'])
            ->middleware('permission:services.gerer');
        Route::delete('/services/{service}', [ServiceAdminController::class, 'destroy'])
            ->middleware('permission:services.gerer');
        Route::put('/services/{service}/responsable', [ServiceAdminController::class, 'designerResponsable'])
            ->middleware('permission:services.gerer');
        Route::get('/services/{service}/responsables-possibles', [ServiceAdminController::class, 'responsablesPossibles'])
            ->middleware('permission:services.gerer');

        Route::prefix('parametres')->middleware('permission:parametres.gerer')->group(function () {
            Route::get('/natures', [ParametreAdminController::class, 'natures']);
            Route::post('/natures', [ParametreAdminController::class, 'creerNature']);
            Route::put('/natures/{nature}', [ParametreAdminController::class, 'modifierNature']);
            Route::delete('/natures/{nature}', [ParametreAdminController::class, 'supprimerNature']);

            Route::get('/qualites', [ParametreAdminController::class, 'qualites']);
            Route::post('/qualites', [ParametreAdminController::class, 'creerQualite']);
            Route::put('/qualites/{qualite}', [ParametreAdminController::class, 'modifierQualite']);
            Route::delete('/qualites/{qualite}', [ParametreAdminController::class, 'supprimerQualite']);

            Route::get('/modeles-message', [ParametreAdminController::class, 'modeles']);
            Route::post('/modeles-message', [ParametreAdminController::class, 'creerModele']);
            Route::put('/modeles-message/{modele}', [ParametreAdminController::class, 'modifierModele']);
            Route::delete('/modeles-message/{modele}', [ParametreAdminController::class, 'supprimerModele']);

            Route::get('/statuts', [ParametreAdminController::class, 'statuts']);
            Route::put('/statuts/{statut}', [ParametreAdminController::class, 'modifierStatut']);

            Route::get('/notifications', [ParametreAdminController::class, 'notifications']);
            Route::put('/notifications', [ParametreAdminController::class, 'modifierNotifications']);
        });

        Route::get('/journaux', [JournalAdminController::class, 'index'])
            ->middleware('permission:journal.voir');
        Route::get('/journaux/export', [JournalAdminController::class, 'export'])
            ->middleware('permission:journal.exporter');
        Route::get('/journaux/tableau-de-bord', [JournalAdminController::class, 'tableauDeBord'])
            ->middleware('permission:journal.voir');
        Route::get('/journaux/{id}', [JournalAdminController::class, 'show'])
            ->middleware('permission:journal.voir')
            ->whereNumber('id');
    });
});
