<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\QueryProfilePdo;

require_once dirname(__DIR__) . '/Support/QueryProfilePdo.php';
require_once dirname(__DIR__) . '/Support/FamilyCalendarReadBaseline.php';
require_once dirname(__DIR__) . '/Support/FamilyCalendarReadFixture.php';

final class FamilyCalendarReadOptimizationTest extends TestCase
{
    /** @var list<string> */
    private array $schemas = [];
    private ?PDO $admin = null;

    private function createDatabase(): QueryProfilePdo
    {
        if (getenv('EVIDENCE_CALENDAR_READ_MARIADB') === '1') {
            $port = getenv('EVIDENCE_MARIADB_SMOKE_PORT') ?: '3306';
            if (!ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) {
                throw new \RuntimeException('Invalid isolated localhost port.');
            }
            $dsn = 'mysql:host=127.0.0.1;port=' . $port . ';charset=utf8mb4';
            $this->admin ??= new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $schema = 'w83_contract_' . bin2hex(random_bytes(8));
            $this->admin->exec('CREATE DATABASE `' . $schema . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $this->schemas[] = $schema;
            $pdo = new QueryProfilePdo($dsn . ';dbname=' . $schema, 'root', '');
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } else {
            $pdo = new QueryProfilePdo('sqlite::memory:');
        }
        return $pdo;
    }

    protected function tearDown(): void
    {
        foreach ($this->schemas as $schema) {
            $this->admin?->exec('DROP DATABASE `' . $schema . '`');
        }
    }

    private function database(): QueryProfilePdo
    {
        $pdo = $this->createDatabase();
        \w83CalendarFixture($pdo);
        $pdo->exec('CREATE TABLE club_team_edges(parent_team_id INT,child_team_id INT,valid_from DATE,valid_to DATE)');
        $pdo->exec("INSERT INTO club_team_edges VALUES(300,100,'2025-01-01','2026-06-30'),"
            . "(100,300,'2026-02-01','2026-03-31')");
        $plan = $pdo->prepare("INSERT INTO planovane_treninky VALUES(?,?,'16:00','17:00','Synthetic','draha',1,1,'planovany')");
        $link = $pdo->prepare('INSERT INTO training_roster_links VALUES(?,?,?,?)');
        for ($week = 0; $week < 52; $week++) {
            $date = (new \DateTimeImmutable('2026-01-01'))->modify('+' . ($week * 7) . ' days')->format('Y-m-d');
            foreach ([100, 200, 300, 400] as $team) {
                $id = 1000 + $week * 4 + intdiv($team, 100);
                $plan->execute([$id, $date]);
                $link->execute([$id, $id, $team, 'Synthetic team']);
            }
        }
        return $pdo;
    }

    public function testCalendarMatchesBaselineWithBoundedMembershipReads(): void
    {
        $pdo = $this->database();
        $expected = \w83FamilyCalendarBaseline($pdo, 1, '2026-01-01', '2026-12-31');
        $pdo->profiling = true;
        $actual = \familyCalendarItems($pdo, 1, '2026-01-01', '2026-12-31');
        self::assertSame($expected, $actual);
        $membership = array_filter($pdo->profile, static fn (array $q): bool => str_contains($q['sql'], 'FROM club_roster_members'));
        self::assertLessThanOrEqual(1, count($membership));
        $edges = array_filter($pdo->profile, static fn (array $q): bool => str_contains($q['sql'], 'FROM club_team_edges'));
        self::assertLessThanOrEqual(1, count($edges));
        $pdo->profiling = false;
        $now = new \DateTimeImmutable('2026-10-30T12:00:00Z');
        self::assertSame(\publicCalendarRender($expected, $now, 'Synthetic'), \publicCalendarRender($actual, $now, 'Synthetic'));
        self::assertStringNotContainsString('Cizí Osoba', json_encode($actual, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    public function testRevocationAndHistoricalChangesAreFresh(): void
    {
        $pdo = $this->database();
        foreach ([
            "UPDATE club_roster_members SET status='removed',valid_to='2026-06-30' WHERE sportovec_id=10",
            "UPDATE club_team_edges SET valid_to='2026-01-01' WHERE parent_team_id=300",
            'DROP TABLE club_team_edges',
            "UPDATE account_person_roles SET status='revoked' WHERE account_id=1",
            'UPDATE verejni_uzivatele SET email_overeno=0 WHERE id=1',
        ] as $sql) {
            $pdo->exec($sql);
            self::assertSame(
                \w83FamilyCalendarBaseline($pdo, 1, '2026-01-01', '2026-12-31'),
                \familyCalendarItems($pdo, 1, '2026-01-01', '2026-12-31')
            );
        }
        self::assertSame([], \familyCalendarItems($pdo, 1, '2026-01-01', '2026-12-31'));
    }

    public function testOverlappingEventUsesMembershipAtItsOriginalStartBeforeRequestedRange(): void
    {
        $pdo = $this->database();
        $pdo->exec('ALTER TABLE club_events ADD visibility TEXT');
        $pdo->exec('ALTER TABLE club_events ADD planning_status TEXT');
        $pdo->exec('ALTER TABLE club_events ADD status TEXT');
        $pdo->exec('CREATE TABLE club_event_roster_targets(event_id INT,team_id INT)');
        $pdo->exec("INSERT INTO club_events VALUES(3,'Long historical event','rosters','planned','active')");
        $pdo->exec("INSERT INTO club_event_sessions VALUES(3,3,'2025-12-01 10:00:00','2026-02-01 10:00:00','Synthetic','scheduled')");
        $pdo->exec('INSERT INTO club_event_roster_targets VALUES(3,300)');
        $pdo->exec("UPDATE club_roster_members SET valid_from='2025-01-01',valid_to='2025-12-31',status='removed' WHERE sportovec_id=10");
        $actual = \familyCalendarItems($pdo, 1, '2026-01-01', '2026-01-31');
        self::assertSame(\w83FamilyCalendarBaseline($pdo, 1, '2026-01-01', '2026-01-31'), $actual);
        self::assertContains('family-plan-3-3-10', array_column($actual, 'uid'));
    }

    public function testProjectionBatchesOnlyExplicitPeopleAndKeepsHistoricalStates(): void
    {
        $pdo = $this->database();
        $insert = $pdo->prepare('INSERT INTO club_roster_members VALUES(?,100,?,?,?,?)');
        for ($i = 1; $i <= 401; $i++) {
            $insert->execute([10000 + $i, 10000 + $i, $i % 3 ? 'active' : 'removed', '2025-01-01',
                $i % 3 ? null : '2026-06-30']);
        }
        foreach ([0, 1, 400, 401] as $size) {
            $ids = $size ? range(10001, 10000 + $size) : [];
            $pdo->profile = [];
            $pdo->profiling = true;
            $projection = new \RosterMembershipReadProjection($pdo, [...$ids, ...$ids], null, '2026-12-31');
            $actual = [];
            foreach (['2026-06-30', '2026-07-01'] as $date) {
                $actual[$date] = array_keys($projection->membersForTarget(100, $date));
                sort($actual[$date]);
            }
            $pdo->profiling = false;
            $membership = array_filter($pdo->profile, static fn (array $q): bool => str_contains($q['sql'], 'FROM club_roster_members'));
            self::assertCount((int) ceil($size / 400), $membership);
            foreach ($actual as $date => $people) {
                $expected = array_values(array_intersect(\kisRosterEffectiveMemberIds($pdo, 100, $date), $ids));
                sort($expected);
                self::assertSame($expected, $people);
            }
        }
    }

    public function testLegacyExplicitZeroIdKeepsTheExistingFeedContract(): void
    {
        $pdo = $this->database();
        $pdo->exec("INSERT INTO sportovci VALUES(0,'Legacy','Explicit','2012-01-01','aktivni')");
        $pdo->exec("INSERT INTO account_person_roles VALUES(3,1,0,'guardian','approved','2020-01-01',NULL)");
        $pdo->exec("INSERT INTO club_roster_members VALUES(3,100,0,'active','2026-01-01',NULL)");
        self::assertSame(
            \w83FamilyCalendarBaseline($pdo, 1, '2026-01-01', '2026-12-31'),
            \familyCalendarItems($pdo, 1, '2026-01-01', '2026-12-31')
        );
    }
}
