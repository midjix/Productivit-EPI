<?php

namespace Tests\Feature\Console;

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
use App\Models\Import;
use App\Models\Tarif;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ReferentielSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\DskEnMemoire;
use Tests\Support\EpiloggEnMemoire;
use Tests\TestCase;

/**
 * `php artisan epi:verifier` : répétition générale avant un import réel.
 * Sources simulées (pattern Adaptateur) ; la commande ne doit RIEN écrire.
 */
class VerifierSourcesTest extends TestCase
{
    use RefreshDatabase;

    private DskEnMemoire $dsk;

    private EpiloggEnMemoire $epilogg;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferentielSeeder::class);

        $this->dsk = new DskEnMemoire;
        $this->dsk->departements = [new DepartementDsk('AISP', 'AISP', true), new DepartementDsk('DIR', 'Direction', true)];
        $this->dsk->comptes = [new CompteDskDto(10, 'Heures normales', 0), new CompteDskDto(20, 'Congés annuels', 1)];
        $this->dsk->salaries = [
            new SalarieDsk(1001, 'Anna EXEMPLE', 'EPI001', 'AISP', null, 'Anna.Exemple@fondationepi.lu', CarbonImmutable::parse('2020-01-06'), null),
            new SalarieDsk(1002, 'Bob TEMOIN', 'EPI002', 'DIR', null, null, CarbonImmutable::parse('2021-09-01'), null),
            new SalarieDsk(1003, 'Chloé SEULE', 'EPI003', 'AISP', null, 'chloe.seule@fondationepi.lu', CarbonImmutable::parse('2022-02-01'), null),
        ];
        $this->dsk->minutes = [
            new MinutesCompteDsk(1001, CarbonImmutable::parse('2026-03-02'), 10, 480),
            new MinutesCompteDsk(1002, CarbonImmutable::parse('2026-03-02'), 10, 420),
            new MinutesCompteDsk(9999, CarbonImmutable::parse('2026-03-02'), 10, 480), // noPers absent de la liste
            new MinutesCompteDsk(1001, CarbonImmutable::parse('2026-04-02'), 10, 480), // hors période
        ];

        $this->epilogg = new EpiloggEnMemoire;
        $this->epilogg->types = [
            new TypeSuiviEpilogg(5, '8.1 AISP', 'T 8.1', 60, false),
            new TypeSuiviEpilogg(13, 'Comité des Fêtes', 'Comité de Fêtes', 60, false),   // AUTRE : tarif connu
            new TypeSuiviEpilogg(4, 'SLEMO 8.2', 'T 8.2', 60, true),
            new TypeSuiviEpilogg(77, 'Tarif inconnu', 'T 7.7', 60, false),               // 7.7 n'existe pas
        ];
        $this->epilogg->salaries = [
            new SalarieEpilogg(31, 'anna.exemple@fondationepi.lu', 'Anna Exemple', true),
            new SalarieEpilogg(32, 'inconnu@fondationepi.lu', 'Sans Correspondance', true),
            new SalarieEpilogg(33, 'parti@fondationepi.lu', 'Ancien Compte', false),
        ];
        $this->epilogg->prestations = [
            new PrestationEpilogg(501, 31, 'anna.exemple@fondationepi.lu', CarbonImmutable::parse('2026-03-02'), 5, 'Entretien', '1.50', false, null, null, null),
            new PrestationEpilogg(502, 32, 'inconnu@fondationepi.lu', CarbonImmutable::parse('2026-03-03'), 5, 'Entretien', '1.00', false, null, null, null),
            new PrestationEpilogg(503, 33, 'parti@fondationepi.lu', CarbonImmutable::parse('2026-03-04'), 5, 'Entretien', '1.00', false, null, null, null),
            new PrestationEpilogg(504, 31, 'anna.exemple@fondationepi.lu', CarbonImmutable::parse('2026-03-05'), 999, 'Entretien', '1.00', false, null, null, null), // type absent
            new PrestationEpilogg(505, 31, 'anna.exemple@fondationepi.lu', CarbonImmutable::parse('2026-05-05'), 5, 'Entretien', '1.00', false, null, null, null),   // hors période
        ];

        $this->app->instance(ConnecteurDsk::class, $this->dsk);
        $this->app->instance(ConnecteurEpilogg::class, $this->epilogg);
    }

    /** @return array<string, mixed> */
    private function diagnosticJson(array $options = []): array
    {
        Artisan::call('epi:verifier', ['--from' => '2026-03-01', '--to' => '2026-03-31', '--json' => true] + $options);

        return json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
    }

    #[Test]
    public function decrit_les_deux_sources_et_leur_appariement_sans_rien_ecrire(): void
    {
        $r = $this->diagnosticJson();

        $this->assertTrue($r['pret']);
        $this->assertSame(['du' => '2026-03-01', 'au' => '2026-03-31'], $r['periode']);

        $dsk = $r['dsk'];
        $this->assertTrue($dsk['ok']);
        $this->assertSame(2, $dsk['departements']);
        $this->assertSame(['DIR (Direction)'], $dsk['departements_nouveaux'], 'AISP est dans le référentiel, DIR non');
        $this->assertSame(2, $dsk['comptes']);
        $this->assertCount(2, $dsk['comptes_nouveaux']);
        $this->assertSame(2, $dsk['comptes_a_qualifier']);
        $this->assertSame(3, $dsk['salaries']);
        $this->assertSame(['Bob TEMOIN (noPers 1002)'], $dsk['salaries_sans_email']);
        $this->assertSame(3, $dsk['lignes_minutes'], 'la ligne d\'avril est hors période');
        $this->assertSame(['9999'], $dsk['minutes_salaries_inconnus']);

        $epilogg = $r['epilogg'];
        $this->assertTrue($epilogg['ok']);
        $this->assertSame(4, $epilogg['types']);
        $this->assertSame(4, $epilogg['types_nouveaux']);
        $this->assertSame(['Tarif inconnu (rate_name « T 7.7 »)'], $epilogg['types_sans_tarif'], 'le type archivé et le type AUTRE ne sont pas signalés');
        $this->assertSame(2, $epilogg['utilisateurs_actifs']);
        $this->assertSame(4, $epilogg['prestations'], 'la prestation de mai est hors période');
        $this->assertSame(1, $epilogg['prestations_utilisateur_inactif']);
        $this->assertSame(1, $epilogg['types_absents_du_referentiel']);

        $appariement = $r['appariement'];
        $this->assertSame(1, $appariement['apparies']);
        $this->assertSame(['inconnu@fondationepi.lu'], $appariement['epilogg_actifs_sans_dsk']);
        $this->assertSame(['chloe.seule@fondationepi.lu'], $appariement['dsk_sans_epilogg']);

        $this->assertSame(0, User::count());
        $this->assertSame(0, CompteDsk::count());
        $this->assertSame(0, CategorieSuivi::count());
        $this->assertSame(0, Import::count());
    }

    #[Test]
    public function tient_compte_des_referentiels_deja_qualifies_en_base(): void
    {
        CompteDsk::create(['no_account' => 10, 'libelle' => 'Heures normales', 'categorie' => 'normal', 'compte_effectif' => true, 'actif' => true]);
        CategorieSuivi::create(['epilogg_attend_type_id' => 77, 'libelle' => 'Tarif inconnu', 'tarif_id' => Tarif::where('code', '8.1')->value('id'), 'actif' => true]);
        User::factory()->create(['email' => 'inconnu@fondationepi.lu']);

        $r = $this->diagnosticJson();

        $this->assertCount(1, $r['dsk']['comptes_nouveaux']);
        $this->assertSame(1, $r['dsk']['comptes_a_qualifier']);
        $this->assertSame(3, $r['epilogg']['types_nouveaux']);
        $this->assertSame([], $r['epilogg']['types_sans_tarif'], 'un type qualifié par l\'Administrateur n\'est plus signalé');
        $this->assertSame(2, $r['appariement']['apparies'], 'un utilisateur déjà en base compte comme apparié');
        $this->assertSame([], $r['appariement']['epilogg_actifs_sans_dsk']);
    }

    #[Test]
    public function une_source_en_panne_est_signalee_sans_masquer_l_autre(): void
    {
        $this->dsk->enPanne = true;

        $code = Artisan::call('epi:verifier', ['--from' => '2026-03-01', '--to' => '2026-03-31', '--json' => true]);
        $r = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(1, $code);
        $this->assertFalse($r['pret']);
        $this->assertFalse($r['dsk']['ok']);
        $this->assertStringContainsString('simulation', $r['dsk']['erreur']);
        $this->assertTrue($r['epilogg']['ok']);
        $this->assertNull($r['appariement'], 'pas d\'appariement possible sans les deux sources');
    }

    #[Test]
    public function peut_se_limiter_a_une_source(): void
    {
        $r = $this->diagnosticJson(['--source' => 'epilogg']);

        $this->assertNull($r['dsk']);
        $this->assertTrue($r['epilogg']['ok']);
        $this->assertNull($r['appariement']);
        $this->assertTrue($r['pret']);

        $this->artisan('epi:verifier', ['--source' => 'fichier'])->assertExitCode(2);
    }

    #[Test]
    public function l_affichage_lisible_annonce_la_lecture_seule_et_le_verdict(): void
    {
        $this->artisan('epi:verifier', ['--from' => '2026-03-01', '--to' => '2026-03-31'])
            ->expectsOutputToContain('aucune écriture')
            ->expectsOutputToContain('Bob TEMOIN (noPers 1002)')
            ->expectsOutputToContain('Tarif inconnu')
            ->expectsOutputToContain('prêt pour l\'import')
            ->assertSuccessful();

        $this->epilogg->enPanne = true;

        $this->artisan('epi:verifier', ['--from' => '2026-03-01', '--to' => '2026-03-31'])
            ->expectsOutputToContain('INJOIGNABLE')
            ->expectsOutputToContain('EpiLogg injoignable (simulation)')
            ->assertFailed();
    }
}
