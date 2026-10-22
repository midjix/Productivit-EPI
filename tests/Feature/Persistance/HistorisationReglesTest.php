<?php

namespace Tests\Feature\Persistance;

use App\Models\CategorieSuivi;
use App\Models\RegleConversion;
use App\Models\Tarif;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Règle d'or du projet : le recalcul d'une période passée retrouve le barème
 * en vigueur à l'époque. Test d'intégration modèle <-> MariaDB (scope `enVigueurLe`).
 */
class HistorisationReglesTest extends TestCase
{
    use RefreshDatabase;

    private Tarif $tarif;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tarif = Tarif::factory()->create(['code' => '9.1']);
        RegleConversion::factory()->close('2020-01-01', '2025-12-31')->create(['tarif_id' => $this->tarif->id, 'minutes' => 30]);
        RegleConversion::factory()->create(['tarif_id' => $this->tarif->id, 'minutes' => 45, 'date_debut_validite' => '2026-01-01']);
    }

    #[Test]
    public function chaque_date_retombe_sur_le_bareme_de_son_epoque(): void
    {
        $this->assertSame(30, $this->tarif->regleEnVigueurLe('2025-12-31')->minutes);
        $this->assertSame(45, $this->tarif->regleEnVigueurLe('2026-01-01')->minutes);
        $this->assertSame(45, $this->tarif->regleEnVigueurLe('2027-05-10')->minutes);
        $this->assertNull($this->tarif->regleEnVigueurLe('2019-06-01'), 'avant toute règle : pas de barème');
    }

    #[Test]
    public function une_seule_regle_est_ouverte_par_tarif(): void
    {
        $this->assertSame(1, $this->tarif->reglesConversion()->ouvertes()->count());
        $this->assertSame(45, $this->tarif->reglesConversion()->ouvertes()->first()->minutes);
    }

    #[Test]
    public function le_type_de_suivi_delegue_le_bareme_a_son_tarif(): void
    {
        $type = CategorieSuivi::factory()->create(['tarif_id' => $this->tarif->id]);
        $autreType = CategorieSuivi::factory()->create(['tarif_id' => $this->tarif->id]);

        $this->assertSame(30, $type->regleEnVigueurLe('2025-03-01')->minutes);
        $this->assertSame(45, $autreType->regleEnVigueurLe('2026-03-01')->minutes, 'deux types partagent le même tarif');
    }

    #[Test]
    public function un_type_non_qualifie_n_a_pas_de_bareme(): void
    {
        $type = CategorieSuivi::factory()->nonQualifie()->create();

        $this->assertNull($type->regleEnVigueurLe('2026-03-01'));
        $this->assertFalse($type->estFacturable());
        $this->assertSame(1, CategorieSuivi::nonQualifies()->count());
    }

    #[Test]
    public function la_facturabilite_vient_du_tarif(): void
    {
        $interne = CategorieSuivi::factory()->create(['tarif_id' => Tarif::factory()->interne()->create()->id]);
        $facturable = CategorieSuivi::factory()->create(['tarif_id' => $this->tarif->id]);

        $this->assertFalse($interne->estFacturable());
        $this->assertTrue($facturable->estFacturable());
    }
}
