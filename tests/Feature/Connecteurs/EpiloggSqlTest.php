<?php

namespace Tests\Feature\Connecteurs;

use App\Etl\Connecteurs\EpiloggSql;
use App\Etl\Dto\PrestationEpilogg;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Connecteur EpiLogg sur une base de test qui REPRODUIT le schéma réel relevé le 22/10/2026
 * (attend_types, attends, interventions, benefits, users), dans une base dédiée
 * `testing_epilogg` lue via la connexion `epilogg` en session READ ONLY.
 *
 * Pas de RefreshDatabase : ce test ne touche pas aux tables de l'application.
 */
class EpiloggSqlTest extends TestCase
{
    private const BASE = 'testing_epilogg';

    protected function setUp(): void
    {
        parent::setUp();

        $defaut = config('database.connections.'.config('database.default'));
        DB::statement('CREATE DATABASE IF NOT EXISTS `'.self::BASE.'`');

        config()->set('database.connections.epilogg', array_merge(config('database.connections.epilogg'), [
            'host' => $defaut['host'], 'port' => $defaut['port'], 'database' => self::BASE,
            'username' => $defaut['username'], 'password' => $defaut['password'],
        ]));
        config()->set('database.connections.epilogg_admin', array_merge($defaut, ['database' => self::BASE]));
        DB::purge('epilogg');
        DB::purge('epilogg_admin');

        $this->creerSchemaEpilogg();
    }

    protected function tearDown(): void
    {
        DB::connection('epilogg_admin')->statement('DROP DATABASE IF EXISTS `'.self::BASE.'`');
        DB::purge('epilogg');
        DB::purge('epilogg_admin');

        parent::tearDown();
    }

    private function creerSchemaEpilogg(): void
    {
        $c = DB::connection('epilogg_admin');
        $c->unprepared(<<<'SQL'
            DROP TABLE IF EXISTS benefits, attends, interventions, attend_types, users;
            CREATE TABLE attend_types (id BIGINT UNSIGNED PRIMARY KEY, name VARCHAR(191) NOT NULL, rate_name VARCHAR(191) NULL, has_APC INT NOT NULL DEFAULT 0, minute_by_package INT NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL);
            CREATE TABLE users (id BIGINT UNSIGNED PRIMARY KEY, name VARCHAR(191) NULL, email VARCHAR(191) NULL UNIQUE, role_id VARCHAR(191) NOT NULL DEFAULT '0', inactive TINYINT(1) NOT NULL DEFAULT 0, deleted_at TIMESTAMP NULL);
            CREATE TABLE attends (id BIGINT UNSIGNED PRIMARY KEY, customer_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL, type_id BIGINT UNSIGNED NOT NULL, deleted_at TIMESTAMP NULL);
            CREATE TABLE interventions (id BIGINT UNSIGNED PRIMARY KEY, name VARCHAR(191) NOT NULL, attend_type_id BIGINT UNSIGNED NULL, deleted_at TIMESTAMP NULL);
            CREATE TABLE benefits (id BIGINT UNSIGNED PRIMARY KEY, attend_id BIGINT UNSIGNED NOT NULL, intervention_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL, appointment_date DATETIME NOT NULL, service_duration INT NULL, travel_duration INT NULL, service_package_used DECIMAL(8,2) NULL DEFAULT 0.00, travel_package_used TINYINT(1) NULL DEFAULT 0, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL);
            INSERT INTO attend_types VALUES (1,'8.1 Follow Up','T 8.1',0,60,NULL,NULL,NULL),(2,'9.1 Service Psychologique','T 9.1',0,30,NULL,NULL,NULL),(4,'SLEMO 8.2','T 8.2',0,60,NULL,NULL,'2022-12-21 15:38:37'),(13,'Comité des Fêtes','Comité de Fêtes',0,60,NULL,NULL,NULL);
            INSERT INTO users (id,name,email,inactive,deleted_at) VALUES (31,'Anna Exemple','Anna.Exemple@fondationepi.lu',0,NULL),(32,'Ancien','ancien@fondationepi.lu',1,NULL),(33,'Supprimé','supprime@fondationepi.lu',0,'2024-01-01 00:00:00');
            INSERT INTO attends VALUES (885,4242,31,2,NULL),(920,4243,31,13,NULL);
            INSERT INTO interventions VALUES (1,'Entretien / réunion avec bénéficiaire ou entourage immédiat',NULL,NULL),(8,'Planification administrative',NULL,NULL);
            INSERT INTO benefits VALUES
              (76212,885,1,31,'2026-10-22 00:00:00',90,45,1.50,1,'2026-10-22 14:25:57','2026-10-22 14:25:57'),
              (76213,920,8,31,'2026-10-21 00:00:00',15,0,0.25,0,NULL,NULL),
              (76214,885,1,31,'2026-09-30 23:59:00',60,0,1.00,0,NULL,NULL),
              (76215,885,1,31,'2026-11-01 00:00:00',60,0,1.00,0,NULL,NULL);
        SQL);
    }

    private function connecteur(): EpiloggSql
    {
        return new EpiloggSql(app(DatabaseManager::class));
    }

    #[Test]
    public function la_session_est_en_lecture_seule_et_toute_ecriture_est_refusee(): void
    {
        $this->connecteur()->verifierConnexion();

        $this->expectException(QueryException::class);
        DB::connection('epilogg')->statement("UPDATE users SET name = 'piraté' WHERE id = 31");
    }

    #[Test]
    public function lit_les_types_de_suivi_tels_quels_archives_compris(): void
    {
        $types = collect($this->connecteur()->typesDeSuivi())->keyBy('id');

        $this->assertCount(4, $types);
        $this->assertSame('T 8.1', $types[1]->rateName);
        $this->assertSame(30, $types[2]->minutesParForfait);
        $this->assertTrue($types[4]->archive);
        $this->assertSame('Comité de Fêtes', $types[13]->rateName);
    }

    #[Test]
    public function lit_les_comptes_utilisateurs_avec_leur_statut(): void
    {
        $salaries = collect($this->connecteur()->salaries())->keyBy('id');

        $this->assertSame('anna.exemple@fondationepi.lu', $salaries[31]->emailNormalise());
        $this->assertTrue($salaries[31]->actif);
        $this->assertFalse($salaries[32]->actif, 'inactive = 1');
        $this->assertFalse($salaries[33]->actif, 'soft-deleted');
    }

    #[Test]
    public function lit_les_prestations_de_la_fenetre_avec_le_type_de_suivi_du_dossier(): void
    {
        $prestations = collect($this->connecteur()->prestations(now()->parse('2026-10-01'), now()->parse('2026-10-31')))->keyBy('id');

        $this->assertCount(2, $prestations, 'bornes incluses : 30/09 23:59 et 01/11 exclus');
        $p = $prestations[76212];
        $this->assertInstanceOf(PrestationEpilogg::class, $p);
        $this->assertSame(2, $p->typeSuiviId, 'type de suivi via attends.type_id (dossier 885)');
        $this->assertSame('1.50', $p->quantite);
        $this->assertTrue($p->forfaitDeplacement);
        $this->assertSame([90, 45], [$p->minutesService, $p->minutesDeplacement]);
        $this->assertSame('2026-10-22', $p->date->toDateString());
        $this->assertStringStartsWith('Entretien', $p->typePrestation);
        $this->assertSame('Anna.Exemple@fondationepi.lu', $p->email);
        $this->assertSame(13, $prestations[76213]->typeSuiviId);
        $this->assertNull($prestations[76213]->modifieLe);
    }
}
