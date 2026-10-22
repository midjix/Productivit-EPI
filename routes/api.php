<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\StatistiqueController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API REST consommée par la SPA Vue.js (préfixe /api, réponses JSON)
|--------------------------------------------------------------------------
|
| Trois niveaux de protection, dans cet ordre :
|   1. `auth:sanctum` : jeton valide obligatoire, sinon 401 ;
|   2. `role:<salarie|coordinateur|administrateur>` : héritage de droits, sinon 403 ;
|   3. policies (`UserPolicy`, `ServicePolicy`) : « un salarié ne voit que SES
|      données, un coordinateur SON service », sinon 403.
| Les filtres (?annee, ?mois) sont validés par PeriodeRequest (422 si invalides).
|
*/

// Authentification — limitée à 5 tentatives par minute et par IP (force brute).
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1')
    ->name('api.login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('api.logout');
    Route::get('/me', [AuthController::class, 'me'])->name('api.me');

    // Consultation (lecture du cache) — tout rôle, périmètre contrôlé par UserPolicy.
    Route::get('/statistiques/me', [StatistiqueController::class, 'moi'])->name('api.statistiques.moi');
    // whereNumber : un identifiant non numérique est rejeté (404) AVANT toute requête —
    // sans cela, MariaDB convertirait « 1 OR 1=1 » en 1 par coercition de type.
    Route::get('/statistiques/salaries/{salarie}', [StatistiqueController::class, 'salarie'])
        ->whereNumber('salarie')->name('api.statistiques.salarie');

    // Coordinateur (et Administrateur par héritage) — périmètre contrôlé par ServicePolicy.
    Route::middleware('role:coordinateur')->group(function () {
        Route::get('/services', [ServiceController::class, 'index'])->name('api.services.index');
        Route::get('/statistiques/services/{service}', [StatistiqueController::class, 'service'])
            ->whereNumber('service')->name('api.statistiques.service');
    });

    // Administrateur — vue « Total EPI ».
    Route::middleware('role:administrateur')->group(function () {
        Route::get('/statistiques/globales', [StatistiqueController::class, 'globale'])->name('api.statistiques.globales');
    });
});
