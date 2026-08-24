<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2) . '/includes/club_calendar.php';

final class ClubCalendarTest extends TestCase
{
    public function testRosterPlanIsVisibleToLinkedFamilyButNotAnonymous(): void
    {
        $pdo=$this->database();$event=$this->event($pdo,['visibility'=>'rosters','team_ids'=>[10]]);
        self::assertCount(1,\clubCalendarEvents($pdo,'2026-09-01','2026-09-30',100,false));
        self::assertCount(0,\clubCalendarEvents($pdo,'2026-09-01','2026-09-30',null,false));
        self::assertCount(1,\clubCalendarEvents($pdo,'2026-09-01','2026-09-30',null,true));
        $detail=\clubCalendarDetail($pdo,$event['id']);self::assertSame('planned',$detail['planning_status']);self::assertSame('rosters',$detail['visibility']);
    }

    public function testPublicVisibilityIsIndependentFromRegistration(): void
    {
        $pdo=$this->database();$event=$this->event($pdo,['visibility'=>'public','planning_status'=>'confirmed','team_ids'=>[]]);
        self::assertCount(1,\clubCalendarEvents($pdo,'2026-09-01','2026-09-30',null,false));
        self::assertSame('draft',$pdo->query('SELECT status FROM club_events WHERE id='.$event['id'])->fetchColumn());
        \clubCalendarSetRegistration($pdo,$event['id'],1,true);
        self::assertSame('open',$pdo->query('SELECT status FROM club_events WHERE id='.$event['id'])->fetchColumn());
    }

    public function testVehicleCollisionRequiresAcknowledgementAndRemainsVisibleEverywhere(): void
    {
        $pdo=$this->database();$first=$this->event($pdo,['name'=>'První závod']);$second=$this->event($pdo,['name'=>'Druhý závod']);
        \clubCalendarReserveVehicle($pdo,$first['id'],1,['vehicle_id'=>1,'starts_at'=>'2026-09-10T08:00','ends_at'=>'2026-09-10T18:00','driver_name'=>'Externí řidič']);
        try{\clubCalendarReserveVehicle($pdo,$second['id'],1,['vehicle_id'=>1,'starts_at'=>'2026-09-10T12:00','ends_at'=>'2026-09-10T20:00']);self::fail('Collision must require explicit acknowledgement.');}catch(\ClubCalendarException$e){self::assertStringContainsString('už rezervované',$e->getMessage());}
        $saved=\clubCalendarReserveVehicle($pdo,$second['id'],1,['vehicle_id'=>1,'starts_at'=>'2026-09-10T12:00','ends_at'=>'2026-09-10T20:00','conflict_acknowledged'=>'1','conflict_note'=>'Domluveno mezi trenéry.']);
        self::assertSame(1,$saved['conflict_count']);self::assertCount(1,\clubCalendarVehicleConflictsForEvent($pdo,$first['id']));self::assertCount(1,\clubCalendarVehicleConflictsForEvent($pdo,$second['id']));
        self::assertSame(1,(int)$pdo->query('SELECT conflict_acknowledged FROM club_event_vehicle_reservations WHERE id='.$saved['id'])->fetchColumn());
    }

    public function testConfirmedParticipationCreatesOneStandardChargeAndPayment(): void
    {
        $pdo=$this->database();$event=$this->event($pdo,['planning_status'=>'confirmed','participant_fee_minor'=>125000]);
        $first=\clubCalendarAddParticipant($pdo,$event['id'],200,1,true,100);$again=\clubCalendarAddParticipant($pdo,$event['id'],200,1,true,100);
        self::assertFalse($first['payer_missing']);self::assertSame($first['charge_id'],$again['charge_id']);
        self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM club_member_charges')->fetchColumn());
        self::assertSame('club_event:club_event:pending:125000',$pdo->query("SELECT charge_type||':'||source_system||':'||status||':'||amount_minor FROM club_member_charges")->fetchColumn());
        self::assertSame(1,(int)$pdo->query("SELECT COUNT(*) FROM payments WHERE payable_type='member_charge' AND status='pending'")->fetchColumn());
        self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM club_event_registrations')->fetchColumn());
    }

    public function testCancellingPaidParticipationCreatesAndClosesAuditedRefundTask(): void
    {
        $pdo=$this->database();$event=$this->event($pdo,['planning_status'=>'confirmed','participant_fee_minor'=>50000]);
        $participant=\clubCalendarAddParticipant($pdo,$event['id'],200,1,true,100);$chargeId=(int)$participant['charge_id'];
        $pdo->exec("UPDATE club_member_charges SET status='paid' WHERE id=$chargeId");
        $pdo->exec("UPDATE payments SET status='paid',paid_at='2026-08-24 12:00:00' WHERE payable_type='member_charge' AND payable_id=$chargeId");
        \clubCalendarCancelParticipant($pdo,(int)$participant['id'],1,'Účast zrušena po úhradě.');
        self::assertSame('refund_required',$pdo->query("SELECT status FROM club_member_charges WHERE id=$chargeId")->fetchColumn());
        self::assertSame('refund_required',$pdo->query("SELECT status FROM payments WHERE payable_type='member_charge' AND payable_id=$chargeId")->fetchColumn());
        $refund=\memberChargeAdminConfirmRefund($pdo,$chargeId,1,'FIO-REF-CALENDAR-1','Ověřeno ve Fio.',true);
        self::assertTrue($refund['changed']);self::assertSame('refunded',$pdo->query("SELECT status FROM club_member_charges WHERE id=$chargeId")->fetchColumn());
        $payment=$pdo->query("SELECT status,refund_reference,refund_confirmed_by_trainer_id FROM payments WHERE payable_type='member_charge' AND payable_id=$chargeId")->fetch(PDO::FETCH_ASSOC);
        self::assertSame('refunded',$payment['status']);self::assertSame('FIO-REF-CALENDAR-1',$payment['refund_reference']);self::assertSame(1,(int)$payment['refund_confirmed_by_trainer_id']);
        self::assertSame(1,(int)$pdo->query("SELECT COUNT(*) FROM club_member_charge_events WHERE charge_id=$chargeId AND action='manual_confirm_refund'")->fetchColumn());
    }

    public function testFamilyCanRegisterOnlyVisibleRosterMemberAfterOpening(): void
    {
        $pdo=$this->database();$event=$this->event($pdo,['planning_status'=>'confirmed','visibility'=>'rosters','team_ids'=>[10]]);\clubCalendarSetRegistration($pdo,$event['id'],1,true);
        $result=\clubCalendarFamilyRegister($pdo,$event['id'],100,200,true);self::assertSame('confirmed',$result['status']);
        self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM club_event_planned_participants')->fetchColumn());
        try{\clubCalendarFamilyRegister($pdo,$event['id'],101,201,true);self::fail('Unrelated account must be rejected.');}catch(\ClubCalendarException$e){self::assertStringContainsString('není určena',$e->getMessage());}
    }

    public function testMigrationImportsFutureLegacyRaceOnlyOnce(): void
    {
        $pdo=$this->database(false);$pdo->exec("INSERT INTO zavody(id,datum,kategorie,popis,poznamka,url_vysledky,trener_id) VALUES(5,'2099-05-01','silnice','Budoucí závod','','',1),(6,'2000-05-01','silnice','Starý závod','','',1)");$migration=require dirname(__DIR__,2).'/migrations/20260824120000_club_calendar_planning.php';$migration['up']($pdo);$migration['up']($pdo);self::assertTrue($migration['verify']($pdo));self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM club_events')->fetchColumn());self::assertSame('race:confirmed:staff:5',$pdo->query("SELECT activity_kind||':'||planning_status||':'||visibility||':'||legacy_race_id FROM club_events")->fetchColumn());
    }

    /** @param array<string,mixed> $override */
    private function event(PDO$pdo,array$override=[]):array
    {
        $input=array_replace(['name'=>'Testovací závod','activity_kind'=>'race','planning_status'=>'planned','visibility'=>'staff','starts_at'=>'2026-09-10T09:00','ends_at'=>'2026-09-10T17:00','location'=>'Velodrom','public_description_plain'=>'Informace pro účastníky.','internal_note'=>'Interní plán.','capacity'=>20,'participant_fee_minor'=>0,'fee_due_days'=>14,'team_ids'=>[]],$override);
        return \clubCalendarSaveEvent($pdo,1,0,$input);
    }

    private function database(bool$apply=true):PDO
    {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT NOT NULL,aktivni INTEGER NOT NULL)');$pdo->exec("INSERT INTO treneri VALUES(1,'Trenér',1),(2,'Druhý trenér',1)");
        $pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,narozeni TEXT)');$pdo->exec("INSERT INTO sportovci VALUES(200,'Anna','Členka','2012-01-01'),(201,'Cizí','Člen','2011-01-01')");
        $pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,email TEXT,aktivni INTEGER,email_overeno INTEGER)');$pdo->exec("INSERT INTO verejni_uzivatele VALUES(100,'Rodič','Členky','parent@example.test',1,1),(101,'Jiný','Účet','other@example.test',1,1)");
        $pdo->exec('CREATE TABLE account_person_roles(id INTEGER PRIMARY KEY AUTOINCREMENT,account_id INTEGER,sportovec_id INTEGER,relation_role TEXT,status TEXT,valid_from TEXT,valid_to TEXT)');$pdo->exec("INSERT INTO account_person_roles(account_id,sportovec_id,relation_role,status,valid_from,valid_to) VALUES(100,200,'guardian','approved','2020-01-01',NULL),(101,201,'guardian','approved','2020-01-01',NULL)");
        $pdo->exec('CREATE TABLE club_seasons(id INTEGER PRIMARY KEY,name TEXT,status TEXT,starts_on TEXT,ends_on TEXT)');$pdo->exec("INSERT INTO club_seasons VALUES(1,'2026','active','2026-01-01','2026-12-31')");
        $pdo->exec('CREATE TABLE club_teams(id INTEGER PRIMARY KEY,season_id INTEGER,code TEXT,name TEXT,status TEXT,created_by_trainer_id INTEGER)');$pdo->exec("INSERT INTO club_teams VALUES(10,1,'U15','U15','active',1),(11,1,'TRACK','Dráha','active',2)");
        $pdo->exec('CREATE TABLE club_roster_members(id INTEGER PRIMARY KEY AUTOINCREMENT,team_id INTEGER,sportovec_id INTEGER,status TEXT,valid_from TEXT,valid_to TEXT)');$pdo->exec("INSERT INTO club_roster_members(team_id,sportovec_id,status,valid_from,valid_to) VALUES(10,200,'active','2026-01-01',NULL),(11,201,'active','2026-01-01',NULL)");
        $pdo->exec("CREATE TABLE club_events(id INTEGER PRIMARY KEY AUTOINCREMENT,code TEXT UNIQUE,event_type TEXT,name TEXT,description_plain TEXT,audience_label TEXT,min_age INTEGER,max_age INTEGER,capacity INTEGER,pricing_policy TEXT,currency TEXT,registration_starts_at TEXT,registration_ends_at TEXT,status TEXT,created_by_trainer_id INTEGER,terms_version TEXT,consent_text_plain TEXT,cancellation_policy_plain TEXT,cancellation_deadline_at TEXT,terms_configured_at TEXT,terms_configured_by_trainer_id INTEGER,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)");
        $pdo->exec("CREATE TABLE club_event_sessions(id INTEGER PRIMARY KEY AUTOINCREMENT,event_id INTEGER,starts_at TEXT,ends_at TEXT,location TEXT,capacity_override INTEGER,status TEXT DEFAULT 'scheduled',created_at TEXT DEFAULT CURRENT_TIMESTAMP)");
        $pdo->exec('CREATE TABLE club_event_admin_events(id INTEGER PRIMARY KEY AUTOINCREMENT,event_id INTEGER,actor_trainer_id INTEGER,action TEXT,subject_type TEXT,subject_id INTEGER,note TEXT,payload_json TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec("CREATE TABLE club_event_registrations(id INTEGER PRIMARY KEY AUTOINCREMENT,event_id INTEGER,account_id INTEGER,sportovec_id INTEGER,relation_role_snapshot TEXT,status TEXT DEFAULT 'confirmed',registered_at TEXT,cancelled_at TEXT,cancellation_note TEXT,waitlisted_at TEXT,promoted_at TEXT,consent_version_snapshot TEXT,consent_text_snapshot TEXT,consented_at TEXT,cancellation_policy_snapshot TEXT,cancellation_deadline_snapshot TEXT,eligibility_team_ids_snapshot TEXT,eligibility_reason_snapshot TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP,UNIQUE(event_id,sportovec_id))");
        $pdo->exec('CREATE TABLE club_event_registration_events(id INTEGER PRIMARY KEY AUTOINCREMENT,registration_id INTEGER,actor_type TEXT,actor_id INTEGER,action TEXT,from_status TEXT,to_status TEXT,note TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec('CREATE TABLE club_event_roster_targets(event_id INTEGER,team_id INTEGER,actor_trainer_id INTEGER,decision_note TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(event_id,team_id))');
        $pdo->exec('CREATE TABLE ucto_vozidla(id INTEGER PRIMARY KEY,znacka_model TEXT,spz TEXT)');$pdo->exec("INSERT INTO ucto_vozidla VALUES(1,'Ford Transit','1A23456')");
        $pdo->exec('CREATE TABLE club_member_charges(id INTEGER PRIMARY KEY AUTOINCREMENT,sportovec_id INTEGER,payer_account_id INTEGER,public_code TEXT UNIQUE,charge_type TEXT,title_snapshot TEXT,period_from TEXT,period_to TEXT,amount_minor INTEGER,currency TEXT,due_on TEXT,status TEXT,source_system TEXT,source_external_id TEXT,source_import_run_id INTEGER,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP,UNIQUE(source_system,source_external_id))');
        $pdo->exec('CREATE TABLE club_member_charge_events(id INTEGER PRIMARY KEY AUTOINCREMENT,charge_id INTEGER,action TEXT,from_status TEXT,to_status TEXT,actor_type TEXT,actor_id INTEGER,reason TEXT,snapshot_json TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec('CREATE TABLE payments(id INTEGER PRIMARY KEY AUTOINCREMENT,payable_type TEXT,payable_id INTEGER,method TEXT,status TEXT,amount_minor INTEGER,currency TEXT,variable_symbol TEXT UNIQUE,iban_snapshot TEXT,bic_snapshot TEXT,account_label_snapshot TEXT,spd_payload TEXT,due_at TEXT,paid_at TEXT,confirmed_by_trainer_id INTEGER,confirmation_note TEXT,refund_sent_at TEXT,refund_reference TEXT,refund_confirmed_by_trainer_id INTEGER,refund_confirmation_note TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP,UNIQUE(payable_type,payable_id))');
        $pdo->exec('CREATE TABLE shop_bank_settings(id INTEGER PRIMARY KEY,iban TEXT,bic TEXT,account_label TEXT,due_days INTEGER,updated_by_trainer_id INTEGER,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');$pdo->exec("INSERT INTO shop_bank_settings VALUES(1,'CZ6508000000192000145399','GIBACZPX','KIS test',14,1,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
        $pdo->exec('CREATE TABLE zavody(id INTEGER PRIMARY KEY AUTOINCREMENT,datum TEXT,kategorie TEXT,popis TEXT,poznamka TEXT,url_vysledky TEXT,trener_id INTEGER)');
        if($apply){$migration=require dirname(__DIR__,2).'/migrations/20260824120000_club_calendar_planning.php';$migration['up']($pdo);self::assertTrue($migration['verify']($pdo));}
        return$pdo;
    }
}
