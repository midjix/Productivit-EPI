<?php

namespace App\Providers;

use App\Etl\Connecteurs\DskHttp;
use App\Etl\Connecteurs\EpiloggSql;
use App\Etl\Contracts\ConnecteurDsk;
use App\Etl\Contracts\ConnecteurEpilogg;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * Pattern Adaptateur : l'EtlService dépend des interfaces ConnecteurDsk / ConnecteurEpilogg ;
     * c'est ici, et seulement ici, qu'on choisit l'implémentation (HTTP réel, SQL réel…).
     * Les tests remplacent ces liaisons par des doublures.
     */
    public function register(): void
    {
        $this->app->bind(ConnecteurDsk::class, fn ($app) => new DskHttp(
            $app->make(Http::class),
            $app->make(Cache::class),
            $app['config']->get('services.dsk'),
        ));

        $this->app->bind(ConnecteurEpilogg::class, fn ($app) => new EpiloggSql(
            $app->make(DatabaseManager::class),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // L'API renvoie les ressources JSON sans enveloppe `data` : la SPA lit
        // directement les champs, et une ressource seule ou imbriquée a la même forme.
        JsonResource::withoutWrapping();
    }
}
