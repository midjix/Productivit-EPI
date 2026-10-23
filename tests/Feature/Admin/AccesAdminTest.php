<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Toutes les routes d'administration sont réservées à l'Administrateur : 401 sans jeton, 403 pour les autres rôles. */
class AccesAdminTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string, string}> */
    public static function routes(): array
    {
        return [
            'journal des imports' => ['GET', '/api/admin/imports'],
            'import manuel' => ['POST', '/api/admin/imports'],
            'recalcul' => ['POST', '/api/admin/recalculs'],
            'compteurs DSK' => ['GET', '/api/admin/comptes-dsk'],
            'types de suivi' => ['GET', '/api/admin/types-suivi'],
            'matrice' => ['GET', '/api/admin/tarifs'],
            'services' => ['GET', '/api/admin/services'],
            'utilisateurs' => ['GET', '/api/admin/utilisateurs'],
        ];
    }

    #[Test]
    #[DataProvider('routes')]
    public function un_coordinateur_et_un_salarie_sont_refuses_et_sans_jeton_c_est_401(string $methode, string $route): void
    {
        $this->json($methode, $route)->assertUnauthorized();

        Sanctum::actingAs(User::factory()->coordinateur()->create());
        $this->json($methode, $route)->assertForbidden();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs(User::factory()->create());
        $this->json($methode, $route)->assertForbidden();
    }
}
