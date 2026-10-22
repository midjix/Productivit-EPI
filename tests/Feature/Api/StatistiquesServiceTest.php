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
 * Cas d'utilisation « Consulter les statistiques de son service »
 * (GET /api/statistiques/services/{service}, GET /api/services).
 */
class StatistiquesServiceTest extends TestCase
{
    use RefreshDatabase;

    private Service $psy;

    private Service $aisp;

    private User $coordPsy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->psy = Service::factory()->create(['code' => 'PSY', 'nom' => 'Service Psychologique']);
        $this->aisp = Service::factory()->create(['code' => 'AISP', 'nom' => 'AISP']);
        $this->coordPsy = User::factory()->coordinateur()->create(['service_id' => $this->psy->id, 'name' => 'Zoé COORD']);
    }

    #[Test]
    public function le_coordinateur_voit_une_synthese_par_salarie_de_son_service_et_le_total_du_service(): void
    {
        $alice = User::factory()->create(['service_id' => $this->psy->id, 'name' => 'Alice MARTIN']);
        $bob = User::factory()->create(['service_id' => $this->psy->id, 'name' => 'bob DURAND']);
        $intrus = User::factory()->create(['service_id' => $this->aisp->id]);

        // Alice : 2 mois à 128 h ; Bob : 1 mois vide (absent) ; l'intrus a des données mais hors service.
        StatistiqueMensuelle::factory()->for($alice, 'utilisateur')->exempleFondation()->periode(2026, 1)->create();
        StatistiqueMensuelle::factory()->for($alice, 'utilisateur')->exempleFondation()->periode(2026, 2)->create();
        StatistiqueMensuelle::factory()->for($bob, 'utilisateur')->vide()->periode(2026, 1)->create();
        StatistiqueMensuelle::factory()->for($intrus, 'utilisateur')->exempleFondation()->periode(2026, 1)->create();
        // Hors période : ignoré.
        StatistiqueMensuelle::factory()->for($alice, 'utilisateur')->exempleFondation()->periode(2025, 12)->create();

        Sanctum::actingAs($this->coordPsy);

        $this->getJson("/api/statistiques/services/{$this->psy->id}?annee=2026")
            ->assertOk()
            ->assertJsonPath('service.code', 'PSY')
            ->assertJsonPath('mois', null)
            ->assertJsonCount(2, 'salaries')
            // Tri alphabétique insensible à la casse.
            ->assertJsonPath('salaries.0.salarie.name', 'Alice MARTIN')
            ->assertJsonPath('salaries.0.synthese.nb_mois', 2)
            ->assertJsonPath('salaries.0.synthese.ratio', '0.8367')
            ->assertJsonPath('salaries.1.salarie.name', 'bob DURAND')
            ->assertJsonPath('salaries.1.synthese.ratio', null)
            // Total du service = rapport des sommes (Bob n'apporte rien).
            ->assertJsonPath('synthese.nb_salaries', 2)
            ->assertJsonPath('synthese.nb_mois', 2)
            ->assertJsonPath('synthese.minutes_total', 2 * 128 * 60)
            ->assertJsonPath('synthese.ratio', '0.8367')
            ->assertJsonMissing(['id' => $intrus->id]);
    }

    #[Test]
    public function le_filtre_mois_restreint_la_periode(): void
    {
        $alice = User::factory()->create(['service_id' => $this->psy->id]);
        StatistiqueMensuelle::factory()->for($alice, 'utilisateur')->exempleFondation()->periode(2026, 1)->create();
        StatistiqueMensuelle::factory()->for($alice, 'utilisateur')->exempleFondation()->periode(2026, 2)->create();

        Sanctum::actingAs($this->coordPsy);

        $this->getJson("/api/statistiques/services/{$this->psy->id}?annee=2026&mois=2")
            ->assertOk()
            ->assertJsonPath('mois', 2)
            ->assertJsonPath('synthese.nb_mois', 1)
            ->assertJsonPath('synthese.minutes_total', 128 * 60);
    }

    #[Test]
    public function un_coordinateur_ne_lit_pas_un_autre_service_et_un_salarie_n_a_pas_acces(): void
    {
        config()->set('app.debug', false);

        Sanctum::actingAs($this->coordPsy);
        $this->getJson("/api/statistiques/services/{$this->aisp->id}")
            ->assertForbidden()
            ->assertJsonStructure(['message'])
            ->assertJsonMissingPath('salaries');

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs(User::factory()->create(['service_id' => $this->psy->id]));
        $this->getJson("/api/statistiques/services/{$this->psy->id}")->assertForbidden();
    }

    #[Test]
    public function un_administrateur_lit_tous_les_services(): void
    {
        Sanctum::actingAs(User::factory()->administrateur()->create());

        $this->getJson("/api/statistiques/services/{$this->psy->id}")->assertOk();
        $this->getJson("/api/statistiques/services/{$this->aisp->id}")->assertOk();
        $this->getJson('/api/statistiques/services/999999')->assertNotFound();
    }

    #[Test]
    public function la_liste_des_services_depend_du_role(): void
    {
        Service::factory()->create(['nom' => 'Service fermé', 'actif' => false]);

        Sanctum::actingAs($this->coordPsy);
        $this->getJson('/api/services')->assertOk()->assertJsonCount(1)->assertJsonPath('0.code', 'PSY');

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs(User::factory()->administrateur()->create(['service_id' => $this->psy->id]));
        $this->getJson('/api/services')->assertOk()->assertJsonCount(2)->assertJsonPath('0.code', 'AISP')->assertJsonPath('1.code', 'PSY');

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/services')->assertForbidden();
    }
}
