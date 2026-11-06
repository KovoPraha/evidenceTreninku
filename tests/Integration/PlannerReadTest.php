<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Tests\Support\PlannerReadBaseline;
use Tests\Support\PlannerReadFixture;
use Tests\Support\QueryProfilePdo;

require_once dirname(__DIR__, 2) . '/includes/training_planner_read.php';

final class PlannerReadTest extends TestCase
{
    private function database(): QueryProfilePdo
    {
        $pdo = new QueryProfilePdo('sqlite::memory:');
        PlannerReadFixture::seed($pdo);
        return $pdo;
    }

    public function testBatchSummariesMatchCanonicalOverviewsAtEveryPlanDate(): void
    {
        $pdo = $this->database();
        foreach (PlannerReadFixture::summaryInputs() as $ids) {
            self::assertSame(trainingRsvpPlanSummaries($pdo, $ids), trainingPlannerRsvpSummaries($pdo, $ids));
        }
        self::assertSame(['expected' => 3, 'going' => 1, 'not_going' => 1, 'pending' => 1], trainingPlannerRsvpSummaries($pdo, [101])[101]);
        self::assertSame(['expected' => 3, 'going' => 1, 'not_going' => 1, 'pending' => 1], trainingPlannerRsvpSummaries($pdo, [102])[102]);
        self::assertStringNotContainsString('PRIVATE SENTINEL', json_encode(trainingPlannerRsvpSummaries($pdo, [101, 102]), JSON_THROW_ON_ERROR));
    }

    public function testPendingFiltersAndHistoryMatchTheOriginalRead(): void
    {
        $pdo = $this->database();
        foreach (['2026-01-01', '2026-03-01', '2027-01-01'] as $date) {
            foreach ([false, true] as $mine) {
                foreach ([0, 7, 8] as $trainer) {
                    foreach ([0, 1, 2] as $group) {
                        foreach ([0, 1, 2, 3] as $team) {
                            $args = [$pdo, $date, $mine, 7, $trainer, $group, $team];
                            self::assertSame(PlannerReadBaseline::pending(...$args), trainingPlannerPendingSummary(...$args));
                        }
                    }
                }
            }
        }
        $pdo->profile = [];
        $pdo->profiling = true;
        trainingPlannerPendingSummary($pdo, '2027-01-01', false, 7, 0, 0, 0);
        self::assertCount(1, $pdo->profile);
        self::assertSame(1, $pdo->profile[0]['fetched_rows']);
    }

    public function testNoCachedMembershipResponsesHierarchyOrSchemaBetweenCalls(): void
    {
        $pdo = $this->database();
        trainingPlannerRsvpSummaries($pdo, [101, 102]);
        $pdo->exec("UPDATE training_rsvps SET response='not_going';UPDATE club_roster_members SET status='cancelled' WHERE sportovec_id=7;
            UPDATE club_team_edges SET valid_to='2025-01-01' WHERE parent_team_id=1");
        self::assertSame(trainingRsvpPlanSummaries($pdo, [101, 102]), trainingPlannerRsvpSummaries($pdo, [101, 102]));
        $pdo->beginTransaction();
        self::assertSame(trainingRsvpPlanSummaries($pdo, [101, 103]), trainingPlannerRsvpSummaries($pdo, [101, 103]));
        $pdo->rollBack();
        $pdo->exec('DROP TABLE training_rsvps');
        self::assertSame([], trainingPlannerRsvpSummaries($pdo, [101]));
    }

    public function testMissingPeopleTablePreservesZeroEntriesAndInvalidDatesCloseMetadataScope(): void
    {
        $pdo = $this->database();
        $pdo->exec("UPDATE planovane_treninky SET datum='invalid' WHERE id=101");
        try {
            trainingPlannerRsvpSummaries($pdo, [101]);
            self::fail('Invalid linked plan date accepted');
        } catch (InvalidArgumentException) {
            self::assertTrue(true);
        }
        $pdo->exec('DROP TABLE sportovci');
        self::assertSame(trainingRsvpPlanSummaries($pdo, [101, 999]), trainingPlannerRsvpSummaries($pdo, [101, 999]));
    }

    public function testQueryCountDoesNotGrowPerPlanAndCountsDoNotReadNamesOrNotes(): void
    {
        $pdo = $this->database();
        $pdo->profiling = true;
        trainingPlannerRsvpSummaries($pdo, [101]);
        $one = count($pdo->profile);
        $pdo->profile = [];
        trainingPlannerRsvpSummaries($pdo, [101, 102, 103, 104, 105, 106, 107, 999]);
        self::assertCount($one, $pdo->profile);
        foreach ($pdo->profile as $query) {
            self::assertStringStartsWith('SELECT', $query['sql']);
            self::assertStringNotContainsString('member_note', $query['sql']);
            self::assertStringNotContainsString('prijmeni', $query['sql']);
        }
    }

    public function testLargeInputUsesBoundedChunksAndPreservesMissingPlanEntries(): void
    {
        $pdo = $this->database();
        $pdo->profiling = true;
        $ids = [101, ...range(2000, 2600)];
        $result = trainingPlannerRsvpSummaries($pdo, $ids);
        self::assertSame($ids, array_keys($result));
        self::assertSame(3, $result[101]['expected']);
        self::assertSame(['expected' => 0, 'going' => 0, 'not_going' => 0, 'pending' => 0], $result[2600]);
        self::assertLessThanOrEqual(14, count($pdo->profile));
        foreach ($pdo->profile as $query) {
            self::assertLessThanOrEqual(500, count($query['parameters']));
        }
    }
}
