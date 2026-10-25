<?php
declare(strict_types=1);
namespace Tests\Integration;
use PDO;
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__,2).'/includes/race_results.php';
final class RaceResultsTest extends TestCase {
 public function testLegacyProjectionPreservesRawValuesWithoutGuessingOrWriting():void{
  $p=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  $p->exec("CREATE TABLE sportovci(id INTEGER,jmeno TEXT,prijmeni TEXT);CREATE TABLE zavod_sportovec(zavod_id INTEGER,sportovec_id INTEGER,cas TEXT,poradi INTEGER,result_status TEXT);INSERT INTO sportovci VALUES(5,'Test','Localhost');INSERT INTO zavod_sportovec VALUES(1,5,'legacy unknown time',2,NULL),(2,5,'00:12.000',1,'finished')");
  $before=$p->query('SELECT * FROM zavod_sportovec')->fetchAll(PDO::FETCH_ASSOC);
  $rows=\raceResultsLegacyPreview($p,1);
  self::assertCount(1,$rows);self::assertSame('person:5',$rows[0]['key']);self::assertSame('legacy unknown time',$rows[0]['time']);self::assertSame(2,$rows[0]['rank']);
  self::assertSame('',$rows[0]['discipline']);self::assertSame('',$rows[0]['heat']);self::assertSame('entered',$rows[0]['status']);
  self::assertSame($before,$p->query('SELECT * FROM zavod_sportovec')->fetchAll(PDO::FETCH_ASSOC));
 }
 private function db():PDO{
  $p=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  $p->exec('CREATE TABLE zavody(id INTEGER PRIMARY KEY);CREATE TABLE sportovci(id INTEGER PRIMARY KEY);CREATE TABLE zavod_sportovec(zavod_id INTEGER,sportovec_id INTEGER);INSERT INTO zavody VALUES(1),(2);INSERT INTO sportovci VALUES(5);INSERT INTO zavod_sportovec VALUES(1,5)');
  $m=require dirname(__DIR__,2).'/migrations/20261025230000_race_result_revisions.php';$m['up']($p);self::assertTrue($m['verify']($p));return $p;
 }
 private function rows():array{return [
  ['key'=>'person:5','person_id'=>'5','name'=>'Vlastní sportovec','club'=>'Kovo','discipline'=>'Sprint','heat'=>'1','status'=>'finished','time'=>'00:12.345','rank'=>'1'],
  ['key'=>'ext:a','person_id'=>'','name'=>'Stejné jméno','club'=>'A','discipline'=>'Sprint','heat'=>'1','status'=>'dns','time'=>'','rank'=>''],
  ['key'=>'ext:b','person_id'=>'','name'=>'Stejné jméno','club'=>'B','discipline'=>'Sprint','heat'=>'1','status'=>'dnf','time'=>'','rank'=>''],
  ['key'=>'person:5','person_id'=>'5','name'=>'Vlastní sportovec','club'=>'Kovo','discipline'=>'Sprint','heat'=>'2','status'=>'finished','time'=>'00:12.001','rank'=>'1'],
 ];}
 public function testDraftPublishCorrectionAndCompensatingRevisionPreserveHistory():void{
  $p=$this->db();$rows=$this->rows();$draft=\raceResultsWrite($p,1,7,true,0,'draft',$rows,'První náhled',str_repeat('a',32));
  self::assertTrue(\raceResultsWrite($p,1,7,true,0,'draft',$rows,'První náhled',str_repeat('a',32))['idempotent']);
  self::assertNull(\raceResultsState($p,1)['published_revision_id']);
  $pub=\raceResultsWrite($p,1,7,true,1,'published',$rows,'Kontrola výsledků',str_repeat('b',32));
  self::assertCount(4,\raceResultsRevision($p,1,$pub['id'])['rows']);
  self::assertSame(3,(int)$p->query('SELECT COUNT(*) FROM race_participants')->fetchColumn());
  self::assertSame(1,(int)$p->query('SELECT COUNT(*) FROM sportovci')->fetchColumn());
  $rows[0]['time']='00:11.999';\raceResultsWrite($p,1,7,true,2,'draft',$rows,'Oprava času',str_repeat('c',32));
  self::assertSame($pub['id'],(int)\raceResultsState($p,1)['published_revision_id']);
  \raceResultsWrite($p,1,7,true,3,'draft',\raceResultsRevision($p,1,$draft['id'])['rows'],'Návrat původního času',str_repeat('d',32));
  self::assertSame('00:12.345',\raceResultsRevision($p,1,$pub['id'])['rows'][0]['time']);
  self::assertSame(4,(int)$p->query('SELECT COUNT(*) FROM race_result_revisions')->fetchColumn());
  self::assertNull(\raceResultsRevision($p,2,$pub['id']));
 }
 public function testStaleVersionAuthorizationAndUnreviewedPublishCannotWrite():void{
  $p=$this->db();$rows=$this->rows();
  foreach([[false,0,'draft'],[true,1,'draft'],[true,0,'published']] as [$allowed,$version,$kind]){
   try{\raceResultsWrite($p,1,7,$allowed,$version,$kind,$rows,'Neplatný zápis',bin2hex(random_bytes(16)));self::fail('Expected rejection');}catch(\DomainException){self::assertSame(0,(int)$p->query('SELECT COUNT(*) FROM race_result_revisions')->fetchColumn());}
  }
 }
 public function testInvalidRowsAndCsvRejectWithoutDeletingPublishedResults():void{
  $p=$this->db();$rows=$this->rows();\raceResultsWrite($p,1,7,true,0,'draft',$rows,'Validní data',str_repeat('a',32));
  foreach([['time'=>'12:99'],['status'=>'dns'],['status'=>'dnf'],['status'=>'dsq'],['person_id'=>'999'],['key'=>'ext:wrong'],['rank'=>'0']] as $bad){
   $invalid=$rows;$invalid[0]=array_replace($invalid[0],$bad);
   try{\raceResultsWrite($p,1,7,true,1,'draft',$invalid,'Chybný řádek',bin2hex(random_bytes(16)));self::fail('Expected validation');}catch(\InvalidArgumentException){self::assertSame(1,(int)\raceResultsState($p,1)['version']);}
  }
  $valid=$rows;$valid[2]['status']='dsq';$valid[3]['discipline']='Keirin';
  self::assertSame('dsq',\raceResultsNormalize($p,1,$valid)[2]['status']);
  self::assertSame('Keirin',\raceResultsNormalize($p,1,$valid)[3]['discipline']);
  self::assertSame(\raceResultsNormalize($p,1,$rows),\raceResultsNormalize($p,1,\raceResultsParseCsv(\raceResultsCsv($rows))));
  $this->expectException(\InvalidArgumentException::class);\raceResultsParseCsv("name;time\nSomeone;01:00");
 }
 public function testFailureAfterParticipantsRollsBackEntireTransaction():void{
  $p=$this->db();$p->exec("CREATE TRIGGER fail_revision BEFORE INSERT ON race_result_revisions BEGIN SELECT RAISE(ABORT,'failure'); END");
  try{\raceResultsWrite($p,1,7,true,0,'draft',$this->rows(),'Test atomicity',str_repeat('a',32));self::fail('Expected failure');}catch(\PDOException){self::assertSame(0,(int)$p->query('SELECT COUNT(*) FROM race_participants')->fetchColumn());self::assertFalse($p->inTransaction());}
 }
 public function testDuplicateHeatAndReusedRequestKeyCannotChangeIdentity():void{
  $p=$this->db();$rows=$this->rows();\raceResultsWrite($p,1,7,true,0,'draft',$rows,'První náhled',str_repeat('a',32));
  try{\raceResultsWrite($p,1,7,true,1,'draft',$rows,'Jiný důvod',str_repeat('a',32));self::fail();}catch(\DomainException){self::assertSame(1,(int)\raceResultsState($p,1)['version']);}
  $rows[]=$rows[0];$this->expectException(\InvalidArgumentException::class);\raceResultsNormalize($p,1,$rows);
 }
 public function testHtmlInImportedNamesIsEscapedInTable():void{
  $p=$this->db();$rows=$this->rows();$rows[1]['name']='<img src=x onerror=alert(1)>';$displayRows=\raceResultsNormalize($p,1,$rows);
  ob_start();include dirname(__DIR__,2).'/includes/race_results_table.php';$html=ob_get_clean();
  self::assertStringNotContainsString('<img',$html);self::assertStringContainsString('&lt;img',$html);
 }
}
