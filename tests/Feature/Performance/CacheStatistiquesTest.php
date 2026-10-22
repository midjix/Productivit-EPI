<?php

namespace Tests\Feature\Performance;

use App\Etl\CalculEfficacite;
use App\Models\Service;
use App\Models\StatistiqueMensuelle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Test de performance justifié par le contexte : la Fondation veut consulter
 * 5 ans d'historique sans attendre. Le cache mensuel rend la lecture
 * indépendante du volume des flux bruts (≈ 2 000 lignes pour 30 salariés × 60 mois,
 * contre plusieurs centaines de milliers de lignes brutes).
 *
 * Seuils volontairement larges (poste de développement sous Docker/Windows, CI partagée) :
 * ils détectent une régression d'ordre de grandeur, pas une variation de 10 %.
 */
class CacheStatistiquesTest extends TestCase
{
    use RefreshDatabase;

    private const NB_SALARIES = 30;

    private const NB_MOIS = 60;

    #[Test]
    public function cinq_ans_d_historique_se_consultent_en_moins_d_une_seconde(): void
    {
        [$admin, $salarie] = $this->peuplerCinqAns();

        Sanctum::actingAs($salarie);
        $debut = hrtime(true);
        $this->getJson('/api/statistiques/me?annee=2023')->assertOk()->assertJsonCount(12, 'mensuelles');
        $dureeSalarie = (hrtime(true) - $debut) / 1e9;

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($admin);
        $debut = hrtime(true);
        $this->getJson('/api/statistiques/globales?annee=2023')->assertOk()->assertJsonPath('synthese.nb_salaries', self::NB_SALARIES);
        $dureeGlobale = (hrtime(true) - $debut) / 1e9;

        $this->assertLessThan(1.0, $dureeSalarie, sprintf('Tableau de bord salarié : %.3f s', $dureeSalarie));
        $this->assertLessThan(2.0, $dureeGlobale, sprintf('Vue globale : %.3f s', $dureeGlobale));
        $this->assertSame(self::NB_SALARIES * self::NB_MOIS, StatistiqueMensuelle::count());
    }

    /** 30 salariés dans 3 services, 60 mois chacun (2022 → 2026), insérés par lots. @return array{User, User} */
    private function peuplerCinqAns(): array
    {
        $services = Service::factory()->count(3)->create();
        $admin = User::factory()->administrateur()->create(['service_id' => $services[0]->id]);
        $salaries = User::factory()->count(self::NB_SALARIES)
            ->sequence(fn ($s) => ['service_id' => $services[$s->index % 3]->id])
            ->create();

        $lignes = [];
        $maintenant = now();

        foreach ($salaries as $salarie) {
            for ($i = 0; $i < self::NB_MOIS; $i++) {
                $calcul = new CalculEfficacite(
                    minutesNormales: 6000 + ($i % 7) * 120,
                    minutesConge: ($i % 5) * 240,
                    minutesMaladie: ($i % 11 === 0) ? 480 : 0,
                    minutesFormation: ($i % 3) * 120,
                    minutesSupervision: ($i % 2) * 60,
                    nbPrestations: '70.00',
                    minutesFacturables: (4000 + ($i % 9) * 100).'.00',
                    minutesActiviteInterne: '60.00',
                );
                $lignes[] = [
                    'user_id' => $salarie->id,
                    'annee' => 2022 + intdiv($i, 12),
                    'mois' => 1 + $i % 12,
                    ...$calcul->attributs(),
                    'calcule_le' => $maintenant,
                    'created_at' => $maintenant,
                    'updated_at' => $maintenant,
                ];
            }
        }

        foreach (array_chunk($lignes, 500) as $lot) {
            StatistiqueMensuelle::insert($lot);
        }

        return [$admin, $salaries->first()];
    }
}
