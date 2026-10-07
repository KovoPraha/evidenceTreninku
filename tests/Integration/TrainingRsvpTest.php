<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use TrainingRsvpException;

require_once dirname(__DIR__, 2) . '/includes/training_rsvp.php';

final class TrainingRsvpTest extends TestCase
{
    public function testGuardianAndAthleteCanAnswerOnlyForTheirUpcomingRosterTraining(): void
    {
        $pdo = $this->database();
        $rows = \trainingRsvpUpcomingForAccount($pdo, 100, date('Y-m-d'), date('Y-m-d', strtotime('+30 days')));
        self::assertCount(1, $rows);
        self::assertSame(10, (int)$rows[0]['sportovec_id']);

        $first = \trainingRsvpSave($pdo, 100, 10, 'going', 'account', 100);
        self::assertTrue($first['changed']);
        self::assertSame('going', $first['response']);
        $again = \trainingRsvpSave($pdo, 100, 10, 'not_going', 'account', 100);
        self::assertTrue($again['changed']);
        self::assertSame(2, (int)$pdo->query('SELECT COUNT(*) FROM training_rsvp_events')->fetchColumn());

        $athlete = \trainingRsvpSave($pdo, 100, 10, 'going', 'athlete', 500);
        self::assertTrue($athlete['changed']);
        self::assertSame(3, (int)$pdo->query('SELECT COUNT(*) FROM training_rsvp_events')->fetchColumn());
        self::assertSame('athlete', $pdo->query('SELECT actor_type FROM training_rsvp_events ORDER BY id DESC LIMIT 1')->fetchColumn());
    }

    public function testUnrelatedAccountAndPastTrainingAreRejected(): void
    {
        $pdo = $this->database();
        try {
            \trainingRsvpSave($pdo, 100, 10, 'going', 'account', 101);
            self::fail('Unrelated account was allowed to answer.');
        } catch (TrainingRsvpException $exception) {
            self::assertStringContainsString('oprávnění', $exception->getMessage());
        }

        try {
            \trainingRsvpSave($pdo, 101, 10, 'going', 'account', 100);
            self::fail('Past training was allowed to accept an answer.');
        } catch (TrainingRsvpException $exception) {
            self::assertStringContainsString('není dostupný', $exception->getMessage());
        }
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM training_rsvps')->fetchColumn());
    }

    public function testTrainerOverviewCollapsesOverlappingTeamLinksAndCountsResponses(): void
    {
        $pdo = $this->database();
        $pdo->exec("INSERT INTO club_roster_members VALUES(3,3,10,'active','2020-01-01',NULL)");
        $pdo->exec("INSERT INTO training_roster_links(id,plan_id,team_id,team_name_snapshot) VALUES(2,100,3,'Tým B')");
        $pdo->exec('INSERT INTO training_roster_expected(id,link_id,sportovec_id) VALUES(2,2,10)');
        self::assertCount(1, \trainingRsvpUpcomingForAccount($pdo, 100, date('Y-m-d'), date('Y-m-d', strtotime('+30 days'))));
        \trainingRsvpSave($pdo, 100, 10, 'going', 'account', 100);

        $overview = \trainingRsvpPlanOverview($pdo, 100);
        self::assertCount(1, $overview);
        self::assertSame(10, (int)$overview[0]['sportovec_id']);
        $summary = \trainingRsvpPlanSummaries($pdo, [100, 100]);
        self::assertSame(['expected' => 1, 'going' => 1, 'not_going' => 0, 'pending' => 0], $summary[100]);
    }

    public function testMigrationIsIdempotent(): void
    {
        $pdo = $this->database(false);
        $migration = require dirname(__DIR__, 2) . '/migrations/20261007120000_training_rsvps.php';
        $migration['up']($pdo);
        $migration['up']($pdo);
        self::assertTrue($migration['verify']($pdo));
    }

    private function database(bool $applyMigration = true): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,narozeni TEXT,stav_clenstvi TEXT)');
        $pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,aktivni INTEGER,email_overeno INTEGER)');
        $pdo->exec('CREATE TABLE account_person_roles(id INTEGER PRIMARY KEY,account_id INTEGER,sportovec_id INTEGER,relation_role TEXT,status TEXT,valid_from TEXT,valid_to TEXT)');
        $pdo->exec('CREATE TABLE child_access_accounts(id INTEGER PRIMARY KEY,sportovec_id INTEGER,login_name TEXT,session_version INTEGER,active INTEGER)');
        $pdo->exec('CREATE TABLE planovane_treninky(id INTEGER PRIMARY KEY,nazev TEXT,datum TEXT,cas_od TEXT,cas_do TEXT,popis TEXT,stav TEXT)');
        $pdo->exec('CREATE TABLE club_roster_members(id INTEGER PRIMARY KEY,team_id INTEGER,sportovec_id INTEGER,status TEXT,valid_from TEXT,valid_to TEXT)');
        $pdo->exec('CREATE TABLE training_roster_links(id INTEGER PRIMARY KEY,plan_id INTEGER,team_id INTEGER,team_name_snapshot TEXT)');
        $pdo->exec('CREATE TABLE training_roster_expected(id INTEGER PRIMARY KEY,link_id INTEGER,sportovec_id INTEGER)');
        $pdo->exec("INSERT INTO sportovci VALUES(10,'Anna','Členka','2012-01-01','aktivni'),(11,'Cizí','Člen','2011-01-01','aktivni')");
        $pdo->exec('INSERT INTO verejni_uzivatele VALUES(100,1,1),(101,1,1)');
        $pdo->exec("INSERT INTO account_person_roles VALUES(1,100,10,'guardian','approved','2020-01-01',NULL),(2,101,11,'guardian','approved','2020-01-01',NULL)");
        $pdo->exec("INSERT INTO child_access_accounts VALUES(500,10,'anna',1,1),(501,11,'cizi',1,1)");
        $future = date('Y-m-d', strtotime('+5 days'));
        $past = date('Y-m-d', strtotime('-1 day'));
        $insertPlan = $pdo->prepare("INSERT INTO planovane_treninky VALUES(?,?,?,?,?,?, 'planovany')");
        $insertPlan->execute([100, 'Budoucí trénink', $future, '16:00:00', '17:00:00', 'Test']);
        $insertPlan->execute([101, 'Starý trénink', $past, '16:00:00', '17:00:00', 'Test']);
        $pdo->exec("INSERT INTO club_roster_members VALUES(1,1,10,'active','2020-01-01',NULL),(2,2,11,'active','2020-01-01',NULL)");
        $pdo->exec("INSERT INTO training_roster_links VALUES(1,100,1,'Tým A'),(3,101,1,'Tým A')");
        $pdo->exec('INSERT INTO training_roster_expected VALUES(1,1,10),(3,3,10)');
        if ($applyMigration) {
            $migration = require dirname(__DIR__, 2) . '/migrations/20261007120000_training_rsvps.php';
            $migration['up']($pdo);
            self::assertTrue($migration['verify']($pdo));
        }
        return $pdo;
    }
}
