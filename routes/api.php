<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API REST consommée par la SPA Vue.js (préfixe /api, réponses JSON)
|--------------------------------------------------------------------------
|
| Deux niveaux de protection, dans cet ordre :
|   1. `auth:sanctum` : jeton valide obligatoire, sinon 401 ;
|   2. `role:<salarie|coordinateur|administrateur>` : héritage de droits, sinon 403.
| La restriction « un salarié ne voit que SES données » est portée par les
| policies des ressources concernées (étape API de consultation).
|
*/

// Authentification — limitée à 5 tentatives par minute et par IP (force brute).
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1')
    ->name('api.login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('api.logout');
    Route::get('/me', [AuthController::class, 'me'])->name('api.me');
});
