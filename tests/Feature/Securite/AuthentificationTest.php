<?php

namespace Tests\Feature\Securite;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Authentification par jeton (Sanctum) : 401 sans jeton, jetons révocables, force brute limitée. */
class AuthentificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('login');
    }

    #[Test]
    public function un_utilisateur_actif_obtient_un_jeton_avec_les_bons_identifiants(): void
    {
        $user = User::factory()->create(['email' => 'marie@epi.test']);

        $reponse = $this->postJson('/api/login', ['email' => 'marie@epi.test', 'password' => 'password']);

        $reponse->assertCreated()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role', 'role_label', 'service']])
            ->assertJsonPath('user.email', 'marie@epi.test')
            ->assertJsonPath('user.role', 'salarie');

        $this->assertSame(1, $user->tokens()->count());
        $this->assertNotSame($reponse->json('token'), $user->tokens()->first()->token, 'seule l\'empreinte du jeton est stockée');
    }

    #[Test]
    public function la_reponse_ne_divulgue_aucune_donnee_rh(): void
    {
        User::factory()->create(['email' => 'marie@epi.test']);

        $json = $this->postJson('/api/login', ['email' => 'marie@epi.test', 'password' => 'password'])->json('user');

        foreach (['password', 'matricule', 'dsk_no_pers', 'epilogg_user_id', 'date_entree', 'date_sortie', 'remember_token'] as $champ) {
            $this->assertArrayNotHasKey($champ, $json, "le champ {$champ} ne doit pas être exposé");
        }
    }

    #[Test]
    public function un_mauvais_mot_de_passe_et_un_email_inconnu_donnent_la_meme_erreur(): void
    {
        User::factory()->create(['email' => 'marie@epi.test']);

        $mauvaisMdp = $this->postJson('/api/login', ['email' => 'marie@epi.test', 'password' => 'faux']);
        $inconnu = $this->postJson('/api/login', ['email' => 'personne@epi.test', 'password' => 'faux']);

        $mauvaisMdp->assertUnprocessable();
        $inconnu->assertUnprocessable();
        $this->assertSame($mauvaisMdp->json('errors'), $inconnu->json('errors'), 'pas d\'énumération de comptes');
    }

    #[Test]
    public function les_entrees_sont_validees_cote_serveur(): void
    {
        $this->postJson('/api/login', ['email' => 'pas-un-email', 'password' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    #[Test]
    public function un_salarie_parti_ne_peut_plus_se_connecter(): void
    {
        User::factory()->sorti()->create(['email' => 'parti@epi.test']);

        $this->postJson('/api/login', ['email' => 'parti@epi.test', 'password' => 'password'])
            ->assertForbidden();
    }

    #[Test]
    public function les_routes_protegees_repondent_401_sans_jeton(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
        $this->postJson('/api/logout')->assertUnauthorized();
        $this->withHeader('Authorization', 'Bearer jeton-invente')->getJson('/api/me')->assertUnauthorized();
    }

    #[Test]
    public function le_jeton_donne_acces_puis_est_revoque_par_la_deconnexion(): void
    {
        User::factory()->create(['email' => 'marie@epi.test']);
        $token = $this->postJson('/api/login', ['email' => 'marie@epi.test', 'password' => 'password'])->json('token');

        $this->withToken($token)->getJson('/api/me')->assertOk()->assertJsonPath('email', 'marie@epi.test');
        $this->withToken($token)->postJson('/api/logout')->assertNoContent();

        $this->oublierLeGuard();
        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
    }

    /**
     * Dans un test, le guard Sanctum garde en mémoire l'utilisateur résolu entre deux
     * requêtes de la même méthode (une seule instance d'application). On le vide pour
     * que la requête suivante ré-authentifie réellement le jeton, comme en production.
     */
    private function oublierLeGuard(): void
    {
        $this->app['auth']->forgetGuards();
    }

    #[Test]
    public function la_deconnexion_ne_revoque_que_le_jeton_courant(): void
    {
        User::factory()->create(['email' => 'marie@epi.test']);
        $pc = $this->postJson('/api/login', ['email' => 'marie@epi.test', 'password' => 'password', 'device_name' => 'pc'])->json('token');
        $mobile = $this->postJson('/api/login', ['email' => 'marie@epi.test', 'password' => 'password', 'device_name' => 'mobile'])->json('token');

        $this->withToken($pc)->postJson('/api/logout')->assertNoContent();

        $this->oublierLeGuard();
        $this->withToken($pc)->getJson('/api/me')->assertUnauthorized();

        $this->oublierLeGuard();
        $this->withToken($mobile)->getJson('/api/me')->assertOk();
    }

    #[Test]
    public function la_force_brute_est_limitee_a_cinq_tentatives_par_minute(): void
    {
        User::factory()->create(['email' => 'marie@epi.test']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['email' => 'marie@epi.test', 'password' => 'faux'])->assertUnprocessable();
        }

        $this->postJson('/api/login', ['email' => 'marie@epi.test', 'password' => 'password'])->assertTooManyRequests();
    }
}
