<?php
declare(strict_types=1);

namespace Tests\Integration;

use KisRosterException;
use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/includes/kis_roster.php';

final class KisRosterRolloverExecutionTest extends TestCase
{
    public function testAgeProgressionExecutesOnceAndKeepsSourceHistory():void
    {
        $pdo=$this->database();
        [$sourceSeason,$targetSeason]=$this->calendarSeasons($pdo);
        $u17=\kisRosterCreateSeries($pdo,7,'U17-X','U17','age','calendar_year','manual',null,15,16);
        $u15=\kisRosterCreateSeries($pdo,7,'U15-X','U15','age','calendar_year','age_progression',(int)$u17['id'],13,14);
        $source=\kisRosterCreateSeriesTeam($pdo,(int)$u15['id'],(int)$sourceSeason['id'],7,'U15-26','U15 2026','Silnice','U15','Test.');
        $target=\kisRosterCreateSeriesTeam($pdo,(int)$u17['id'],(int)$targetSeason['id'],7,'U17-27','U17 2027','Silnice','U17','Test.');
        $member=\kisRosterAddMember($pdo,(int)$source['id'],10,7,'manual','2026-01-01','Test.');
        $eventsBefore=(int)$pdo->query('SELECT COUNT(*) FROM club_roster_events')->fetchColumn();
        $preview=\kisRosterPreviewRollover($pdo,(int)$source['id'],(int)$targetSeason['id']);
        self::assertSame(0,$preview['mutation_count']);self::assertSame('age_progression',$preview['proposals'][0]['action']);self::assertSame(64,strlen($preview['fingerprint']));
        self::assertSame('active',$pdo->query('SELECT status FROM club_roster_members WHERE id='.(int)$member['id'])->fetchColumn());
        $result=\kisRosterExecuteRollover($pdo,(int)$source['id'],(int)$targetSeason['id'],7,'Rocni postup.',true,$preview['fingerprint']);
        self::assertSame(1,$result['moved_count']);self::assertFalse($result['idempotent']);
        $sourceRow=$pdo->query('SELECT status,valid_to FROM club_roster_members WHERE id='.(int)$member['id'])->fetch(PDO::FETCH_ASSOC);self::assertSame(['status'=>'removed','valid_to'=>'2026-12-31'],$sourceRow);
        $targetRow=$pdo->query('SELECT sportovec_id,status,valid_from,valid_to FROM club_roster_members WHERE team_id='.(int)$target['id'])->fetch(PDO::FETCH_ASSOC);self::assertSame(10,(int)$targetRow['sportovec_id']);self::assertSame('active',$targetRow['status']);self::assertSame('2027-01-01',$targetRow['valid_from']);self::assertNull($targetRow['valid_to']);
        $again=\kisRosterExecuteRollover($pdo,(int)$source['id'],(int)$targetSeason['id'],7,'Opakovani.',true,$preview['fingerprint']);self::assertTrue($again['idempotent']);self::assertSame($result['run_id'],$again['run_id']);self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM club_roster_rollover_runs')->fetchColumn());self::assertSame($eventsBefore+2,(int)$pdo->query('SELECT COUNT(*) FROM club_roster_events')->fetchColumn());
    }

    public function testCarryForwardMovesButManualAndRenewalDoNotMutate():void
    {
        $pdo=$this->database();[$sourceSeason,$targetSeason]=$this->calendarSeasons($pdo);
        $carry=\kisRosterCreateSeries($pdo,7,'TRACK-X','Draha','discipline','calendar_year','carry_forward');$carrySource=\kisRosterCreateSeriesTeam($pdo,(int)$carry['id'],(int)$sourceSeason['id'],7,'TR-26','Draha 2026','Draha','Open','Test.');$carryTarget=\kisRosterCreateSeriesTeam($pdo,(int)$carry['id'],(int)$targetSeason['id'],7,'TR-27','Draha 2027','Draha','Open','Test.');\kisRosterAddMember($pdo,(int)$carrySource['id'],10,7,'manual','2026-01-01','Test.');$preview=\kisRosterPreviewRollover($pdo,(int)$carrySource['id'],(int)$targetSeason['id']);$result=\kisRosterExecuteRollover($pdo,(int)$carrySource['id'],(int)$targetSeason['id'],7,'Prenos discipliny.',true,$preview['fingerprint']);self::assertSame(1,$result['moved_count']);self::assertSame(10,(int)$pdo->query('SELECT sportovec_id FROM club_roster_members WHERE team_id='.(int)$carryTarget['id'])->fetchColumn());
        $manual=\kisRosterCreateSeries($pdo,7,'SPEC-X','Vyber','special','calendar_year','manual');$manualSource=\kisRosterCreateSeriesTeam($pdo,(int)$manual['id'],(int)$sourceSeason['id'],7,'SP-26','Vyber 2026','Mix','Open','Test.');\kisRosterCreateSeriesTeam($pdo,(int)$manual['id'],(int)$targetSeason['id'],7,'SP-27','Vyber 2027','Mix','Open','Test.');$manualMember=\kisRosterAddMember($pdo,(int)$manualSource['id'],11,7,'manual','2026-01-01','Test.');$manualPreview=\kisRosterPreviewRollover($pdo,(int)$manualSource['id'],(int)$targetSeason['id']);$manualResult=\kisRosterExecuteRollover($pdo,(int)$manualSource['id'],(int)$targetSeason['id'],7,'Manual zustava.',true,$manualPreview['fingerprint']);self::assertSame(0,$manualResult['moved_count']);self::assertSame(1,$manualResult['skipped_count']);self::assertSame('active',$pdo->query('SELECT status FROM club_roster_members WHERE id='.(int)$manualMember['id'])->fetchColumn());
        $schoolA=\kisRosterCreateSeason($pdo,7,'SCH-26-X','Skolni 2026','2026-09-01','2027-08-31','school_year');$schoolB=\kisRosterCreateSeason($pdo,7,'SCH-27-X','Skolni 2027','2027-09-01','2028-08-31','school_year');$hobby=\kisRosterCreateSeries($pdo,7,'HOB-X','Krouzek','hobby','school_year','renewal_required');$hobbySource=\kisRosterCreateSeriesTeam($pdo,(int)$hobby['id'],(int)$schoolA['id'],7,'H-26','Krouzek 2026','Mix','Open','Test.');\kisRosterCreateSeriesTeam($pdo,(int)$hobby['id'],(int)$schoolB['id'],7,'H-27','Krouzek 2027','Mix','Open','Test.');$hobbyMember=\kisRosterAddMember($pdo,(int)$hobbySource['id'],12,7,'manual','2026-09-01','Test.');$renewPreview=\kisRosterPreviewRollover($pdo,(int)$hobbySource['id'],(int)$schoolB['id']);$renewResult=\kisRosterExecuteRollover($pdo,(int)$hobbySource['id'],(int)$schoolB['id'],7,'Ceka na prodlouzeni.',true,$renewPreview['fingerprint']);self::assertSame(0,$renewResult['moved_count']);self::assertSame('active',$pdo->query('SELECT status FROM club_roster_members WHERE id='.(int)$hobbyMember['id'])->fetchColumn());
    }

    public function testAuditedOverrideTransitionsHobbyPersonIntoRaceTeamAndSkipWins():void
    {
        $pdo=$this->database();[, $raceSeason]=$this->calendarSeasons($pdo);$school=\kisRosterCreateSeason($pdo,7,'SCH-X','Skolni','2026-09-01','2027-08-31','school_year');$hobbySeries=\kisRosterCreateSeries($pdo,7,'HOB-TR','Krouzek','hobby','school_year','renewal_required');$hobby=\kisRosterCreateSeriesTeam($pdo,(int)$hobbySeries['id'],(int)$school['id'],7,'HOBBY-X','Krouzek','Mix','Open','Test.');$raceSeries=\kisRosterCreateSeries($pdo,7,'RACE-X','U17 zavodni','age','calendar_year','manual',null,15,16);$race=\kisRosterCreateSeriesTeam($pdo,(int)$raceSeries['id'],(int)$raceSeason['id'],7,'RACE-27','U17 zavodni','Silnice','U17','Test.');$source=\kisRosterAddMember($pdo,(int)$hobby['id'],10,7,'manual','2026-09-01','Test.');
        $exception=\kisRosterSetRolloverException($pdo,(int)$hobby['id'],(int)$raceSeason['id'],10,'target_override',(int)$race['id'],7,'Schvaleny prechod do zavodniho tymu.',true);self::assertTrue($exception['created']);self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM club_roster_rollover_exception_events')->fetchColumn());$preview=\kisRosterPreviewRollover($pdo,(int)$hobby['id'],(int)$raceSeason['id']);self::assertSame('target_override',$preview['proposals'][0]['action']);$result=\kisRosterExecuteRollover($pdo,(int)$hobby['id'],(int)$raceSeason['id'],7,'Prechod krouzek-zavodni.',true,$preview['fingerprint']);self::assertSame(1,$result['moved_count']);self::assertSame(10,(int)$pdo->query('SELECT sportovec_id FROM club_roster_members WHERE team_id='.(int)$race['id'])->fetchColumn());self::assertSame('removed',$pdo->query('SELECT status FROM club_roster_members WHERE id='.(int)$source['id'])->fetchColumn());self::assertSame(3,(int)$pdo->query('SELECT COUNT(*) FROM sportovci')->fetchColumn());
        $pdo2=$this->database();[$a,$b]=$this->calendarSeasons($pdo2);$series=\kisRosterCreateSeries($pdo2,7,'SKIP-X','Draha','discipline','calendar_year','carry_forward');$st=\kisRosterCreateSeriesTeam($pdo2,(int)$series['id'],(int)$a['id'],7,'SK-26','Zdroj','Draha','Open','Test.');\kisRosterCreateSeriesTeam($pdo2,(int)$series['id'],(int)$b['id'],7,'SK-27','Cil','Draha','Open','Test.');$member=\kisRosterAddMember($pdo2,(int)$st['id'],10,7,'manual','2026-01-01','Test.');\kisRosterSetRolloverException($pdo2,(int)$st['id'],(int)$b['id'],10,'skip',null,7,'Individualni odklad.',true);$p=\kisRosterPreviewRollover($pdo2,(int)$st['id'],(int)$b['id']);self::assertSame('skip_exception',$p['proposals'][0]['action']);$r=\kisRosterExecuteRollover($pdo2,(int)$st['id'],(int)$b['id'],7,'Respektovat vyjimku.',true,$p['fingerprint']);self::assertSame(0,$r['moved_count']);self::assertSame('active',$pdo2->query('SELECT status FROM club_roster_members WHERE id='.(int)$member['id'])->fetchColumn());
    }

    public function testStaleFingerprintAndInjectedFailureRollbackEverything():void
    {
        $pdo=$this->database();[$a,$b]=$this->calendarSeasons($pdo);$series=\kisRosterCreateSeries($pdo,7,'ROLL-X','Draha','discipline','calendar_year','carry_forward');$source=\kisRosterCreateSeriesTeam($pdo,(int)$series['id'],(int)$a['id'],7,'R-26','Zdroj','Draha','Open','Test.');$target=\kisRosterCreateSeriesTeam($pdo,(int)$series['id'],(int)$b['id'],7,'R-27','Cil','Draha','Open','Test.');$member=\kisRosterAddMember($pdo,(int)$source['id'],10,7,'manual','2026-01-01','Test.');$preview=\kisRosterPreviewRollover($pdo,(int)$source['id'],(int)$b['id']);\kisRosterSetRolloverException($pdo,(int)$source['id'],(int)$b['id'],10,'skip',null,7,'Zmena po nahledu.',true);try{\kisRosterExecuteRollover($pdo,(int)$source['id'],(int)$b['id'],7,'Stary nahled.',true,$preview['fingerprint']);self::fail('Stale preview accepted.');}catch(KisRosterException$e){self::assertStringContainsString('zmenil',$e->getMessage());}
        \kisRosterSetRolloverException($pdo,(int)$source['id'],(int)$b['id'],10,'target_override',(int)$target['id'],7,'Navrat k proveditelnemu cili.',true);$fresh=\kisRosterPreviewRollover($pdo,(int)$source['id'],(int)$b['id']);$pdo->exec("CREATE TRIGGER fail_rollover_item BEFORE INSERT ON club_roster_rollover_run_items BEGIN SELECT RAISE(ABORT,'simulated'); END");try{\kisRosterExecuteRollover($pdo,(int)$source['id'],(int)$b['id'],7,'Rollback.',true,$fresh['fingerprint']);self::fail('Injected failure ignored.');}catch(KisRosterException$e){self::assertStringContainsString('bez castecneho',$e->getMessage());}self::assertSame('active',$pdo->query('SELECT status FROM club_roster_members WHERE id='.(int)$member['id'])->fetchColumn());self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM club_roster_rollover_runs')->fetchColumn());self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM club_roster_members')->fetchColumn());
    }

    /** @return array{0:array<string,mixed>,1:array<string,mixed>} */
    private function calendarSeasons(PDO$pdo):array{return[\kisRosterCreateSeason($pdo,7,'CAL-26-X','2026','2026-01-01','2026-12-31','calendar_year'),\kisRosterCreateSeason($pdo,7,'CAL-27-X','2027','2027-01-01','2027-12-31','calendar_year')];}
    private function database():PDO
    {
        $pdo=new PDO('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);$pdo->exec('PRAGMA foreign_keys=ON');$pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT)');$pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,narozeni TEXT,uciid TEXT,stav_clenstvi TEXT)');$pdo->exec("INSERT INTO treneri VALUES(7,'Admin')");$pdo->exec("INSERT INTO sportovci VALUES(10,'Anna','A','2012-01-01','U10','aktivni'),(11,'Bela','B','2013-01-01','U11','aktivni'),(12,'Cyril','C','2014-01-01','U12','aktivni')");$base=require dirname(__DIR__,2).'/migrations/20260804090000_kis_teams_rosters.php';$base['up']($pdo);$policy=require dirname(__DIR__,2).'/migrations/20260804110000_kis_roster_policies.php';$policy['up']($pdo);$migration=require dirname(__DIR__,2).'/migrations/20260804170000_kis_roster_rollover_execution.php';$migration['up']($pdo);$migration['up']($pdo);self::assertTrue($migration['verify']($pdo));return$pdo;
    }
}
