<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\QueryProfilePdo;

require_once dirname(__DIR__) . '/Support/QueryProfilePdo.php';
require_once dirname(__DIR__) . '/Support/AthleteTimelineFixture.php';
require_once dirname(__DIR__) . '/Support/AthleteTimelineBaseline.php';
require_once dirname(__DIR__, 2) . '/includes/athlete_training_timeline.php';

final class AthleteTimelineReadOptimizationTest extends TestCase
{
    /** @var list<string> */
    private array $schemas = [];
    private ?PDO $admin = null;

    private function database(int $weeks = 52): QueryProfilePdo
    {
        if (getenv('EVIDENCE_TIMELINE_MARIADB') === '1') {
            $port = getenv('EVIDENCE_MARIADB_SMOKE_PORT') ?: '3306';
            if (!ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) {
                throw new \RuntimeException('Invalid isolated localhost port.');
            }
            $dsn = 'mysql:host=127.0.0.1;port=' . $port . ';charset=utf8mb4';
            $this->admin ??= new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $schema = 'w81_contract_' . bin2hex(random_bytes(8));
            $this->admin->exec('CREATE DATABASE `' . $schema . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $this->schemas[] = $schema;
            $pdo = new QueryProfilePdo($dsn . ';dbname=' . $schema, 'root', '');
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } else {
            $pdo = new QueryProfilePdo('sqlite::memory:');
        }
        \Tests\Support\athleteTimelineFixture($pdo, $weeks);
        return $pdo;
    }

    protected function tearDown(): void
    {
        foreach ($this->schemas as $schema) {
            $this->admin?->exec('DROP DATABASE `' . $schema . '`');
        }
    }

    public function testTimelineMatchesHistoricalBaselineAcrossWeeks(): void
    {
        foreach ([0, 1, 12, 52] as $weeks) {
            $pdo = $this->database($weeks);
            self::assertSame(\w81TimelineBaseline($pdo, 8, 2026), \athleteTrainingTimeline($pdo, 8, 2026));
            self::assertSame([], \athleteTrainingTimeline($pdo, 999, 2026));
        }
    }

    public function testMembershipQueriesStayBoundedAcrossWholeYear(): void
    {
        $pdo = $this->database();
        $pdo->profiling = true;
        $items = \athleteTrainingTimeline($pdo, 8, 2026);
        self::assertNotEmpty($items);
        $membership = array_filter($pdo->profile, static fn (array $q): bool =>
            str_contains($q['sql'], 'FROM club_roster_members') || str_contains($q['sql'], 'FROM club_team_edges'));
        self::assertLessThanOrEqual(2, count($membership), 'Load historical membership and hierarchy once per projection.');
    }

    public function testChangesAndOptionalTablesRemainVisibleOnNextCall(): void
    {
        $pdo = $this->database(12);
        foreach ([
            "UPDATE club_roster_members SET status='cancelled' WHERE team_id=3",
            "UPDATE club_team_edges SET valid_to='2026-01-31' WHERE parent_team_id=1",
            'DROP TABLE club_team_edges',
            'DROP TABLE club_roster_members',
            'DROP TABLE training_rsvps',
            'DROP TABLE trenink_sportovec',
        ] as $change) {
            $pdo->exec($change);
            self::assertSame(\w81TimelineBaseline($pdo, 8, 2026), \athleteTrainingTimeline($pdo, 8, 2026));
        }
    }

    public function testStatesNullsAndInclusiveDateBoundariesMatchBaseline(): void
    {
        $pdo = $this->database(52);
        $pdo->exec('DELETE FROM club_roster_members');
        $insert = $pdo->prepare('INSERT INTO club_roster_members VALUES(3,8,?,?,?)');
        foreach (['active', 'removed', 'cancelled', 'ACTIVE', 'REMOVED', null] as $status) {
            foreach ([null, '2026-01-01', '2026-06-25', '2026-12-31'] as $end) {
                $pdo->exec('DELETE FROM club_roster_members');
                $insert->execute([$status, '2026-06-25', $end]);
                self::assertSame(\w81TimelineBaseline($pdo, 8, 2026), \athleteTrainingTimeline($pdo, 8, 2026));
            }
        }
    }

    public function testClosedActualsAndDirectAttendanceKeepTheirDistinctMeaning(): void
    {
        $pdo = $this->database(12);
        $pdo->exec('CREATE TABLE training_closures(status TEXT,current_revision_id INT)');
        $pdo->exec('CREATE TABLE training_closure_revisions(id INT,actual_date DATE,training_id INT,snapshot_json TEXT,'
            . 'version INT,closed_at TEXT,closed_by INT)');
        $insert = $pdo->prepare('INSERT INTO training_closure_revisions VALUES(?,?,?,?,?,?,?)');
        $insert->execute([1, '2026-12-31', 99999,
            json_encode(['training_id' => 99999, 'participant_ids' => [8], 'duration_minutes' => 90], JSON_THROW_ON_ERROR),
            2, '2026-12-31 18:00:00', 7]);
        $pdo->exec("INSERT INTO training_closures VALUES('closed',1)");
        $expected = \w81TimelineBaseline($pdo, 8, 2026);
        self::assertSame($expected, \athleteTrainingTimeline($pdo, 8, 2026));
        self::assertTrue($expected[0]['attendance_recorded']);
        self::assertTrue($expected[0]['attended']);
        self::assertSame(2, $expected[0]['closure_version']);
        $pdo->exec("UPDATE training_closures SET status='draft'");
        $actual = \athleteTrainingTimeline($pdo, 8, 2026);
        self::assertSame(\w81TimelineBaseline($pdo, 8, 2026), $actual);
        self::assertTrue($actual[0]['attendance_recorded']);
        self::assertFalse($actual[0]['attended']);
    }

    public function testIndependentConnectionsPeopleAndYearBoundaries(): void
    {
        $first = $this->database(1);
        $second = $this->database(1);
        $second->exec('DELETE FROM club_roster_members');
        $first->exec("INSERT INTO club_roster_members VALUES(NULL,8,'active','2026-01-01',NULL),"
            . "(3,999,'active','2025-01-01','2025-12-31'),(3,999,'active','2027-01-01',NULL)");
        foreach ([$first, $second, $first] as $pdo) {
            foreach ([8, 999] as $person) {
                foreach ([2025, 2026, 2027] as $year) {
                    self::assertSame(\w81TimelineBaseline($pdo, $person, $year), \athleteTrainingTimeline($pdo, $person, $year));
                }
            }
        }
    }
}
