<?php

declare(strict_types=1);

namespace Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2).'/includes/coach_attendance.php';

final class CoachAttendanceTest extends TestCase
{
    private function fixture(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        foreach ([
            'treneri(id INTEGER PRIMARY KEY,jmeno TEXT,aktivni INTEGER)',
            'staff_user_positions(trainer_id INTEGER,position_code TEXT)',
            'staff_superadmins(trainer_id INTEGER)',
            'sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT)',
            'treninky(id INTEGER PRIMARY KEY,datum TEXT,delka REAL,napln TEXT,kategorie TEXT,poznamka TEXT)',
            'trenink_trener(trenink_id INTEGER,trener_id INTEGER)',
            'trenink_sportovec(trenink_id INTEGER,sportovec_id INTEGER)',
            'trenink_skupina(trenink_id INTEGER,skupina_id INTEGER)',
            'planovane_treninky(id INTEGER PRIMARY KEY,trener_id INTEGER,datum TEXT,cas_od TEXT,cas_do TEXT,stav TEXT,trenink_id INTEGER,nazev TEXT,skupina_id INTEGER)',
            'training_roster_links(id INTEGER PRIMARY KEY,plan_id INTEGER,trenink_id INTEGER,team_id INTEGER,'
                . 'target_date TEXT,team_code_snapshot TEXT,team_name_snapshot TEXT,created_by_trainer_id INTEGER)',
            'training_roster_expected(id INTEGER PRIMARY KEY,link_id INTEGER,sportovec_id INTEGER,roster_member_id INTEGER,member_valid_from_snapshot TEXT,member_valid_to_snapshot TEXT)',
            'club_teams(id INTEGER PRIMARY KEY,status TEXT)',
            'club_roster_members(team_id INTEGER,sportovec_id INTEGER,valid_from TEXT,valid_to TEXT,status TEXT)',
            'training_rsvps(plan_id INTEGER,sportovec_id INTEGER,response TEXT,member_note TEXT)',
        ] as $schema) {
            $pdo->exec('CREATE TABLE '.$schema);
        }
        $migration = require dirname(__DIR__, 2).'/migrations/20261023020000_training_closure.php';
        $migration['up']($pdo);
        $pdo->exec("INSERT INTO treneri VALUES(1,'LOCALHOST Coach',1),(2,'LOCALHOST Other',1)");
        $pdo->exec("INSERT INTO staff_user_positions VALUES(1,'coach'),(2,'coach')");
        $pdo->exec("INSERT INTO sportovci VALUES(10,'A','Active'),(11,'B','Expired'),(12,'C','Excused'),(13,'D','New')");
        $pdo->exec("INSERT INTO club_teams VALUES(3,'active')");
        $date = (new DateTimeImmutable('yesterday', new DateTimeZone('Europe/Prague')))->format('Y-m-d');
        $statement = $pdo->prepare('INSERT INTO planovane_treninky VALUES(4,1,?,\'10:00\',\'11:00\',\'planovany\',NULL,\'LOCALHOST lesson\',NULL)');
        $statement->execute([$date]);
        $pdo->prepare("INSERT INTO training_roster_links VALUES(5,4,NULL,3,?,'TEST','LOCALHOST Team',1)")->execute([$date]);
        $pdo->exec("INSERT INTO training_roster_expected VALUES(1,5,10,NULL,'2026-01-01',NULL),(2,5,11,NULL,'2026-01-01',NULL),(3,5,12,NULL,'2026-01-01',NULL)");
        $pdo->prepare("INSERT INTO club_roster_members VALUES(3,10,'2026-01-01',NULL,'active'),(3,11,'2026-01-01',?,'removed'),(3,12,'2026-01-01',NULL,'active'),(3,13,'2026-01-01',NULL,'active')")
            ->execute([(new DateTimeImmutable($date))->modify('-1 day')->format('Y-m-d')]);
        $pdo->exec("INSERT INTO training_rsvps VALUES(4,12,'not_going','Rodinná omluva')");
        $_SESSION = ['trener_id' => 1,'staff_positions' => ['coach'],'staff_active_position' => 'coach'];
        return $pdo;
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testDatedRosterExcuseDraftFinalAndLostUpdate(): void
    {
        $pdo = $this->fixture();
        $view = \coachAttendanceLoad($pdo, 4, 1);
        self::assertSame([10,12,13], array_keys($view['people']));
        self::assertSame('excused', $view['people'][12]['state']);
        try {
            \coachAttendanceSave($pdo, 4, 1, 0, [10 => 'present',12 => 'absent',13 => '']);
            self::fail('Excuse overwritten');
        } catch (\TrainingClosureException $e) {
            self::assertStringContainsString('omluvu', $e->getMessage());
        }
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM treninky')->fetchColumn());
        $draft = \coachAttendanceSave($pdo, 4, 1, 0, [10 => 'present',12 => 'excused',13 => '']);
        self::assertSame('draft', $draft['status']);
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM trenink_sportovec')->fetchColumn());
        $final = \coachAttendanceSave($pdo, 4, 1, 1, [10 => 'present',12 => 'excused',13 => 'absent']);
        self::assertSame('closed', $final['status']);
        self::assertSame([10], array_map('intval', $pdo->query('SELECT sportovec_id FROM trenink_sportovec')->fetchAll(PDO::FETCH_COLUMN)));
        self::assertSame('absent', \trainingClosureStatesForPerson($pdo, 13, '2026-01-01', '2099-12-31')[$final['training_id']]['state']);
        try {
            \coachAttendanceSave($pdo, 4, 1, 1, [10 => 'present',12 => 'excused',13 => 'absent']);
            self::fail('Stale revision accepted');
        } catch (\TrainingClosureException $e) {
            self::assertStringContainsString('mezitím', $e->getMessage());
        }
        self::assertSame(2, (int)$pdo->query('SELECT COUNT(*) FROM training_closure_revisions')->fetchColumn());
    }

    public function testOtherCoachCannotOpenOrSavePlan(): void
    {
        $pdo = $this->fixture();
        $_SESSION = ['trener_id' => 2,'staff_positions' => ['coach'],'staff_active_position' => 'coach'];
        $this->expectException(\TrainingClosureException::class);
        \coachAttendanceLoad($pdo, 4, 2);
    }

    public function testCoachCanRecordAnExcuseReceivedAtTheLessonWithReasonAndHistory(): void
    {
        $pdo = $this->fixture();
        try {
            \coachAttendanceSave($pdo, 4, 1, 0, [10 => 'present',12 => 'excused',13 => 'excused'], [13 => 'Krátký']);
            self::fail('Short excuse accepted');
        } catch (\TrainingClosureException $e) {
            self::assertStringContainsString('důvod', $e->getMessage());
        }
        $final = \coachAttendanceSave(
            $pdo,
            4,
            1,
            0,
            [10 => 'present',12 => 'excused',13 => 'excused'],
            [13 => 'Omluva přijatá trenérem při lekci']
        );
        self::assertSame('closed', $final['status']);
        $view = \coachAttendanceLoad($pdo, 4, 1);
        self::assertSame('excused', $view['people'][13]['state']);
        self::assertTrue($view['people'][13]['manual_excuse']);
        self::assertSame(
            'Omluva přijatá trenérem při lekci',
            $view['current']['snapshot']['attendance_excuse_reasons'][13]
        );
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM training_rsvps WHERE sportovec_id=13')->fetchColumn());
    }

    public function testBookedMakeupAppearsSeparatelyWithoutRosterMembership(): void
    {
        $pdo = $this->fixture();
        foreach (['club_makeup_policies','club_makeup_offer_policies','club_makeup_offer_routes',
            'club_makeup_offer_periods','club_makeup_excuse_rules','club_makeup_slots',
            'club_makeup_credits','club_makeup_events','club_makeup_config_events'] as $table) {
            $pdo->exec("CREATE TABLE $table(id INTEGER PRIMARY KEY)");
        }
        $pdo->exec('CREATE TABLE club_makeup_reservations(id INTEGER PRIMARY KEY,target_plan_id INTEGER,sportovec_id INTEGER,status TEXT)');
        $pdo->exec("INSERT INTO sportovci VALUES(14,'E','Replacement')");
        $pdo->exec("INSERT INTO club_makeup_reservations VALUES(70,4,14,'booked')");
        $view = \coachAttendanceLoad($pdo, 4, 1);
        self::assertTrue($view['people'][14]['makeup']);
        self::assertSame(70, $view['people'][14]['reservation_id']);
        $draft = \coachAttendanceSave($pdo, 4, 1, 0, [10 => 'present',12 => 'excused',13 => 'absent',14 => '']);
        self::assertSame('draft', $draft['status']);
        self::assertSame(70, \trainingClosureCurrent($pdo, $draft['training_id'])['snapshot']['makeup_reservation_ids'][14]);
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM club_roster_members WHERE sportovec_id=14')->fetchColumn());
    }

    public function testFinalAttendanceConsumesBookedMakeupAndCorrectionUpdatesIt(): void
    {
        $pdo = $this->fixture();
        foreach (['club_makeup_policies','club_makeup_offer_policies','club_makeup_offer_routes',
            'club_makeup_excuse_rules','club_makeup_slots','club_makeup_config_events'] as $table) {
            $pdo->exec("CREATE TABLE $table(id INTEGER PRIMARY KEY)");
        }
        $pdo->exec('CREATE TABLE club_makeup_offer_periods(id INTEGER PRIMARY KEY,offer_id INTEGER,period_kind TEXT,next_offer_id INTEGER)');
        $pdo->exec('CREATE TABLE club_makeup_credits(id INTEGER PRIMARY KEY,source_plan_id INTEGER,sportovec_id INTEGER,source_enrollment_id INTEGER,status TEXT)');
        $pdo->exec('CREATE TABLE club_makeup_reservations(id INTEGER PRIMARY KEY,credit_id INTEGER,target_plan_id INTEGER,sportovec_id INTEGER,status TEXT,active_token TEXT)');
        $pdo->exec('CREATE TABLE club_makeup_events(id INTEGER PRIMARY KEY,credit_id INTEGER,reservation_id INTEGER,action TEXT,actor_type TEXT,actor_id INTEGER)');
        $pdo->exec('CREATE TABLE club_program_enrollments(id INTEGER PRIMARY KEY,source_order_item_id INTEGER,offer_id INTEGER,status TEXT)');
        $pdo->exec('CREATE TABLE shop_order_items(id INTEGER PRIMARY KEY,line_amount_minor INTEGER)');
        $pdo->exec('CREATE TABLE club_program_offers(id INTEGER PRIMARY KEY,ends_on TEXT,program_id INTEGER,team_id INTEGER)');
        foreach (['makeup_source_enrollment_id INTEGER','makeup_excused_at TEXT','makeup_cutoff_hours INTEGER'] as $column) {
            $pdo->exec('ALTER TABLE training_rsvps ADD COLUMN '.$column);
        }
        $pdo->exec("INSERT INTO sportovci VALUES(14,'E','Replacement')");
        $pdo->exec("INSERT INTO club_makeup_credits VALUES(60,99,14,NULL,'reserved')");
        $pdo->exec("INSERT INTO club_makeup_reservations VALUES(70,60,4,14,'booked','active')");
        $final = \coachAttendanceSave($pdo, 4, 1, 0, [10 => 'present',12 => 'excused',13 => 'absent',14 => 'present']);
        self::assertSame('closed', $final['status']);
        self::assertSame('attended', $pdo->query('SELECT status FROM club_makeup_reservations WHERE id=70')->fetchColumn());
        self::assertSame('used', $pdo->query('SELECT status FROM club_makeup_credits WHERE id=60')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM club_roster_members WHERE sportovec_id=14')->fetchColumn());
        self::assertSame(70, \trainingClosureStatesForPerson($pdo, 14, '2026-01-01', '2099-12-31')[$final['training_id']]['makeup_reservation_id']);
        $corrected = \coachAttendanceSave($pdo, 4, 1, 1, [10 => 'present',12 => 'excused',13 => 'absent',14 => 'absent']);
        self::assertSame('closed', $corrected['status']);
        self::assertSame('no_show', $pdo->query('SELECT status FROM club_makeup_reservations WHERE id=70')->fetchColumn());
        self::assertSame('used', $pdo->query('SELECT status FROM club_makeup_credits WHERE id=60')->fetchColumn());
    }

    public function testCoachDeadlineRequiresSportsLeadForLaterCorrection(): void
    {
        $pdo = $this->fixture();
        $old = (new DateTimeImmutable('now', new DateTimeZone('Europe/Prague')))->modify('-5 days')->format('Y-m-d');
        $pdo->prepare('UPDATE planovane_treninky SET datum=? WHERE id=4')->execute([$old]);
        $pdo->prepare('UPDATE training_roster_links SET target_date=? WHERE id=5')->execute([$old]);
        try {
            \coachAttendanceSave($pdo, 4, 1, 0, [10 => 'present',11 => 'absent',12 => 'excused',13 => 'absent']);
            self::fail('Late coach save accepted');
        } catch (\TrainingClosureException $e) {
            self::assertStringContainsString('72 hodin', $e->getMessage());
        }
        $pdo->exec("INSERT INTO staff_user_positions VALUES(1,'sports_lead')");
        $_SESSION['staff_positions'] = ['coach','sports_lead'];
        $_SESSION['staff_active_position'] = 'sports_lead';
        self::assertSame('closed', \coachAttendanceSave($pdo, 4, 1, 0, [10 => 'present',11 => 'absent',12 => 'excused',13 => 'absent'])['status']);
    }
}
