<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Tests\Support\CoachHistoryFixture as Fixture;
use Tests\Support\QueryProfilePdo;

require_once dirname(__DIR__) . '/Support/QueryProfilePdo.php';
require_once dirname(__DIR__) . '/Support/RosterReadDatabase.php';
require_once dirname(__DIR__) . '/Support/CoachHistoryFixture.php';

final class CoachHistoryReadTest extends TestCase
{
    use \Tests\Support\RosterReadDatabase;

    private function seeded(int $size = 151): QueryProfilePdo
    {
        $pdo = $this->database();
        Fixture::seed($pdo, $size);
        return $pdo;
    }

    public function testEveryPageReconstructsOriginalScopedHistoryAndAllRelatedValues(): void
    {
        $pdo = $this->seeded();
        // Exercise legacy multiplicity and a duplicate ID straddling a page boundary.
        $pdo->exec('INSERT INTO trenink_trener VALUES(130,1),(130,1),(130,1)');
        $pdo->exec('DELETE FROM trenink_trener WHERE trenink_id=151 AND trener_id=1');
        foreach (Fixture::cases($pdo) as [$from, $params]) {
            foreach ([0, 1, 2, 3, 99] as $team) {
                $reference = Fixture::normalize(Fixture::original($pdo, $from, $params, $team));
                $last = \CoachHistoryRead::page($pdo, $from, $params, $team, 1000000);
                self::assertSame(count($reference['rows']), $last['total']);
                self::assertSame($last['pages'], $last['page']);
                $all = [];
                for ($page = 1; $page <= $last['pages']; $page++) {
                    $new = \CoachHistoryRead::page($pdo, $from, $params, $team, $page);
                    $wanted = array_slice($reference['rows'], ($page - 1) * 25, 25);
                    self::assertSame($wanted, $new['rows']);
                    self::assertLessThanOrEqual(25, count($new['rows']));
                    self::assertSame(count($reference['rows']), $new['total']);
                    self::assertSame(Fixture::subset($reference, $wanted), Fixture::normalize(Fixture::related($pdo, $new['rows'])));
                    array_push($all, ...$new['rows']);
                }
                self::assertSame($reference['rows'], $all);
                self::assertSame(1, \CoachHistoryRead::page($pdo, $from, $params, $team, -5)['page']);
            }
        }
    }

    public function testOnlyPageDetailsAreMaterializedAndPredicatesRemainFresh(): void
    {
        $pdo = $this->seeded(1200);
        [$from, $params] = Fixture::cases($pdo)['all'];
        $pdo->profile = [];
        $pdo->profiling = true;
        $first = \CoachHistoryRead::page($pdo, $from, $params, 0, 1);
        $pdo->profiling = false;
        self::assertSame([1200, 25], array_column($pdo->profile, 'fetched_rows'));
        self::assertLessThanOrEqual(26, max(array_map(static fn (array $q): int => count($q['parameters']), $pdo->profile)));
        foreach ($pdo->profile as $q) {
            self::assertStringContainsString('tt.trener_id=:uid', $q['sql']);
        }
        $id = (int)$first['rows'][0]['id'];
        $pdo->exec("UPDATE treninky SET poznamka='Fresh synthetic note' WHERE id=" . $id);
        self::assertSame('Fresh synthetic note', \CoachHistoryRead::page($pdo, $from, $params, 0, 1)['rows'][0]['poznamka']);
        $pdo->exec('DELETE FROM trenink_trener WHERE trenink_id=' . $id . ' AND trener_id=1');
        self::assertSame(1199, \CoachHistoryRead::page($pdo, $from, $params, 0, 1)['total']);
        [$scope, $scopeParams] = Fixture::cases($pdo)['scope'];
        self::assertSame(30, \CoachHistoryRead::page($pdo, $scope, $scopeParams, 0, 1)['total']);
        $scopeParams[':scope_id'] = 3;
        self::assertSame(3, \CoachHistoryRead::page($pdo, $scope, $scopeParams, 0, 1)['total']);
    }
}
