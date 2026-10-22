<?php

namespace Tests\Feature\Persistance;

use App\Models\CategorieSuivi;
use App\Models\RegleConversion;
use App\Models\Service;
use App\Models\StatistiqueMensuelle;
use App\Models\Tarif;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\ReferentielSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SeedersTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function le_referentiel_charge_le_bareme_valide_avec_la_fondation(): void
    {
        $this->seed(ReferentielSeeder::class);

        $this->assertSame(60, Tarif::where('code', '8.1')->first()->regleEnVigueurLe('2026-04-01')->minutes);
        $this->assertSame(60, Tarif::where('code', '8.2')->first()->regleEnVigueurLe('2026-04-01')->minutes);
        $this->assertSame(30, Tarif::where('code', '9.1')->first()->regleEnVigueurLe('2026-04-01')->minutes);
        $this->assertSame(60, Tarif::where('code', Tarif::CODE_AUTRE)->first()->regleEnVigueurLe('2026-04-01')->minutes);
        $this->assertFalse(Tarif::where('code', Tarif::CODE_AUTRE)->first()->facturable);
        $this->assertSame(5, Service::count());
    }

    #[Test]
    public function le_referentiel_est_idempotent_et_ne_reecrit_pas_un_bareme_existant(): void
    {
        $this->seed(ReferentielSeeder::class);

        // L'Administrateur a fait évoluer le barème 9.1 : clôture + nouvelle règle.
        $tarif91 = Tarif::where('code', '9.1')->first();
        $tarif91->reglesConversion()->ouvertes()->update(['date_fin_validite' => '2026-12-31']);
        RegleConversion::create(['tarif_id' => $tarif91->id, 'minutes' => 45, 'date_debut_validite' => '2027-01-01']);

        $this->seed(ReferentielSeeder::class);

        $this->assertSame(4, Tarif::count());
        $this->assertSame(5, Service::count());
        $this->assertSame(5, RegleConversion::count(), 'aucune règle ajoutée ni supprimée au second passage');
        $this->assertSame(45, $tarif91->regleEnVigueurLe('2027-03-01')->minutes, 'l\'historique de l\'Administrateur est intact');
    }

    #[Test]
    public function la_demo_couvre_les_cas_limites_et_laisse_le_cache_vide(): void
    {
        $this->seed([ReferentielSeeder::class, DemoSeeder::class]);

        $this->assertSame(1, User::where('role', 'administrateur')->count());
        $this->assertSame(4, User::where('role', 'coordinateur')->count());
        $this->assertTrue(User::where('actif', false)->whereNotNull('date_sortie')->exists(), 'un salarié parti');
        $this->assertTrue(User::where('actif', true)->whereNull('epilogg_user_id')->exists(), 'un salarié sans correspondance EpiLogg');
        $this->assertSame(1, CategorieSuivi::nonQualifies()->count(), 'un type de suivi non qualifié');
        $this->assertSame(0, StatistiqueMensuelle::count(), 'le cache est réservé à l\'EtlService');
    }
}
