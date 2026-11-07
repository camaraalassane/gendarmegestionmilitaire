<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CartePvcApiController;
use App\Http\Middleware\CheckCartePvcApiKey;

/*
|--------------------------------------------------------------------------
| API Routes - Application Cartes PVC (Angular)
|--------------------------------------------------------------------------
|
| Toutes ces routes sont préfixées par /api
| Exemple : GET /api/v1/cartes-pvc/ping
|
*/

Route::prefix('v1/cartes-pvc')->middleware(['throttle:60,1'])->group(function () {

    // 1. Route de diagnostic sans clé (Ping pour valider la connectivité réseau depuis Angular)
    Route::get('/ping', [CartePvcApiController::class, 'ping']);

    // 2. Routes protégées par clé API (X-API-KEY dans les headers)
    Route::middleware([CheckCartePvcApiKey::class])->group(function () {

        // Statistiques globales
        Route::get('/stats', [CartePvcApiController::class, 'stats']);

        // Liste des grades (pour select/filtres Angular)
        Route::get('/grades', [CartePvcApiController::class, 'grades']);

        // Liste des militaires avec recherche et filtres
        Route::get('/militaires', [CartePvcApiController::class, 'index']);

        // Récupération par lot pour impression de cartes multiples
        Route::post('/batch', [CartePvcApiController::class, 'batch']);

        // Recherche directe par matricule (pour scanner code-barre)
        Route::get('/militaires/matricule/{matricule}', [CartePvcApiController::class, 'showByMatricule']);

        // Fiche militaire complète pour impression (par ID ou Matricule)
        Route::get('/militaires/{idOrMatricule}', [CartePvcApiController::class, 'show']);
    });
});
