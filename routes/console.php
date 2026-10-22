<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Planification (Laravel Scheduler) — exécutée par `php artisan schedule:run`
| déclenché chaque minute par le CRON du conteneur / du VPS :
|   * * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1
|--------------------------------------------------------------------------
*/

// Import quotidien DSK + EpiLogg (fenêtre glissante) puis recalcul du cache mensuel.
// `withoutOverlapping` : jamais deux imports simultanés ; `onOneServer` : un seul nœud
// si l'application est répliquée. La sortie est journalisée pour la supervision.
Schedule::command('epi:import')
    ->dailyAt('02:00')
    ->timezone('Europe/Luxembourg')
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/epi-import.log'));
