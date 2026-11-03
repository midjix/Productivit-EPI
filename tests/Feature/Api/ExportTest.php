<?php

namespace Tests\Feature\Api;

use App\Enums\CategorieHeure;
use App\Enums\Role;
use App\Models\CategorieSuivi;
use App\Models\CompteDsk;
use App\Models\DonneeDsk;
use App\Models\DonneeEpilogg;
use App\Models\Service;
use App\Models\StatistiqueMensuelle;
use App\Models\Tarif;
use App\Models\User;
use Database\Seeders\ReferentielSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use OpenSpout\Reader\XLSX\Reader;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Export Excel de la vue courante : le classeur reprend les chiffres de la vue JSON
 * (relu avec OpenSpout), dans la langue demandée, avec les droits de la vue.
 */
class ExportTest extends TestCase
{
    use RefreshDatabase;

    private const MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    #[Test]
    public function un_salarie_exporte_son_annee_avec_la_synthese_et_les_mois(): void
    {
        $salarie = User::factory()->create(['name' => 'Sandy EXEMPLE']);
        StatistiqueMensuelle::factory()->exempleFondation()->periode(2026, 4)->create(['user_id' => $salarie->id]);
        StatistiqueMensuelle::factory()->vide()->periode(2026, 5)->create(['user_id' => $salarie->id]);
        Sanctum::actingAs($salarie);

        $reponse = $this->get('/api/exports/statistiques/me?annee=2026', ['Accept-Language' => 'fr'])
            ->assertOk()
            ->assertHeader('Content-Type', self::MIME);
        $this->assertStringContainsString('efficacite-sandy-exemple-2026.xlsx', $reponse->headers->get('Content-Disposition'));

        $feuilles = $this->lire($reponse);
        $this->assertSame(['Synthèse', 'Mois'], array_keys($feuilles));

        $synthese = $this->parLibelle($feuilles['Synthèse']);
        $this->assertSame('Sandy EXEMPLE', $synthese['Périmètre'][1]);
        $this->assertSame('2026', (string) $synthese['Période'][1]);
        $this->assertEquals(128.0, $synthese['Total heures dispo'][1]);
        $this->assertEquals(106.0, $synthese['Heures dispo EPI'][1]);
        $this->assertEquals(98.0, $synthese['Heures dispo client'][1]);
        $this->assertEquals(82.0, $synthese['Prestations facturables'][1]);
        $this->assertEquals(0.8367, $synthese['Efficacité'][1]);
        $this->assertSame('Oui', $synthese['Objectif atteint'][1]);
        $this->assertSame('Total − congés − maladie', $synthese['Heures dispo EPI'][2]);

        $mois = $feuilles['Mois'];
        $this->assertSame('Mois', $mois[0][0]);
        $this->assertCount(3, $mois, 'en-tête + deux mois');
        $this->assertSame('Avril 2026', $mois[1][0]);
        $this->assertEquals(128.0, $mois[1][1]);
        $this->assertEquals(0.8367, $mois[1][11]);
        $this->assertSame('Mai 2026', $mois[2][0]);
        $this->assertSame('Non calculable', $mois[2][12]);
    }

    #[Test]
    public function les_droits_sont_ceux_de_la_vue_et_la_langue_suit_la_requete(): void
    {
        $this->seed(ReferentielSeeder::class);
        $aisp = Service::where('code', 'AISP')->first();
        $psy = Service::where('code', 'PSY')->first();
        $coordinateur = User::factory()->create(['role' => Role::Coordinateur, 'service_id' => $aisp->id]);
        $collegue = User::factory()->create(['service_id' => $aisp->id, 'name' => 'Collègue AISP']);
        StatistiqueMensuelle::factory()->exempleFondation()->periode(2026, 4)->create(['user_id' => $collegue->id]);
        Sanctum::actingAs($coordinateur);

        $this->get("/api/exports/statistiques/services/{$psy->id}?annee=2026")->assertForbidden();
        $this->get('/api/exports/statistiques/globales?annee=2026')->assertForbidden();
        $this->get('/api/exports/statistiques/detail?perimetre=epi&du=2026-04-01&au=2026-04-30')->assertForbidden();
        $this->get("/api/exports/statistiques/services/{$aisp->id}?annee=2026&mois=13")->assertUnprocessable();

        $reponse = $this->get("/api/exports/statistiques/services/{$aisp->id}?annee=2026&mois=4", ['Accept-Language' => 'en'])->assertOk();
        $this->assertStringContainsString('efficacite-aisp-2026-04.xlsx', $reponse->headers->get('Content-Disposition'));

        $feuilles = $this->lire($reponse);
        $this->assertSame(['Summary', 'Employees'], array_keys($feuilles));
        $this->assertSame('Collègue AISP', $feuilles['Employees'][1][0]);
        $this->assertEquals(0.8367, $feuilles['Employees'][1][12]);
        $this->assertSame('Yes', $feuilles['Employees'][1][13]);
        $this->assertSame('Target met', $this->parLibelle($feuilles['Summary'])['Target met'][0]);
    }

    #[Test]
    public function l_administrateur_exporte_le_detail_avec_une_feuille_par_tableau(): void
    {
        $this->seed(ReferentielSeeder::class);
        $admin = User::factory()->create(['role' => Role::Administrateur]);
        $aisp = Service::where('code', 'AISP')->first();
        $sandy = User::factory()->create(['name' => 'Sandy EXEMPLE', 'service_id' => $aisp->id]);
        $compte = CompteDsk::factory()->categorie(CategorieHeure::Normal)->create(['no_account' => 10, 'libelle' => 'Heures normales']);
        DonneeDsk::factory()->create(['user_id' => $sandy->id, 'compte_dsk_id' => $compte->id, 'date' => '2026-04-01', 'minutes' => 100 * 60]);
        $type = CategorieSuivi::factory()->create(['libelle' => '8.1 AISP', 'tarif_id' => Tarif::where('code', '8.1')->first()->id, 'service_id' => $aisp->id]);
        DonneeEpilogg::factory()->create(['user_id' => $sandy->id, 'categorie_suivi_id' => $type->id, 'date' => '2026-04-07', 'quantite' => 60, 'type_prestation' => 'Entretien']);
        Sanctum::actingAs($admin);

        $reponse = $this->get("/api/exports/statistiques/detail?perimetre=epi&du=2026-04-01&au=2026-04-30&types_suivi[]={$type->id}", ['Accept-Language' => 'fr'])
            ->assertOk()->assertHeader('Content-Type', self::MIME);
        $this->assertStringContainsString('detail-total-epi-2026-04-01-2026-04-30.xlsx', $reponse->headers->get('Content-Disposition'));

        $feuilles = $this->lire($reponse);
        $this->assertSame(['Synthèse', 'Types de suivi', 'Tarifs', 'Services', 'Types de prestation', 'Compteurs DSK'], array_keys($feuilles));

        $synthese = $this->parLibelle($feuilles['Synthèse']);
        $this->assertSame('Total EPI', $synthese['Périmètre'][1]);
        $this->assertSame('8.1 AISP', $synthese['Filtre types de suivi'][1]);
        $this->assertEquals(100.0, $synthese['Total heures dispo'][1]);
        $this->assertEquals(60.0, $synthese['Prestations facturables'][1]);
        $this->assertEquals(0.6, $synthese['Efficacité'][1]);

        $this->assertEquals(['8.1 AISP', '8.1', 'Oui', 'AISP', 60, 60], $feuilles['Types de suivi'][1]);
        $this->assertSame('8.1', $feuilles['Tarifs'][1][0]);
        $this->assertEquals(1.0, $feuilles['Tarifs'][1][5], 'part du facturable');
        $this->assertEquals([10, 'Heures normales', 'Heures normales', 'Oui', 100, 1], $feuilles['Compteurs DSK'][1]);
    }

    /**
     * Relit le classeur renvoyé : nom de feuille → lignes (valeurs brutes).
     *
     * @return array<string, list<list<mixed>>>
     */
    private function lire(TestResponse $reponse): array
    {
        $chemin = tempnam(sys_get_temp_dir(), 'epi-test-');
        file_put_contents($chemin, file_get_contents($reponse->getFile()->getPathname()));

        $reader = new Reader;
        $reader->open($chemin);
        $feuilles = [];
        foreach ($reader->getSheetIterator() as $feuille) {
            $lignes = [];
            foreach ($feuille->getRowIterator() as $ligne) {
                $lignes[] = $ligne->toArray();
            }
            $feuilles[$feuille->getName()] = $lignes;
        }
        $reader->close();
        unlink($chemin);

        return $feuilles;
    }

    /**
     * Lignes d'une feuille indexées par leur première colonne.
     *
     * @param  list<list<mixed>>  $lignes
     * @return array<string, list<mixed>>
     */
    private function parLibelle(array $lignes): array
    {
        $resultat = [];
        foreach ($lignes as $l) {
            if (isset($l[0]) && is_string($l[0]) && $l[0] !== '') {
                $resultat[$l[0]] = $l;
            }
        }

        return $resultat;
    }
}
