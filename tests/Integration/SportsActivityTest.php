<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__,2).'/includes/sports_activity.php';

final class SportsActivityTest extends TestCase
{
    private function db():PDO {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,aktivni INT)');$pdo->exec('INSERT INTO treneri VALUES(1,1),(2,1)');
        $pdo->exec('CREATE TABLE staff_user_positions(trainer_id INT,position_code TEXT)');$pdo->exec("INSERT INTO staff_user_positions VALUES(1,'sports_lead'),(2,'coach')");
        $pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY)');$pdo->exec('INSERT INTO sportovci VALUES(10),(20)');
        $pdo->exec('CREATE TABLE club_events(id INTEGER PRIMARY KEY,name TEXT,activity_kind TEXT,planning_status TEXT,legacy_race_id INT)');$pdo->exec("INSERT INTO club_events VALUES(1,'LOCALHOST Závod','race','completed',55),(2,'LOCALHOST Akce','camp','confirmed',NULL)");
        $pdo->exec('CREATE TABLE club_event_people(event_id INT,trainer_id INT,person_role TEXT)');
        $m=require dirname(__DIR__,2).'/migrations/20261023030000_sports_activity.php';$m['up']($pdo);self::assertTrue($m['verify']($pdo));
        $_SESSION=['trener_id'=>1,'staff_positions'=>['sports_lead'],'staff_active_position'=>'sports_lead'];return $pdo;
    }
    protected function tearDown():void {$_SESSION=[];}
    private function input(string $stage='started'):array{return ['stage'=>$stage,'actual_date'=>in_array($stage,['started','attended'],true)?'2026-01-10':'','result_status'=>$stage==='started'?'DNF':'','reason'=>'LOCALHOST doložený stav z výsledkové listiny','source_reference'=>'LOCALHOST originál listiny','confirmed'=>true];}
    private function record(PDO $pdo,string $stage='started',int $version=0,string $key='local-sport-record-1'):array {return sportsEventRecord($pdo,1,10,1,$this->input($stage),$version,$key);}

    public function testNominationsAndRaceLinkNeverCountAsStart():void {
        $pdo=$this->db();self::assertSame(0,sportsActivitySummary($pdo,10,'2026-01-01','2026-12-31')['race_count']);
        foreach(['nominated','accepted','registered'] as $v=>$stage){$this->record($pdo,$stage,$v,'local-stage-key-'.$v);self::assertSame(0,sportsActivitySummary($pdo,10,'2026-01-01','2026-12-31')['race_count']);}
        $this->record($pdo,'started',3,'local-actual-start');$s=sportsActivitySummary($pdo,10,'2026-01-01','2026-12-31');self::assertSame(1,$s['race_count']);self::assertSame('DNF',$s['activities'][0]['result_status']);self::assertNull($s['activities'][0]['duration_minutes']);
    }
    public function testRetryIsIdempotentAndChangedRetryRejected():void {
        $pdo=$this->db();$a=$this->record($pdo);$b=$this->record($pdo);self::assertSame($a['id'],$b['id']);self::assertFalse($b['changed']);self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM sports_event_revisions')->fetchColumn());
        $this->expectException(RuntimeException::class);sportsEventRecord($pdo,1,10,1,$this->input('registered'),0,'local-sport-record-1');
    }
    public function testStaleVersionCannotOverwrite():void {
        $pdo=$this->db();$this->record($pdo);try{$this->record($pdo,'cancelled',0,'local-stale-cancel');self::fail('stale version accepted');}catch(RuntimeException $e){self::assertStringContainsString('verzi',$e->getMessage());}self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM sports_event_revisions')->fetchColumn());
    }
    public function testCorrectionPreservesHandedOverStatementAndSignalsDifference():void {
        $pdo=$this->db();$this->record($pdo);$id=sportsActivityStatement($pdo,10,'2026-01-01','2026-12-31',1,true);self::assertSame($id,sportsActivityStatement($pdo,10,'2026-01-01','2026-12-31',1,true));
        $before=$pdo->query('SELECT * FROM sports_activity_statements')->fetch();$this->record($pdo,'cancelled',1,'local-correction-key');$current=sportsActivitySummary($pdo,10,'2026-01-01','2026-12-31');self::assertSame(0,$current['race_count']);self::assertNotSame($before['snapshot_hash'],sportsActivityHash($current));self::assertSame($before,$pdo->query('SELECT * FROM sports_activity_statements')->fetch());self::assertSame(2,(int)$pdo->query('SELECT COUNT(*) FROM sports_event_revisions')->fetchColumn());
    }
    public function testUnknownValueAndUnitStayNullAndFormulaCannotExecute():void {
        $pdo=$this->db();$input=$this->input();$input['result_value']='=HYPERLINK("https://invalid")';sportsEventRecord($pdo,1,10,1,$input,0,'local-formula-key');$s=sportsActivitySummary($pdo,10,'2026-01-01','2026-12-31');self::assertNull($s['activities'][0]['result_unit']);self::assertStringContainsString("'=HYPERLINK",sportsActivityCsv($s));self::assertStringContainsString('sports_event_revisions',sportsActivityCsv($s));
    }
    public function testFutureActualDateRejected():void {$this->db();$i=$this->input();$i['actual_date']='2099-01-01';$this->expectException(InvalidArgumentException::class);sportsEventValidate($i);}
    public function testDnsCannotBeStarted():void {$this->db();$i=$this->input();$i['result_status']='DNS';$this->expectException(InvalidArgumentException::class);sportsEventValidate($i);}
    public function testFinishedCannotBeMereAttendance():void {$this->db();$i=$this->input('attended');$i['result_status']='FINISHED';$this->expectException(InvalidArgumentException::class);sportsEventValidate($i);}
    public function testDnsRegistrationIsNotStart():void {$pdo=$this->db();$i=$this->input('registered');$i['result_status']='DNS';sportsEventRecord($pdo,1,10,1,$i,0,'local-dns-key-1');self::assertSame(0,sportsActivitySummary($pdo,10,'2026-01-01','2026-12-31')['race_count']);}
    public function testConfirmationAndEvidenceRequired():void {$this->db();$i=$this->input();$i['confirmed']=false;$this->expectException(InvalidArgumentException::class);sportsEventValidate($i);}
    public function testRevokedLeadCannotReadOrWriteWithStaleSession():void {$pdo=$this->db();$pdo->exec("DELETE FROM staff_user_positions WHERE trainer_id=1");$this->expectException(RuntimeException::class);$this->record($pdo);}
    public function testCoachCannotWritePersonOutsideAssignedScope():void {$pdo=$this->db();$_SESSION=['trener_id'=>2,'staff_positions'=>['coach'],'staff_active_position'=>'coach'];$this->expectException(RuntimeException::class);sportsEventRecord($pdo,1,10,2,$this->input(),0,'local-foreign-key');}
    public function testNonRaceCannotGetStart():void {$pdo=$this->db();$this->expectException(InvalidArgumentException::class);sportsEventRecord($pdo,2,10,1,$this->input(),0,'local-nonrace-key');}
    public function testCancelledCalendarCannotGetActualAttendance():void {$pdo=$this->db();$pdo->exec("UPDATE club_events SET planning_status='cancelled' WHERE id=1");$this->expectException(InvalidArgumentException::class);$this->record($pdo);}
    public function testPeriodIsExactAndNoDuplicateEventOnCorrection():void {$pdo=$this->db();$this->record($pdo);$this->record($pdo,'started',1,'local-correction-2');self::assertSame(1,sportsActivitySummary($pdo,10,'2026-01-10','2026-01-10')['race_count']);self::assertSame(0,sportsActivitySummary($pdo,10,'2026-01-11','2026-12-31')['race_count']);self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM sports_event_participation')->fetchColumn());}
    public function testTrainingAndRaceShareCanonicalPeriodAndFrozenCorrection():void {
        $pdo=$this->db();$m=require dirname(__DIR__,2).'/migrations/20261023020000_training_closure.php';$m['up']($pdo);
        $snapshot=['training_id'=>77,'actual_date'=>'2026-01-10','actual_started_at'=>'2026-01-10 10:00:00','actual_ended_at'=>'2026-01-10 11:15:00','duration_minutes'=>75,'participant_ids'=>[10],'coach_ids'=>[1],'plan_id'=>88];
        $insert=$pdo->prepare("INSERT INTO training_closure_revisions(training_id,version,status,actual_date,actual_started_at,actual_ended_at,duration_minutes,snapshot_json,reason,closed_by) VALUES(77,?,'closed','2026-01-10','2026-01-10 10:00:00','2026-01-10 11:15:00',75,?,'LOCALHOST actual paper attendance',1)");$insert->execute([1,json_encode($snapshot)]);$revision=(int)$pdo->lastInsertId();$pdo->exec("INSERT INTO training_closures VALUES(77,$revision,1,'closed')");
        $this->record($pdo);$summary=sportsActivitySummary($pdo,10,'2026-01-01','2026-12-31');self::assertSame([1,75,1],[$summary['training_count'],$summary['training_minutes'],$summary['race_count']]);self::assertCount(2,$summary['activities']);self::assertStringContainsString('training_closure_revisions',sportsActivityCsv($summary));
        $statement=sportsActivityStatement($pdo,10,'2026-01-01','2026-12-31',1,true);$original=$pdo->query('SELECT snapshot_json FROM sports_activity_statements WHERE id='.$statement)->fetchColumn();
        $snapshot['participant_ids']=[];$insert->execute([2,json_encode($snapshot)]);$revision=(int)$pdo->lastInsertId();$pdo->exec("UPDATE training_closures SET current_revision_id=$revision,version=2 WHERE training_id=77");
        self::assertSame(0,sportsActivitySummary($pdo,10,'2026-01-01','2026-12-31')['training_count']);self::assertSame($original,$pdo->query('SELECT snapshot_json FROM sports_activity_statements WHERE id='.$statement)->fetchColumn());self::assertSame(75,json_decode($original,true)['training_minutes']);
    }
    public function testMeasurementPreservesRawUnknownUnitsWithoutInventingZeros():void {
        $pdo=$this->db();$pdo->exec('CREATE TABLE treninky(id INTEGER PRIMARY KEY,datum TEXT)');$pdo->exec("INSERT INTO treninky VALUES(1,'2026-01-10')");
        $pdo->exec('CREATE TABLE trenink_mereni(trenink_id INT,mereni_id INT)');$pdo->exec('INSERT INTO trenink_mereni VALUES(1,1),(1,1)');
        $pdo->exec('CREATE TABLE mereni_zaznamy(id INT,sportovec_id INT,typ TEXT,vzdalenost TEXT,distance_unit TEXT,distance_meters REAL,cas TEXT,duration_ms INT,rpe TEXT,rpe_value REAL,contract_version TEXT)');$pdo->exec("INSERT INTO mereni_zaznamy VALUES(1,10,'kolo','2 okruhy',NULL,NULL,'rychle',NULL,'pohodově',NULL,NULL)");
        $summary=sportsActivitySummary($pdo,10,'2026-01-01','2026-12-31');self::assertSame(0,$summary['training_count']);self::assertCount(1,$summary['measurements']);self::assertNull($summary['measurements'][0]['distance_meters']);self::assertNull($summary['measurements'][0]['duration_ms']);self::assertStringContainsString('2 okruhy',sportsActivityMeasurementsCsv($summary));self::assertStringContainsString('rychle',sportsActivityMeasurementsCsv($summary));
    }
}
