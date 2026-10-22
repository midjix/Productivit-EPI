<?php

namespace Tests\Feature\Securite;

use App\Models\StatistiqueMensuelle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Injection SQL : deux lignes de défense vérifiées sur l'API de consultation.
 *  1. La validation serveur rejette toute entrée non conforme (422) avant la moindre requête.
 *  2. Les valeurs acceptées sont transmises à MariaDB en paramètres liés (requêtes
 *     préparées par Eloquent), jamais concaténées dans le SQL.
 */
class InjectionTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string}> */
    public static function charges(): array
    {
        return [
            'commentaire SQL' => ["' OR 1=1 --"],
            'tautologie' => ['1 OR 1=1'],
            'union' => ['2026 UNION SELECT password FROM users'],
            'point-virgule' => ['2026; DROP TABLE statistiques_mensuelles'],
            'nombre flottant' => ['2026.0'],
            'tableau' => ['2026[]'],
        ];
    }

    #[Test]
    #[DataProvider('charges')]
    public function une_charge_d_injection_dans_un_filtre_est_rejetee_en_422(string $charge): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/statistiques/me?annee='.urlencode($charge))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('annee');

        $this->getJson('/api/statistiques/me?annee=2026&mois='.urlencode($charge))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('mois');

        $this->assertTrue(DB::getSchemaBuilder()->hasTable('statistiques_mensuelles'), 'La table doit toujours exister.');
    }

    #[Test]
    public function une_charge_dans_un_identifiant_de_route_donne_404_sans_executer_de_sql_injecte(): void
    {
        Sanctum::actingAs(User::factory()->administrateur()->create());

        $this->getJson('/api/statistiques/salaries/'.rawurlencode("1 OR 1=1"))->assertNotFound();
        $this->getJson('/api/statistiques/services/'.rawurlencode("1' OR '1'='1"))->assertNotFound();
    }

    #[Test]
    public function les_filtres_valides_sont_passes_en_parametres_lies(): void
    {
        $salarie = User::factory()->create();
        StatistiqueMensuelle::factory()->for($salarie, 'utilisateur')->periode(2026, 4)->create();
        Sanctum::actingAs($salarie);

        DB::enableQueryLog();
        $this->getJson('/api/statistiques/me?annee=2026')->assertOk()->assertJsonCount(1, 'mensuelles');
        $requetes = collect(DB::getQueryLog());
        DB::disableQueryLog();

        $lecture = $requetes->first(fn (array $q) => str_contains($q['query'], 'statistiques_mensuelles') && str_contains($q['query'], '`annee` = ?'));

        $this->assertNotNull($lecture, 'La lecture du cache doit apparaître dans le journal des requêtes.');
        $this->assertStringNotContainsString('2026', $lecture['query'], 'La valeur ne doit jamais être concaténée dans le SQL.');
        $this->assertContains(2026, $lecture['bindings'], 'La valeur doit voyager en paramètre lié.');
    }
}
