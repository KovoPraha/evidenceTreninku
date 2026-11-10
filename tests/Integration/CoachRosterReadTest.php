<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/coach_roster_read.php';

final class CoachRosterReadTest extends TestCase
{
    private const TODAY = '2026-11-10';

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,aktivni INTEGER)');
        $pdo->exec('INSERT INTO treneri VALUES(8,1),(9,1)');
        $pdo->exec('CREATE TABLE staff_user_positions(trainer_id INTEGER,position_code TEXT)');
        $pdo->exec("INSERT INTO staff_user_positions VALUES(8,'coach'),(9,'coach')");
        $pdo->exec('CREATE TABLE staff_superadmins(trainer_id INTEGER)');
        $pdo->exec('CREATE TABLE club_seasons(id INTEGER PRIMARY KEY,name TEXT,starts_on TEXT,ends_on TEXT)');
        $pdo->exec("INSERT INTO club_seasons VALUES(1,'2026/27','2026-09-01','2027-06-30'),"
            . "(2,'2025/26','2025-09-01','2026-06-30')");
        $pdo->exec('CREATE TABLE club_teams(id INTEGER PRIMARY KEY,name TEXT,team_kind TEXT,activity_kind TEXT,season_id INTEGER,status TEXT)');
        $pdo->exec("INSERT INTO club_teams VALUES(20,'Aligátoři','group','cycling',1,'active'),"
            . "(21,'Aligátoři – soupiska','roster','cycling',1,'active'),"
            . "(22,'Cizí kroužek','roster','cycling',1,'active'),"
            . "(23,'Minulý rok','roster','cycling',2,'active')");
        $pdo->exec('CREATE TABLE club_roster_members(team_id INTEGER,sportovec_id INTEGER,status TEXT,valid_from TEXT,valid_to TEXT)');
        $pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,rodne_cislo TEXT,email_rodice TEXT)');
        $pdo->exec("INSERT INTO sportovci VALUES(1,'Anežka','Testová','SENSITIVE-RC','SENSITIVE-MAIL'),"
            . "(2,'Boris','Jiný','SENSITIVE-RC-2','SENSITIVE-MAIL-2'),"
            . "(3,'Cyril','Čekající','SENSITIVE-RC-3','SENSITIVE-MAIL-3')");
        $pdo->exec("INSERT INTO club_roster_members VALUES(21,1,'active','2026-09-01',NULL),(22,2,'active','2026-09-01',NULL),(21,3,'pending','2026-09-01',NULL)");
        $migration = require dirname(__DIR__, 2) . '/migrations/20261022214000_coach_scope.php';
        $migration['up']($pdo);
        $pdo->exec("INSERT INTO coach_scope_grants(trainer_id,scope_kind,scope_id,valid_from,reason,created_by_trainer_id) "
            . "VALUES(8,'team',20,datetime('now','-1 day'),'Test',8),"
            . "(8,'team',21,datetime('now','-1 day'),'Test',8),"
            . "(8,'team',23,datetime('now','-1 day'),'Test',8)");
        $this->coach();
        return $pdo;
    }

    private function coach(): void
    {
        $_SESSION = ['trener_id' => 8, 'staff_positions' => ['coach'], 'staff_active_position' => 'coach'];
    }

    public function testRouteIsCoachWorkAndHasNoOtherPosition(): void
    {
        self::assertSame('coach', staffRouteOwner('moje_soupisky.php'));
        self::assertSame(['coach'], staffRouteAllowedPositions('moje_soupisky.php'));
        $work = staffPositionMenuGroups('coach', false)[0];
        self::assertSame('Moje práce', $work['label']);
        self::assertContains('moje_soupisky.php', array_column($work['items'], 'route'));
    }

    public function testOnlyDirectlyAssignedRosterAndCurrentActiveChildrenAreVisible(): void
    {
        $pdo = $this->database();
        $teams = coachRosterAssignedTeams($pdo, self::TODAY);
        self::assertSame([20, 21], array_map(static fn (array $team): int => (int)$team['id'], $teams));
        self::assertSame([0, 1], array_map(static fn (array $team): int => $team['member_count'], $teams));
        self::assertSame('2026/27', $teams[1]['season_name']);
        self::assertSame(['id', 'jmeno', 'prijmeni'], array_keys(coachRosterMembers($pdo, 21, self::TODAY)[0]));
        self::assertSame('Anežka', coachRosterMembers($pdo, 21, self::TODAY, 'Testová Anežka')[0]['jmeno']);
        self::assertSame([], coachRosterMembers($pdo, 20, self::TODAY));
        self::assertSame([], coachRosterMembers($pdo, 21, self::TODAY, 'Čekající'));
    }

    public function testApprovalAndEndOfMembershipImmediatelyChangeProjection(): void
    {
        $pdo = $this->database();
        self::assertCount(1, coachRosterMembers($pdo, 21, self::TODAY));
        $pdo->exec("UPDATE club_roster_members SET status='active' WHERE sportovec_id=3");
        self::assertCount(2, coachRosterMembers($pdo, 21, self::TODAY));
        $pdo->exec("UPDATE club_roster_members SET valid_to='2026-11-09' WHERE sportovec_id=1");
        self::assertSame([3], array_map('intval', array_column(coachRosterMembers($pdo, 21, self::TODAY), 'id')));
        self::assertSame(1, coachRosterAssignedTeams($pdo, self::TODAY)[1]['member_count']);
    }

    public function testForeignRosterAndRevokedOrExpiredGrantAreDenied(): void
    {
        $pdo = $this->database();
        try {
            coachRosterMembers($pdo, 22, self::TODAY);
            self::fail('Foreign roster was opened.');
        } catch (DomainException) {
        }
        try {
            coachRosterMembers($pdo, 23, self::TODAY);
            self::fail('Past season roster was opened.');
        } catch (DomainException) {
        }
        $pdo->exec("UPDATE coach_scope_grants SET revoked_at=CURRENT_TIMESTAMP WHERE scope_id=21");
        self::assertSame([20], array_map('intval', array_column(coachRosterAssignedTeams($pdo, self::TODAY), 'id')));
        try {
            coachRosterMembers($pdo, 21, self::TODAY);
            self::fail('Revoked roster was opened.');
        } catch (DomainException) {
        }
        $pdo->exec("UPDATE coach_scope_grants SET valid_to='2020-01-01' WHERE scope_id=20");
        self::assertSame([], coachRosterAssignedTeams($pdo, self::TODAY));
    }

    public function testPositionAndDatabaseAssignmentAreRequired(): void
    {
        $pdo = $this->database();
        $_SESSION['staff_positions'] = ['registrar'];
        $_SESSION['staff_active_position'] = 'registrar';
        $this->expectException(DomainException::class);
        coachRosterAssignedTeams($pdo, self::TODAY);
    }

    public function testRemovedCoachAssignmentIsDeniedEvenWithOldSession(): void
    {
        $pdo = $this->database();
        $pdo->exec('DELETE FROM staff_user_positions WHERE trainer_id=8');
        $this->expectException(DomainException::class);
        coachRosterAssignedTeams($pdo, self::TODAY);
    }

    public function testCapacityAndScheduleUseCurrentProgramData(): void
    {
        $pdo = $this->database();
        $pdo->exec('CREATE TABLE club_program_offers(id INTEGER PRIMARY KEY,program_id INTEGER,team_id INTEGER,'
            . 'product_id INTEGER,variant_id INTEGER,capacity INTEGER,status TEXT,starts_on TEXT,ends_on TEXT)');
        $pdo->exec("INSERT INTO club_program_offers VALUES(1,4,21,298,5,12,'active','2026-09-01','2027-06-30')");
        $pdo->exec('CREATE TABLE club_program_enrollments(offer_id INTEGER,sportovec_id INTEGER,status TEXT,source_order_item_id INTEGER)');
        $pdo->exec("INSERT INTO club_program_enrollments VALUES(1,1,'active',100)");
        $pdo->exec('CREATE TABLE club_program_schedule_slots(program_id INTEGER,weekday INTEGER,starts_at TEXT,ends_at TEXT,location_name TEXT,valid_from TEXT,valid_to TEXT)');
        $pdo->exec("INSERT INTO club_program_schedule_slots VALUES(4,2,'16:00','17:00','Hala','2026-09-01','2027-06-30'),(4,3,'18:00','19:00','Staré místo','2025-01-01','2025-06-30')");
        $summary = coachRosterAssignedTeams($pdo, self::TODAY)[1]['offer'];
        self::assertSame(12, $summary['capacity']);
        self::assertSame(11, $summary['available']);
        self::assertSame(['Út 16:00–17:00 · Hala'], $summary['place_time']);
    }
}
