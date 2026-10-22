<?php

namespace Tests\Feature\Api;

use App\Models\Service;
use App\Models\StatistiqueMensuelle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cas d'utilisation « Consulter son tableau de bord » (GET /api/statistiques/me)
 * et son périmètre : un salarié ne lit que SES données ; un coordinateur celles
 * de son service ; un administrateur toutes.
 */
class TableauDeBordSalarieTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function un_salarie_lit_ses_mois_de_l_annee_dans_l_ordre_et_une_synthese_annuelle(): void
    {
        $salarie = User::factory()->create();
        StatistiqueMensuelle::factory()->for($salarie, 'utilisateur')->exempleFondation()->periode(2026, 4)->create();
        StatistiqueMensuelle::factory()->for($salarie, 'utilisateur')->exempleFondation()->periode(2026, 3)->create();
        StatistiqueMensuelle::factory()->for($salarie, 'utilisateur')->exempleFondation()->periode(2025, 12)->create();
        // Bruit : un autre salarié la même année ne doit jamais apparaître.
        StatistiqueMensuelle::factory()->periode(2026, 4)->create();

        Sanctum::actingAs($salarie);

        $reponse = $this->getJson('/api/statistiques/me?annee=2026')
            ->assertOk()
            ->assertJsonPath('salarie.id', $salarie->id)
            ->assertJsonPath('annee', 2026)
            ->assertJsonPath('annees_disponibles', [2026, 2025])
            ->assertJsonCount(2, 'mensuelles')
            ->assertJsonPath('mensuelles.0.mois', 3)
            ->assertJsonPath('mensuelles.1.mois', 4)
            // Exemple 1 de la Fondation, mois par mois…
            ->assertJsonPath('mensuelles.0.minutes_total', 128 * 60)
            ->assertJsonPath('mensuelles.0.minutes_dispo_epi', 106 * 60)
            ->assertJsonPath('mensuelles.0.minutes_dispo_client', '5880.00')
            ->assertJsonPath('mensuelles.0.ratio', '0.8367')
            ->assertJsonPath('mensuelles.0.objectif_atteint', true)
            // …et en synthèse annuelle : rapport des SOMMES (deux mois identiques → même ratio).
            ->assertJsonPath('synthese.nb_mois', 2)
            ->assertJsonPath('synthese.minutes_total', 2 * 128 * 60)
            ->assertJsonPath('synthese.minutes_prestations_facturables', '9840.00')
            ->assertJsonPath('synthese.ratio', '0.8367')
            ->assertJsonPath('synthese.objectif', '0.7500')
            ->assertJsonPath('synthese.objectif_atteint', true);

        // Minimisation RGPD : identité réduite au strict nécessaire.
        $reponse->assertJsonMissingPath('salarie.email')
            ->assertJsonMissingPath('salarie.matricule')
            ->assertJsonMissingPath('salarie.dsk_no_pers')
            ->assertJsonMissingPath('salarie.epilogg_user_id');
    }

    #[Test]
    public function sans_filtre_l_annee_courante_est_servie_et_une_annee_vide_donne_une_synthese_nulle(): void
    {
        $salarie = User::factory()->create();
        Sanctum::actingAs($salarie);

        $this->getJson('/api/statistiques/me')
            ->assertOk()
            ->assertJsonPath('annee', now()->year)
            ->assertJsonPath('mensuelles', [])
            ->assertJsonPath('annees_disponibles', [])
            ->assertJsonPath('synthese.nb_mois', 0)
            ->assertJsonPath('synthese.ratio', null)
            ->assertJsonPath('synthese.objectif_atteint', null);
    }

    #[Test]
    public function un_salarie_ne_peut_pas_lire_les_donnees_d_un_autre_salarie(): void
    {
        config()->set('app.debug', false);
        $service = Service::factory()->create();
        [$moi, $collegue] = User::factory()->count(2)->create(['service_id' => $service->id]);
        StatistiqueMensuelle::factory()->for($collegue, 'utilisateur')->periode(2026, 1)->create();

        Sanctum::actingAs($moi);

        $this->getJson("/api/statistiques/salaries/{$collegue->id}?annee=2026")
            ->assertForbidden()
            ->assertJsonStructure(['message'])
            ->assertJsonMissingPath('mensuelles')
            ->assertJsonMissingPath('exception');

        // Lui-même par l'URL explicite : autorisé (même périmètre que /me).
        $this->getJson("/api/statistiques/salaries/{$moi->id}")->assertOk()->assertJsonPath('salarie.id', $moi->id);
    }

    #[Test]
    public function un_coordinateur_lit_les_salaries_de_son_service_mais_pas_ceux_d_un_autre(): void
    {
        $monService = Service::factory()->create();
        $autreService = Service::factory()->create();
        $coordinateur = User::factory()->coordinateur()->create(['service_id' => $monService->id]);
        $monSalarie = User::factory()->create(['service_id' => $monService->id]);
        $autreSalarie = User::factory()->create(['service_id' => $autreService->id]);

        Sanctum::actingAs($coordinateur);

        $this->getJson("/api/statistiques/salaries/{$monSalarie->id}")->assertOk();
        $this->getJson("/api/statistiques/salaries/{$autreSalarie->id}")->assertForbidden();
    }

    #[Test]
    public function un_coordinateur_sans_service_ne_voit_personne_d_autre_que_lui(): void
    {
        $coordinateur = User::factory()->coordinateur()->create(['service_id' => null]);
        $sansService = User::factory()->create(['service_id' => null]);

        Sanctum::actingAs($coordinateur);

        // Pas de « null === null » qui ouvrirait tous les salariés sans service.
        $this->getJson("/api/statistiques/salaries/{$sansService->id}")->assertForbidden();
        $this->getJson("/api/statistiques/salaries/{$coordinateur->id}")->assertOk();
    }

    #[Test]
    public function un_administrateur_lit_n_importe_quel_salarie(): void
    {
        $admin = User::factory()->administrateur()->create();
        $salarie = User::factory()->create();

        Sanctum::actingAs($admin);

        $this->getJson("/api/statistiques/salaries/{$salarie->id}")->assertOk()->assertJsonPath('salarie.id', $salarie->id);
    }

    #[Test]
    public function un_salarie_inexistant_donne_404_et_sans_jeton_401(): void
    {
        $this->getJson('/api/statistiques/me')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->administrateur()->create());
        $this->getJson('/api/statistiques/salaries/999999')->assertNotFound();
    }

    #[Test]
    public function les_filtres_invalides_sont_rejetes_en_422_avant_toute_lecture(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/statistiques/me?annee=abc')->assertUnprocessable()->assertJsonValidationErrors('annee');
        $this->getJson('/api/statistiques/me?annee=1999')->assertUnprocessable()->assertJsonValidationErrors('annee');
        $this->getJson('/api/statistiques/me?annee='.(now()->year + 2))->assertUnprocessable()->assertJsonValidationErrors('annee');
        $this->getJson('/api/statistiques/me?annee=2026&mois=13')->assertUnprocessable()->assertJsonValidationErrors('mois');
        $this->getJson('/api/statistiques/me?annee=2026&mois=0')->assertUnprocessable()->assertJsonValidationErrors('mois');
    }
}
