<?php
declare(strict_types=1);
namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use ClubMakeupException;

require_once dirname(__DIR__,2).'/includes/club_makeup.php';
require_once dirname(__DIR__,2).'/includes/training_rsvp.php';

final class ClubMakeupTest extends TestCase
{
    public function testExcusedPaidLessonCarriesOnlyAfterNextSemesterIsPaidAndCannotBeSpentTwice():void
    {
        $pdo=$this->fixture();
        \clubMakeupPolicySave($pdo,1,1,1,10,true,12,true);
        \clubMakeupPolicySave($pdo,1,2,1,20,true,12,true);
        \clubMakeupOfferPeriodSave($pdo,1,12,'second',null);
        \clubMakeupOfferPeriodSave($pdo,1,11,'first',12);
        \clubMakeupSlotSave($pdo,1,200,21,1,2016,2018);
        \clubMakeupFinalizeSource($pdo,100,500,1);
        \clubMakeupFinalizeSource($pdo,100,500,1);
        self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM club_makeup_credits')->fetchColumn());
        $credit=\clubMakeupCreditsForPerson($pdo,10)[0];
        self::assertSame('carried_locked',$credit['display_status']);
        self::assertSame(['pending'=>0,'available'=>0,'locked'=>1,'reserved'=>0,'used'=>0],\clubMakeupProfileSummary($pdo,10));
        self::assertSame([],\clubMakeupAvailableSlots($pdo,10,(int)$credit['id']));
        try { \clubMakeupReserve($pdo,10,(int)$credit['id'],200,'athlete',50);self::fail('Unpaid continuation was accepted.'); }
        catch(ClubMakeupException $exception){self::assertStringContainsString('navazující pololetí',$exception->getMessage());}
        $pdo->exec("UPDATE club_program_enrollments SET status='active' WHERE id=41");
        $pdo->exec('UPDATE shop_order_items SET line_amount_minor=0 WHERE id=31');
        self::assertSame([],\clubMakeupAvailableSlots($pdo,10,(int)$credit['id']));
        $pdo->exec('UPDATE shop_order_items SET line_amount_minor=10000 WHERE id=31');
        self::assertCount(1,\clubMakeupAvailableSlots($pdo,10,(int)$credit['id']));
        self::assertSame(1,\clubMakeupProfileSummary($pdo,10)['available']);
        $reservation=\clubMakeupReserve($pdo,10,(int)$credit['id'],200,'athlete',50);
        self::assertGreaterThan(0,$reservation);
        self::assertCount(1,\clubMakeupReservationsForPlan($pdo,200));
        try { \clubMakeupReserve($pdo,10,(int)$credit['id'],200,'athlete',50);self::fail('Credit was spent twice.'); }
        catch(ClubMakeupException) {}
        self::assertSame(1,(int)$pdo->query("SELECT COUNT(*) FROM club_makeup_reservations WHERE status='booked'")->fetchColumn());
        \clubMakeupCancelReservation($pdo,10,$reservation,'athlete',50);
        self::assertSame('available',$pdo->query('SELECT status FROM club_makeup_credits')->fetchColumn());
        self::assertCount(0,\clubMakeupReservationsForPlan($pdo,200));
        $second=\clubMakeupReserve($pdo,10,(int)$credit['id'],200,'athlete',50);
        self::assertNotSame($reservation,$second);
        $pdo->exec('INSERT INTO treninky(id,datum) VALUES(501,\''.date('Y-m-d',strtotime('+1 day')).'\')');
        $pdo->exec('INSERT INTO trenink_sportovec(trenink_id,sportovec_id) VALUES(501,10)');
        \clubMakeupFinalizeTarget($pdo,200,501,1);
        self::assertSame('used',$pdo->query('SELECT status FROM club_makeup_credits')->fetchColumn());
        self::assertSame('attended',$pdo->query('SELECT status FROM club_makeup_reservations ORDER BY id DESC LIMIT 1')->fetchColumn());
    }

    public function testAgeAndCapacityAreCheckedAgainAtReservationAndMigrationRepeats():void
    {
        $pdo=$this->fixture();
        $migration=require dirname(__DIR__,2).'/migrations/20261014110000_club_makeup_lessons.php';
        $migration['up']($pdo);self::assertTrue($migration['verify']($pdo));
        \clubMakeupPolicySave($pdo,1,2,1,20,true,12,true);
        \clubMakeupSlotSave($pdo,1,200,21,1,2016,2018);
        $pdo->exec("INSERT INTO club_makeup_credits(source_plan_id,sportovec_id,source_enrollment_id,source_offer_id,status,expires_on,excused_at) VALUES(100,10,40,11,'available','".date('Y-m-d',strtotime('+3 months'))."','".date('Y-m-d',strtotime('-3 days'))."')");
        $credit=(int)$pdo->lastInsertId();
        $pdo->exec("UPDATE sportovci SET narozeni='2010-01-01' WHERE id=10");
        self::assertSame([],\clubMakeupAvailableSlots($pdo,10,$credit));
        $this->expectException(ClubMakeupException::class);
        $this->expectExceptionMessage('Věk');
        \clubMakeupReserve($pdo,10,$credit,200,'athlete',50);
    }

    public function testAdminCutoffCanChangeForFutureTrainingsButActualAttendancePreventsCredit():void
    {
        $pdo=$this->fixture();
        $pdo->exec("UPDATE club_program_enrollments SET status='active' WHERE id=41");
        $tomorrow=date('Y-m-d',strtotime('+1 day'));
        $pdo->prepare("INSERT INTO planovane_treninky VALUES(300,1,NULL,'Další trénink',?,'16:00:00','planovany',NULL)")->execute([$tomorrow]);
        $pdo->exec('INSERT INTO training_roster_links VALUES(3,300,10)');
        \clubMakeupPolicySave($pdo,1,1,1,10,true,12,true);
        self::assertSame(12,\clubMakeupExcuseEntitlement($pdo,300,10,\clubMakeupNow())['hours']);
        \clubMakeupPolicySave($pdo,1,1,1,10,true,48,true);
        self::assertNull(\clubMakeupExcuseEntitlement($pdo,300,10,\clubMakeupNow()));
        $pdo->exec('INSERT INTO trenink_sportovec(trenink_id,sportovec_id) VALUES(500,10)');
        \clubMakeupFinalizeSource($pdo,100,500,1);
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM club_makeup_credits')->fetchColumn());
        $pdo->exec('DELETE FROM trenink_sportovec WHERE trenink_id=500');
        $pdo->exec('UPDATE shop_order_items SET line_amount_minor=0 WHERE id=30');
        \clubMakeupFinalizeSource($pdo,100,500,1);
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM club_makeup_credits')->fetchColumn());
    }

    public function testAnnualPaidOfferDoesNotNeedAnotherPurchaseForSecondSemester():void
    {
        $pdo=$this->fixture();
        $end=date('Y-m-d',strtotime('+3 months'));
        $start=date('Y-m-d',strtotime('-3 months'));
        $pdo->prepare('INSERT INTO club_program_offers(id,program_id,season_id,team_id,name,starts_on,ends_on,birth_year_from,birth_year_to) VALUES(13,1,1,10,?,?,?,2016,2018)')->execute(['Zubříci celoročně',$start,$end]);
        $pdo->exec('INSERT INTO shop_order_items VALUES(34,20000)');
        $pdo->prepare("INSERT INTO club_program_enrollments VALUES(44,13,10,34,'active',?,?)")->execute([$start,$end]);
        $pdo->exec('UPDATE training_rsvps SET makeup_source_enrollment_id=44 WHERE plan_id=100');
        \clubMakeupOfferPeriodSave($pdo,1,13,'annual',null);
        \clubMakeupPolicySave($pdo,1,2,1,20,true,12,true);
        \clubMakeupSlotSave($pdo,1,200,21,1,2016,2018);
        \clubMakeupFinalizeSource($pdo,100,500,1);
        $credit=\clubMakeupCreditsForPerson($pdo,10)[0];
        self::assertSame('available',$credit['display_status']);
        self::assertCount(1,\clubMakeupAvailableSlots($pdo,10,(int)$credit['id']));
    }

    public function testOneMakeupSlotCannotBeOverbooked():void
    {
        $pdo=$this->fixture();
        $pdo->exec("UPDATE club_program_enrollments SET status='active' WHERE id=41");
        $pdo->exec("INSERT INTO sportovci VALUES(11,'Ema','Druhá','2017-06-01','aktivni')");
        $pdo->exec("INSERT INTO child_access_accounts VALUES(51,11,'ema',1,1)");
        $pdo->exec('INSERT INTO shop_order_items VALUES(32,10000),(33,10000)');
        $firstEnd=date('Y-m-d',strtotime('-1 day'));$secondStart=date('Y-m-d');$secondEnd=date('Y-m-d',strtotime('+3 months'));
        $pdo->prepare("INSERT INTO club_program_enrollments VALUES(42,11,11,32,'active',?,?)")->execute([date('Y-m-d',strtotime('-3 months')),$firstEnd]);
        $pdo->prepare("INSERT INTO club_program_enrollments VALUES(43,12,11,33,'active',?,?)")->execute([$secondStart,$secondEnd]);
        $pdo->exec("INSERT INTO club_roster_members VALUES(2,10,11,'active','2020-01-01',NULL)");
        $pdo->prepare("INSERT INTO training_rsvps(plan_id,sportovec_id,response,member_note,makeup_excused_at,makeup_source_enrollment_id,makeup_cutoff_hours) VALUES(100,11,'not_going','',?,42,12)")->execute([date('Y-m-d H:i:s',strtotime('-3 days'))]);
        \clubMakeupOfferPeriodSave($pdo,1,12,'second',null);\clubMakeupOfferPeriodSave($pdo,1,11,'first',12);
        \clubMakeupPolicySave($pdo,1,2,1,20,true,12,true);\clubMakeupSlotSave($pdo,1,200,21,1,2016,2018);
        \clubMakeupFinalizeSource($pdo,100,500,1);
        $credits=$pdo->query('SELECT id,sportovec_id FROM club_makeup_credits ORDER BY sportovec_id')->fetchAll(PDO::FETCH_ASSOC);
        self::assertCount(2,$credits);
        \clubMakeupReserve($pdo,10,(int)$credits[0]['id'],200,'athlete',50);
        self::assertSame([],\clubMakeupAvailableSlots($pdo,11,(int)$credits[1]['id']));
        try { \clubMakeupReserve($pdo,11,(int)$credits[1]['id'],200,'athlete',51);self::fail('Slot was overbooked.'); }
        catch(ClubMakeupException $exception){self::assertStringContainsString('Kapacita',$exception->getMessage());}
        \clubMakeupSlotDisable($pdo,1,200);
        self::assertSame(1,(int)$pdo->query("SELECT COUNT(*) FROM club_makeup_reservations WHERE status='booked'")->fetchColumn());
        self::assertSame(0,(int)$pdo->query('SELECT accepting FROM club_makeup_slots WHERE plan_id=200')->fetchColumn());
        self::assertSame([],\clubMakeupAvailableSlots($pdo,11,(int)$credits[1]['id']));
        $this->expectException(ClubMakeupException::class);
        $this->expectExceptionMessage('Termín není dostupný');
        \clubMakeupReserve($pdo,11,(int)$credits[1]['id'],200,'athlete',51);
    }

    public function testRsvpNoteChangeKeepsTimelyExcuseButAttendingClearsIt():void
    {
        $pdo=$this->fixture();
        $pdo->exec("UPDATE club_program_enrollments SET status='active' WHERE id=41");
        $pdo->prepare("INSERT INTO planovane_treninky VALUES(300,1,NULL,'Budoucí trénink',?,'16:00:00','planovany',NULL)")->execute([date('Y-m-d',strtotime('+1 day'))]);
        $pdo->exec('INSERT INTO training_roster_links VALUES(3,300,10)');
        \clubMakeupPolicySave($pdo,1,1,1,10,true,12,true);
        $first=\trainingRsvpSave($pdo,300,10,'not_going','athlete',50,'Omlouvám se.');
        self::assertTrue($first['makeup_eligible']);
        $original=$pdo->query('SELECT makeup_excused_at FROM training_rsvps WHERE plan_id=300')->fetchColumn();
        self::assertNotNull($original);
        $note=\trainingRsvpSave($pdo,300,10,'not_going','athlete',50,'Nemohu přijít.');
        self::assertTrue($note['makeup_eligible']);
        self::assertSame($original,$pdo->query('SELECT makeup_excused_at FROM training_rsvps WHERE plan_id=300')->fetchColumn());
        \trainingRsvpSave($pdo,300,10,'going','athlete',50);
        self::assertNull($pdo->query('SELECT makeup_excused_at FROM training_rsvps WHERE plan_id=300')->fetchColumn());
        \clubMakeupPolicySave($pdo,1,1,1,10,true,48,true);
        $late=\trainingRsvpSave($pdo,300,10,'not_going','athlete',50);
        self::assertFalse($late['makeup_eligible']);
        self::assertSame('late',$late['makeup_reason']);
        self::assertSame('late',$pdo->query('SELECT makeup_denial_reason FROM training_rsvps WHERE plan_id=300')->fetchColumn());
        self::assertStringContainsString('po lhůtě',\trainingRsvpSavedMessage($late));
    }

    public function testRejectedApologyExplainsPolicyPaymentAndRetainsOriginalReason():void
    {
        $pdo=$this->fixture();
        $future=date('Y-m-d',strtotime('+3 days'));
        $pdo->prepare("INSERT INTO planovane_treninky VALUES(300,1,NULL,'Budoucí trénink',?,'16:00:00','planovany',NULL)")->execute([$future]);
        $pdo->exec('INSERT INTO training_roster_links VALUES(3,300,10)');
        $pdo->prepare('UPDATE club_program_offers SET ends_on=? WHERE id=11')->execute([date('Y-m-d',strtotime('+1 week'))]);
        $pdo->prepare("UPDATE club_program_enrollments SET valid_to=?,status='pending' WHERE id=40")->execute([date('Y-m-d',strtotime('+1 week'))]);
        self::assertSame('policy_disabled',\clubMakeupExcuseFailureReason($pdo,300,10,\clubMakeupNow()));
        \clubMakeupPolicySave($pdo,1,1,1,10,true,12,true);
        self::assertSame('unpaid',\clubMakeupExcuseFailureReason($pdo,300,10,\clubMakeupNow()));
        $saved=\trainingRsvpSave($pdo,300,10,'not_going','athlete',50);
        self::assertSame('unpaid',$saved['makeup_reason']);
        self::assertStringContainsString('není potvrzená placená účast',\trainingRsvpSavedMessage($saved));
        $pdo->exec("UPDATE club_program_enrollments SET status='active' WHERE id=40");
        self::assertNotNull(\clubMakeupExcuseEntitlement($pdo,300,10,\clubMakeupNow()));
        $again=\trainingRsvpSave($pdo,300,10,'not_going','athlete',50,'Přidávám důvod.');
        self::assertSame('unpaid',$again['makeup_reason']);
        self::assertSame('unpaid',\clubMakeupDeniedExcusesForPerson($pdo,10)[0]['makeup_denial_reason']);
    }

    public function testOfferReviewDoesNotInferSemesterFromNameOrDuration():void
    {
        self::assertSame('unclassified',\clubMakeupOfferReviewStatus(null));
        self::assertSame('classified',\clubMakeupOfferReviewStatus(['period_kind'=>'single']));
        self::assertSame('classified',\clubMakeupOfferReviewStatus(['period_kind'=>'first']));
    }

    public function testCancellingTargetOrNextSemesterReleasesReservationAndBlocksNewOne():void
    {
        $pdo=$this->fixture();
        $pdo->exec("UPDATE club_program_enrollments SET status='active' WHERE id=41");
        \clubMakeupOfferPeriodSave($pdo,1,12,'second',null);\clubMakeupOfferPeriodSave($pdo,1,11,'first',12);
        \clubMakeupPolicySave($pdo,1,2,1,20,true,12,true);\clubMakeupSlotSave($pdo,1,200,21,1,2016,2018);
        \clubMakeupFinalizeSource($pdo,100,500,1);
        $credit=(int)$pdo->query('SELECT id FROM club_makeup_credits')->fetchColumn();
        \clubMakeupReserve($pdo,10,$credit,200,'athlete',50);
        $pdo->beginTransaction();\clubMakeupCancelPlannedTarget($pdo,200,1);$pdo->commit();
        self::assertSame('available',$pdo->query('SELECT status FROM club_makeup_credits')->fetchColumn());
        self::assertSame('cancelled',$pdo->query('SELECT status FROM club_makeup_reservations')->fetchColumn());
        \clubMakeupReserve($pdo,10,$credit,200,'athlete',50);
        $pdo->beginTransaction();
        $pdo->exec("UPDATE club_program_enrollments SET status='cancelled' WHERE id=41");
        \clubMakeupOnEnrollmentCancelled($pdo,41,1);$pdo->commit();
        self::assertSame('available',$pdo->query('SELECT status FROM club_makeup_credits')->fetchColumn());
        self::assertSame(0,(int)$pdo->query("SELECT COUNT(*) FROM club_makeup_reservations WHERE status='booked'")->fetchColumn());
        self::assertFalse(\clubMakeupCreditUsableOn($pdo,$pdo->query('SELECT * FROM club_makeup_credits')->fetch(PDO::FETCH_ASSOC),date('Y-m-d',strtotime('+1 day'))));
    }

    public function testUsedCarryCreditBlocksCancellationOfPaidContinuation():void
    {
        $pdo=$this->fixture();
        $pdo->exec("UPDATE club_program_enrollments SET status='active' WHERE id=41");
        \clubMakeupOfferPeriodSave($pdo,1,12,'second',null);
        \clubMakeupOfferPeriodSave($pdo,1,11,'first',12);
        \clubMakeupPolicySave($pdo,1,2,1,20,true,12,true);
        \clubMakeupSlotSave($pdo,1,200,21,1,2016,2018);
        \clubMakeupFinalizeSource($pdo,100,500,1);
        $credit=(int)$pdo->query('SELECT id FROM club_makeup_credits')->fetchColumn();
        \clubMakeupReserve($pdo,10,$credit,200,'athlete',50);
        $pdo->exec('INSERT INTO treninky(id,datum) VALUES(501,\''.date('Y-m-d',strtotime('+1 day')).'\')');
        \clubMakeupFinalizeTarget($pdo,200,501,1);
        $pdo->beginTransaction();
        $pdo->exec("UPDATE club_program_enrollments SET status='cancelled' WHERE id=41");
        try {
            \clubMakeupOnEnrollmentCancelled($pdo,41,1);
            self::fail('Cancellation with spent carry credit was accepted.');
        } catch (ClubMakeupException $exception) {
            self::assertStringContainsString('vyčerpanou převedenou',$exception->getMessage());
            $pdo->rollBack();
        }
        self::assertSame('active',$pdo->query('SELECT status FROM club_program_enrollments WHERE id=41')->fetchColumn());
    }

    public function testAttendanceCorrectionDoesNotRestoreCreditAfterSourceEnrollmentIsCancelled():void
    {
        $pdo=$this->fixture();
        \clubMakeupFinalizeSource($pdo,100,500,1);
        $pdo->beginTransaction();
        $pdo->exec("UPDATE club_program_enrollments SET status='cancelled' WHERE id=40");
        \clubMakeupOnEnrollmentCancelled($pdo,40,1);
        $pdo->commit();
        \clubMakeupReconcileRecordedAttendance($pdo,500,1);
        self::assertSame('revoked',$pdo->query('SELECT status FROM club_makeup_credits')->fetchColumn());
    }

    private function fixture():PDO
    {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $pdo->exec('PRAGMA foreign_keys=ON');
        foreach([
            'CREATE TABLE treneri(id INTEGER PRIMARY KEY)',
            'CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,narozeni TEXT,stav_clenstvi TEXT)',
            'CREATE TABLE child_access_accounts(id INTEGER PRIMARY KEY,sportovec_id INTEGER,login_name TEXT,session_version INTEGER,active INTEGER)',
            'CREATE TABLE club_programs(id INTEGER PRIMARY KEY,name TEXT)',
            'CREATE TABLE club_seasons(id INTEGER PRIMARY KEY,name TEXT)',
            'CREATE TABLE club_teams(id INTEGER PRIMARY KEY,season_id INTEGER,name TEXT)',
            'CREATE TABLE club_program_offers(id INTEGER PRIMARY KEY,program_id INTEGER,season_id INTEGER,team_id INTEGER,name TEXT,starts_on TEXT,ends_on TEXT,birth_year_from INTEGER,birth_year_to INTEGER,product_id INTEGER,variant_id INTEGER)',
            'CREATE TABLE shop_order_items(id INTEGER PRIMARY KEY,line_amount_minor INTEGER)',
            'CREATE TABLE club_program_enrollments(id INTEGER PRIMARY KEY,offer_id INTEGER,sportovec_id INTEGER,source_order_item_id INTEGER,status TEXT,valid_from TEXT,valid_to TEXT)',
            'CREATE TABLE planovane_treninky(id INTEGER PRIMARY KEY,trener_id INTEGER,serie_id INTEGER,nazev TEXT,datum TEXT,cas_od TEXT,stav TEXT,trenink_id INTEGER)',
            'CREATE TABLE training_roster_links(id INTEGER PRIMARY KEY,plan_id INTEGER,team_id INTEGER)',
            'CREATE TABLE club_roster_members(id INTEGER PRIMARY KEY,team_id INTEGER,sportovec_id INTEGER,status TEXT,valid_from TEXT,valid_to TEXT)',
            'CREATE TABLE training_rsvps(id INTEGER PRIMARY KEY AUTOINCREMENT,plan_id INTEGER,sportovec_id INTEGER,account_id INTEGER NULL,access_account_id INTEGER NULL,response TEXT,member_note TEXT,responded_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE(plan_id,sportovec_id))',
            'CREATE TABLE training_rsvp_events(id INTEGER PRIMARY KEY AUTOINCREMENT,rsvp_id INTEGER,actor_type TEXT,actor_id INTEGER,from_response TEXT,to_response TEXT,note TEXT,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)',
            'CREATE TABLE treninky(id INTEGER PRIMARY KEY,datum TEXT)',
            'CREATE TABLE trenink_sportovec(trenink_id INTEGER,sportovec_id INTEGER)',
        ] as $schema)$pdo->exec($schema);
        $migration=require dirname(__DIR__,2).'/migrations/20261014110000_club_makeup_lessons.php';$migration['up']($pdo);
        $reasonMigration=require dirname(__DIR__,2).'/migrations/20261016100000_training_rsvp_makeup_reason.php';$reasonMigration['up']($pdo);self::assertTrue($reasonMigration['verify']($pdo));
        $sourceDate=date('Y-m-d',strtotime('-2 days'));
        $firstEnd=date('Y-m-d',strtotime('-1 day'));
        $secondStart=date('Y-m-d');
        $secondEnd=date('Y-m-d',strtotime('+3 months'));
        $pdo->exec("INSERT INTO treneri VALUES(1)");
        $pdo->exec("INSERT INTO sportovci VALUES(10,'Anna','Testová','2017-05-02','aktivni')");
        $pdo->exec("INSERT INTO child_access_accounts VALUES(50,10,'anna',1,1)");
        $pdo->exec("INSERT INTO club_programs VALUES(1,'Zdroj'),(2,'Cíl')");
        $pdo->exec("INSERT INTO club_seasons VALUES(1,'Školní rok')");
        $pdo->exec("INSERT INTO club_teams VALUES(10,1,'Zubříci'),(20,1,'Morčata')");
        $offer=$pdo->prepare('INSERT INTO club_program_offers(id,program_id,season_id,team_id,name,starts_on,ends_on,birth_year_from,birth_year_to) VALUES(?,?,?,?,?,?,?,?,?)');
        $offer->execute([11,1,1,10,'Zubříci první',date('Y-m-d',strtotime('-3 months')),$firstEnd,2016,2018]);
        $offer->execute([12,1,1,10,'Zubříci druhé',$secondStart,$secondEnd,2016,2018]);
        $offer->execute([21,2,1,20,'Morčata druhé',$secondStart,$secondEnd,2016,2018]);
        $pdo->exec('INSERT INTO shop_order_items VALUES(30,10000),(31,10000)');
        $enrollment=$pdo->prepare('INSERT INTO club_program_enrollments VALUES(?,?,?,?,?,?,?)');
        $enrollment->execute([40,11,10,30,'active',date('Y-m-d',strtotime('-3 months')),$firstEnd]);
        $enrollment->execute([41,12,10,31,'pending',$secondStart,$secondEnd]);
        $plans=$pdo->prepare('INSERT INTO planovane_treninky VALUES(?,?,?,?,?,?,?,?)');
        $plans->execute([100,1,null,'Zdrojový trénink',$sourceDate,'16:00:00','evidovany',500]);
        $plans->execute([200,1,null,'Náhradní trénink',date('Y-m-d',strtotime('+1 day')),'16:00:00','planovany',null]);
        $pdo->exec('INSERT INTO training_roster_links VALUES(1,100,10),(2,200,20)');
        $pdo->exec("INSERT INTO club_roster_members VALUES(1,10,10,'active','2020-01-01',NULL)");
        $pdo->exec("INSERT INTO treninky VALUES(500,'$sourceDate')");
        $excused=date('Y-m-d H:i:s',strtotime('-3 days'));
        $statement=$pdo->prepare("INSERT INTO training_rsvps(plan_id,sportovec_id,response,member_note,makeup_excused_at,makeup_source_enrollment_id,makeup_cutoff_hours) VALUES(100,10,'not_going','',?,40,12)");$statement->execute([$excused]);
        return $pdo;
    }
}
