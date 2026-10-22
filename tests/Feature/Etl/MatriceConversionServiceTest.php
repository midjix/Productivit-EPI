<?php

namespace Tests\Feature\Etl;

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
use App\Services\MatriceConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Cas d'utilisation « Gérer la matrice de conversion » : clôture + création + recalcul, en transaction. */
class MatriceConversionServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tarif $tarif;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tarif = Tarif::factory()->create(['code' => '8.1']);
        RegleConversion::factory()->create(['tarif_id' => $this->tarif->id, 'minutes' => 60, 'date_debut_validite' => '2020-01-01']);
    }

    #[Test]
    public function cloture_l_ancienne_regle_et_cree_la_nouvelle(): void
    {
        $admin = User::factory()->administrateur()->create();

        $nouvelle = app(MatriceConversionService::class)
            ->changerBareme($this->tarif, 45, now()->parse('2026-07-01'), $admin, 'Décision CA de juin 2026');

        $this->tarif->refresh();
        $this->assertSame(2, $this->tarif->reglesConversion()->count());
        $this->assertSame('2026-06-30', $this->tarif->reglesConversion()->where('minutes', 60)->first()->date_fin_validite->toDateString());
        $this->assertTrue($nouvelle->estOuverte());
        $this->assertSame($admin->id, $nouvelle->created_by);
        $this->assertSame(60, $this->tarif->regleEnVigueurLe('2026-06-30')->minutes);
        $this->assertSame(45, $this->tarif->regleEnVigueurLe('2026-07-01')->minutes);
    }

    #[Test]
    public function refuse_un_chevauchement_et_une_valeur_nulle(): void
    {
        $service = app(MatriceConversionService::class);

        try {
            $service->changerBareme($this->tarif, 45, now()->parse('2019-06-01'));
            $this->fail('un chevauchement doit être refusé');
        } catch (InvalidArgumentException) {
        }

        $this->expectException(InvalidArgumentException::class);
        $service->changerBareme($this->tarif, 0, now()->parse('2026-07-01'));
    }

    #[Test]
    public function un_changement_de_bareme_recalcule_les_mois_concernes_sans_toucher_au_passe(): void
    {
        $user = User::factory()->create();
        $type = CategorieSuivi::factory()->create(['tarif_id' => $this->tarif->id]);
        $normal = CompteDsk::factory()->categorie(CategorieHeure::Normal)->create();

        foreach (['2026-06-15', '2026-07-15'] as $date) {
            DonneeDsk::factory()->create(['user_id' => $user->id, 'compte_dsk_id' => $normal->id, 'date' => $date, 'minutes' => 600]);
            DonneeEpilogg::factory()->create(['user_id' => $user->id, 'categorie_suivi_id' => $type->id, 'date' => $date, 'quantite' => 2]);
        }
        app(ConsolidationService::class)->recalculerPeriode(now()->parse('2026-06-01'), now()->parse('2026-07-31'));

        $this->assertSame('120.00', (string) StatistiqueMensuelle::where('mois', 7)->first()->minutes_prestations_facturables);

        app(MatriceConversionService::class)->changerBareme($this->tarif, 45, now()->parse('2026-07-01'));

        $this->assertSame('120.00', (string) StatistiqueMensuelle::where('mois', 6)->first()->minutes_prestations_facturables, 'juin garde le barème de juin');
        $this->assertSame('90.00', (string) StatistiqueMensuelle::where('mois', 7)->first()->minutes_prestations_facturables, 'juillet est recalculé à 45 min');
    }
}
