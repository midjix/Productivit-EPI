<?php

namespace Tests\Feature\Admin;

use App\Enums\CategorieHeure;
use App\Models\CategorieSuivi;
use App\Models\CompteDsk;
use App\Models\DonneeDsk;
use App\Models\DonneeEpilogg;
use App\Models\RegleConversion;
use App\Models\StatistiqueMensuelle;
use App\Models\Tarif;
use App\Models\User;
use App\Services\ConsolidationService;
use Database\Seeders\ReferentielSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Cas d'utilisation « Gérer la matrice de conversion » via l'API : historisation garantie, recalcul inclus. */
class MatriceAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Tarif $tarif;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferentielSeeder::class);
        $this->admin = User::factory()->administrateur()->create(['name' => 'Admin EPI']);
        Sanctum::actingAs($this->admin);
        $this->tarif = Tarif::where('code', '9.1')->first();
    }

    #[Test]
    public function la_matrice_expose_chaque_tarif_avec_son_historique_et_le_bareme_en_vigueur(): void
    {
        $this->getJson('/api/admin/tarifs')
            ->assertOk()
            ->assertJsonCount(4)
            ->assertJsonPath('0.code', '8.1')
            ->assertJsonPath('0.minutes_en_vigueur', 60)
            ->assertJsonPath('0.regles.0.en_vigueur', true)
            ->assertJsonPath('0.regles.0.date_fin_validite', null)
            ->assertJsonPath('2.code', '9.1')
            ->assertJsonPath('2.minutes_en_vigueur', 30)
            ->assertJsonPath('3.code', 'AUTRE')
            ->assertJsonPath('3.facturable', false);
    }

    #[Test]
    public function un_nouveau_bareme_cloture_l_ancien_cree_le_nouveau_et_recalcule(): void
    {
        $salarie = User::factory()->create();
        $normales = CompteDsk::factory()->categorie(CategorieHeure::Normal)->create();
        $type = CategorieSuivi::factory()->create(['tarif_id' => $this->tarif->id]);
        foreach (['2026-03-02', '2026-04-02'] as $date) {
            DonneeDsk::factory()->create(['user_id' => $salarie->id,'compte_dsk_id' => $normales->id, 'date' => $date, 'minutes' => 480]);
            DonneeEpilogg::factory()->create(['user_id' => $salarie->id,'categorie_suivi_id' => $type->id, 'date' => $date, 'quantite' => 2]);
        }
        app(ConsolidationService::class)->recalculerPeriode(now()->parse('2026-03-01'), now()->parse('2026-04-30'));

        $this->postJson("/api/admin/tarifs/{$this->tarif->id}/regles", ['minutes' => 45, 'date_debut' => '2026-04-01', 'commentaire' => 'Revalorisation'])
            ->assertCreated()
            ->assertJsonPath('minutes', 45)
            ->assertJsonPath('date_debut_validite', '2026-04-01')
            ->assertJsonPath('en_vigueur', true)
            ->assertJsonPath('auteur', 'Admin EPI')
            ->assertJsonPath('commentaire', 'Revalorisation');

        $regles = RegleConversion::where('tarif_id', $this->tarif->id)->orderBy('date_debut_validite')->get();
        $this->assertCount(2, $regles, 'l\'ancienne règle n\'est jamais écrasée');
        $this->assertSame('2026-03-31', $regles[0]->date_fin_validite->toDateString(), 'clôturée à la veille');
        $this->assertSame(30, $regles[0]->minutes, 'valeur historique intacte');

        $cache = StatistiqueMensuelle::where('user_id', $salarie->id)->orderBy('mois')->get();
        $this->assertSame('60.00', $cache[0]->minutes_prestations_facturables, 'mars : barème 2 × 30 min');
        $this->assertSame('90.00', $cache[1]->minutes_prestations_facturables, 'avril : barème 2 × 45 min');

        $this->getJson('/api/admin/tarifs')->assertJsonPath('2.minutes_en_vigueur', 45)->assertJsonCount(2, '2.regles');
    }

    #[Test]
    public function une_date_anterieure_a_la_regle_en_vigueur_ou_des_minutes_nulles_sont_refusees(): void
    {
        $this->postJson("/api/admin/tarifs/{$this->tarif->id}/regles", ['minutes' => 45, 'date_debut' => '2019-06-01'])
            ->assertUnprocessable()->assertJsonValidationErrors('date_debut');

        // Règle du référentiel ouverte au 2020-01-01 : une date égale ou antérieure est incohérente (vérifiée par le service).
        $this->postJson("/api/admin/tarifs/{$this->tarif->id}/regles", ['minutes' => 45, 'date_debut' => '2020-01-01'])
            ->assertUnprocessable()->assertJsonValidationErrors('date_debut');

        $this->postJson("/api/admin/tarifs/{$this->tarif->id}/regles", ['minutes' => 0, 'date_debut' => '2026-04-01'])
            ->assertUnprocessable()->assertJsonValidationErrors('minutes');

        $this->postJson("/api/admin/tarifs/{$this->tarif->id}/regles", ['minutes' => 'trente', 'date_debut' => '2026-04-01'])
            ->assertUnprocessable()->assertJsonValidationErrors('minutes');

        $this->postJson('/api/admin/tarifs/999999/regles', ['minutes' => 45, 'date_debut' => '2026-04-01'])->assertNotFound();

        $this->assertSame(1, RegleConversion::where('tarif_id', $this->tarif->id)->count(), 'aucune règle créée');
    }
}
