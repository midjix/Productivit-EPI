<?php

namespace Tests\Feature\Securite;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Changement de son propre mot de passe (PUT /api/me/mot-de-passe). */
class MotDePasseTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function l_ancien_mot_de_passe_est_exige_et_le_nouveau_suit_la_politique(): void
    {
        $user = User::factory()->create(['password' => 'AncienMotDePasse1']);
        $token = $user->createToken('spa')->plainTextToken;
        $entetes = ['Authorization' => "Bearer {$token}"];

        $this->putJson('/api/me/mot-de-passe', ['mot_de_passe_actuel' => 'faux', 'password' => 'NouveauMotDePasse2!', 'password_confirmation' => 'NouveauMotDePasse2!'], $entetes)
            ->assertUnprocessable()->assertJsonValidationErrors('mot_de_passe_actuel');

        // Politique : 12 caractères, minuscule, majuscule, chiffre, symbole. Tout ce qui manque est signalé d'un coup.
        $erreurs = $this->putJson('/api/me/mot-de-passe', ['mot_de_passe_actuel' => 'AncienMotDePasse1', 'password' => 'court', 'password_confirmation' => 'court'], $entetes)
            ->assertUnprocessable()->assertJsonValidationErrors('password')->json('errors.password');
        $this->assertCount(4, $erreurs, 'longueur, majuscule/minuscule, chiffre et symbole doivent être signalés ensemble');
        $this->assertStringContainsString('majuscule', implode(' ', $erreurs));
        $this->assertStringContainsString('symbole', implode(' ', $erreurs));

        foreach (['NouveauMotDePasse2', 'nouveaumotdepasse2!', 'NouveauMotDePasse!!'] as $incomplet) {
            $this->putJson('/api/me/mot-de-passe', ['mot_de_passe_actuel' => 'AncienMotDePasse1', 'password' => $incomplet, 'password_confirmation' => $incomplet], $entetes)
                ->assertUnprocessable()->assertJsonValidationErrors('password');
        }

        $this->putJson('/api/me/mot-de-passe', ['mot_de_passe_actuel' => 'AncienMotDePasse1', 'password' => 'AncienMotDePasse1', 'password_confirmation' => 'AncienMotDePasse1'], $entetes)
            ->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->app['auth']->forgetGuards();
        $this->putJson('/api/me/mot-de-passe', [], ['Authorization' => 'Bearer invalide'])->assertUnauthorized();
    }

    #[Test]
    public function le_changement_conserve_la_session_courante_et_deconnecte_les_autres_appareils(): void
    {
        $user = User::factory()->create(['email' => 'moi@fondationepi.lu', 'password' => 'AncienMotDePasse1']);
        $courant = $user->createToken('spa')->plainTextToken;
        $autre = $user->createToken('mobile')->plainTextToken;

        $this->putJson('/api/me/mot-de-passe', [
            'mot_de_passe_actuel' => 'AncienMotDePasse1', 'password' => 'NouveauMotDePasse2!', 'password_confirmation' => 'NouveauMotDePasse2!',
        ], ['Authorization' => "Bearer {$courant}"])->assertNoContent();

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/me', ['Authorization' => "Bearer {$courant}"])->assertOk();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/me', ['Authorization' => "Bearer {$autre}"])->assertUnauthorized();

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', ['email' => 'moi@fondationepi.lu', 'password' => 'NouveauMotDePasse2!'])->assertCreated();
    }
}
