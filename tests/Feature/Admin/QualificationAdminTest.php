<?php

namespace Tests\Feature\Admin;

use App\Enums\CategorieHeure;
use App\Models\CategorieSuivi;
use App\Models\CompteDsk;
use App\Models\DonneeDsk;
use App\Models\DonneeEpilogg;
use App\Models\StatistiqueMensuelle;
use App\Models\Tarif;
use App\Models\User;
use App\Services\ConsolidationService;
use Database\Seeders\ReferentielSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cas d'utilisation « Qualifier les compteurs DSK » et « Qualifier les types de suivi » :
 * la qualification est appliquée ET le cache des mois concernés est recalculé
 * (file d'attente synchrone en test).
 */
class QualificationAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $salarie;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferentielSeeder::class);
        Sanctum::actingAs(User::factory()->administrateur()->create());
        $this->salarie = User::factory()->create();
    }

    #[Test]
    public function qualifier_un_compteur_dsk_recalcule_les_mois_ou_il_apparait(): void
    {
        $normales = CompteDsk::factory()->categorie(CategorieHeure::Normal)->create(['no_account' => 10]);
        $nouveau = CompteDsk::factory()->create(['no_account' => 30, 'libelle' => 'Maladie']); // importé : « autre », hors total

        DonneeDsk::factory()->create(['user_id' => $this->salarie->id,'compte_dsk_id' => $normales->id, 'date' => '2026-03-02', 'minutes' => 480]);
        DonneeDsk::factory()->create(['user_id' => $this->salarie->id,'compte_dsk_id' => $nouveau->id, 'date' => '2026-03-03', 'minutes' => 480]);
        DonneeDsk::factory()->create(['user_id' => $this->salarie->id,'compte_dsk_id' => $normales->id, 'date' => '2026-05-04', 'minutes' => 480]);
        app(ConsolidationService::class)->recalculerPeriode(now()->parse('2026-03-01'), now()->parse('2026-05-31'));

        $this->assertSame(480, StatistiqueMensuelle::where('mois', 3)->first()->minutes_total, 'avant : compteur hors total');

        $this->patchJson("/api/admin/comptes-dsk/{$nouveau->id}", ['categorie' => 'maladie', 'compte_effectif' => true])
            ->assertOk()
            ->assertJsonPath('compte.categorie', 'maladie')
            ->assertJsonPath('compte.compte_effectif', true)
            ->assertJsonPath('compte.nb_lignes', 1)
            ->assertJsonPath('recalcul.du', '2026-03-01')
            ->assertJsonPath('recalcul.au', '2026-03-31');

        $mars = StatistiqueMensuelle::where('mois', 3)->first();
        $this->assertSame(960, $mars->minutes_total, 'après : le compteur entre dans le total');
        $this->assertSame(480, $mars->minutes_maladie);
        $this->assertSame(480, $mars->minutes_dispo_epi, 'la maladie est déduite de la dispo EPI');

        $this->getJson('/api/admin/comptes-dsk')->assertOk()->assertJsonCount(2)->assertJsonPath('0.no_account', 10);
    }

    #[Test]
    public function qualifier_un_type_de_suivi_valorise_ses_prestations_dans_le_cache(): void
    {
        $normales = CompteDsk::factory()->categorie(CategorieHeure::Normal)->create();
        DonneeDsk::factory()->create(['user_id' => $this->salarie->id,'compte_dsk_id' => $normales->id, 'date' => '2026-03-02', 'minutes' => 480]);

        $type = CategorieSuivi::factory()->nonQualifie()->create(['libelle' => 'Atelier parentalité', 'service_id' => null]);
        DonneeEpilogg::factory()->create(['user_id' => $this->salarie->id,'categorie_suivi_id' => $type->id, 'date' => '2026-03-02', 'quantite' => 2]);
        app(ConsolidationService::class)->recalculerMois($this->salarie->id, 2026, 3);

        $this->assertSame('0.00', StatistiqueMensuelle::first()->minutes_prestations_facturables, 'non qualifié : exclu du calcul');
        $this->getJson('/api/admin/types-suivi?non_qualifies=1')->assertOk()->assertJsonCount(1)->assertJsonPath('0.qualifie', false)->assertJsonPath('0.nb_prestations', 1);

        $tarif = Tarif::where('code', '8.1')->first();

        $this->patchJson("/api/admin/types-suivi/{$type->id}", ['tarif_id' => $tarif->id])
            ->assertOk()
            ->assertJsonPath('type.qualifie', true)
            ->assertJsonPath('type.tarif.code', '8.1')
            ->assertJsonPath('recalcul.du', '2026-03-01');

        $this->assertSame('120.00', StatistiqueMensuelle::first()->minutes_prestations_facturables, '2 forfaits × 60 min');
        $this->getJson('/api/admin/types-suivi?non_qualifies=1')->assertOk()->assertJsonCount(0);
    }

    #[Test]
    public function une_qualification_invalide_est_rejetee_sans_recalcul(): void
    {
        $compte = CompteDsk::factory()->create();
        $type = CategorieSuivi::factory()->nonQualifie()->create();

        $this->patchJson("/api/admin/comptes-dsk/{$compte->id}", ['categorie' => 'vacances'])->assertUnprocessable()->assertJsonValidationErrors('categorie');
        $this->patchJson("/api/admin/comptes-dsk/{$compte->id}", [])->assertUnprocessable();
        $this->patchJson("/api/admin/types-suivi/{$type->id}", ['tarif_id' => 999999])->assertUnprocessable()->assertJsonValidationErrors('tarif_id');
        $this->patchJson("/api/admin/types-suivi/{$type->id}", ['tarif_id' => 'abc'])->assertUnprocessable();
        $this->patchJson('/api/admin/comptes-dsk/999999', ['categorie' => 'maladie'])->assertNotFound();

        $this->assertNull($type->fresh()->tarif_id);
        $this->assertSame(CategorieHeure::Autre, $compte->fresh()->categorie);
    }

    #[Test]
    public function qualifier_un_compteur_sans_donnees_ne_planifie_aucun_recalcul(): void
    {
        $compte = CompteDsk::factory()->create();

        $this->patchJson("/api/admin/comptes-dsk/{$compte->id}", ['actif' => false])
            ->assertOk()
            ->assertJsonPath('compte.actif', false)
            ->assertJsonPath('recalcul', null);
    }
}
