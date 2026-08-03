<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use TrainingRosterBridgeException;

require_once dirname(__DIR__, 2) . '/includes/training_roster_bridge.php';

final class TrainingRosterBridgeTest extends TestCase
{
    public function testOverlappingTeamsYieldOneExpectedPersonAndRespectValidity(): void
    {
        $pdo = $this->database();
        $pdo->exec("INSERT INTO club_roster_members(team_id,sportovec_id,status,source,valid_from,valid_to,created_by_trainer_id) VALUES
            (1,10,'active','manual','2026-01-01',NULL,7),
            (2,10,'active','manual','2026-01-01',NULL,7),
            (1,11,'active','manual','2026-08-11',NULL,7),
            (2,12,'active','manual','2026-01-01','2026-08-09',7)");

        $result = \trainingRosterBridgeReplacePlanTeams($pdo, 100, [1, 2], 7);
        self::assertSame(['link_count' => 2, 'expected_count' => 1], $result);
        self::assertSame([10], array_column(\trainingRosterBridgeExpectedForPlan($pdo, 100), 'id'));
        self::assertSame(2, (int)$pdo->query('SELECT COUNT(*) FROM training_roster_expected WHERE sportovec_id=10')->fetchColumn());
    }

    public function testReplacingBindingsIsIdempotentAndRefreshesSnapshots(): void
    {
        $pdo = $this->database();
        $pdo->exec("INSERT INTO club_roster_members(team_id,sportovec_id,status,source,valid_from,created_by_trainer_id) VALUES(1,10,'active','manual','2026-01-01',7)");
        \trainingRosterBridgeReplacePlanTeams($pdo, 100, [1, 1], 7);
        \trainingRosterBridgeReplacePlanTeams($pdo, 100, [1], 7);
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM training_roster_links WHERE plan_id=100')->fetchColumn());
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM training_roster_expected')->fetchColumn());
        self::assertSame('TEAM-A', $pdo->query('SELECT team_code_snapshot FROM training_roster_links')->fetchColumn());
    }

    public function testPlanningNeverChangesAttendanceAndLegacyPlanCanHaveNoRoster(): void
    {
        $pdo = $this->database();
        $pdo->exec('INSERT INTO trenink_sportovec(trenink_id,sportovec_id) VALUES(200,12)');
        $result = \trainingRosterBridgeReplacePlanTeams($pdo, 100, [], 7);
        self::assertSame(['link_count' => 0, 'expected_count' => 0], $result);
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM trenink_sportovec')->fetchColumn());
        self::assertSame([], \trainingRosterBridgeExpectedForPlan($pdo, 100));
    }

    public function testInactiveOrOutOfSeasonTeamFailsClosedAndPreservesOldLinks(): void
    {
        $pdo = $this->database();
        \trainingRosterBridgeReplacePlanTeams($pdo, 100, [1], 7);
        $pdo->exec("UPDATE club_teams SET status='inactive' WHERE id=2");
        try {
            \trainingRosterBridgeReplacePlanTeams($pdo, 100, [2], 7);
            self::fail('Inactive team accepted.');
        } catch (TrainingRosterBridgeException $exception) {
            self::assertStringContainsString('není aktivní', $exception->getMessage());
        }
        self::assertSame([1], \trainingRosterBridgePlanTeamIds($pdo, 100));
    }

    public function testDatabaseFailureRollsBackReplacement(): void
    {
        $pdo = $this->database();
        \trainingRosterBridgeReplacePlanTeams($pdo, 100, [1], 7);
        $pdo->exec("CREATE TRIGGER reject_team_b BEFORE INSERT ON training_roster_links WHEN NEW.team_id=2 BEGIN SELECT RAISE(ABORT,'simulated'); END");
        try {
            \trainingRosterBridgeReplacePlanTeams($pdo, 100, [2], 7);
            self::fail('Injected database error was ignored.');
        } catch (TrainingRosterBridgeException $exception) {
            self::assertStringContainsString('bez částečného zápisu', $exception->getMessage());
        }
        self::assertSame([1], \trainingRosterBridgePlanTeamIds($pdo, 100));
    }

    public function testActualTrainingCanAlsoCarryRosterSnapshotWithoutWritingAttendance(): void
    {
        $pdo = $this->database();
        $pdo->exec("INSERT INTO club_roster_members(team_id,sportovec_id,status,source,valid_from,created_by_trainer_id) VALUES(1,10,'active','manual','2026-01-01',7)");
        $result = \trainingRosterBridgeReplaceTrainingTeams($pdo, 200, [1], 7);
        self::assertSame(1, $result['expected_count']);
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM trenink_sportovec')->fetchColumn());
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM training_roster_links WHERE trenink_id=200 AND plan_id IS NULL')->fetchColumn());
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT)');
        $pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,narozeni TEXT,uciid TEXT,stav_clenstvi TEXT)');
        $pdo->exec('CREATE TABLE planovane_treninky(id INTEGER PRIMARY KEY,datum TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE treninky(id INTEGER PRIMARY KEY,datum TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE trenink_sportovec(trenink_id INTEGER NOT NULL,sportovec_id INTEGER NOT NULL)');
        $pdo->exec("INSERT INTO treneri VALUES(7,'Admin')");
        $pdo->exec("INSERT INTO sportovci VALUES(10,'Anna','A','2012-01-01','U10','aktivni'),(11,'Běla','B','2013-01-01','U11','aktivni'),(12,'Cyril','C','2014-01-01','U12','aktivni')");
        $pdo->exec("INSERT INTO planovane_treninky VALUES(100,'2026-08-10')");
        $pdo->exec("INSERT INTO treninky VALUES(200,'2026-08-10')");
        $base = require dirname(__DIR__, 2) . '/migrations/20260804090000_kis_teams_rosters.php';
        $base['up']($pdo);
        $pdo->exec("INSERT INTO club_seasons(id,code,name,starts_on,ends_on,status,created_by_trainer_id) VALUES(1,'2026','2026','2026-01-01','2026-12-31','active',7)");
        $pdo->exec("INSERT INTO club_teams(id,season_id,code,name,discipline,age_label,status,created_by_trainer_id) VALUES
            (1,1,'TEAM-A','Tým A','dráha','open','active',7),(2,1,'TEAM-B','Tým B','silnice','open','active',7)");
        $migration = require dirname(__DIR__, 2) . '/migrations/20260804130000_training_roster_bridge.php';
        $migration['up']($pdo);
        $migration['up']($pdo);
        self::assertTrue($migration['verify']($pdo));
        return $pdo;
    }
}
