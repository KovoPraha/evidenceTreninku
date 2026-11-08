<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Tests\Support\AthleteProfileFixture as Fixture;

require_once dirname(__DIR__) . '/Support/QueryProfilePdo.php';
require_once dirname(__DIR__) . '/Support/RosterReadDatabase.php';
require_once dirname(__DIR__) . '/Support/AthleteProfileFixture.php';

final class AthleteProfileReadTest extends TestCase
{
    use \Tests\Support\RosterReadDatabase;

    public function testEveryHistoryAndMeasurementPageReconstructsOriginalRowsAndSummaries(): void
    {
        $pdo = $this->database();
        Fixture::seed($pdo);
        foreach ([1, 2, 99] as $sid) {
            foreach ([0, 2024, 2025, 2026, 2099] as $year) {
                $old = Fixture::originalHistory($pdo, $sid, $year);
                $last = \AthleteProfileRead::history($pdo, $sid, $year, 1000000);
                $all = [];
                for ($p = 1; $p <= $last['pages']; $p++) {
                    $new = \AthleteProfileRead::history($pdo, $sid, $year, $p);
                    $rows = array_slice($old['rows'], ($p - 1) * 25, 25);
                    self::assertEquals(Fixture::comparable(Fixture::subset($old, $rows)), Fixture::comparable($new));
                    // Values and PHP scalar types, including notes and measurements, remain exact.
                    self::assertSame($rows, $new['rows']);
                    self::assertSame($old['minutes'], $new['minutes']);
                    foreach (['notes', 'measurements'] as $field) {
                        self::assertSame(Fixture::comparable(Fixture::subset($old, $rows))[$field], Fixture::comparable($new)[$field]);
                    }
                    array_push($all, ...$new['rows']);
                }
                self::assertSame($old['rows'], $all);
                self::assertSame($last['pages'], $last['page']);
            }
            $old = Fixture::originalHistory($pdo, $sid, 0);
            self::assertSame(count($old['rows']), \AthleteProfileRead::countTrainings($pdo, $sid));
            $years = array_values(array_unique(array_map(static fn ($r) => (int) substr($r['datum'], 0, 4), $old['rows'])));
            rsort($years);
            self::assertSame($years, array_map('intval', \AthleteProfileRead::years($pdo, $sid)));
            $measurements = Fixture::originalMeasurements($pdo, $sid);
            self::assertSame(count($measurements), \AthleteProfileRead::countMeasurements($pdo, $sid));
            $types = array_count_values(array_column($measurements, 'typ'));
            foreach (array_unique(['', 'KOLO', 'kolo ', 'unknown', 'absent', ...array_keys($types)]) as $type) {
                $type = (string) $type;
                $expected = array_values(array_filter($measurements, static fn ($r) => $type === '' || $r['typ'] === $type));
                $last = \AthleteProfileRead::measurements($pdo, $sid, $type, 1000000);
                $all = [];
                for ($p = 1; $p <= $last['pages']; $p++) {
                    $new = \AthleteProfileRead::measurements($pdo, $sid, $type, $p);
                    self::assertSame($types, $new['types']);
                    self::assertSame(count($expected), $new['total']);
                    self::assertSame(array_slice($expected, ($p - 1) * 25, 25), $new['rows']);
                    array_push($all, ...$new['rows']);
                }
                self::assertSame($expected, $all);
            }
        }
    }

    public function testAttendanceOnlyMeasurementOnlyAndForeignOrOrphanLinks(): void
    {
        $pdo = $this->database();
        Fixture::seed($pdo, 30);
        // Attendance only, measurement only, foreign only, and neither.
        $pdo->exec('DELETE FROM trenink_mereni WHERE trenink_id IN(1,3,4);
            DELETE FROM trenink_sportovec WHERE sportovec_id=1 AND trenink_id IN(2,3,4)');
        $old = Fixture::originalHistory($pdo, 1, 0);
        $ids = array_map('intval', array_column($old['rows'], 'id'));
        self::assertContains(1, $ids);
        self::assertContains(2, $ids);
        self::assertNotContains(3, $ids);
        self::assertNotContains(4, $ids);
        $new = \AthleteProfileRead::history($pdo, 1, 0, 1);
        self::assertSame(Fixture::comparable(Fixture::subset($old, array_slice($old['rows'], 0, 25))), Fixture::comparable($new));
        self::assertSame(count($ids), \AthleteProfileRead::countTrainings($pdo, 1));
    }

    public function testNativeBindingsBoundedDetailsAndFreshData(): void
    {
        $pdo = $this->database();
        Fixture::seed($pdo, 1000);
        if ($pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
        }
        $pdo->profile = [];
        $pdo->profiling = true;
        $first = \AthleteProfileRead::history($pdo, 1, 0, 1);
        $pdo->profiling = false;
        self::assertSame([1000, 25, 25], array_slice(array_column($pdo->profile, 'fetched_rows'), 0, 3));
        self::assertLessThanOrEqual(28, max(array_map(static fn ($q) => count($q['parameters']), $pdo->profile)));
        $id = (int) $first['rows'][0]['id'];
        $pdo->exec("UPDATE treninky SET napln='Fresh content' WHERE id=" . $id);
        self::assertSame('Fresh content', \AthleteProfileRead::history($pdo, 1, 0, 1)['rows'][0]['napln']);
        $pdo->exec('DELETE FROM trenink_sportovec WHERE trenink_id=' . $id . ' AND sportovec_id=1');
        $pdo->exec('DELETE FROM trenink_mereni WHERE trenink_id=' . $id);
        self::assertSame(999, \AthleteProfileRead::history($pdo, 1, 0, 1)['total']);
        self::assertSame(1, \AthleteProfileRead::requested(['profile_page' => []]));
        self::assertSame(1000000, \AthleteProfileRead::requested(['profile_page' => PHP_INT_MAX]));
        self::assertSame(1, \AthleteProfileRead::history($pdo, 99, 0, -1)['page']);
    }
}
