<?php

namespace Tests\Feature\Api;

use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Liste des salariés consultables (barre de recherche) : périmètre par rôle, identité minimale. */
class RechercheSalariesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function un_coordinateur_ne_liste_que_les_salaries_actifs_de_son_service(): void
    {
        $psy = Service::factory()->create(['code' => 'PSY']);
        $aisp = Service::factory()->create(['code' => 'AISP']);
        $coord = User::factory()->coordinateur()->create(['service_id' => $psy->id, 'name' => 'Zoé COORD']);
        User::factory()->create(['service_id' => $psy->id, 'name' => 'Alice MARTIN']);
        User::factory()->sorti()->create(['service_id' => $psy->id, 'name' => 'Bruno PARTI']);
        User::factory()->create(['service_id' => $aisp->id, 'name' => 'Chloé AUTRE']);

        Sanctum::actingAs($coord);

        $this->getJson('/api/salaries')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.name', 'Alice MARTIN')
            ->assertJsonPath('0.service.code', 'PSY')
            ->assertJsonPath('1.name', 'Zoé COORD')
            ->assertJsonMissing(['name' => 'Chloé AUTRE'])
            ->assertJsonMissing(['name' => 'Bruno PARTI'])
            ->assertJsonMissingPath('0.email')
            ->assertJsonMissingPath('0.matricule');
    }

    #[Test]
    public function un_administrateur_liste_tous_les_salaries_actifs_et_un_salarie_est_refuse(): void
    {
        $admin = User::factory()->administrateur()->create();
        User::factory()->count(3)->create();
        User::factory()->sorti()->create();

        Sanctum::actingAs($admin);
        $this->getJson('/api/salaries')->assertOk()->assertJsonCount(4);

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/salaries')->assertForbidden();
    }
}
