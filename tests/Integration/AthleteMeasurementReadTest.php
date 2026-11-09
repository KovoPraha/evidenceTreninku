<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\AthleteProfileFixture as Fixture;

require_once dirname(__DIR__) . '/Support/QueryProfilePdo.php';
require_once dirname(__DIR__) . '/Support/RosterReadDatabase.php';
require_once dirname(__DIR__) . '/Support/AthleteProfileFixture.php';
require_once dirname(__DIR__) . '/Support/AthleteMeasurementReference.php';

final class AthleteMeasurementReadTest extends TestCase
{
    use \Tests\Support\RosterReadDatabase;

    public function testFrozenCountsOrderEveryFilterPageAndPdoMode(): void
    {
        $pdo = $this->database();
        Fixture::seed($pdo, 101);
        $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if ($mysql) {
            $pdo->exec("SET SESSION sql_mode='STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
            $pdo->exec('SET SESSION group_concat_max_len=128');
        }
        $pdo->exec("INSERT INTO treninky(id,datum) VALUES(1001,NULL),(1002,'0000-00-00'),(1003,'2035-01-01');
            INSERT INTO trenink_mereni VALUES(1001,10,NULL),(1002,10,-1),(1003,10,0),
                (1003,10,0),(1003,11,NULL),(1003,12,-2147483648),(1003,13,2147483647)");
        $types = ['0', '01', 'á', 'á', 'KOLO ', '', null];
        $update = $pdo->prepare('UPDATE mereni_zaznamy SET typ=? WHERE id=?');
        foreach ($types as $i => $type) {
            $update->execute([$type, 10 + $i]);
        }
        foreach ($mysql ? [false, true] : [false] as $emulate) {
            if ($mysql) {
                $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $emulate);
            }
            foreach ([false, true] as $stringify) {
                $pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, $stringify);
                foreach ([1, 2, 99] as $sid) {
                    foreach (['', 'kolo', 'KOLO', 'kolo ', 'KOLO ', '0', '01', 'á', 'á', 'absent'] as $type) {
                        foreach ([-1, 1, 2, 1000000] as $page) {
                            self::assertSame(
                                \AthleteMeasurementReference::measurements($pdo, $sid, $type, $page),
                                \AthleteProfileRead::measurements($pdo, $sid, $type, $page)
                            );
                        }
                    }
                }
            }
        }
        $pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
        $pdo->exec("UPDATE mereni_zaznamy SET typ='Fresh exact type' WHERE id=10;
            DELETE FROM trenink_mereni WHERE mereni_id=11");
        self::assertSame(\AthleteMeasurementReference::measurements($pdo, 1, '', 1), \AthleteProfileRead::measurements($pdo, 1, '', 1));
    }

    public function testWindowSummaryIgnoresFreshGroupConcatLimits(): void
    {
        $pdo = $this->database();
        Fixture::seed($pdo, 100);
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            $pdo->profile = [];
            $pdo->profiling = true;
            $actual = \AthleteProfileRead::measurements($pdo, 1, '', 1);
            $pdo->profiling = false;
            self::assertSame(\AthleteMeasurementReference::measurements($pdo, 1, '', 1), $actual);
            self::assertCount(2, $pdo->profile);
            self::assertStringNotContainsString('GROUP_CONCAT', implode("\n", array_column($pdo->profile, 'sql')));
            return;
        }
        foreach ([128, 4, 1048576] as $limit) {
            $pdo->exec('SET SESSION group_concat_max_len=' . $limit);
            $expected = \AthleteMeasurementReference::measurements($pdo, 1, '', 1);
            $pdo->profile = [];
            $pdo->profiling = true;
            $actual = \AthleteProfileRead::measurements($pdo, 1, '', 1);
            $pdo->profiling = false;
            self::assertSame($expected, $actual);
            $sql = implode("\n", array_column($pdo->profile, 'sql'));
            self::assertStringNotContainsString('GROUP_CONCAT', $sql);
            self::assertStringContainsString('ROW_NUMBER()', $sql);
            self::assertSame($limit, (int) $pdo->query('SELECT @@SESSION.group_concat_max_len')->fetchColumn());
        }
    }

    public function testDenseTrainingAndEarlyLatePageBoundaryRemainExact(): void
    {
        $pdo = $this->database();
        Fixture::seed($pdo, 100);
        // More than one page belongs to a single training; later pages cross
        // the bounded-selector threshold while preserving duplicate links.
        $pdo->exec("INSERT INTO treninky(id,datum) VALUES(1001,'2035-01-01')");
        $insert = $pdo->prepare('INSERT INTO trenink_mereni VALUES(1001,10,?)');
        for ($position = 0; $position < 900; ++$position) {
            $insert->execute([$position]);
        }
        $pdo->exec("UPDATE mereni_zaznamy SET typ='KOLO ' WHERE id=10");
        $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        foreach ($mysql ? [false, true] : [false] as $emulate) {
            if ($mysql) {
                $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $emulate);
            }
            foreach ([false, true] as $stringify) {
                $pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, $stringify);
                foreach (['', 'KOLO ', 'KOLO', 'kolo ', 'absent'] as $type) {
                    foreach ([1, 2, 31, 32, 33, 1000000] as $page) {
                        self::assertSame(
                            \AthleteMeasurementReference::measurements($pdo, 1, $type, $page),
                            \AthleteProfileRead::measurements($pdo, 1, $type, $page)
                        );
                    }
                }
            }
        }
    }

    public function testOldLargeProfileBehindNewerForeignTrainingsRetainsEveryPage(): void
    {
        $pdo = $this->database();
        Fixture::seed($pdo, 100);
        $training = $pdo->prepare("INSERT INTO treninky(id,datum) VALUES(?,'2036-01-01')");
        $measurement = $pdo->prepare("INSERT INTO mereni_zaznamy(id,sportovec_id,typ) VALUES(?,2,'foreign')");
        $link = $pdo->prepare('INSERT INTO trenink_mereni VALUES(?,?,0)');
        for ($number = 1; $number <= 128; ++$number) {
            $training->execute([10000 + $number]);
            $measurement->execute([1000000 + $number]);
            $link->execute([10000 + $number, 1000000 + $number]);
        }
        foreach ([1, 2, 32, 33, 1000000] as $page) {
            self::assertSame(
                \AthleteMeasurementReference::measurements($pdo, 1, '', $page),
                \AthleteProfileRead::measurements($pdo, 1, '', $page)
            );
        }
    }

    public function testSigned64BitKeysAndPositionsRemainExact(): void
    {
        $pdo = $this->database();
        Fixture::seed($pdo, 100);
        $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if ($mysql) {
            $pdo->exec('ALTER TABLE treninky MODIFY id BIGINT NOT NULL;
                ALTER TABLE trenink_mereni MODIFY trenink_id BIGINT,MODIFY mereni_id BIGINT,MODIFY poradi BIGINT;
                ALTER TABLE mereni_zaznamy MODIFY id BIGINT NOT NULL;
                SET SESSION group_concat_max_len=128');
        }
        $train = $pdo->prepare('INSERT INTO treninky(id,datum) VALUES(?,?)');
        $measurement = $pdo->prepare('INSERT INTO mereni_zaznamy(id,sportovec_id,typ) VALUES(?,1,?)');
        $link = $pdo->prepare('INSERT INTO trenink_mereni VALUES(?,?,?)');
        $train->execute(['9223372036854775807', '2035-01-01']);
        $train->execute(['9223372036854775806', '2035-01-01']);
        foreach (['9007199254740992', '9007199254740993', '9223372036854775807', '-9223372036854775808'] as $n => $id) {
            $measurement->execute([$id, 'wide-' . $n]);
            $link->execute(['9223372036854775807', $id, $n < 2 ? '9223372036854775807' : '-9223372036854775808']);
            $link->execute(['9223372036854775806', $id, null]);
        }
        foreach ($mysql ? [false, true] : [false] as $emulate) {
            if ($mysql) {
                $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $emulate);
            }
            foreach (['', 'wide-0', 'wide-2'] as $type) {
                self::assertSame(\AthleteMeasurementReference::measurements($pdo, 1, $type, 1), \AthleteProfileRead::measurements($pdo, 1, $type, 1));
            }
        }
    }
}
