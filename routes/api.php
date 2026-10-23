<?php

use App\Http\Controllers\Api\Admin\CompteDskController;
use App\Http\Controllers\Api\Admin\ImportController;
use App\Http\Controllers\Api\Admin\RecalculController;
use App\Http\Controllers\Api\Admin\ServiceAdminController;
use App\Http\Controllers\Api\Admin\TarifController;
use App\Http\Controllers\Api\Admin\TypeSuiviController;
use App\Http\Controllers\Api\Admin\UtilisateurController;
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

    // Administration (role:administrateur) : supervision des imports, recalcul,
    // qualification des référentiels, matrice de conversion, organigramme.
    Route::middleware('role:administrateur')->prefix('admin')->name('api.admin.')->group(function () {
        Route::get('/imports', [ImportController::class, 'index'])->name('imports.index');
        Route::post('/imports', [ImportController::class, 'store'])->name('imports.store');
        Route::get('/imports/{import}', [ImportController::class, 'show'])->whereNumber('import')->name('imports.show');
        Route::post('/recalculs', [RecalculController::class, 'store'])->name('recalculs.store');

        Route::get('/comptes-dsk', [CompteDskController::class, 'index'])->name('comptes-dsk.index');
        Route::patch('/comptes-dsk/{compte}', [CompteDskController::class, 'update'])->whereNumber('compte')->name('comptes-dsk.update');
        Route::get('/types-suivi', [TypeSuiviController::class, 'index'])->name('types-suivi.index');
        Route::patch('/types-suivi/{type}', [TypeSuiviController::class, 'update'])->whereNumber('type')->name('types-suivi.update');

        Route::get('/tarifs', [TarifController::class, 'index'])->name('tarifs.index');
        Route::post('/tarifs/{tarif}/regles', [TarifController::class, 'storeRegle'])->whereNumber('tarif')->name('tarifs.regles.store');

        Route::get('/services', [ServiceAdminController::class, 'index'])->name('services.index');
        Route::patch('/services/{service}', [ServiceAdminController::class, 'update'])->whereNumber('service')->name('services.update');
        Route::get('/utilisateurs', [UtilisateurController::class, 'index'])->name('utilisateurs.index');
        Route::patch('/utilisateurs/{utilisateur}', [UtilisateurController::class, 'update'])->whereNumber('utilisateur')->name('utilisateurs.update');
        Route::put('/utilisateurs/{utilisateur}/mot-de-passe', [UtilisateurController::class, 'definirMotDePasse'])->whereNumber('utilisateur')->name('utilisateurs.mot-de-passe');
    });
});
