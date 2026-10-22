<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__,2).'/includes/medical_clearance.php';
final class MedicalClearanceTest extends TestCase {
 private function database():PDO {
  $p=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$p->exec('PRAGMA foreign_keys=ON');
  $p->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT,aktivni INTEGER);CREATE TABLE staff_user_positions(trainer_id INTEGER,position_code TEXT);CREATE TABLE staff_superadmins(trainer_id INTEGER);CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT);');
  $p->exec("INSERT INTO treneri VALUES(1,'LOCALHOST Registrar',1),(2,'LOCALHOST Coach',1);INSERT INTO staff_user_positions VALUES(1,'registrar'),(2,'coach');INSERT INTO sportovci VALUES(1,'LOCALHOST child'),(2,'LOCALHOST foreign');");
  foreach(['20261023040000_medical_clearance','20261022213000_operational_cases']as$name){$m=require dirname(__DIR__,2).'/migrations/'.$name.'.php';$m['up']($p);self::assertTrue($m['verify']($p));}
  $_SESSION=['trener_id'=>1,'staff_positions'=>['registrar'],'staff_active_position'=>'registrar'];return$p;
 }
 private function watch(PDO $p):int {
  $policy=medicalClearancePolicyCreate($p,['purpose'=>'LOCALHOST tréninky','legal_basis'=>'LOCALHOST syntetické potvrzení','retention_rule'=>'LOCALHOST jen test','activity_rule'=>'LOCALHOST vedoucí posoudí chybějící podklad','reminder_days'=>30,'confirmed'=>'1']);
  return medicalClearanceWatchCreate($p,['sportovec_id'=>1,'policy_id'=>$policy,'reason'=>'LOCALHOST sledování']);
 }
 private function record(PDO $p,int $watch,string $until='2030-01-31'):int{return medicalClearanceRecord($p,['watch_id'=>$watch,'issued_on'=>'2026-01-01','valid_until'=>$until,'original_location'=>'LOCALHOST uzamčený šanon SECRET','reason'=>'LOCALHOST podání']);}
 private function denied(callable $fn):void {try{$fn();self::fail('Expected rejection');}catch(DomainException|InvalidArgumentException $e){self::assertNotSame('',$e->getMessage());}}
 protected function tearDown():void{$_SESSION=[];}
 public function testPendingVerificationInclusiveExpiryAndNoSensitiveMetadata():void {
  $p=$this->database();$watch=$this->watch($p);self::assertSame('missing',medicalClearanceStatus($p,1,'LOCALHOST tréninky','2026-02-01')['status']);
  $id=$this->record($p,$watch);self::assertSame('pending',medicalClearanceStatus($p,1,'LOCALHOST tréninky','2026-02-01')['status']);
  medicalClearanceDecide($p,$id,1,'verified','LOCALHOST porovnáno s originálem');
  self::assertSame('valid',medicalClearanceStatus($p,1,'LOCALHOST tréninky','2030-01-31')['status']);
  self::assertSame('expired',medicalClearanceStatus($p,1,'LOCALHOST tréninky','2030-02-01')['status']);
  self::assertSame('not_yet_valid',medicalClearanceStatus($p,1,'LOCALHOST tréninky','2025-12-31')['status']);
  self::assertStringNotContainsString('SECRET',json_encode(medicalClearanceView($p,1,'2030-01-31')));
  self::assertSame('2030-01-01',$p->query('SELECT next_review_on FROM medical_clearance_watches')->fetchColumn());
 }
 public function testReplacementWaitsForVerificationAndStaleDecisionRejected():void {
  $p=$this->database();$watch=$this->watch($p);$a=$this->record($p,$watch);medicalClearanceDecide($p,$a,1,'verified','LOCALHOST ověření');
  $b=$this->record($p,$watch,'2031-01-31');self::assertSame('2030-01-31',medicalClearanceStatus($p,1,'LOCALHOST tréninky','2026-02-01')['valid_until']);
  $this->denied(fn()=>$this->record($p,$watch));medicalClearanceDecide($p,$b,1,'verified','LOCALHOST nový originál');
  self::assertSame('superseded',$p->query('SELECT state FROM medical_clearance_records WHERE id='.$a)->fetchColumn());
  self::assertSame($a,(int)$p->query('SELECT replaces_id FROM medical_clearance_records WHERE id='.$b)->fetchColumn());
  $this->denied(fn()=>medicalClearanceDecide($p,$b,1,'revoked','LOCALHOST zastaralý formulář'));
  medicalClearanceDecide($p,$b,2,'revoked','LOCALHOST odvolání');self::assertSame('revoked',medicalClearanceStatus($p,1,'LOCALHOST tréninky','2026-02-01')['status']);
 }
 public function testExpiryWorkItemCannotBeClosedByContactAndHasOwner():void {
  $p=$this->database();$watch=$this->watch($p);$input=['source_type'=>'medical_watch','source_id'=>$watch,'owner_trainer_id'=>1,'next_step'=>'LOCALHOST vyžádat originál','review_on'=>date('Y-m-d'),'status'=>'open','revision'=>0,'reason'=>'LOCALHOST osobní agenda'];
  $case=operationalCaseSave($p,1,'registrar',$input);self::assertGreaterThan(0,$case);
  medicalClearanceContact($p,$watch,'LOCALHOST rodič osobně kontaktován');$this->denied(fn()=>operationalCaseSave($p,1,'registrar',array_replace($input,['status'=>'closed','revision'=>1])));
  $id=$this->record($p,$watch);medicalClearanceDecide($p,$id,1,'verified','LOCALHOST ověřeno');operationalCaseSave($p,1,'registrar',array_replace($input,['status'=>'closed','revision'=>1]));
  self::assertSame('closed',$p->query('SELECT status FROM operational_cases')->fetchColumn());
 }
 public function testLiveRoleRevocationAndNoImplicitCoachDocumentAccess():void {
  $p=$this->database();$watch=$this->watch($p);$id=$this->record($p,$watch);
  $_SESSION=['trener_id'=>2,'staff_positions'=>['coach'],'staff_active_position'=>'coach'];$this->denied(fn()=>medicalClearanceDownload($p,$id));$this->denied(fn()=>medicalClearanceDecide($p,$id,1,'verified','LOCALHOST denied'));
  $_SESSION=['trener_id'=>1,'staff_positions'=>['registrar'],'staff_active_position'=>'registrar'];$p->exec('DELETE FROM staff_user_positions WHERE trainer_id=1');
  $this->denied(fn()=>medicalClearanceView($p,1,'2026-02-01'));$this->denied(fn()=>medicalClearanceDecide($p,$id,1,'verified','LOCALHOST denied'));
 }
 public function testInvalidDatesAndFailedAuditRollBackEvidence():void {
  $p=$this->database();$watch=$this->watch($p);$this->denied(fn()=>medicalClearanceStatus($p,1,'LOCALHOST tréninky','2026-02-31'));
  $p->exec("CREATE TRIGGER fail_medical BEFORE INSERT ON medical_clearance_events BEGIN SELECT RAISE(ABORT,'LOCALHOST audit failure'); END");
  try{$this->record($p,$watch);self::fail();}catch(PDOException $e){self::assertStringContainsString('audit failure',$e->getMessage());}
  self::assertSame(0,(int)$p->query('SELECT COUNT(*) FROM medical_clearance_records')->fetchColumn());self::assertFalse($p->inTransaction());
 }
 public function testReplacementPreservesHistoricalDateAndDoesNotFillValidityGap():void {
  $p=$this->database();$watch=$this->watch($p);$a=$this->record($p,$watch,'2026-03-31');medicalClearanceDecide($p,$a,1,'verified','LOCALHOST původní podklad');
  $b=medicalClearanceRecord($p,['watch_id'=>$watch,'issued_on'=>'2026-05-01','valid_until'=>'2030-01-31','original_location'=>'LOCALHOST nový originál','reason'=>'LOCALHOST další prohlídka']);medicalClearanceDecide($p,$b,1,'verified','LOCALHOST nový podklad');
  self::assertSame('valid',medicalClearanceStatus($p,1,'LOCALHOST tréninky','2026-03-31')['status']);
  self::assertSame('2026-03-31',medicalClearanceStatus($p,1,'LOCALHOST tréninky','2026-03-31')['valid_until']);
  self::assertSame('expired',medicalClearanceStatus($p,1,'LOCALHOST tréninky','2026-04-01')['status']);
  self::assertSame('valid',medicalClearanceStatus($p,1,'LOCALHOST tréninky','2026-05-01')['status']);
  self::assertSame('2030-01-31',medicalClearanceStatus($p,1,'LOCALHOST tréninky','2026-05-01')['valid_until']);
  medicalClearanceDecide($p,$b,2,'revoked','LOCALHOST aktuální podklad odvolán');
  self::assertSame('revoked',medicalClearanceStatus($p,1,'LOCALHOST tréninky','2026-03-31')['status']);
 }
 public function testRevokedHistoricalPredecessorCannotBeUsedAndCurrentStaysValid():void {
  $p=$this->database();$watch=$this->watch($p);$a=$this->record($p,$watch);medicalClearanceDecide($p,$a,1,'verified','LOCALHOST původní podklad');
  $b=medicalClearanceRecord($p,['watch_id'=>$watch,'issued_on'=>'2026-05-01','valid_until'=>'2031-01-31','original_location'=>'LOCALHOST nový originál','reason'=>'LOCALHOST další prohlídka']);medicalClearanceDecide($p,$b,1,'verified','LOCALHOST nový podklad');
  medicalClearanceDecide($p,$a,3,'revoked','LOCALHOST dodatečně zjištěný chybný historický podklad');
  self::assertSame('revoked',medicalClearanceStatus($p,1,'LOCALHOST tréninky','2026-04-01')['status']);
  self::assertSame('valid',medicalClearanceStatus($p,1,'LOCALHOST tréninky','2026-05-01')['status']);
  $c=medicalClearanceRecord($p,['watch_id'=>$watch,'issued_on'=>'2026-08-01','valid_until'=>'2032-01-31','original_location'=>'LOCALHOST další originál','reason'=>'LOCALHOST třetí prohlídka']);medicalClearanceDecide($p,$c,1,'verified','LOCALHOST třetí podklad');
  self::assertSame('revoked',medicalClearanceStatus($p,1,'LOCALHOST tréninky','2026-04-01')['status']);
  self::assertSame('2031-01-31',medicalClearanceStatus($p,1,'LOCALHOST tréninky','2026-06-01')['valid_until']);
  self::assertSame('2032-01-31',medicalClearanceStatus($p,1,'LOCALHOST tréninky','2026-09-01')['valid_until']);
  $draft=$this->record($p,$watch,'2033-01-31');medicalClearanceDecide($p,$draft,1,'rejected','LOCALHOST odmítnutá neúplná náhrada');
  self::assertSame('2032-01-01',$p->query('SELECT next_review_on FROM medical_clearance_watches')->fetchColumn());
 }
}
