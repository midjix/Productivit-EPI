<?php

namespace Tests\Feature\Etl;

use App\Enums\CategorieHeure;
use App\Enums\StatutImport;
use App\Etl\Contracts\ConnecteurDsk;
use App\Etl\Contracts\ConnecteurEpilogg;
use App\Etl\Dto\CompteDskDto;
use App\Etl\Dto\DepartementDsk;
use App\Etl\Dto\MinutesCompteDsk;
use App\Etl\Dto\PrestationEpilogg;
use App\Etl\Dto\SalarieDsk;
use App\Etl\Dto\SalarieEpilogg;
use App\Etl\Dto\TypeSuiviEpilogg;
use App\Models\CategorieSuivi;
use App\Models\CompteDsk;
use App\Models\DonneeDsk;
use App\Models\DonneeEpilogg;
use App\Models\Import;
use App\Models\Service;
use App\Models\StatistiqueMensuelle;
use App\Models\User;
use App\Services\EtlService;
use Carbon\CarbonImmutable;
use Database\Seeders\ReferentielSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Support\DskEnMemoire;
use Tests\Support\EpiloggEnMemoire;
use Tests\TestCase;

/**
 * Cas d'utilisation « Importer les données » de bout en bout, sources simulées :
 * synchronisation des référentiels, appariement par email, fenêtre remplacée,
 * journal d'import, consolidation du cache.
 */
class EtlServiceTest extends TestCase
{
    use RefreshDatabase;

    private DskEnMemoire $dsk;

    private EpiloggEnMemoire $epilogg;

    private CarbonImmutable $du;

    private CarbonImmutable $au;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferentielSeeder::class);

        $this->du = CarbonImmutable::parse('2026-03-01');
        $this->au = CarbonImmutable::parse('2026-03-31');

        $this->dsk = new DskEnMemoire;
        $this->dsk->departements = [new DepartementDsk('AISP', 'AISP', true), new DepartementDsk('PSY', 'Service Psychologique', true)];
        $this->dsk->comptes = [new CompteDskDto(10, 'Heures normales', 0), new CompteDskDto(20, 'Congés annuels', 1)];
        $this->dsk->salaries = [
            new SalarieDsk(1001, 'Anna EXEMPLE', 'EPI001', 'AISP', null, 'Anna.Exemple@fondationepi.lu', CarbonImmutable::parse('2020-01-06'), null),
            new SalarieDsk(1002, 'Bob TEMOIN', 'EPI002', 'PSY', null, null, CarbonImmutable::parse('2021-09-01'), null),
        ];
        $this->dsk->minutes = [
            new MinutesCompteDsk(1001, CarbonImmutable::parse('2026-03-02'), 10, 480),
            new MinutesCompteDsk(1001, CarbonImmutable::parse('2026-03-03'), 20, 480),
            new MinutesCompteDsk(1002, CarbonImmutable::parse('2026-03-02'), 10, 420),
            new MinutesCompteDsk(9999, CarbonImmutable::parse('2026-03-02'), 10, 480), // salarié inconnu de DSK /listAll
        ];

        $this->epilogg = new EpiloggEnMemoire;
        $this->epilogg->types = [
            new TypeSuiviEpilogg(5, '8.1 AISP', 'T 8.1', 60, false),
            new TypeSuiviEpilogg(2, '9.1 Service Psychologique', 'T 9.1', 30, false),
            new TypeSuiviEpilogg(13, 'Comité des Fêtes', 'Comité de Fêtes', 60, false),
            new TypeSuiviEpilogg(4, 'SLEMO 8.2', 'T 8.2', 60, true),
            new TypeSuiviEpilogg(77, 'Tarif inconnu', 'T 7.7', 60, false),
        ];
        $this->epilogg->salaries = [
            new SalarieEpilogg(31, 'anna.exemple@fondationepi.lu', 'Anna Exemple', true),
            new SalarieEpilogg(32, 'inconnu@fondationepi.lu', 'Sans Correspondance', true),
            new SalarieEpilogg(33, 'parti@fondationepi.lu', 'Ancien Compte', false),
        ];
        $this->epilogg->prestations = [
            $this->prestation(501, 31, '2026-03-02', 5, '1.50', 'Entretien'),
            $this->prestation(502, 31, '2026-03-03', 13, '0.25', 'Planification'),
            $this->prestation(503, 32, '2026-03-02', 5, '1.00', 'Entretien'),   // utilisateur sans correspondance
            $this->prestation(504, 31, '2026-03-04', 77, '1.00', 'Entretien'),  // type sans tarif
            $this->prestation(505, 31, '2026-04-01', 5, '1.00', 'Entretien'),   // hors fenêtre
        ];

        $this->app->instance(ConnecteurDsk::class, $this->dsk);
        $this->app->instance(ConnecteurEpilogg::class, $this->epilogg);
    }

    private function prestation(int $id, int $user, string $date, int $type, string $quantite, string $intervention): PrestationEpilogg
    {
        return new PrestationEpilogg($id, $user, null, CarbonImmutable::parse($date), $type, $intervention, $quantite, false, 60, 0, null);
    }

    #[Test]
    public function import_dsk_synchronise_referentiels_et_salaries_puis_charge_les_minutes(): void
    {
        $import = app(EtlService::class)->importerDsk($this->du, $this->au);

        $this->assertSame(StatutImport::Succes, $import->statut);
        $this->assertSame(3, $import->nb_lignes, 'les minutes du noPers inconnu sont ignorées');
        $this->assertTrue(Service::where('code', 'AISP')->exists());
        $this->assertSame(CategorieHeure::Autre, CompteDsk::where('no_account', 20)->first()->categorie, 'nouveau compteur : à qualifier');

        $anna = User::where('dsk_no_pers', 1001)->first();
        $this->assertSame('anna.exemple@fondationepi.lu', $anna->email, 'email DSK normalisé en minuscules');
        $this->assertSame('EPI001', $anna->matricule);
        $this->assertSame('AISP', $anna->service->code);
        $this->assertStringContainsString('@sans-email.invalid', User::where('dsk_no_pers', 1002)->first()->email);

        $resume = json_decode($import->message, true);
        $this->assertSame([9999], $resume['salaries_dsk_inconnus']);
        $this->assertSame(1, $resume['salaries_sans_email']);
    }

    #[Test]
    public function import_epilogg_apparie_par_email_et_signale_les_cas_a_traiter(): void
    {
        $etl = app(EtlService::class);
        $etl->importerDsk($this->du, $this->au);
        $import = $etl->importerEpilogg($this->du, $this->au);

        $this->assertSame(StatutImport::Succes, $import->statut);
        // Chargées : 501, 502 et 504 (type non qualifié : conservée brute, exclue seulement au calcul).
        // Ignorée : 503 (utilisateur sans correspondance). Jamais vue : 505 (hors fenêtre).
        $this->assertSame(3, $import->nb_lignes, $import->message);

        $anna = User::where('dsk_no_pers', 1001)->first();
        $this->assertSame(31, $anna->epilogg_user_id, 'appariement persisté');

        $types = CategorieSuivi::with('tarif')->get()->keyBy('epilogg_attend_type_id');
        $this->assertSame('8.1', $types[5]->tarif->code);
        $this->assertSame('AUTRE', $types[13]->tarif->code, 'rate_name sans code → activité interne');
        $this->assertFalse($types[4]->actif, 'type archivé côté EpiLogg');
        $this->assertNull($types[77]->tarif_id, 'tarif inconnu (7.7) → non qualifié');

        $resume = json_decode($import->message, true);
        $this->assertSame(['inconnu@fondationepi.lu'], $resume['epilogg_sans_correspondance'], 'seuls les comptes actifs sont signalés');
        $this->assertSame(['Tarif inconnu'], $resume['types_non_qualifies']);
        $this->assertSame(1, $resume['prestations_ignorees'], $import->message);
        $this->assertTrue(DonneeEpilogg::where('reference_externe', '504')->exists(), 'type non qualifié : donnée brute conservée');

        $prestation = DonneeEpilogg::where('reference_externe', '501')->first();
        $this->assertSame('1.50', (string) $prestation->quantite);
        $this->assertSame('Entretien', $prestation->type_prestation);
    }

    #[Test]
    public function le_tarif_choisi_par_l_administrateur_n_est_jamais_ecrase_par_l_import(): void
    {
        $etl = app(EtlService::class);
        $etl->importerEpilogg($this->du, $this->au);

        $comite = CategorieSuivi::where('epilogg_attend_type_id', 13)->first();
        $comite->update(['tarif_id' => \App\Models\Tarif::where('code', '8.1')->first()->id]);

        $etl->importerEpilogg($this->du, $this->au);

        $this->assertSame('8.1', $comite->fresh()->tarif->code);
    }

    #[Test]
    public function la_fenetre_remplacee_reflete_une_suppression_cote_source(): void
    {
        $etl = app(EtlService::class);
        $etl->importerDsk($this->du, $this->au);
        $etl->importerEpilogg($this->du, $this->au);
        $this->assertTrue(DonneeEpilogg::where('reference_externe', '502')->exists());

        // La prestation 502 est annulée dans EpiLogg (suppression physique), la 501 corrigée.
        $this->epilogg->prestations = [
            $this->prestation(501, 31, '2026-03-02', 5, '2.00', 'Entretien'),
            $this->prestation(503, 32, '2026-03-02', 5, '1.00', 'Entretien'),
        ];
        $etl->importerEpilogg($this->du, $this->au);

        $this->assertFalse(DonneeEpilogg::where('reference_externe', '502')->exists(), 'annulation reflétée');
        $this->assertSame('2.00', (string) DonneeEpilogg::where('reference_externe', '501')->first()->quantite, 'correction reflétée');
        $this->assertSame(1, DonneeEpilogg::count(), 'aucun doublon après ré-import');
    }

    #[Test]
    public function un_import_complet_journalise_et_consolide_le_cache(): void
    {
        // L'Administrateur a déjà qualifié le compteur 10 (heures normales) ; le 20 reste « autre », hors total.
        CompteDsk::factory()->categorie(CategorieHeure::Normal)->create(['no_account' => 10]);

        $resultat = app(EtlService::class)->importerTout($this->du, $this->au);

        $this->assertSame(2, Import::where('statut', StatutImport::Succes->value)->count());
        $this->assertSame(2, $resultat['statistiques'], 'deux salariés × mars 2026');

        $anna = User::where('dsk_no_pers', 1001)->first();
        $stat = StatistiqueMensuelle::where('user_id', $anna->id)->where('annee', 2026)->where('mois', 3)->first();
        $this->assertSame(480, $stat->minutes_normales);
        $this->assertSame(480, $stat->minutes_total, 'le compteur 20, non qualifié, ne compte pas');
        $this->assertSame('90.00', (string) $stat->minutes_prestations_facturables, '1,5 forfait 8.1 × 60 min');
        $this->assertSame('15.00', (string) $stat->minutes_activite_interne, '0,25 forfait Comité × 60 min');
        $this->assertSame('0.1935', (string) $stat->ratio, '90 / (480 − 15)');
    }

    #[Test]
    public function une_source_en_panne_laisse_un_import_en_echec_et_remonte_l_erreur(): void
    {
        $this->epilogg->enPanne = true;

        try {
            app(EtlService::class)->importerEpilogg($this->du, $this->au);
            $this->fail('l\'exception doit remonter');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('injoignable', $e->getMessage());
        }

        $import = Import::latest('id')->first();
        $this->assertSame(StatutImport::Echec, $import->statut);
        $this->assertStringContainsString('injoignable', $import->message);
        $this->assertNotNull($import->termine_le);
        $this->assertSame(0, DonneeEpilogg::count());
    }
}
