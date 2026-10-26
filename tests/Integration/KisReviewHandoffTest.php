<?php
declare(strict_types=1);
namespace Tests\Integration;
use PDO;
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__).'/Support/KisReviewFixture.php';
final class KisReviewHandoffTest extends TestCase{
 private PDO $p;private string $archive;private string|false $old;
 protected function setUp():void{$this->old=getenv('KIS_SOURCE_ARCHIVE_DIR');$this->archive=sys_get_temp_dir().'/kis-review-'.bin2hex(random_bytes(6));mkdir($this->archive);putenv('KIS_SOURCE_ARCHIVE_DIR='.$this->archive);$this->p=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);\kisReviewFixture($this->p,$this->archive);}
 protected function tearDown():void{foreach(glob($this->archive.'/*.raw') as $f)unlink($f);rmdir($this->archive);putenv($this->old===false?'KIS_SOURCE_ARCHIVE_DIR':'KIS_SOURCE_ARCHIVE_DIR='.$this->old);}
 private function preview():array{return \kisReviewPreview($this->p,1,\kisReviewSource($this->p,1)['fingerprint'],[1=>['target'=>'5','teams'=>[1]],2=>['target'=>'new','teams'=>[1]]],'2026-10-25',7,true);}
 public function testSourceFeedbackDistinguishesMissingRunWrongStateAndMissingArchive():void{
  try{\kisReviewSource($this->p,999999);self::fail();}catch(\DomainException $e){self::assertStringContainsString('běh neexistuje',$e->getMessage());}
  $this->p->exec("UPDATE kis_import_runs SET status='applied' WHERE id=1");
  try{\kisReviewSource($this->p,1);self::fail();}catch(\DomainException $e){self::assertStringContainsString('není ve stavu Náhled',$e->getMessage());}
  $this->p->exec("UPDATE kis_import_runs SET status='preview',source_manifest_json='{}' WHERE id=1");
  try{\kisReviewSource($this->p,1);self::fail();}catch(\DomainException $e){self::assertStringContainsString('Chybí archivovaný export',$e->getMessage());}
  self::assertSame(0,(int)$this->p->query('SELECT COUNT(*) FROM kis_review_plans')->fetchColumn());
 }
 public function testApplyRollbackKeepMoneyAccountsAndAuditAndAreIdempotent():void{
  $plan=$this->preview();$finance=\kisReviewRows($this->p,'payments');$r=\kisReviewExecute($this->p,$plan['id'],$plan['fingerprint'],7,true,true,'Syntetická přejímka');
  self::assertSame(2,$r['people']);self::assertTrue(\kisReviewExecute($this->p,$plan['id'],$plan['fingerprint'],7,true,true,'Opakování přejímky')['idempotent']);
  self::assertSame('Opravená',$this->p->query('SELECT jmeno FROM sportovci WHERE id=5')->fetchColumn());
  self::assertSame('opravena',$this->p->query('SELECT first_name_norm FROM sportovci WHERE id=5')->fetchColumn());
  self::assertSame(2,(int)$this->p->query("SELECT COUNT(*) FROM club_roster_members WHERE status='active'")->fetchColumn());
  \kisReviewExecute($this->p,$plan['id'],$plan['fingerprint'],7,true,true,'Návrat přejímky',true);
  self::assertTrue(\kisReviewExecute($this->p,$plan['id'],$plan['fingerprint'],7,true,true,'Opakování návratu',true)['idempotent']);
  self::assertSame('Původní',$this->p->query('SELECT jmeno FROM sportovci WHERE id=5')->fetchColumn());
  self::assertNull($this->p->query('SELECT first_name_norm FROM sportovci WHERE id=5')->fetchColumn());
  self::assertSame('archiv',$this->p->query("SELECT stav_clenstvi FROM sportovci WHERE kis_external_id='KIS-NEW'")->fetchColumn());
  self::assertSame(0,(int)$this->p->query("SELECT COUNT(*) FROM club_roster_members WHERE status='active'")->fetchColumn());
  self::assertSame(2,(int)$this->p->query('SELECT COUNT(*) FROM kis_review_events')->fetchColumn());
  self::assertSame($finance,\kisReviewRows($this->p,'payments'));
 }
 public function testTargetDriftAndSourceDriftBlockBeforeAnyCanonicalWrite():void{
  $plan=$this->preview();$this->p->exec("UPDATE sportovci SET jmeno='Ruční změna' WHERE id=5");
  try{\kisReviewExecute($this->p,$plan['id'],$plan['fingerprint'],7,true,true,'Starý náhled');self::fail();}catch(\DomainException){self::assertSame(0,(int)$this->p->query('SELECT COUNT(*) FROM kis_review_events')->fetchColumn());}
  $plan=$this->preview();$this->p->exec("UPDATE kis_import_rows SET jmeno='Změna zdroje' WHERE id=2");
  $this->expectException(\DomainException::class);\kisReviewExecute($this->p,$plan['id'],$plan['fingerprint'],7,true,true,'Změněný zdroj');
 }
 public function testRollbackRefusesNewDependentOrderAndDoesNotPartiallyUndo():void{
  $plan=$this->preview();\kisReviewExecute($this->p,$plan['id'],$plan['fingerprint'],7,true,true,'Syntetická přejímka');
  $new=(int)$this->p->query("SELECT id FROM sportovci WHERE kis_external_id='KIS-NEW'")->fetchColumn();$this->p->exec('INSERT INTO shop_order_items(sportovec_id) VALUES('.$new.')');
  try{\kisReviewExecute($this->p,$plan['id'],$plan['fingerprint'],7,true,true,'Konfliktní návrat',true);self::fail();}catch(\DomainException){self::assertSame('Opravená',$this->p->query('SELECT jmeno FROM sportovci WHERE id=5')->fetchColumn());self::assertSame('applied',\kisReviewPlan($this->p,$plan['id'])['status']);}
 }
 public function testWrongFingerprintMissingPermissionAndConfirmationAreBlocked():void{
  $plan=$this->preview();foreach([[str_repeat('0',64),true,true],[$plan['fingerprint'],false,true],[$plan['fingerprint'],true,false]] as [$fp,$allowed,$confirmed]){
   try{\kisReviewExecute($this->p,$plan['id'],$fp,7,$allowed,$confirmed,'Neplatné potvrzení');self::fail();}catch(\DomainException){self::assertSame(1,(int)$this->p->query('SELECT COUNT(*) FROM sportovci')->fetchColumn());}
  }
 }
 public function testArchiveAndDuplicateStableIdCannotBeIgnored():void{
  file_put_contents($this->archive.'/users.raw','changed');
  try{$this->preview();self::fail();}catch(\KisSourceArchiveException){self::assertSame(0,(int)$this->p->query('SELECT COUNT(*) FROM kis_review_plans')->fetchColumn());}
  file_put_contents($this->archive.'/users.raw','Synthetic users');$this->p->exec("UPDATE kis_import_rows SET kis_external_id='KIS-5' WHERE id=2");
  $this->expectException(\DomainException::class);$this->preview();
 }
 public function testFailureInsideRosterWriteRollsBackPeopleAndAudit():void{
  $plan=$this->preview();$this->p->exec("CREATE TRIGGER fail_roster BEFORE INSERT ON club_roster_members BEGIN SELECT RAISE(ABORT,'failure'); END");
  try{\kisReviewExecute($this->p,$plan['id'],$plan['fingerprint'],7,true,true,'Test atomicity');self::fail();}catch(\PDOException){self::assertSame('Původní',$this->p->query('SELECT jmeno FROM sportovci WHERE id=5')->fetchColumn());self::assertSame(0,(int)$this->p->query('SELECT COUNT(*) FROM sportovec_history')->fetchColumn());}
 }
 public function testRollbackSurvivesLostSourceArchive():void{
  $plan=$this->preview();\kisReviewExecute($this->p,$plan['id'],$plan['fingerprint'],7,true,true,'Syntetická přejímka');
  unlink($this->archive.'/users.raw');
  \kisReviewExecute($this->p,$plan['id'],$plan['fingerprint'],7,true,true,'Návrat bez zdroje',true);
  self::assertSame('rolled_back',\kisReviewPlan($this->p,$plan['id'])['status']);
 }
 public function testMissingBirthDateRequiresVerifiedSourceInsteadOfGuess():void{
  $this->p->exec('UPDATE kis_import_rows SET narozeni=NULL WHERE id=2');
  $this->expectException(\DomainException::class);$this->preview();
 }
 public function testMissingManifestAndWrongStableIdMappingCannotProceed():void{
  try{\kisReviewPreview($this->p,1,\kisReviewSource($this->p,1)['fingerprint'],[1=>['target'=>'new'],2=>['target'=>'5']],'2026-10-25',7,true);self::fail();}catch(\DomainException){self::assertSame(0,(int)$this->p->query('SELECT COUNT(*) FROM kis_review_plans')->fetchColumn());}
  $manifest=json_decode($this->p->query('SELECT source_manifest_json FROM kis_import_runs WHERE id=1')->fetchColumn(),true);unset($manifest['sources']['payments']);
  $this->p->prepare('UPDATE kis_import_runs SET source_manifest_json=? WHERE id=1')->execute([json_encode($manifest)]);
  $this->expectException(\DomainException::class);$this->preview();
 }
}
