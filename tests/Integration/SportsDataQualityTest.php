<?php
declare(strict_types=1);

namespace Tests\Integration;

use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/sports_data_quality.php';

final class SportsDataQualityTest extends TestCase
{
    public function testInventoryAggregatesQualityWithoutReturningPeopleOrValues(): void
    {
        $inventory = \sportsDataQualityInventory($this->database(), new DateTimeImmutable('2026-08-05 12:00:00'));

        self::assertSame([], $inventory['unavailable']);
        self::assertSame([
            'training_attendance',
            'structured_measurements',
            'legacy_measurements',
            'race_results',
            'stress_tests',
        ], array_keys($inventory['sources']));
        self::assertSame(8, $inventory['total_records']);
        self::assertSame(12, $inventory['finding_count']);

        $training = $inventory['sources']['training_attendance'];
        self::assertSame(2, $training['record_count']);
        self::assertSame(['missing_category', 'missing_duration', 'broken_attendance_link', 'implicit_duration_unit'], array_column($training['findings'], 'key'));

        $measurements = $inventory['sources']['structured_measurements'];
        self::assertSame(['unlinked_measurement', 'missing_athlete', 'ambiguous_distance_unit', 'freeform_time_rpe'], array_column($measurements['findings'], 'key'));
        self::assertSame('citlivé sportovní údaje', $measurements['classification']);

        $encoded = json_encode($inventory, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('sportovec_id', $encoded);
        self::assertStringNotContainsString('10 km', $encoded);
        self::assertStringNotContainsString('01:00', $encoded);
    }

    public function testMissingTablesAreUnavailableInsteadOfZeroQuality(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $inventory = \sportsDataQualityInventory($pdo, new DateTimeImmutable('2026-08-05 12:00:00'));

        self::assertCount(5, $inventory['unavailable']);
        self::assertSame(0, $inventory['total_records']);
        foreach ($inventory['sources'] as $source) {
            self::assertFalse($source['available']);
            self::assertSame('unavailable', $source['status']);
        }
    }

    public function testPageIsAdminOnlyReadOnlyAndLinked(): void
    {
        $root = dirname(__DIR__, 2);
        $page = (string)file_get_contents($root . '/sports_data_quality_admin.php');
        $header = (string)file_get_contents($root . '/hlavicka.php');

        self::assertStringContainsString("roleAtLeast('admin')", $page);
        self::assertStringContainsString('Cache-Control: no-store, private', $page);
        self::assertStringContainsString('Přehled je pouze ke čtení', $page);
        self::assertStringContainsString('neobsahuje jména ani naměřené hodnoty', $page);
        self::assertStringNotContainsString('REQUEST_METHOD', $page);
        self::assertStringNotContainsString('sportovec_id', $page);
        self::assertStringContainsString('sports_data_quality_admin.php', $header);
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY)');
        $pdo->exec('CREATE TABLE treninky(id INTEGER PRIMARY KEY,kategorie TEXT,delka REAL)');
        $pdo->exec('CREATE TABLE trenink_sportovec(trenink_id INTEGER,sportovec_id INTEGER)');
        $pdo->exec('CREATE TABLE mereni_zaznamy(id INTEGER PRIMARY KEY,sportovec_id INTEGER,vzdalenost REAL,cas TEXT,rpe TEXT)');
        $pdo->exec('CREATE TABLE trenink_mereni(trenink_id INTEGER,mereni_id INTEGER)');
        $pdo->exec('CREATE TABLE zavod_mereni(zavod_id INTEGER,mereni_id INTEGER)');
        $pdo->exec('CREATE TABLE mereni(id INTEGER PRIMARY KEY,trenink_id INTEGER,sportovec_id INTEGER,vzdalenost TEXT,cas TEXT)');
        $pdo->exec('CREATE TABLE zavody(id INTEGER PRIMARY KEY)');
        $pdo->exec('CREATE TABLE zavod_sportovec(zavod_id INTEGER,sportovec_id INTEGER,poradi INTEGER,cas TEXT,body REAL)');
        $pdo->exec('CREATE TABLE zatezove_testy(id INTEGER PRIMARY KEY,sportovec_id INTEGER,vaha_kg REAL,vyska_cm REAL)');
        $pdo->exec('CREATE TABLE zatezove_testy_soubory(id INTEGER PRIMARY KEY,test_id INTEGER)');

        $pdo->exec('INSERT INTO sportovci VALUES(1)');
        $pdo->exec("INSERT INTO treninky VALUES(1,NULL,NULL),(2,'silnice',1.5)");
        $pdo->exec('INSERT INTO trenink_sportovec VALUES(1,1),(999,1)');
        $pdo->exec("INSERT INTO mereni_zaznamy VALUES(1,1,10.0,'00:30','7'),(2,NULL,NULL,NULL,NULL)");
        $pdo->exec('INSERT INTO trenink_mereni VALUES(1,1)');
        $pdo->exec("INSERT INTO mereni VALUES(1,1,1,'10 km','30 min')");
        $pdo->exec('INSERT INTO zavody VALUES(1)');
        $pdo->exec("INSERT INTO zavod_sportovec VALUES(1,1,NULL,NULL,NULL),(1,NULL,NULL,'01:00',NULL)");
        $pdo->exec('INSERT INTO zatezove_testy VALUES(1,1,60.0,170.0)');
        $pdo->exec('INSERT INTO zatezove_testy_soubory VALUES(1,1)');
        return $pdo;
    }
}
