<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\QueryProfilePdo;

require_once dirname(__DIR__) . '/Support/QueryProfilePdo.php';
require_once dirname(__DIR__) . '/Support/RosterReadDatabase.php';
require_once dirname(__DIR__) . '/Support/TrainingRosterFilterFixture.php';
require_once dirname(__DIR__) . '/Support/TrainingRosterFilterBaseline.php';
require_once dirname(__DIR__, 2) . '/includes/training_roster_bridge.php';

final class TrainingRosterFilterReadTest extends TestCase
{
    use \Tests\Support\RosterReadDatabase;

    public function testFiltersMatchHistoricalBaselineAndQueryBudget(): void
    {
        foreach ([0, 1, 12, 52] as $weeks) {
            $pdo = $this->database();
            $rows = \w82FilterFixture($pdo, $weeks);
            foreach ([0, 1, 2, 3, 20, 999] as $team) {
                foreach (['Plan', 'Training'] as $kind) {
                    $baseline = 'w82Filter' . $kind . 'Baseline';
                    $current = 'trainingRosterBridgeFilter' . $kind . 'Rows';
                    $expected = $baseline($pdo, $rows, $team);
                    $pdo->profile = [];
                    $pdo->profiling = true;
                    self::assertSame($expected, $current($pdo, $rows, $team));
                    $pdo->profiling = false;
                    $edges = array_filter($pdo->profile, static fn (array $q): bool => str_contains($q['sql'], 'FROM club_team_edges'));
                    self::assertLessThanOrEqual(1, count($edges), 'Historical edges loaded once per filter.');
                    $links = array_filter($pdo->profile, static fn (array $q): bool => str_contains($q['sql'], 'FROM training_roster_links'));
                    self::assertLessThanOrEqual((int) ceil(count($rows) / 500), count($links));
                }
            }
        }
    }

    public function testFreshCallsCyclesDuplicatesAndMissingEdges(): void
    {
        $pdo = $this->database();
        $rows = \w82FilterFixture($pdo, 52);
        $rows[] = $rows[0];
        unset($rows[2]);
        $pdo->exec('INSERT INTO training_roster_links VALUES(1,NULL,2),(1,NULL,2),(NULL,1,2)');
        foreach ([
            "UPDATE club_team_edges SET valid_to='2026-01-01' WHERE child_team_id=2",
            "INSERT INTO club_team_edges VALUES(20,1,'2026-12-24',NULL)",
            'DROP TABLE club_team_edges',
        ] as $change) {
            $pdo->exec($change);
            foreach (['Plan', 'Training'] as $kind) {
                $baseline = 'w82Filter' . $kind . 'Baseline';
                $current = 'trainingRosterBridgeFilter' . $kind . 'Rows';
                self::assertSame($baseline($pdo, $rows, 1), $current($pdo, $rows, 1));
            }
        }
    }
}
