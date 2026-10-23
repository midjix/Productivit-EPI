<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes web : une seule page, la SPA Vue.js
|--------------------------------------------------------------------------
|
| Toute URL qui n'est ni /api/* ni /up sert la coquille HTML de la SPA ;
| c'est le routeur Vue (mode historique) qui affiche la bonne vue.
| Les données ne passent que par l'API JSON (routes/api.php).
|
*/

Route::view('/{any?}', 'app')
    ->where('any', '^(?!api/|up$).*')
    ->name('spa');
