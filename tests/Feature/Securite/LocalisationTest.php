<?php

namespace Tests\Feature\Securite;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * L'API répond dans la langue demandée par `Accept-Language` (fr par défaut, en, de) :
 * messages de validation (avec libellés de champs traduits), refus d'accès, connexion.
 */
class LocalisationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string, string, string, string}> */
    public static function langues(): array
    {
        return [
            // Sans préférence exploitable, la langue de référence de la Fondation s'applique.
            'aucune préférence → français' => ['', 'fr', 'année', 'Identifiants incorrects.'],
            'français explicite' => ['fr-LU,fr;q=0.9', 'fr', 'année', 'Identifiants incorrects.'],
            'anglais' => ['en-GB,en;q=0.8', 'en', 'year', 'Incorrect credentials.'],
            'allemand' => ['de-LU,de;q=0.9,fr;q=0.5', 'de', 'Jahr', 'Ungültige Anmeldedaten.'],
            'langue non prise en charge → français' => ['pt-PT,pt;q=0.9', 'fr', 'année', 'Identifiants incorrects.'],
        ];
    }

    #[Test]
    #[DataProvider('langues')]
    public function la_validation_et_la_connexion_repondent_dans_la_langue_demandee(string $enTete, string $attendue, string $libelleAnnee, string $messageConnexion): void
    {
        // Le client de test envoie « en-us » par défaut (Symfony) : un en-tête vide simule l'absence de préférence.
        $entetes = ['Accept-Language' => $enTete];

        $this->postJson('/api/login', ['email' => 'inconnu@fondationepi.lu', 'password' => 'faux'], $entetes)
            ->assertUnprocessable()
            ->assertHeader('Content-Language', $attendue)
            ->assertJsonPath('errors.email.0', $messageConnexion);

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs(User::factory()->create());

        $reponse = $this->getJson('/api/statistiques/me?annee=abc', $entetes)->assertUnprocessable();
        $this->assertStringContainsString($libelleAnnee, $reponse->json('errors.annee.0'));
        $this->assertStringNotContainsString('annee ', $reponse->json('errors.annee.0'), 'le nom technique du champ ne doit pas apparaître');
    }

    #[Test]
    public function les_refus_d_acces_sont_traduits(): void
    {
        config()->set('app.debug', false);
        $autre = User::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/statistiques/salaries/{$autre->id}", ['Accept-Language' => 'de'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Sie haben keinen Zugriff auf die Daten dieses Mitarbeiters.');

        $this->getJson('/api/statistiques/globales', ['Accept-Language' => 'en'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Insufficient rights for this action.');

        $this->getJson('/api/me', ['Accept-Language' => 'de'])
            ->assertOk()
            ->assertJsonPath('role_label', 'Mitarbeiter');
    }
}
