<?php

namespace App\Providers;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
