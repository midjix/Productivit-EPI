<?php

namespace Tests\Feature\Api;

use App\Models\Service;
use App\Models\StatistiqueMensuelle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Cas d'utilisation « Consulter les statistiques globales » (GET /api/statistiques/globales, Administrateur). */
class StatistiquesGlobalesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function l_administrateur_voit_une_synthese_par_service_et_le_total_epi(): void
    {
        $psy = Service::factory()->create(['code' => 'PSY', 'nom' => 'Service Psychologique']);
        $aisp = Service::factory()->create(['code' => 'AISP', 'nom' => 'AISP']);
        $admin = User::factory()->administrateur()->create();

        // AISP : un salarié à l'exemple 1 (82 h facturables / 98 h dispo client).
        $a = User::factory()->create(['service_id' => $aisp->id]);
        StatistiqueMensuelle::factory()->for($a, 'utilisateur')->exempleFondation()->periode(2026, 4)->create();

        // PSY : un salarié à mi-temps, 20 h facturables / 40 h dispo client (ratio 0,5).
        $p = User::factory()->create(['service_id' => $psy->id]);
        StatistiqueMensuelle::factory()->for($p, 'utilisateur')->periode(2026, 4)->create([
            'minutes_normales' => 40 * 60, 'minutes_conge' => 0, 'minutes_maladie' => 0, 'minutes_formation' => 0,
            'minutes_supervision' => 0, 'minutes_deplacement' => 0, 'minutes_autres' => 0,
            'minutes_total' => 40 * 60, 'minutes_dispo_epi' => 40 * 60, 'minutes_dispo_client' => '2400.00',
            'nb_prestations' => '20.00', 'minutes_prestations_facturables' => '1200.00', 'minutes_activite_interne' => '0.00',
            'ratio' => '0.5000', 'ratio_brut' => '0.5000',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/statistiques/globales?annee=2026&mois=4')
            ->assertOk()
            ->assertJsonCount(2, 'services')
            ->assertJsonPath('services.0.service.code', 'AISP')
            ->assertJsonPath('services.0.synthese.ratio', '0.8367')
            ->assertJsonPath('services.1.service.code', 'PSY')
            ->assertJsonPath('services.1.synthese.ratio', '0.5000')
            // Total EPI = (4920 + 1200) / (5880 + 2400) = 0,7391 — et NON la moyenne des ratios (0,6684).
            ->assertJsonPath('synthese.nb_salaries', 2)
            ->assertJsonPath('synthese.minutes_prestations_facturables', '6120.00')
            ->assertJsonPath('synthese.minutes_dispo_client', '8280.00')
            ->assertJsonPath('synthese.ratio', '0.7391')
            ->assertJsonPath('synthese.objectif_atteint', false);
    }

    #[Test]
    public function un_salarie_rattache_a_aucun_service_est_regroupe_a_part(): void
    {
        $admin = User::factory()->administrateur()->create();
        $orphelin = User::factory()->create(['service_id' => null]);
        StatistiqueMensuelle::factory()->for($orphelin, 'utilisateur')->exempleFondation()->periode(2026, 4)->create();

        Sanctum::actingAs($admin);

        $this->getJson('/api/statistiques/globales?annee=2026')
            ->assertOk()
            ->assertJsonCount(1, 'services')
            ->assertJsonPath('services.0.service', null)
            ->assertJsonPath('services.0.synthese.ratio', '0.8367');
    }

    #[Test]
    public function seul_l_administrateur_y_accede(): void
    {
        Sanctum::actingAs(User::factory()->coordinateur()->create());
        $this->getJson('/api/statistiques/globales')->assertForbidden();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/statistiques/globales')->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/statistiques/globales')->assertUnauthorized();
    }
}
