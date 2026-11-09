<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\AthleteProfileFixture as Fixture;
use Tests\Support\QueryProfilePdo;

require_once dirname(__DIR__) . '/Support/QueryProfilePdo.php';
require_once dirname(__DIR__) . '/Support/RosterReadDatabase.php';
require_once dirname(__DIR__) . '/Support/AthleteProfileFixture.php';

final class AthleteHeaderReadTest extends TestCase
{
    use \Tests\Support\RosterReadDatabase;

    public function testExactYearsTypesAndCountsWithDuplicatesOrphansAndFreshMutations(): void
    {
        $pdo = $this->database();
        Fixture::seed($pdo, 151);
        $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if ($mysql) {
            // Only this disposable schema/session: exercise legacy zero dates too.
            $pdo->exec("SET SESSION sql_mode='STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        }
        $pdo->exec("INSERT INTO treninky(id,datum) VALUES(1001,NULL),(1002,'0000-00-00'),
            (1003,'1999-01-01'),(1004,'2035-12-31'),(1005,'2035-12-31');
            INSERT INTO trenink_sportovec VALUES(1001,1),(1001,1),(1002,1),(1003,1),
                (1004,2),(1005,2),(999999,1),(NULL,1),(1005,NULL);
            INSERT INTO mereni_zaznamy(id,sportovec_id,typ) VALUES(90001,1,'kolo'),
                (90002,1,'unlinked'),(90003,2,'foreign');
            INSERT INTO trenink_mereni VALUES(1001,90001,0),(1001,90001,1),(1002,90001,0),
                (1004,90001,0),(1005,90003,0),(1005,9999999,0),(999999,90001,0),(NULL,90001,0)");
        foreach ($mysql ? [false, true] : [false] as $emulate) {
            if ($mysql) {
                $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $emulate);
            }
            foreach ([false, true] as $stringify) {
                $pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, $stringify);
                foreach ([1, 2, 99, 0, -1] as $sid) {
                    $this->assertHeader($pdo, $sid);
                }
            }
        }
        $pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
        $header = \AthleteProfileRead::header($pdo, 1);
        self::assertSame([2035, 2026, 2025, 2024, 1999, 0, null], $header['years']);
        self::assertSame(155, $header['trainings']);
        self::assertSame(151 * 9 + intdiv(151, 11) + 4, $header['measurements']);
        // No caching: deleting/adding links must be visible immediately.
        $pdo->exec('DELETE FROM trenink_sportovec WHERE trenink_id=1003;
            DELETE FROM trenink_mereni WHERE trenink_id=1004;
            INSERT INTO trenink_sportovec VALUES(1005,1);
            INSERT INTO trenink_mereni VALUES(1005,90001,0),(1005,90001,0)');
        $this->assertHeader($pdo, 1);
        $header = \AthleteProfileRead::header($pdo, 1);
        self::assertSame(154, $header['trainings']);
        self::assertSame([2035, 2026, 2025, 2024, 0, null], $header['years']);
    }

    public function testLargeHistoryReturnsOnlyYearAggregatesAndEmptyProfileStaysEmpty(): void
    {
        $pdo = $this->database();
        Fixture::seed($pdo, 1000);
        $expected = $this->reference($pdo, 1);
        $pdo->profile = [];
        $pdo->profiling = true;
        self::assertSame($expected, \AthleteProfileRead::header($pdo, 1));
        $pdo->profiling = false;
        self::assertCount(1, $pdo->profile);
        self::assertSame(count($expected['years']), $pdo->profile[0]['fetched_rows']);
        self::assertSame(['years' => [], 'trainings' => 0, 'measurements' => 0], \AthleteProfileRead::header($pdo, 99));
    }

    private function reference(PDO $pdo, int $sid): array
    {
        return ['years' => \AthleteProfileRead::years($pdo, $sid),
            'trainings' => \AthleteProfileRead::countTrainings($pdo, $sid),
            'measurements' => \AthleteProfileRead::countMeasurements($pdo, $sid)];
    }

    private function assertHeader(QueryProfilePdo $pdo, int $sid): void
    {
        self::assertSame($this->reference($pdo, $sid), \AthleteProfileRead::header($pdo, $sid));
    }
}
