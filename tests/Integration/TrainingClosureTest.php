<?php
declare(strict_types=1);
namespace Tests\Integration;
use PDO;
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__,2).'/includes/training_closure.php';

final class TrainingClosureTest extends TestCase {
 private function fixture():PDO {
    $p=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    foreach([
      'treneri(id INTEGER PRIMARY KEY,jmeno TEXT,aktivni INTEGER)',
      'staff_user_positions(trainer_id INTEGER,position_code TEXT)',
      'staff_superadmins(trainer_id INTEGER)',
      'sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT)',
      'treninky(id INTEGER PRIMARY KEY,datum TEXT,delka REAL,napln TEXT,kategorie TEXT)',
      'trenink_trener(trenink_id INTEGER,trener_id INTEGER)',
      'trenink_sportovec(trenink_id INTEGER,sportovec_id INTEGER)',
      'trenink_mereni(trenink_id INTEGER,mereni_id INTEGER)',
      'planovane_treninky(id INTEGER PRIMARY KEY,trener_id INTEGER,datum TEXT,cas_od TEXT,cas_do TEXT,sportoviste_id INTEGER,stav TEXT,trenink_id INTEGER,nazev TEXT)',
      'training_roster_links(id INTEGER PRIMARY KEY,plan_id INTEGER,trenink_id INTEGER,team_id INTEGER)',
      'training_roster_expected(link_id INTEGER,sportovec_id INTEGER)',
      'club_teams(id INTEGER PRIMARY KEY,status TEXT)',
      'sportovec_obdobi(id INTEGER PRIMARY KEY,sportovec_id INTEGER,datum_od TEXT,datum_do TEXT,vyplaceno INTEGER,pocet_treninku INTEGER,castka_celkem REAL)',
    ] as $schema)$p->exec('CREATE TABLE '.$schema);
    $p->exec("INSERT INTO treneri VALUES(1,'LOCALHOST Coach',1),(2,'LOCALHOST Lead',1)");$p->exec("INSERT INTO staff_user_positions VALUES(2,'sports_lead')");
    $p->exec("INSERT INTO sportovci VALUES(10,'LOCALHOST','One'),(11,'LOCALHOST','Two')");$p->exec("INSERT INTO club_teams VALUES(3,'active')");
    $p->exec("INSERT INTO treninky VALUES(100,'2026-01-02',1.5,'LOCALHOST Session','draha')");$p->exec('INSERT INTO trenink_sportovec VALUES(100,10)');$p->exec('INSERT INTO trenink_trener VALUES(100,1)');
    $m=require dirname(__DIR__,2).'/migrations/20261023020000_training_closure.php';$m['up']($p);$m['up']($p);self::assertTrue($m['verify']($p));
    $_SESSION=['trener_id'=>2,'staff_positions'=>['sports_lead'],'staff_active_position'=>'sports_lead'];return $p;
 }
 protected function tearDown():void {$_SESSION=[];}
 private function input():array{return ['actual_started_at'=>'2026-01-02 15:00:00','actual_ended_at'=>'2026-01-02 16:30:00','participant_ids'=>[10],'coach_ids'=>[1],'reason'=>'LOCALHOST potvrzení skutečné docházky.','confirmed'=>true,'record_source'=>'paper'];}
 public function testClosurePreservesVersionsAndRejectsLostUpdate():void {
    $p=$this->fixture();$first=\trainingClosureClose($p,100,0,$this->input(),2);self::assertSame(1,$first['version']);
    self::assertSame(90,\trainingClosedActivitiesForPerson($p,10,'2026-01-01','2026-01-31')[0]['duration_minutes']);
    $input=$this->input();$input['participant_ids']=[11];$second=\trainingClosureClose($p,100,1,$input,2);
    self::assertSame(2,$second['version']);self::assertSame([],\trainingClosedActivitiesForPerson($p,10,'2026-01-01','2026-01-31'));
    $firstStored=json_decode($p->query('SELECT snapshot_json FROM training_closure_revisions WHERE version=1')->fetchColumn(),true);self::assertSame([10],$firstStored['participant_ids']);self::assertSame('paper',$firstStored['record_source']);
    try{\trainingClosureClose($p,100,1,$input,2);self::fail('Stale write accepted');}catch(\TrainingClosureException $e){self::assertStringContainsString('mezitím',$e->getMessage());}
    self::assertSame(2,(int)$p->query('SELECT COUNT(*) FROM training_closure_revisions')->fetchColumn());
 }
 public function testFutureAndUnconfirmedAttendanceRemainDraft():void {
    $p=$this->fixture();$input=$this->input();$input['actual_ended_at']='2099-01-01 16:00:00';
    try{\trainingClosureClose($p,100,0,$input,2);self::fail('Future end accepted');}catch(\TrainingClosureException $e){self::assertStringContainsString('konci',$e->getMessage());}
    $input=$this->input();$input['confirmed']=false;
    try{\trainingClosureClose($p,100,0,$input,2);self::fail('Unconfirmed accepted');}catch(\TrainingClosureException){}
    self::assertNull(\trainingClosureCurrent($p,100));self::assertSame([],\trainingClosedActivitiesForPerson($p,10,'2026-01-01','2026-01-31'));
 }
 public function testClosedRewardSnapshotIsUnchangedAndReviewIsCreated():void {
    $p=$this->fixture();$p->exec("INSERT INTO sportovec_obdobi VALUES(90,10,'2026-01-01','2026-01-31',1,8,1200)");
    $result=\trainingClosureClose($p,100,0,$this->input(),2);self::assertTrue($result['review_required']);
    $input=$this->input();$input['void']=true;\trainingClosureClose($p,100,1,$input,2);
    self::assertSame([],\trainingClosedActivitiesForPerson($p,10,'2026-01-01','2026-01-31'));self::assertSame(1200.0,(float)$p->query('SELECT castka_celkem FROM sportovec_obdobi')->fetchColumn());self::assertSame(8,(int)$p->query('SELECT pocet_treninku FROM sportovec_obdobi')->fetchColumn());self::assertSame(2,(int)$p->query('SELECT COUNT(*) FROM training_closure_reviews')->fetchColumn());
 }
 public function testFirstClosureRemovingRawParticipantStillCreatesRewardReview():void {
    $p=$this->fixture();$p->exec("INSERT INTO sportovec_obdobi VALUES(90,10,'2026-01-01','2026-01-31',1,8,1200)");$input=$this->input();$input['participant_ids']=[];
    $r=\trainingClosureClose($p,100,0,$input,2);self::assertTrue($r['review_required']);self::assertSame(90,(int)$p->query('SELECT reference_id FROM training_closure_reviews')->fetchColumn());
 }
 public function testClosedLegacyEditAndDependentDeleteAreBlocked():void {
    $p=$this->fixture();\trainingClosureClose($p,100,0,$this->input(),2);$p->beginTransaction();
    try{\trainingClosureAssertLegacyMutable($p,100);self::fail('Raw closed edit accepted');}catch(\TrainingClosureException $e){self::assertStringContainsString('Uzavřený',$e->getMessage());}finally{$p->rollBack();}
    $p=$this->fixture();$p->exec('INSERT INTO trenink_mereni VALUES(100,99)');$p->beginTransaction();
    try{\trainingClosureAssertLegacyMutable($p,100,true);self::fail('Measured training deleted');}catch(\TrainingClosureException $e){self::assertStringContainsString('Navázaný',$e->getMessage());}finally{$p->rollBack();}
 }
 public function testLiveRoleRevocationAndGuestReasonAreRequired():void {
    $p=$this->fixture();$p->exec("DELETE FROM staff_user_positions WHERE trainer_id=2");
    try{\trainingClosureClose($p,100,0,$this->input(),2);self::fail('Stale role accepted');}catch(\TrainingClosureException $e){self::assertStringContainsString('neplatí',$e->getMessage());}
    $p->exec("INSERT INTO staff_user_positions VALUES(2,'sports_lead')");$p->exec("INSERT INTO planovane_treninky VALUES(4,1,'2026-01-02','15:00','16:30',NULL,'evidovany',100,'LOCALHOST')");
    try{\trainingClosureClose($p,100,0,$this->input(),2);self::fail('Unexplained guest accepted');}catch(\TrainingClosureException $e){self::assertStringContainsString('hosta',$e->getMessage());}
    $input=$this->input();$input['guest_reasons']=[10=>'LOCALHOST zkušební účast'];self::assertSame(1,\trainingClosureClose($p,100,0,$input,2)['version']);
 }
 public function testInactiveActorCannotCloseAndExplicitSuperadminRemainsSupported():void {
    $p=$this->fixture();$p->exec('UPDATE treneri SET aktivni=0 WHERE id=2');
    try{\trainingClosureClose($p,100,0,$this->input(),2);self::fail('Inactive actor accepted');}catch(\TrainingClosureException $e){self::assertStringContainsString('neplatí',$e->getMessage());}
    self::assertSame(0,(int)$p->query('SELECT COUNT(*) FROM training_closure_revisions')->fetchColumn());
    $p->exec('UPDATE treneri SET aktivni=1 WHERE id=2;DELETE FROM staff_user_positions WHERE trainer_id=2;INSERT INTO staff_superadmins VALUES(2)');
    self::assertSame(1,\trainingClosureClose($p,100,0,$this->input(),2)['version']);
    $p->exec('DELETE FROM staff_superadmins WHERE trainer_id=2');
    try{\trainingClosureClose($p,100,1,$this->input(),2);self::fail('Revoked superadmin accepted');}catch(\TrainingClosureException $e){self::assertStringContainsString('neplatí',$e->getMessage());}
    self::assertSame(1,(int)$p->query('SELECT COUNT(*) FROM training_closure_revisions')->fetchColumn());
 }
 public function testPlanningCollisionBlocksTrainerAndRosterButAllowsAdjacentTimes():void {
    $p=$this->fixture();$p->exec("INSERT INTO planovane_treninky VALUES(4,1,'2026-01-02','15:00','16:30',NULL,'planovany',NULL,'LOCALHOST')");$p->exec('INSERT INTO training_roster_links VALUES(1,4,NULL,3)');
    $p->beginTransaction();
    try{\trainingScheduleAssertAvailable($p,2,1,'2026-01-02','16:00','17:00',null,[]);self::fail('Coach collision accepted');}catch(\TrainingClosureException $e){self::assertStringContainsString('trenér',$e->getMessage());}
    try{\trainingScheduleAssertAvailable($p,2,2,'2026-01-02','16:00','17:00',null,[3]);self::fail('Roster collision accepted');}catch(\TrainingClosureException $e){self::assertStringContainsString('soupiska',$e->getMessage());}
    \trainingScheduleAssertAvailable($p,2,1,'2026-01-02','16:30','17:30',null,[3]);$p->commit();
 }
 public function testMakeupRequiresCurrentClosedRevisionAndActualEnd():void {
    $p=$this->fixture();require_once dirname(__DIR__,2).'/includes/club_makeup.php';$p->exec("INSERT INTO planovane_treninky VALUES(4,1,'2026-01-02','15:00','16:30',NULL,'evidovany',100,'LOCALHOST')");
    self::assertFalse(\clubMakeupTrainingEnded($p,4,100));$input=$this->input();$input['guest_reasons']=[10=>'LOCALHOST zkušební účast'];\trainingClosureClose($p,100,0,$input,2);self::assertTrue(\clubMakeupTrainingEnded($p,4,100));$input['void']=true;\trainingClosureClose($p,100,1,$input,2);self::assertFalse(\clubMakeupTrainingEnded($p,4,100));
 }
 public function testOwnVenueReservationAllowsTitleEditButNotSilentMove():void {
    $p=$this->fixture();$p->exec('ALTER TABLE planovane_treninky ADD COLUMN rezervace_id INTEGER');$p->exec('CREATE TABLE sportovist(id INTEGER PRIMARY KEY,aktivni INTEGER)');$p->exec('INSERT INTO sportovist VALUES(2,1)');$p->exec('CREATE TABLE rezervace_sportovist(id INTEGER PRIMARY KEY,sportoviste_id INTEGER,datum TEXT,cas_od TEXT,cas_do TEXT)');
    $p->exec("INSERT INTO planovane_treninky VALUES(4,1,'2026-01-02','15:00','16:30',2,'planovany',NULL,'LOCALHOST',7)");$p->exec("INSERT INTO rezervace_sportovist VALUES(7,2,'2026-01-02','15:00','16:30')");$p->beginTransaction();
    \trainingScheduleAssertAvailable($p,2,1,'2026-01-02','15:00','16:30',2,[],4);
    try{\trainingScheduleAssertAvailable($p,2,1,'2026-01-02','17:00','18:00',2,[],4);self::fail('Linked reservation silently moved');}catch(\TrainingClosureException $e){self::assertStringContainsString('rezervované',$e->getMessage());}
    try{\trainingScheduleAssertAvailable($p,2,1,'2026-01-02','15:00','16:30',null,[],4);self::fail('Linked venue silently removed');}catch(\TrainingClosureException $e){self::assertStringContainsString('rezervované',$e->getMessage());}$p->rollBack();
 }
}
