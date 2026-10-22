<?php

namespace Tests\Feature\Connecteurs;

use App\Etl\Connecteurs\DskHttp;
use App\Etl\Dto\MinutesCompteDsk;
use App\Etl\Dto\SalarieDsk;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Connecteur DSK sur réponses HTTP ENREGISTRÉES (Http::fake), construites d'après la
 * spécification OpenAPI « DSK API v2 » : l'API réelle n'est pas joignable depuis le poste
 * de développement (VPN limité à la base EpiLogg).
 */
class DskHttpTest extends TestCase
{
    private const BASE = 'http://srv-data/API/dsknet/v2';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function connecteur(?string $tokenStatique = null): DskHttp
    {
        return new DskHttp(app(Factory::class), Cache::store(), [
            'url' => 'http://srv-data/API/',
            'prefix' => 'dsknet/v2',
            'login' => 'api.epi',
            'password' => 'secret',
            'token' => $tokenStatique,
            'timeout' => 5,
        ]);
    }

    /** @param  array<int, array<string, mixed>>  $data */
    private function page(array $data, int $cur = 1, int $last = 1): array
    {
        return ['meta' => ['dataCnt' => count($data), 'page' => ['curIdx' => $cur, 'lastIdx' => $last]], 'data' => $data];
    }

    #[Test]
    public function se_connecte_une_fois_puis_reutilise_le_jeton_en_cache(): void
    {
        Http::fake([
            self::BASE.'/login' => Http::response(['noPers' => 1, 'token' => 'JETON-1', 'validUntil' => now()->addHours(2)->toIso8601String()]),
            self::BASE.'/departments*' => Http::response($this->page([
                ['codeDept' => 'AISP', 'activ' => true, 'caption' => [['langID' => 2, 'caption' => 'Service AISP']]],
                ['codeDept' => 'OLD', 'activ' => false, 'caption' => []],
            ])),
        ]);

        $c = $this->connecteur();
        $d1 = iterator_to_array($c->departements());
        $d2 = iterator_to_array($c->departements());

        $this->assertSame('Service AISP', $d1[0]->libelle);
        $this->assertSame('OLD', $d1[1]->libelle, 'sans libellé : le code sert de libellé');
        $this->assertFalse($d1[1]->actif);
        $this->assertCount(2, $d2);
        Http::assertSentCount(3, 'un seul login pour deux lectures');
        Http::assertSent(fn ($r) => str_contains($r->url(), '/departments') && $r->hasHeader('Authorization', 'Bearer JETON-1'));
    }

    #[Test]
    public function parcourt_toutes_les_pages_et_ne_garde_que_les_champs_utiles(): void
    {
        $pers = fn (int $no, string $email) => ['pers' => [
            'noPers' => $no, 'firstName' => 'Anna', 'lastName' => 'Exemple', 'codePers' => "EPI{$no}", 'codeDept' => 'AISP',
            'wageNum' => '42', 'email' => $email, 'startDateRef' => '2020-01-06', 'endDateRef' => null,
            'iban' => 'LU00 0000 0000 0000 0000', 'birthDate' => '1990-01-01', 'address' => 'rue Secrète 1',
        ]];

        Http::fake([
            self::BASE.'/listAll/details?*page=1*' => Http::response($this->page([$pers(1001, 'a@fondationepi.lu')], 1, 2)),
            self::BASE.'/listAll/details?*page=2*' => Http::response($this->page([$pers(1002, ' B@FondationEPI.lu ')], 2, 2)),
        ]);

        $salaries = iterator_to_array($this->connecteur('STATIQUE')->salaries(now()->parse('2026-03-01'), now()->parse('2026-03-31')));

        $this->assertCount(2, $salaries);
        $this->assertInstanceOf(SalarieDsk::class, $salaries[0]);
        $this->assertSame('Anna EXEMPLE', $salaries[0]->nomComplet);
        $this->assertSame('b@fondationepi.lu', $salaries[1]->emailNormalise());
        $this->assertSame('2020-01-06', $salaries[0]->dateEntree->toDateString());
        $this->assertNull($salaries[0]->dateSortie);
        $this->assertObjectNotHasProperty('iban', $salaries[0], 'les données RH superflues ne sont jamais conservées');
        Http::assertSent(fn ($r) => str_contains($r->url(), 'dateFrom=2026-03-01') && str_contains($r->url(), 'dateTo=2026-03-31'));
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/login'), 'jeton statique : pas de login');
    }

    #[Test]
    public function aplatit_les_comptes_calcules_par_jour(): void
    {
        Http::fake([
            self::BASE.'/accounts/calc/all*' => Http::response($this->page([
                ['noPers' => 1001, 'theDate' => '2026-03-02', 'accounts' => [['noAccount' => 10, 'value' => 480], ['noAccount' => 20, 'value' => 0]]],
                ['noPers' => 1002, 'theDate' => '2026-03-02', 'accounts' => [['noAccount' => 10, 'value' => 419.6]]],
            ])),
        ]);

        $minutes = iterator_to_array($this->connecteur('STATIQUE')->minutesParJour(now()->parse('2026-03-02'), now()->parse('2026-03-02')));

        $this->assertCount(3, $minutes);
        $this->assertInstanceOf(MinutesCompteDsk::class, $minutes[0]);
        $this->assertSame([1001, 10, 480], [$minutes[0]->noPers, $minutes[0]->noAccount, $minutes[0]->minutes]);
        $this->assertSame(420, $minutes[2]->minutes, 'valeur arrondie à la minute entière');
    }

    #[Test]
    public function se_reconnecte_une_fois_sur_jeton_expire(): void
    {
        Cache::put('dsk.api.token', 'PERIME', now()->addHour());

        Http::fake([
            self::BASE.'/login' => Http::response(['token' => 'NEUF', 'validUntil' => now()->addHour()->toIso8601String()]),
            self::BASE.'/accounts*' => Http::sequence()
                ->push(['failMsg' => 'invalid token'], 401)
                ->push($this->page([['noAccount' => 10, 'caption' => [['langID' => 2, 'caption' => 'Heures normales']], 'type' => 0]])),
        ]);

        $comptes = iterator_to_array($this->connecteur()->comptes());

        $this->assertSame('Heures normales', $comptes[0]->libelle);
        $this->assertSame('NEUF', Cache::get('dsk.api.token'));
    }

    #[Test]
    public function une_connexion_refusee_remonte_le_code_dsk(): void
    {
        Http::fake([self::BASE.'/login' => Http::response(['token' => null, 'failCode' => '02', 'failMsg' => 'Account is locked'])]);

        $this->expectExceptionMessage('02 Account is locked');
        iterator_to_array($this->connecteur()->comptes());
    }
}
