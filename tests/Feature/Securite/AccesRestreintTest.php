<?php

namespace Tests\Feature\Securite;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Version d'essai : la connexion peut être réservée à un rôle minimum
 * (config `epi.role_minimum_connexion`, variable EPI_ROLE_MINIMUM_CONNEXION).
 */
class AccesRestreintTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('login');
    }

    #[Test]
    public function par_defaut_tous_les_roles_se_connectent(): void
    {
        User::factory()->create(['email' => 'salarie@epi.test']);

        $this->postJson('/api/login', ['email' => 'salarie@epi.test', 'password' => 'password'])->assertCreated();
    }

    #[Test]
    public function en_phase_de_test_seuls_les_administrateurs_se_connectent(): void
    {
        config(['epi.role_minimum_connexion' => 'administrateur']);
        $salarie = User::factory()->create(['email' => 'salarie@epi.test']);
        User::factory()->coordinateur()->create(['email' => 'coord@epi.test']);
        User::factory()->administrateur()->create(['email' => 'admin@epi.test']);

        $this->postJson('/api/login', ['email' => 'salarie@epi.test', 'password' => 'password'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Version d\'essai : seuls les comptes « Administrateur » peuvent se connecter pour le moment.');
        $this->postJson('/api/login', ['email' => 'coord@epi.test', 'password' => 'password'])->assertForbidden();
        $this->postJson('/api/login', ['email' => 'admin@epi.test', 'password' => 'password'])->assertCreated();

        $this->assertSame(0, $salarie->tokens()->count(), 'aucun jeton n\'est émis pour un compte refusé');
    }

    #[Test]
    public function un_mauvais_mot_de_passe_reste_prioritaire_sur_la_restriction(): void
    {
        // On ne révèle pas le rôle d'un compte à qui ne connaît pas son mot de passe.
        config(['epi.role_minimum_connexion' => 'administrateur']);
        User::factory()->create(['email' => 'salarie@epi.test']);

        $this->postJson('/api/login', ['email' => 'salarie@epi.test', 'password' => 'faux'])->assertUnprocessable();
    }

    #[Test]
    public function un_role_minimum_inconnu_equivaut_a_tout_le_monde(): void
    {
        config(['epi.role_minimum_connexion' => 'directeur']);
        User::factory()->create(['email' => 'salarie@epi.test']);

        $this->postJson('/api/login', ['email' => 'salarie@epi.test', 'password' => 'password'])->assertCreated();
    }

    #[Test]
    public function le_refus_est_traduit_dans_la_langue_demandee(): void
    {
        config(['epi.role_minimum_connexion' => 'administrateur']);
        User::factory()->create(['email' => 'salarie@epi.test']);

        $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/login', ['email' => 'salarie@epi.test', 'password' => 'password'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Trial version: only "Administrator" accounts can sign in for now.');
    }
}
