<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\PlannerHierarchyFixture;
use Tests\Support\QueryProfilePdo;

use function Tests\Support\plannerHierarchyPendingBaseline;

require_once dirname(__DIR__) . '/Support/QueryProfilePdo.php';
require_once dirname(__DIR__) . '/Support/RosterReadDatabase.php';
require_once dirname(__DIR__) . '/Support/PlannerHierarchyFixture.php';
require_once dirname(__DIR__) . '/Support/PlannerHierarchyBaseline.php';
require_once dirname(__DIR__, 2) . '/includes/training_planner_read.php';

final class PlannerHierarchyReadTest extends TestCase
{
    use \Tests\Support\RosterReadDatabase;

    private function seeded(int $plans = 720): QueryProfilePdo
    {
        $pdo = $this->database();
        PlannerHierarchyFixture::seed($pdo, $plans);
        return $pdo;
    }

    public function testAllFiltersAndTemporalGraphMatchFrozenMain(): void
    {
        $pdo = $this->seeded();
        foreach (['2026-01-01', '2026-01-31', '2026-02-01', '2026-02-16', '2026-03-01', '2026-04-01', '2027-01-01'] as $today) {
            foreach ([false, true] as $mine) {
                foreach ([0, 7, 8] as $trainer) {
                    foreach ([0, 1, 99] as $group) {
                        foreach ([0, 1, 2, 3, 4, 9, 10, 11, 999] as $team) {
                            $args = [$pdo, $today, $mine, 7, $trainer, $group, $team];
                            self::assertSame(plannerHierarchyPendingBaseline(...$args), \trainingPlannerPendingSummary(...$args));
                        }
                    }
                }
            }
        }
    }

    public function testLargeHistoryTransfersDatesInsteadOfPlanIdsAndLinks(): void
    {
        $pdo = $this->seeded(3600);
        $pdo->profiling = true;
        $before = plannerHierarchyPendingBaseline($pdo, '2027-01-01', false, 7, 0, 0, 1);
        $rowsBefore = array_sum(array_column($pdo->profile, 'fetched_rows'));
        $pdo->profile = [];
        $after = \trainingPlannerPendingSummary($pdo, '2027-01-01', false, 7, 0, 0, 1);
        self::assertSame($before, $after);
        self::assertLessThan($rowsBefore / 10, array_sum(array_column($pdo->profile, 'fetched_rows')));
        self::assertStringContainsString('EXISTS', end($pdo->profile)['sql']);
        foreach ($pdo->profile as $query) {
            self::assertStringStartsWith('SELECT', $query['sql']);
            self::assertLessThanOrEqual(900, count($query['parameters']));
        }
    }

    public function testSmallAndTransactionReadsKeepCanonicalQueryBudget(): void
    {
        $pdo = $this->seeded(40);
        foreach ([false, true] as $transaction) {
            if ($transaction) {
                $pdo->beginTransaction();
            }
            $pdo->profile = [];
            $pdo->profiling = true;
            $expected = plannerHierarchyPendingBaseline($pdo, '2027-01-01', false, 7, 0, 0, 1);
            $before = $pdo->profile;
            $pdo->profile = [];
            self::assertSame($expected, \trainingPlannerPendingSummary($pdo, '2027-01-01', false, 7, 0, 0, 1));
            self::assertCount(count($before), $pdo->profile);
            if ($transaction) {
                self::assertSame(array_column($before, 'sql'), array_column($pdo->profile, 'sql'));
                self::assertSame(array_column($before, 'parameters'), array_column($pdo->profile, 'parameters'));
                $pdo->rollBack();
            }
            $pdo->profiling = false;
        }
    }

    public function testFreshChangesAndMissingHierarchyAreNeverCached(): void
    {
        $pdo = $this->seeded();
        foreach (["UPDATE club_team_edges SET valid_to='2025-01-01'",
            'DELETE FROM training_roster_links WHERE team_id=1',
            "UPDATE planovane_treninky SET stav='evidovany' WHERE trener_id=7",
            'DROP TABLE club_team_edges'] as $mutation) {
            \trainingPlannerPendingSummary($pdo, '2027-01-01', false, 7, 0, 0, 1);
            $pdo->exec($mutation);
            self::assertSame(
                plannerHierarchyPendingBaseline($pdo, '2027-01-01', false, 7, 0, 0, 1),
                \trainingPlannerPendingSummary($pdo, '2027-01-01', false, 7, 0, 0, 1)
            );
        }
    }

    public function testSiblingsDoNotMatchAndRepeatedLinksCountOnce(): void
    {
        $pdo = $this->seeded(100);
        $pdo->exec("DELETE FROM club_team_edges; INSERT INTO club_team_edges VALUES
            (1,2,'2020-01-01',NULL),(1,3,'2020-01-01',NULL);
            UPDATE training_roster_links SET team_id=3");
        self::assertSame(
            ['count' => 0, 'oldest_date' => null],
            \trainingPlannerPendingSummary($pdo, '2027-01-01', false, 7, 0, 0, 2)
        );
        self::assertSame(
            plannerHierarchyPendingBaseline($pdo, '2027-01-01', false, 7, 0, 0, 1),
            \trainingPlannerPendingSummary($pdo, '2027-01-01', false, 7, 0, 0, 1)
        );
    }

    public function testLargeGraphFallsBackWithinSqlParameterBudget(): void
    {
        $pdo = $this->seeded(100);
        $insert = $pdo->prepare('INSERT INTO club_team_edges VALUES(?,?,?,NULL)');
        for ($i = 20; $i < 540; $i++) {
            $insert->execute([1, $i, '2020-01-01']);
        }
        $pdo->profiling = true;
        $expected = plannerHierarchyPendingBaseline($pdo, '2027-01-01', false, 7, 0, 0, 1);
        $pdo->profile = [];
        self::assertSame($expected, \trainingPlannerPendingSummary($pdo, '2027-01-01', false, 7, 0, 0, 1));
        self::assertFalse((bool) array_filter($pdo->profile, static fn (array $q): bool => str_contains($q['sql'], 'EXISTS')));
    }

    public function testMalformedCandidateKeepsCanonicalException(): void
    {
        $pdo = $this->seeded(100);
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $pdo->exec("SET SESSION sql_mode='ALLOW_INVALID_DATES'");
        }
        $pdo->exec("UPDATE planovane_treninky SET datum='2026-02-31' WHERE id=1");
        $this->expectException(\InvalidArgumentException::class);
        \trainingPlannerPendingSummary($pdo, '2027-01-01', false, 7, 0, 0, 1);
    }

    public function testLongDailyHistoryFallsBackWithoutLosingTheOldestPlan(): void
    {
        $pdo = $this->seeded(4100);
        $update = $pdo->prepare('UPDATE planovane_treninky SET datum=? WHERE id=?');
        $start = new \DateTimeImmutable('2010-01-01');
        $pdo->beginTransaction();
        for ($i = 1; $i <= 4100; $i++) {
            $update->execute([$start->modify('+' . $i . ' days')->format('Y-m-d'), $i]);
        }
        // Ensure all dates remain candidates, including case-insensitive native status values.
        $pdo->exec("UPDATE planovane_treninky SET stav='planovany'");
        $pdo->commit();
        $expected = plannerHierarchyPendingBaseline($pdo, '2027-01-01', false, 7, 0, 0, 1);
        $pdo->profile = [];
        $pdo->profiling = true;
        self::assertSame($expected, \trainingPlannerPendingSummary($pdo, '2027-01-01', false, 7, 0, 0, 1));
        $dateQueries = array_values(array_filter($pdo->profile, static fn (array $q): bool => str_contains($q['sql'], 'SELECT DISTINCT pt.datum')));
        self::assertCount(1, $dateQueries);
        self::assertSame(4097, $dateQueries[0]['fetched_rows']);
    }
}
