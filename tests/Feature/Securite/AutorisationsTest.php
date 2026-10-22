<?php

namespace Tests\Feature\Securite;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Middleware `role:<rôle>` : 403 si le rôle est insuffisant, avec héritage de droits.
 * Les routes sont déclarées dans le test lui-même : on vérifie le mécanisme,
 * indépendamment des endpoints métier qui l'utiliseront.
 */
class AutorisationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth:sanctum', 'role:salarie'])->get('/api/_test/salarie', fn () => ['ok' => true]);
        Route::middleware(['auth:sanctum', 'role:coordinateur'])->get('/api/_test/coordinateur', fn () => ['ok' => true]);
        Route::middleware(['auth:sanctum', 'role:administrateur'])->get('/api/_test/administrateur', fn () => ['ok' => true]);
    }

    #[Test]
    #[DataProvider('matrice')]
    public function la_matrice_des_droits_est_respectee(string $etat, string $route, int $statutAttendu): void
    {
        $factory = User::factory();
        $user = match ($etat) {
            'salarie' => $factory->create(),
            'coordinateur' => $factory->coordinateur()->create(),
            'administrateur' => $factory->administrateur()->create(),
        };

        Sanctum::actingAs($user);

        $this->getJson("/api/_test/{$route}")->assertStatus($statutAttendu);
    }

    /** @return array<string, array{string, string, int}> */
    public static function matrice(): array
    {
        return [
            'salarié → route salarié' => ['salarie', 'salarie', 200],
            'salarié → route coordinateur' => ['salarie', 'coordinateur', 403],
            'salarié → route administrateur' => ['salarie', 'administrateur', 403],
            'coordinateur → route salarié (héritage)' => ['coordinateur', 'salarie', 200],
            'coordinateur → route coordinateur' => ['coordinateur', 'coordinateur', 200],
            'coordinateur → route administrateur' => ['coordinateur', 'administrateur', 403],
            'administrateur → route salarié (héritage)' => ['administrateur', 'salarie', 200],
            'administrateur → route coordinateur (héritage)' => ['administrateur', 'coordinateur', 200],
            'administrateur → route administrateur' => ['administrateur', 'administrateur', 200],
        ];
    }

    #[Test]
    public function sans_jeton_c_est_401_avant_meme_le_controle_de_role(): void
    {
        $this->getJson('/api/_test/administrateur')->assertUnauthorized();
    }

    #[Test]
    public function un_refus_de_role_est_une_reponse_json_403_sans_fuite_d_information(): void
    {
        // Comportement de production : sans mode debug, aucune trace ni classe d'exception dans la réponse.
        config()->set('app.debug', false);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/_test/administrateur')
            ->assertForbidden()
            ->assertJsonStructure(['message'])
            ->assertJsonMissingPath('exception')
            ->assertJsonMissingPath('trace');
    }
}
