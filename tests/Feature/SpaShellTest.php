<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La coquille HTML de la SPA est servie pour toute URL applicative (routeur Vue en
 * mode historique), jamais pour l'API ni pour la sonde de santé.
 */
class SpaShellTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function urlsApplicatives(): array
    {
        return [
            'racine' => ['/'],
            'connexion' => ['/connexion'],
            'tableau de bord d\'un salarié' => ['/salaries/12'],
            'écran d\'administration' => ['/administration'],
            'URL inconnue (404 gérée par Vue)' => ['/page/inexistante'],
        ];
    }

    #[Test]
    #[DataProvider('urlsApplicatives')]
    public function toute_url_applicative_sert_la_coquille_de_la_spa(string $url): void
    {
        $this->get($url)
            ->assertOk()
            ->assertSee('<div id="app"></div>', false)
            ->assertSee('<html lang="fr">', false);
    }

    #[Test]
    public function l_api_et_la_sonde_de_sante_ne_sont_pas_captees_par_la_spa(): void
    {
        $this->getJson('/api/route/inconnue')->assertNotFound()->assertJsonStructure(['message']);
        $this->get('/up')->assertOk()->assertDontSee('<div id="app">', false);
    }
}
