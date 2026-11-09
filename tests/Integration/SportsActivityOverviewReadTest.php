<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Tests\Support\SportsActivityOverviewFixture as Fixture;

require_once dirname(__DIR__) . '/Support/QueryProfilePdo.php';
require_once dirname(__DIR__) . '/Support/RosterReadDatabase.php';
require_once dirname(__DIR__) . '/Support/SportsActivityOverviewFixture.php';
require_once dirname(__DIR__, 2) . '/includes/sports_activity_overview_read.php';

final class SportsActivityOverviewReadTest extends TestCase
{
    use \Tests\Support\RosterReadDatabase;

    public function testOverviewExactlyMatchesCanonicalCurrentActivitiesAndLeavesFullSnapshotsIntact(): void
    {
        $pdo = $this->database();
        Fixture::seed($pdo);
        foreach ([1, 2, 99] as $person) {
            foreach ([['2026-01-01', '2026-12-31'], ['2026-01-01', '2026-01-01'],
                ['2026-12-31', '2026-12-31'], ['2025-12-31', '2027-01-01'], ['2099-01-01', '2099-12-31']] as [$from, $to]) {
                $full = \sportsActivitySummary($pdo, $person, $from, $to);
                $hash = \sportsActivityHash($full);
                self::assertSame(
                    array_intersect_key($full, array_flip(['training_count', 'training_minutes', 'race_count'])),
                    \SportsActivityOverviewRead::totals($pdo, $person, $from, $to)
                );
                self::assertSame($hash, \sportsActivityHash(\sportsActivitySummary($pdo, $person, $from, $to)));
                if ($person === 1 && $from === '2026-01-01') {
                    self::assertCount(100, $full['measurements']);
                    self::assertStringContainsString('2 okruhy', \sportsActivityMeasurementsCsv($full));
                }
            }
        }
        $pdo->profile = [];
        $pdo->profiling = true;
        \SportsActivityOverviewRead::totals($pdo, 1, '2026-01-01', '2026-12-31');
        $pdo->profiling = false;
        self::assertNotEmpty($pdo->profile);
        foreach ($pdo->profile as $query) {
            self::assertStringNotContainsString('mereni_zaznamy', $query['sql']);
            self::assertMatchesRegularExpression('/^\s*(SELECT|SHOW)\b/i', $query['sql']);
        }
        $pdo->exec("UPDATE training_closures SET status='void'; UPDATE sports_event_participation SET current_revision_id=NULL");
        self::assertSame(
            ['training_count' => 0, 'training_minutes' => 0, 'race_count' => 0],
            \SportsActivityOverviewRead::totals($pdo, 1, '2026-01-01', '2026-12-31')
        );
    }

    public function testInvalidInputsAndCorruptCurrentJsonRemainRejected(): void
    {
        $pdo = $this->database();
        Fixture::seed($pdo, 1);
        foreach ([[0, '2026-01-01', '2026-12-31'], [1, 'not-a-date', '2026-12-31'],
            [1, '2026-12-31', '2026-01-01']] as [$person, $from, $to]) {
            foreach (['sportsActivitySummary', [\SportsActivityOverviewRead::class, 'totals']] as $reader) {
                try {
                    $reader($pdo, $person, $from, $to);
                    self::fail('Invalid input accepted.');
                } catch (\InvalidArgumentException $error) {
                    self::assertNotSame('', $error->getMessage());
                }
            }
        }
        $pdo->exec("UPDATE sports_event_revisions SET snapshot_json='invalid-json' WHERE stage='started'");
        foreach (['sportsActivitySummary', [\SportsActivityOverviewRead::class, 'totals']] as $reader) {
            try {
                $reader($pdo, 1, '2025-01-01', '2027-12-31');
                self::fail('Corrupt snapshot accepted.');
            } catch (\JsonException) {
                self::assertTrue(true);
            }
        }
    }
}
