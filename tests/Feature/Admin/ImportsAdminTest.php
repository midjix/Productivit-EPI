<?php

namespace Tests\Feature\Admin;

use App\Enums\SourceImport;
use App\Enums\StatutImport;
use App\Etl\Contracts\ConnecteurDsk;
use App\Etl\Contracts\ConnecteurEpilogg;
use App\Jobs\ImporterSourcesJob;
use App\Jobs\RecalculerStatistiquesJob;
use App\Models\Import;
use App\Models\User;
use Database\Seeders\ReferentielSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\DskEnMemoire;
use Tests\Support\EpiloggEnMemoire;
use Tests\TestCase;

/** Cas d'utilisation « Superviser les imports », « Importer les données » (manuel) et « Recalculer ». */
class ImportsAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->administrateur()->create(['name' => 'Admin EPI']);
        Sanctum::actingAs($this->admin);
    }

    #[Test]
    public function le_journal_est_pagine_du_plus_recent_au_plus_ancien_et_filtrable(): void
    {
        Import::factory()->create(['source' => SourceImport::Dsk, 'demarre_le' => '2026-10-01 02:00:00', 'termine_le' => '2026-10-01 02:02:00', 'message' => json_encode(['salaries' => 12])]);
        Import::factory()->echec('VPN injoignable')->create(['source' => SourceImport::Epilogg, 'demarre_le' => '2026-10-02 02:00:00']);
        Import::factory()->create(['source' => SourceImport::Epilogg, 'demarre_le' => '2026-10-03 02:00:00', 'user_id' => $this->admin->id, 'message' => json_encode(['types_non_qualifies' => ['Atelier']])]);

        $this->getJson('/api/admin/imports')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.statut', 'succes')
            ->assertJsonPath('data.0.declencheur', 'Admin EPI')
            ->assertJsonPath('data.0.resume.types_non_qualifies', ['Atelier'])
            ->assertJsonPath('data.0.erreur', null)
            ->assertJsonPath('data.1.statut', 'echec')
            ->assertJsonPath('data.1.erreur', 'VPN injoignable')
            ->assertJsonPath('data.1.resume', null)
            ->assertJsonPath('data.1.declencheur', 'Planificateur')
            ->assertJsonPath('data.2.source', 'dsk')
            ->assertJsonPath('data.2.duree_secondes', 120)
            ->assertJsonStructure(['meta' => ['current_page', 'total']]);

        $this->getJson('/api/admin/imports?statut=echec')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/admin/imports?source=epilogg')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/admin/imports?source=sap')->assertUnprocessable();
    }

    #[Test]
    public function un_import_manuel_est_mis_en_file_d_attente_avec_la_periode_demandee(): void
    {
        Queue::fake();

        $this->postJson('/api/admin/imports', ['source' => 'epilogg', 'du' => '2026-09-01', 'au' => '2026-09-30'])
            ->assertStatus(202)
            ->assertJsonPath('source', 'epilogg')
            ->assertJsonPath('du', '2026-09-01')
            ->assertJsonPath('au', '2026-09-30');

        Queue::assertPushed(ImporterSourcesJob::class, fn (ImporterSourcesJob $job) => $job->source === 'epilogg'
            && $job->du->toDateString() === '2026-09-01'
            && $job->au->toDateString() === '2026-09-30'
            && $job->declencheurId === $this->admin->id);

        // Sans période : fenêtre glissante par défaut.
        $this->postJson('/api/admin/imports')->assertStatus(202)->assertJsonPath('source', 'all')->assertJsonPath('au', now()->toDateString());
    }

    #[Test]
    public function une_periode_incoherente_ou_future_est_rejetee(): void
    {
        Queue::fake();

        $this->postJson('/api/admin/imports', ['du' => '2026-09-30', 'au' => '2026-09-01'])->assertUnprocessable()->assertJsonValidationErrors('au');
        $this->postJson('/api/admin/imports', ['au' => now()->addDay()->toDateString()])->assertUnprocessable()->assertJsonValidationErrors('au');
        $this->postJson('/api/admin/imports', ['du' => '2019-12-31'])->assertUnprocessable()->assertJsonValidationErrors('du');
        $this->postJson('/api/admin/imports', ['source' => 'sap'])->assertUnprocessable()->assertJsonValidationErrors('source');
        $this->postJson('/api/admin/imports', ['du' => "2026-09-01' OR 1=1"])->assertUnprocessable()->assertJsonValidationErrors('du');

        Queue::assertNothingPushed();
    }

    #[Test]
    public function l_import_manuel_execute_journalise_le_declencheur_et_consolide(): void
    {
        // File d'attente synchrone en test : le job s'exécute dans la requête, sur des sources simulées vides.
        $this->seed(ReferentielSeeder::class);
        $this->app->instance(ConnecteurDsk::class, new DskEnMemoire);
        $this->app->instance(ConnecteurEpilogg::class, new EpiloggEnMemoire);

        $this->postJson('/api/admin/imports', ['du' => '2026-09-01', 'au' => '2026-09-30'])->assertStatus(202);

        $imports = Import::orderBy('id')->get();
        $this->assertCount(2, $imports);
        $this->assertSame([SourceImport::Dsk, SourceImport::Epilogg], $imports->pluck('source')->all());
        $this->assertTrue($imports->every(fn (Import $i) => $i->statut === StatutImport::Succes && $i->user_id === $this->admin->id));

        $this->getJson("/api/admin/imports/{$imports[0]->id}")->assertOk()->assertJsonPath('declencheur', 'Admin EPI');
        $this->getJson('/api/admin/imports/999999')->assertNotFound();
    }

    #[Test]
    public function un_recalcul_a_la_demande_est_mis_en_file_d_attente(): void
    {
        Queue::fake();

        $this->postJson('/api/admin/recalculs')->assertOk()->assertJsonPath('message', 'Aucune donnée importée : rien à recalculer.');
        Queue::assertNothingPushed();

        $this->postJson('/api/admin/recalculs', ['du' => '2026-01-01', 'au' => '2026-03-31'])->assertStatus(202);
        Queue::assertPushed(RecalculerStatistiquesJob::class, fn (RecalculerStatistiquesJob $j) => $j->du->toDateString() === '2026-01-01' && $j->au->toDateString() === '2026-03-31');
    }
}
