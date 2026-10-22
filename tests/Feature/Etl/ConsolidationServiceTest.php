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
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Du flux brut au cache mensuel : catégories DSK, barème à la date de chaque prestation, cascade. */
class ConsolidationServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tarif $t91;

    private Tarif $interne;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->t91 = Tarif::factory()->create(['code' => '9.1']);
        $this->interne = Tarif::factory()->interne()->create();

        RegleConversion::factory()->close('2020-01-01', '2025-12-31')->create(['tarif_id' => $this->t91->id, 'minutes' => 30]);
        RegleConversion::factory()->create(['tarif_id' => $this->t91->id, 'minutes' => 45, 'date_debut_validite' => '2026-01-01']);
        RegleConversion::factory()->create(['tarif_id' => $this->interne->id, 'minutes' => 60]);
    }

    #[Test]
    public function reproduit_le_jeu_d_essai_de_la_fondation_depuis_les_flux_bruts(): void
    {
        $comptes = [
            [CategorieHeure::Normal, 100], [CategorieHeure::Conge, 16], [CategorieHeure::Maladie, 6],
            [CategorieHeure::Formation, 4], [CategorieHeure::Supervision, 2],
        ];
        foreach ($comptes as [$categorie, $heures]) {
            $compte = CompteDsk::factory()->categorie($categorie)->create();
            DonneeDsk::factory()->create(['user_id' => $this->user->id, 'compte_dsk_id' => $compte->id, 'date' => '2026-04-10', 'minutes' => $heures * 60]);
        }
        // Un compteur technique hors total ne doit pas compter.
        $technique = CompteDsk::factory()->categorie(CategorieHeure::Autre, effectif: false)->create();
        DonneeDsk::factory()->create(['user_id' => $this->user->id, 'compte_dsk_id' => $technique->id, 'date' => '2026-04-11', 'minutes' => 9999]);

        $psy = CategorieSuivi::factory()->create(['tarif_id' => $this->t91->id]);
        $comite = CategorieSuivi::factory()->create(['tarif_id' => $this->interne->id]);
        // 82 h facturables en 2026 à 45 min/forfait = 109,33 forfaits → on prend 4920 min exactement : 109,33… non entier,
        // donc on compose : 100 forfaits × 45 = 4500 + 9,33… → on utilise 108 × 45 = 4860 + 1,33 × 45 = 60 → 4920.
        DonneeEpilogg::factory()->create(['user_id' => $this->user->id, 'categorie_suivi_id' => $psy->id, 'date' => '2026-04-03', 'quantite' => 108]);
        DonneeEpilogg::factory()->create(['user_id' => $this->user->id, 'categorie_suivi_id' => $psy->id, 'date' => '2026-04-04', 'quantite' => 1.33]);
        DonneeEpilogg::factory()->create(['user_id' => $this->user->id, 'categorie_suivi_id' => $comite->id, 'date' => '2026-04-05', 'quantite' => 2]);

        $stat = app(ConsolidationService::class)->recalculerMois($this->user->id, 2026, 4);

        $this->assertSame(128 * 60, $stat->minutes_total);
        $this->assertSame(106 * 60, $stat->minutes_dispo_epi);
        $this->assertSame('4919.85', (string) $stat->minutes_prestations_facturables, '108 × 45 + 1,33 × 45 = 4 860 + 59,85');
        $this->assertSame('120.00', (string) $stat->minutes_activite_interne);
        $this->assertSame('5880.00', (string) $stat->minutes_dispo_client);
        $this->assertSame('0.8367', (string) $stat->ratio, '4919,85 / 5880 = 0,8367 — identique au fichier de la Fondation');
        $this->assertSame('111.33', (string) $stat->nb_prestations);
    }

    #[Test]
    public function applique_le_bareme_en_vigueur_a_la_date_de_chaque_prestation(): void
    {
        $psy = CategorieSuivi::factory()->create(['tarif_id' => $this->t91->id]);
        $normal = CompteDsk::factory()->categorie(CategorieHeure::Normal)->create();

        foreach (['2025-12-15', '2026-01-15'] as $date) {
            DonneeDsk::factory()->create(['user_id' => $this->user->id, 'compte_dsk_id' => $normal->id, 'date' => $date, 'minutes' => 600]);
            DonneeEpilogg::factory()->create(['user_id' => $this->user->id, 'categorie_suivi_id' => $psy->id, 'date' => $date, 'quantite' => 2]);
        }

        $service = app(ConsolidationService::class);
        $dec = $service->recalculerMois($this->user->id, 2025, 12);
        $jan = $service->recalculerMois($this->user->id, 2026, 1);

        $this->assertSame('60.00', (string) $dec->minutes_prestations_facturables, '2 forfaits × 30 min (barème 2025)');
        $this->assertSame('90.00', (string) $jan->minutes_prestations_facturables, '2 forfaits × 45 min (barème 2026)');
        $this->assertSame('0.1000', (string) $dec->ratio);
        $this->assertSame('0.1500', (string) $jan->ratio);
    }

    #[Test]
    public function ignore_les_types_non_qualifies_et_les_prestations_sans_bareme(): void
    {
        $nonQualifie = CategorieSuivi::factory()->nonQualifie()->create();
        $psy = CategorieSuivi::factory()->create(['tarif_id' => $this->t91->id]);
        $normal = CompteDsk::factory()->categorie(CategorieHeure::Normal)->create();

        DonneeDsk::factory()->create(['user_id' => $this->user->id, 'compte_dsk_id' => $normal->id, 'date' => '2019-06-10', 'minutes' => 480]);
        DonneeEpilogg::factory()->create(['user_id' => $this->user->id, 'categorie_suivi_id' => $psy->id, 'date' => '2019-06-10', 'quantite' => 3]);
        DonneeEpilogg::factory()->create(['user_id' => $this->user->id, 'categorie_suivi_id' => $nonQualifie->id, 'date' => '2019-06-11', 'quantite' => 3]);

        $stat = app(ConsolidationService::class)->recalculerMois($this->user->id, 2019, 6);

        $this->assertSame('0.00', (string) $stat->minutes_prestations_facturables, 'aucun barème avant 2020 : rien n\'est inventé');
        $this->assertSame('0.0000', (string) $stat->ratio);
    }

    #[Test]
    public function recalculer_periode_couvre_tous_les_mois_touches_et_est_idempotent(): void
    {
        $psy = CategorieSuivi::factory()->create(['tarif_id' => $this->t91->id]);
        $autre = User::factory()->create();

        foreach ([[$this->user, '2026-02-10'], [$this->user, '2026-03-10'], [$autre, '2026-03-20']] as [$u, $d]) {
            DonneeEpilogg::factory()->create(['user_id' => $u->id, 'categorie_suivi_id' => $psy->id, 'date' => $d, 'quantite' => 1]);
        }

        $service = app(ConsolidationService::class);
        $this->assertSame(3, $service->recalculerPeriode(now()->parse('2026-01-01'), now()->parse('2026-12-31')));
        $this->assertSame(3, $service->recalculerPeriode(now()->parse('2026-01-01'), now()->parse('2026-12-31')));
        $this->assertSame(3, StatistiqueMensuelle::count(), 'une ligne par (salarié, mois), jamais de doublon');
    }
}
