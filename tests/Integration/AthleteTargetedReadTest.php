<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\AthleteProfileFixture as Fixture;

require_once dirname(__DIR__) . '/Support/QueryProfilePdo.php';
require_once dirname(__DIR__) . '/Support/RosterReadDatabase.php';
require_once dirname(__DIR__) . '/Support/AthleteProfileFixture.php';
require_once dirname(__DIR__) . '/Support/AthleteTargetedReference.php';

final class AthleteTargetedReadTest extends TestCase
{
    use \Tests\Support\RosterReadDatabase;

    public function testFrozenFullResultParityForEveryPageYearAndPdoMode(): void
    {
        $pdo = $this->database();
        Fixture::seed($pdo, 101);
        $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if ($mysql) {
            $pdo->exec("SET SESSION sql_mode='STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        }
        $pdo->exec("INSERT INTO treninky(id,datum,delka) VALUES(1001,NULL,'1,25'),(1002,'0000-00-00','0'),
            (1003,'1999-01-01','0.1'),(1004,'2035-12-31','0.2'),(1005,'2026-00-00','0.125'),(1006,'2026-01-00','-0.125');
            INSERT INTO trenink_sportovec VALUES(1001,1),(1001,1),(1002,1),(999999,1);
            INSERT INTO trenink_mereni VALUES(1003,10,0),(1003,10,0),(1004,19,0),(1004,999999,0),(1005,10,0),(1006,11,0)");
        if ($mysql) {
            self::assertGreaterThan(0, \AthleteTargetedReference::history($pdo, 1, 2026, 1)['total']);
        }
        $values = ['', '0', '0.0', ' 0', '1,25', '0.1', '-0.125', 'words', '1,2,3',
            '1e12', '1e-12', '-1e12', '1.234567890123e-7', ' 2.5 hours'];
        $update = $pdo->prepare('UPDATE treninky SET delka=? WHERE id=?');
        foreach ($values as $i => $value) {
            $update->execute([$value, $i + 1]);
        }
        foreach ($mysql ? [false, true] : [false] as $emulate) {
            if ($mysql) {
                $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $emulate);
            }
            foreach ([false, true] as $stringify) {
                $pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, $stringify);
                foreach ([1, 2, 99] as $sid) {
                    foreach ([0, 2024, 2025, 2026, 1999, 2099] as $year) {
                        foreach ([-1, 1, 2, 4, 5, 1000000, PHP_INT_MAX] as $page) {
                            self::assertSame(
                                \AthleteTargetedReference::history($pdo, $sid, $year, $page),
                                \AthleteProfileRead::history($pdo, $sid, $year, $page)
                            );
                        }
                    }
                }
            }
        }
    }

    public function testMeasurementOnlySigned64BitOwnershipAndExtremeYears(): void
    {
        $pdo = $this->database();
        Fixture::seed($pdo, 3);
        $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $pdo->exec('DELETE FROM trenink_sportovec; DELETE FROM trenink_mereni; DELETE FROM mereni_zaznamy; DELETE FROM treninky');
        if ($mysql) {
            $pdo->exec("SET SESSION sql_mode='STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
                ALTER TABLE treninky MODIFY id BIGINT NOT NULL;
                ALTER TABLE trenink_mereni MODIFY trenink_id BIGINT,MODIFY mereni_id BIGINT;
                ALTER TABLE mereni_zaznamy MODIFY id BIGINT NOT NULL");
        }
        $training = $pdo->prepare('INSERT INTO treninky(id,datum,delka) VALUES(?,?,?)');
        $measurement = $pdo->prepare("INSERT INTO mereni_zaznamy(id,sportovec_id,typ) VALUES(?,1,'kolo')");
        $link = $pdo->prepare('INSERT INTO trenink_mereni VALUES(?,?,0)');
        foreach (['9223372036854775807', '9223372036854775806', '-9223372036854775808'] as $number => $id) {
            $training->execute([$id, ['2026-00-00', '2026-01-00', '2025-12-31'][$number], ['1e-12', '-1e12', '0.1'][$number]]);
            $measurement->execute([$id]);
            $link->execute([$id, $id]);
        }
        foreach ($mysql ? [false, true] : [false] as $emulate) {
            if ($mysql) {
                $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $emulate);
            }
            foreach ([false, true] as $stringify) {
                $pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, $stringify);
                foreach ([0, 2026, 2025, 2099, 10000, PHP_INT_MAX] as $year) {
                    foreach ([1, PHP_INT_MAX] as $page) {
                        self::assertSame(
                            \AthleteTargetedReference::history($pdo, 1, $year, $page),
                            \AthleteProfileRead::history($pdo, 1, $year, $page)
                        );
                    }
                }
            }
        }
    }

    public function testLastPageClampingExactMultiplesAndFreshOwnership(): void
    {
        $pdo = $this->database();
        Fixture::seed($pdo, 100);
        foreach ([1, 2, 4, 5, PHP_INT_MAX] as $page) {
            self::assertSame(\AthleteTargetedReference::history($pdo, 1, 0, $page), \AthleteProfileRead::history($pdo, 1, 0, $page));
        }
        $pdo->exec('DELETE FROM trenink_sportovec WHERE sportovec_id=1 AND trenink_id=1;
            DELETE FROM trenink_mereni WHERE trenink_id=1');
        $last = \AthleteProfileRead::history($pdo, 1, 0, PHP_INT_MAX);
        self::assertSame(99, $last['total']);
        self::assertCount(24, $last['rows']);
        self::assertSame(\AthleteTargetedReference::history($pdo, 1, 0, PHP_INT_MAX), $last);
        $pdo->exec("UPDATE treninky SET napln='Fresh exact content',delka='0.1234567' WHERE id=100;
            UPDATE sportovec_poznamka SET poznamka='Fresh own note' WHERE sportovec_id=1 AND trenink_id=100");
        self::assertSame(\AthleteTargetedReference::history($pdo, 1, 0, 1), \AthleteProfileRead::history($pdo, 1, 0, 1));
    }
}
