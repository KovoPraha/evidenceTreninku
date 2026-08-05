<?php
declare(strict_types=1);

namespace Tests\Integration;

use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/sports_import_review.php';

final class SportsImportReviewTest extends TestCase
{
    public function testReviewClassifiesLegacyRowsWithoutGuessingOrExposingPeople(): void
    {
        $review = \sportsImportReview($this->database(), 200, new DateTimeImmutable('2026-08-05 12:00:00'));

        self::assertTrue($review['available']);

        $measurements = $review['measurements'];
        self::assertSame(5, $measurements['total']);
        self::assertSame(1, $measurements['v1_total']);
        self::assertSame(['m' => 0, 'km' => 1], $measurements['v1_units']);
        self::assertSame(1, $measurements['v1_with_duration']);
        self::assertSame(3, $measurements['legacy_with_values']);
        self::assertSame(1, $measurements['legacy_without_values']);
        self::assertSame(1, $measurements['convertible_count']);
        self::assertSame(2, $measurements['ambiguous_count']);
        self::assertFalse($measurements['truncated']);

        self::assertSame([2, 3], array_column($measurements['ambiguous_rows'], 'id'));
        $distanceRow = $measurements['ambiguous_rows'][0];
        self::assertSame('trénink 2026-08-01', $distanceRow['kontext']);
        self::assertSame(['vzdalenost', 'cas'], array_column($distanceRow['fields'], 'field'));
        self::assertSame(['ambiguous', 'deterministic'], array_column($distanceRow['fields'], 'verdict'));

        $freeformRow = $measurements['ambiguous_rows'][1];
        self::assertSame('bez vazby', $freeformRow['kontext']);
        self::assertSame(['cas', 'rpe'], array_column($freeformRow['fields'], 'field'));
        self::assertSame(['ambiguous', 'ambiguous'], array_column($freeformRow['fields'], 'verdict'));

        $races = $review['races'];
        self::assertSame(3, $races['total']);
        self::assertSame(1, $races['v1_total']);
        self::assertSame(1, $races['v1_statuses']['finished']);
        self::assertSame(1, $races['legacy_with_result']);
        self::assertSame(1, $races['legacy_without_result']);
        self::assertSame(2, $races['ambiguous_count']);
        $raceRow = $races['ambiguous_rows'][0];
        self::assertSame(1, $raceRow['zavod_id']);
        self::assertSame(1, $raceRow['radek']);
        self::assertSame('závod #1 · 2026-07-15 silnice', $raceRow['kontext']);
        self::assertSame('stav', $raceRow['fields'][0]['field']);
        self::assertSame('ambiguous', $raceRow['fields'][0]['verdict']);
        self::assertSame(['stav', 'cas'], array_column($raceRow['fields'], 'field'));
        self::assertSame(2, $races['ambiguous_rows'][1]['radek']);

        $legacyText = $review['legacy_text_table'];
        self::assertTrue($legacyText['available']);
        self::assertSame(7, $legacyText['total']);
        self::assertSame(2, $legacyText['recognized_count']);
        self::assertSame(5, $legacyText['ambiguous_count']);
        self::assertFalse($legacyText['truncated']);
        self::assertCount(7, $legacyText['rows']);
        self::assertSame([1, 2, 3, 4, 5, 6, 7], array_column($legacyText['rows'], 'id'));
        self::assertSame(
            ['recognized', 'recognized', 'ambiguous', 'ambiguous', 'ambiguous', 'ambiguous', 'ambiguous'],
            array_column($legacyText['rows'], 'verdict')
        );

        $bothRecognized = $legacyText['rows'][0];
        self::assertSame('trénink 2026-08-01', $bothRecognized['kontext']);
        self::assertSame(['vzdalenost', 'cas'], array_column($bothRecognized['fields'], 'field'));
        self::assertSame(['recognized', 'recognized'], array_column($bothRecognized['fields'], 'verdict'));
        self::assertSame('10 km', $bothRecognized['fields'][0]['value']);

        $noSpaceUnitRow = $legacyText['rows'][2];
        self::assertSame('trénink 2026-08-01', $noSpaceUnitRow['kontext']);
        self::assertSame('recognized', $noSpaceUnitRow['fields'][0]['verdict']);
        self::assertSame('200m', $noSpaceUnitRow['fields'][0]['value']);
        self::assertSame('ambiguous', $noSpaceUnitRow['fields'][1]['verdict']);

        $bothEmptyRow = $legacyText['rows'][6];
        self::assertSame('ambiguous', $bothEmptyRow['verdict']);
        self::assertSame('', $bothEmptyRow['fields'][0]['value']);
        self::assertSame('ambiguous', $bothEmptyRow['fields'][0]['verdict']);
        self::assertSame('ambiguous', $bothEmptyRow['fields'][1]['verdict']);

        $encoded = json_encode($review, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('sportovec_id', $encoded);
        self::assertStringNotContainsString('jmeno', $encoded);
        self::assertStringNotContainsString('Novák', $encoded);
    }

    public function testRowListIsTruncatedLoudlyWithoutHidingTotals(): void
    {
        $review = \sportsImportReview($this->database(), 1, new DateTimeImmutable('2026-08-05 12:00:00'));

        $measurements = $review['measurements'];
        self::assertSame(2, $measurements['ambiguous_count']);
        self::assertCount(1, $measurements['ambiguous_rows']);
        self::assertTrue($measurements['truncated']);

        $races = $review['races'];
        self::assertSame(2, $races['ambiguous_count']);
        self::assertCount(1, $races['ambiguous_rows']);
        self::assertTrue($races['truncated']);

        $legacyText = $review['legacy_text_table'];
        self::assertSame(7, $legacyText['total']);
        self::assertSame(2, $legacyText['recognized_count']);
        self::assertSame(5, $legacyText['ambiguous_count']);
        self::assertCount(1, $legacyText['rows']);
        self::assertTrue($legacyText['truncated']);
    }

    public function testMissingContractIsUnavailableInsteadOfZero(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $review = \sportsImportReview($pdo, 200, new DateTimeImmutable('2026-08-05 12:00:00'));

        self::assertFalse($review['available']);
        self::assertFalse($review['measurements']['available']);
        self::assertFalse($review['races']['available']);
        self::assertSame([], $review['measurements']['ambiguous_rows']);
        self::assertSame([], $review['races']['ambiguous_rows']);
        self::assertFalse($review['legacy_text_table']['available']);
        self::assertSame(0, $review['legacy_text_table']['total']);
        self::assertSame([], $review['legacy_text_table']['rows']);
    }

    public function testPageIsAdminOnlyReadOnlyAndLinked(): void
    {
        $root = dirname(__DIR__, 2);
        $page = (string)file_get_contents($root . '/sports_import_review_admin.php');
        $header = (string)file_get_contents($root . '/hlavicka.php');

        self::assertStringContainsString("roleAtLeast('admin')", $page);
        self::assertStringContainsString('Cache-Control: no-store, private', $page);
        self::assertStringContainsString('pouze ke čtení a nic neimportuje', $page);
        self::assertStringContainsString('bez jmen a identifikátorů sportovců', $page);
        self::assertStringNotContainsString('REQUEST_METHOD', $page);
        self::assertStringNotContainsString('csrf_field', $page);
        self::assertStringNotContainsString('<form', $page);
        self::assertStringNotContainsString('<input', $page);
        self::assertStringNotContainsString('sportovec_id', $page);
        self::assertStringContainsString('id="legacy-text"', $page);
        self::assertStringContainsString('M3.5e', $page);
        self::assertStringContainsString('sports_import_review_admin.php', $header);
    }

    public function testLegacyTextRecognitionMatchesOnlyTheDocumentedPatterns(): void
    {
        $recognizedDistances = ['10 km', '10km', '10 m', '200m', '10,5 km', '10.5 km', '0 km'];
        foreach ($recognizedDistances as $value) {
            self::assertSame('recognized', \sportsImportReviewClassifyLegacyDistance($value)['verdict'], "distance '$value' should be recognized");
        }

        $ambiguousDistances = ['', '   ', '25', '10-15 km', 'cca 10 km', '10 km, 5 km', '10 KM', '10  km', '10 kg'];
        foreach ($ambiguousDistances as $value) {
            self::assertSame('ambiguous', \sportsImportReviewClassifyLegacyDistance($value)['verdict'], "distance '$value' should be ambiguous");
        }

        $recognizedTimes = ['30:00', '05:30.250', '1:02:03', '30 min', '30min', '2,5 min'];
        foreach ($recognizedTimes as $value) {
            self::assertSame('recognized', \sportsImportReviewClassifyLegacyTime($value)['verdict'], "time '$value' should be recognized");
        }

        $ambiguousTimes = ['', '   ', '30s', '14.52', 'por', '1h02m', '10-15 min', 'cca 30 min', '30 MIN'];
        foreach ($ambiguousTimes as $value) {
            self::assertSame('ambiguous', \sportsImportReviewClassifyLegacyTime($value)['verdict'], "time '$value' should be ambiguous");
        }
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT)');
        $pdo->exec('CREATE TABLE treninky(id INTEGER PRIMARY KEY,datum TEXT)');
        $pdo->exec('CREATE TABLE zavody(id INTEGER PRIMARY KEY,datum TEXT,kategorie TEXT)');
        $pdo->exec('CREATE TABLE mereni_zaznamy(id INTEGER PRIMARY KEY,typ TEXT,sportovec_id INTEGER,vzdalenost REAL,cas TEXT,rpe TEXT,contract_version TEXT,distance_unit TEXT,distance_meters REAL,duration_ms INTEGER,rpe_value REAL)');
        $pdo->exec('CREATE TABLE trenink_mereni(trenink_id INTEGER,mereni_id INTEGER)');
        $pdo->exec('CREATE TABLE zavod_mereni(zavod_id INTEGER,mereni_id INTEGER)');
        $pdo->exec('CREATE TABLE zavod_sportovec(zavod_id INTEGER,sportovec_id INTEGER,poradi INTEGER,cas TEXT,body REAL,result_contract_version TEXT,result_status TEXT,result_time_ms INTEGER)');
        $pdo->exec('CREATE TABLE mereni(id INTEGER PRIMARY KEY,trenink_id INTEGER,sportovec_id INTEGER,vzdalenost TEXT,cas TEXT)');

        $pdo->exec("INSERT INTO sportovci VALUES(1,'Novák')");
        $pdo->exec("INSERT INTO treninky VALUES(1,'2026-08-01')");
        $pdo->exec("INSERT INTO treninky VALUES(2,'2026-08-02')");
        $pdo->exec("INSERT INTO zavody VALUES(1,'2026-07-15','silnice')");

        // 1: v1 row, 2: legacy distance without unit + strict time, 3: legacy freeform time + bad RPE,
        // 4: legacy strict time only (deterministic), 5: legacy without values.
        $pdo->exec("INSERT INTO mereni_zaznamy VALUES(1,'kolo',1,10.0,'30:00',NULL,'sports-measurement-v1','km',10000,1800000,NULL)");
        $pdo->exec("INSERT INTO mereni_zaznamy VALUES(2,'kolo',1,10.0,'30:00',NULL,NULL,NULL,NULL,NULL,NULL)");
        $pdo->exec("INSERT INTO mereni_zaznamy VALUES(3,'posilovna',1,NULL,'cca 2 min','silné',NULL,NULL,NULL,NULL,NULL)");
        $pdo->exec("INSERT INTO mereni_zaznamy VALUES(4,'beh',1,NULL,'05:30.250',NULL,NULL,NULL,NULL,NULL,NULL)");
        $pdo->exec("INSERT INTO mereni_zaznamy VALUES(5,'kolo',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL)");
        $pdo->exec('INSERT INTO trenink_mereni VALUES(1,2)');

        // v1 finished, legacy with freeform time result, legacy without result.
        $pdo->exec("INSERT INTO zavod_sportovec VALUES(1,1,1,'30:00',NULL,'sports-measurement-v1','finished',1800000)");
        $pdo->exec("INSERT INTO zavod_sportovec VALUES(1,1,NULL,'1h02m',NULL,NULL,NULL,NULL)");
        $pdo->exec("INSERT INTO zavod_sportovec VALUES(1,NULL,NULL,NULL,NULL,NULL,NULL,NULL)");

        // M3.5e legacy `mereni` fixture: 2 rows recognizable in both fields,
        // 5 rows ambiguous per the documented recognizer contract.
        // 1: recognized km + recognized min (both fields recognized -> row recognized).
        $pdo->exec("INSERT INTO mereni VALUES(1,1,1,'10 km','30 min')");
        // 2: recognized m + recognized strict time (row recognized).
        $pdo->exec("INSERT INTO mereni VALUES(2,2,1,'500 m','01:45.500')");
        // 3: recognized m without a space + unsupported time unit "s" (row ambiguous).
        $pdo->exec("INSERT INTO mereni VALUES(3,1,1,'200m','30s')");
        // 4: distance range + recognized strict time (row ambiguous).
        $pdo->exec("INSERT INTO mereni VALUES(4,2,1,'10-15 km','45:00')");
        // 5: "cca" prefix + empty time (row ambiguous, empty is always ambiguous).
        $pdo->exec("INSERT INTO mereni VALUES(5,1,1,'cca 10 km',NULL)");
        // 6: multiple values + decimal time without a colon (row ambiguous).
        $pdo->exec("INSERT INTO mereni VALUES(6,2,1,'10 km, 5 km','14.52')");
        // 7: both fields empty (row ambiguous).
        $pdo->exec("INSERT INTO mereni VALUES(7,1,1,NULL,NULL)");
        return $pdo;
    }
}
