<?php

namespace Tests\Feature\Api;

use App\Enums\CategorieHeure;
use App\Models\CategorieSuivi;
use App\Models\CompteDsk;
use App\Models\DonneeDsk;
use App\Models\DonneeEpilogg;
use App\Models\Service;
use App\Models\Tarif;
use App\Models\User;
use Database\Seeders\ReferentielSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Écran « Détail » (niveau de détail du fichier « Données app EPI.xlsx », exemple 1) :
 * calcul depuis les flux bruts sur une période libre, détail par type de suivi / tarif /
 * service / type de prestation / compteur DSK, filtres, périmètres et droits.
 */
class DetailTest extends TestCase
{
    use RefreshDatabase;

    private Service $aisp;

    private User $sandy;

    private CategorieSuivi $typeAisp;

    private CategorieSuivi $typeInterC;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferentielSeeder::class);

        $this->aisp = Service::where('code', 'AISP')->first();
        $this->sandy = User::factory()->create(['name' => 'Sandy EXEMPLE', 'service_id' => $this->aisp->id]);

        // Compteurs DSK d'avril 2026 (exemple 1) : 100 h normales, 12 h congés, 4 h récup, 6 h maladie, 4 h formation, 2 h supervision.
        $comptes = [
            [10, 'Heures normales', CategorieHeure::Normal, 100 * 60],
            [20, 'Congés annuels', CategorieHeure::Conge, 12 * 60],
            [22, 'Récupération de solde', CategorieHeure::Conge, 4 * 60],
            [30, 'Maladie', CategorieHeure::Maladie, 6 * 60],
            [41, 'Congé formation ministère', CategorieHeure::Formation, 4 * 60],
            [50, 'Supervision individuelle', CategorieHeure::Supervision, 2 * 60],
        ];
        foreach ($comptes as $i => [$no, $libelle, $categorie, $minutes]) {
            $compte = CompteDsk::factory()->categorie($categorie)->create(['no_account' => $no, 'libelle' => $libelle]);
            DonneeDsk::factory()->create(['user_id' => $this->sandy->id, 'compte_dsk_id' => $compte->id, 'date' => sprintf('2026-04-%02d', $i + 1), 'minutes' => $minutes]);
        }
        // Hors période : mai.
        DonneeDsk::factory()->create(['user_id' => $this->sandy->id, 'compte_dsk_id' => CompteDsk::first()->id, 'date' => '2026-05-04', 'minutes' => 480]);

        $t81 = Tarif::where('code', '8.1')->first();
        $autre = Tarif::where('code', Tarif::CODE_AUTRE)->first();
        $this->typeAisp = CategorieSuivi::factory()->create(['libelle' => '8.1 AISP', 'tarif_id' => $t81->id, 'service_id' => $this->aisp->id]);
        $this->typeInterC = CategorieSuivi::factory()->create(['libelle' => '8.1 AISP (Inter-C)', 'tarif_id' => $t81->id, 'service_id' => $this->aisp->id]);
        $permis = CategorieSuivi::factory()->create(['libelle' => 'Permis de conduire', 'tarif_id' => $autre->id, 'service_id' => null]);

        // 60 forfaits AISP (entretiens) + 22 Inter-C (dont 10 déplacements) = 82 h facturables ; 2 forfaits permis = 2 h internes.
        DonneeEpilogg::factory()->create(['user_id' => $this->sandy->id, 'categorie_suivi_id' => $this->typeAisp->id, 'date' => '2026-04-07', 'quantite' => 60, 'type_prestation' => 'Entretien']);
        DonneeEpilogg::factory()->create(['user_id' => $this->sandy->id, 'categorie_suivi_id' => $this->typeInterC->id, 'date' => '2026-04-08', 'quantite' => 12, 'type_prestation' => 'Entretien']);
        DonneeEpilogg::factory()->create(['user_id' => $this->sandy->id, 'categorie_suivi_id' => $this->typeInterC->id, 'date' => '2026-04-09', 'quantite' => 10, 'type_prestation' => 'Déplacement']);
        DonneeEpilogg::factory()->create(['user_id' => $this->sandy->id, 'categorie_suivi_id' => $permis->id, 'date' => '2026-04-10', 'quantite' => 2, 'type_prestation' => 'Entretien']);
    }

    #[Test]
    public function un_salarie_obtient_le_detail_de_l_exemple_1_sur_une_periode_libre(): void
    {
        Sanctum::actingAs($this->sandy);

        $r = $this->getJson("/api/statistiques/detail?perimetre=salarie&id={$this->sandy->id}&du=2026-04-01&au=2026-04-30")
            ->assertOk()
            ->assertJsonPath('perimetre.libelle', 'Sandy EXEMPLE')
            ->assertJsonPath('synthese.minutes_total', 128 * 60)
            ->assertJsonPath('synthese.minutes_conge', 16 * 60)
            ->assertJsonPath('synthese.minutes_maladie', 6 * 60)
            ->assertJsonPath('synthese.minutes_dispo_epi', 106 * 60)
            ->assertJsonPath('synthese.minutes_formation', 4 * 60)
            ->assertJsonPath('synthese.minutes_supervision', 2 * 60)
            ->assertJsonPath('synthese.minutes_activite_interne', '120.00')
            ->assertJsonPath('synthese.minutes_dispo_client', '5880.00')
            ->assertJsonPath('synthese.minutes_prestations_facturables', '4920.00')
            ->assertJsonPath('synthese.ratio', '0.8367')
            ->assertJsonPath('synthese.objectif_atteint', true)
            ->assertJsonPath('synthese.nb_salaries', 1)
            ->assertJsonCount(6, 'comptes_dsk')
            ->assertJsonPath('comptes_dsk.0.no_account', 10)
            ->assertJsonPath('comptes_dsk.0.minutes', 6000)
            ->assertJsonCount(3, 'types_suivi')
            ->assertJsonPath('types_suivi.0.libelle', '8.1 AISP')
            ->assertJsonPath('types_suivi.0.minutes', '3600.00')
            ->assertJsonPath('types_suivi.1.nb_forfaits', '22.00')
            ->assertJsonCount(2, 'tarifs')
            ->assertJsonPath('tarifs.0.code', '8.1')
            ->assertJsonPath('tarifs.0.minutes', '4920.00')
            ->assertJsonPath('tarifs.1.code', 'AUTRE')
            ->assertJsonPath('tarifs.1.minutes', '120.00')
            ->assertJsonCount(2, 'types_prestation')
            ->assertJsonPath('types_prestation.0.libelle', 'Déplacement')
            ->assertJsonPath('types_prestation.0.nb_forfaits', '10.00');

        $this->assertSame(['AISP', null], array_column($r->json('services'), 'code'));
    }

    #[Test]
    public function les_filtres_ne_portent_que_sur_les_prestations(): void
    {
        Sanctum::actingAs($this->sandy);
        $base = "/api/statistiques/detail?perimetre=salarie&id={$this->sandy->id}&du=2026-04-01&au=2026-04-30";

        // Un seul type de suivi : les heures DSK restent, seules les prestations changent.
        $this->getJson("{$base}&types_suivi[]={$this->typeAisp->id}")
            ->assertOk()
            ->assertJsonPath('synthese.minutes_total', 128 * 60)
            ->assertJsonPath('synthese.minutes_prestations_facturables', '3600.00')
            ->assertJsonPath('synthese.minutes_activite_interne', '0.00')
            ->assertJsonCount(1, 'types_suivi');

        $this->getJson("{$base}&types_prestation[]=Déplacement")
            ->assertOk()
            ->assertJsonPath('synthese.minutes_prestations_facturables', '600.00')
            ->assertJsonPath('synthese.nb_prestations', '10.00');

        // Période réduite : plus aucune prestation ni heure.
        $this->getJson("/api/statistiques/detail?perimetre=salarie&id={$this->sandy->id}&du=2026-04-20&au=2026-04-25")
            ->assertOk()
            ->assertJsonPath('synthese.minutes_total', 0)
            ->assertJsonPath('synthese.ratio', null);
    }

    #[Test]
    public function les_perimetres_respectent_les_roles(): void
    {
        $autreService = Service::where('code', 'PSY')->first();
        $coordAisp = User::factory()->coordinateur()->create(['service_id' => $this->aisp->id]);
        $coordPsy = User::factory()->coordinateur()->create(['service_id' => $autreService->id]);
        $admin = User::factory()->administrateur()->create();
        $periode = 'du=2026-04-01&au=2026-04-30';

        Sanctum::actingAs($this->sandy);
        $this->getJson("/api/statistiques/detail?perimetre=service&id={$this->aisp->id}&{$periode}")->assertForbidden();
        $this->getJson("/api/statistiques/detail?perimetre=epi&{$periode}")->assertForbidden();
        $this->getJson('/api/statistiques/filtres')->assertOk()->assertJsonPath('perimetres', ['salarie'])->assertJsonPath('tarifs', []);

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($coordAisp);
        $this->getJson("/api/statistiques/detail?perimetre=service&id={$this->aisp->id}&{$periode}")->assertOk()->assertJsonPath('synthese.ratio', '0.8367');
        $this->getJson("/api/statistiques/detail?perimetre=tarif&id=1&{$periode}")->assertForbidden();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($coordPsy);
        $this->getJson("/api/statistiques/detail?perimetre=service&id={$this->aisp->id}&{$periode}")->assertForbidden();
        $this->getJson("/api/statistiques/detail?perimetre=salarie&id={$this->sandy->id}&{$periode}")->assertForbidden();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($admin);
        $t81 = Tarif::where('code', '8.1')->first();
        $this->getJson("/api/statistiques/detail?perimetre=epi&{$periode}")->assertOk()->assertJsonPath('perimetre.libelle', 'Total EPI')->assertJsonPath('synthese.nb_salaries', 1);
        // Tarif 8.1 : seules les prestations 8.1 ; l'activité interne n'entre pas dans la cascade (exemple 3 du fichier).
        $this->getJson("/api/statistiques/detail?perimetre=tarif&id={$t81->id}&{$periode}")->assertOk()
            ->assertJsonPath('perimetre.libelle', 'Tarif 8.1')
            ->assertJsonPath('synthese.minutes_activite_interne', '0.00')
            ->assertJsonPath('synthese.minutes_dispo_client', '6000.00')
            ->assertJsonPath('synthese.ratio', '0.8200');
        $this->getJson("/api/statistiques/detail?perimetre=interne&{$periode}")->assertOk()
            ->assertJsonPath('synthese.minutes_activite_interne', '120.00')
            ->assertJsonPath('synthese.minutes_prestations_facturables', '0.00');
        $this->getJson('/api/statistiques/filtres')->assertOk()
            ->assertJsonPath('perimetres', ['salarie', 'service', 'tarif', 'epi', 'interne'])
            ->assertJsonPath('types_prestation', ['Déplacement', 'Entretien'])
            ->assertJsonCount(3, 'types_suivi');
    }

    #[Test]
    public function les_entrees_invalides_sont_rejetees(): void
    {
        Sanctum::actingAs($this->sandy);
        $id = $this->sandy->id;

        $this->getJson("/api/statistiques/detail?perimetre=salarie&id={$id}&du=2026-04-30&au=2026-04-01")->assertUnprocessable()->assertJsonValidationErrors('au');
        $this->getJson("/api/statistiques/detail?perimetre=salarie&id={$id}&du=2024-01-01&au=2026-04-01")->assertUnprocessable()->assertJsonValidationErrors('au');
        $this->getJson("/api/statistiques/detail?perimetre=planete&id={$id}&du=2026-04-01&au=2026-04-30")->assertUnprocessable()->assertJsonValidationErrors('perimetre');
        $this->getJson("/api/statistiques/detail?perimetre=salarie&du=2026-04-01&au=2026-04-30")->assertUnprocessable()->assertJsonValidationErrors('id');
        $this->getJson("/api/statistiques/detail?perimetre=salarie&id={$id}&du=2026-04-01&au=2026-04-30&types_suivi[]=999999")->assertUnprocessable();
        $this->getJson("/api/statistiques/detail?perimetre=salarie&id=999999&du=2026-04-01&au=2026-04-30")->assertNotFound();
    }
}
