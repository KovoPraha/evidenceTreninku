<?php
declare(strict_types=1);
namespace Tests\Integration;
use KisRosterException;use PDO;use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__,2).'/includes/kis_roster.php';

final class KisRosterTest extends TestCase
{
    public function testSeasonTeamAndRosterLifecycleIsIdempotentAndAudited():void
    {
        $pdo=$this->database();$season=\kisRosterCreateSeason($pdo,7,'2026-27','Sezóna 2026/27','2026-07-01','2027-06-30');$sameSeason=\kisRosterCreateSeason($pdo,7,'2026-27','Sezóna 2026/27','2026-07-01','2027-06-30');self::assertSame($season['id'],$sameSeason['id']);
        $team=\kisRosterCreateTeam($pdo,(int)$season['id'],7,'U15','Dráha U15','Dráha','U15','Založení týmu.');$sameTeam=\kisRosterCreateTeam($pdo,(int)$season['id'],7,'U15','Dráha U15','Dráha','U15','Opakování.');self::assertSame($team['id'],$sameTeam['id']);
        $added=\kisRosterAddMember($pdo,(int)$team['id'],10,7,'manual','2026-08-01','Zařazení.');$same=\kisRosterAddMember($pdo,(int)$team['id'],10,7,'manual','2026-08-01','Opakování.');self::assertTrue($added['created']);self::assertFalse($same['created']);self::assertSame('UCI-10',$pdo->query('SELECT kis_external_id_snapshot FROM club_roster_members')->fetchColumn());
        $removed=\kisRosterRemoveMember($pdo,(int)$added['id'],7,'2026-09-01','Přestup.');$removedAgain=\kisRosterRemoveMember($pdo,(int)$added['id'],7,'2026-09-01','Opakování.');self::assertTrue($removed['changed']);self::assertFalse($removedAgain['changed']);
        $reactivated=\kisRosterAddMember($pdo,(int)$team['id'],10,7,'kis_shadow','2026-10-01','Návrat podle ověřeného shadow importu.');self::assertFalse($reactivated['created']);self::assertSame('active',$pdo->query('SELECT status FROM club_roster_members')->fetchColumn());self::assertSame(4,(int)$pdo->query('SELECT COUNT(*) FROM club_roster_events')->fetchColumn());
        $detail=\kisRosterTeamDetail($pdo,(int)$team['id']);self::assertSame('Dráha U15',$detail['team']['name']);self::assertCount(1,$detail['members']);
    }

    public function testArchivedPersonCannotBeAdded():void
    {
        $pdo=$this->database();$season=\kisRosterCreateSeason($pdo,7,'2026','2026','2026-01-01','2026-12-31');$team=\kisRosterCreateTeam($pdo,(int)$season['id'],7,'ELITE','Elite','Silnice','Elite','Test.');
        $this->expectException(KisRosterException::class);$this->expectExceptionMessage('Archivovaného');\kisRosterAddMember($pdo,(int)$team['id'],11,7,'manual','2026-01-01','Test.');
    }

    public function testBothSeasonCalendarsAndAllPoliciesAreRepresented():void
    {
        $pdo=$this->database();
        $school=\kisRosterCreateSeason($pdo,7,'SCHOOL-26','Skolni 2026/27','2026-09-01','2027-08-31','school_year');
        $calendar=\kisRosterCreateSeason($pdo,7,'CAL-2027','Rok 2027','2027-01-01','2027-12-31','calendar_year');
        self::assertSame('school_year',$school['season_type']);self::assertSame('calendar_year',$calendar['season_type']);
        $u15=\kisRosterCreateSeries($pdo,7,'SER-U15','U15','age','calendar_year','manual',null,13,14);
        $u13=\kisRosterCreateSeries($pdo,7,'SER-U13','U13','age','calendar_year','age_progression',(int)$u15['id'],11,12);
        $hobby=\kisRosterCreateSeries($pdo,7,'SER-HOBBY','Krouzek','hobby','school_year','renewal_required');
        $track=\kisRosterCreateSeries($pdo,7,'SER-TRACK','Draha','discipline','calendar_year','carry_forward');
        $special=\kisRosterCreateSeries($pdo,7,'SER-SPECIAL','Vyber','special','calendar_year','manual');
        self::assertSame(['age_progression','renewal_required','carry_forward','manual'],[$u13['rollover_policy'],$hobby['rollover_policy'],$track['rollover_policy'],$special['rollover_policy']]);
    }

    public function testInvalidPolicyCombinationAndCalendarMismatchAreRejected():void
    {
        $pdo=$this->database();
        try{\kisRosterCreateSeries($pdo,7,'BAD-HOBBY','Bad','hobby','calendar_year','renewal_required');self::fail('Invalid series combination accepted.');}catch(\InvalidArgumentException){self::assertTrue(true);}
        $school=\kisRosterCreateSeason($pdo,7,'SCHOOL-27','Skolni','2027-09-01','2028-08-31','school_year');
        $age=\kisRosterCreateSeries($pdo,7,'AGE-15','U15','age','calendar_year','manual');
        $this->expectException(\InvalidArgumentException::class);\kisRosterCreateSeriesTeam($pdo,(int)$age['id'],(int)$school['id'],7,'BAD-TEAM','Bad team','Draha','U15','Mismatch.');
    }

    public function testLegacyTeamAndManyToManyMembershipRemainCompatible():void
    {
        $pdo=$this->database();$season=\kisRosterCreateSeason($pdo,7,'CAL-26','2026','2026-01-01','2026-12-31');
        $legacy=\kisRosterCreateTeam($pdo,(int)$season['id'],7,'LEGACY','Legacy','Draha','Open','Legacy.');
        $series=\kisRosterCreateSeries($pdo,7,'TRACK','Draha','discipline','calendar_year','carry_forward');$track=\kisRosterCreateSeriesTeam($pdo,(int)$series['id'],(int)$season['id'],7,'TRACK-26','Draha 2026','Draha','Open','Serie.');
        \kisRosterAddMember($pdo,(int)$legacy['id'],10,7,'manual','2026-01-01','Prvni.');\kisRosterAddMember($pdo,(int)$track['id'],10,7,'manual','2026-01-01','Druha.');
        self::assertNull($legacy['series_id']);self::assertSame(2,(int)$pdo->query("SELECT COUNT(*) FROM club_roster_members WHERE sportovec_id=10 AND status='active'")->fetchColumn());
    }

    public function testRolloverPreviewDoesNotMutateRoster():void
    {
        $pdo=$this->database();$sourceSeason=\kisRosterCreateSeason($pdo,7,'CAL-26','2026','2026-01-01','2026-12-31','calendar_year');$targetSeason=\kisRosterCreateSeason($pdo,7,'CAL-27','2027','2027-01-01','2027-12-31','calendar_year');
        $series=\kisRosterCreateSeries($pdo,7,'ROAD','Silnice','discipline','calendar_year','carry_forward');$source=\kisRosterCreateSeriesTeam($pdo,(int)$series['id'],(int)$sourceSeason['id'],7,'ROAD-26','Silnice 2026','Silnice','Open','Zdroj.');$target=\kisRosterCreateSeriesTeam($pdo,(int)$series['id'],(int)$targetSeason['id'],7,'ROAD-27','Silnice 2027','Silnice','Open','Cil.');\kisRosterAddMember($pdo,(int)$source['id'],10,7,'manual','2026-01-01','Clen.');
        $before=(int)$pdo->query('SELECT COUNT(*) FROM club_roster_members')->fetchColumn();$events=(int)$pdo->query('SELECT COUNT(*) FROM club_roster_events')->fetchColumn();$preview=\kisRosterPreviewRollover($pdo,(int)$source['id'],(int)$targetSeason['id']);
        self::assertSame('carry_forward',$preview['policy']);self::assertSame((int)$target['id'],$preview['proposals'][0]['target_team_id']);self::assertSame(0,$preview['mutation_count']);self::assertSame($before,(int)$pdo->query('SELECT COUNT(*) FROM club_roster_members')->fetchColumn());self::assertSame($events,(int)$pdo->query('SELECT COUNT(*) FROM club_roster_events')->fetchColumn());
    }

    public function testMigrationBackfillsLegacySeasonsAndKeepsLegacyTeamNullable():void
    {
        $pdo=new PDO('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);$pdo->exec('PRAGMA foreign_keys=ON');$pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT)');$pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,narozeni TEXT,uciid TEXT,stav_clenstvi TEXT)');$pdo->exec("INSERT INTO treneri VALUES(7,'Admin')");
        $base=require dirname(__DIR__,2).'/migrations/20260804090000_kis_teams_rosters.php';$base['up']($pdo);$pdo->exec("INSERT INTO club_seasons(code,name,starts_on,ends_on,status,created_by_trainer_id) VALUES('LEG-CAL','Legacy calendar','2026-01-01','2026-12-31','active',7),('LEG-SCHOOL','Legacy school','2026-09-01','2027-08-31','active',7)");$pdo->exec("INSERT INTO club_teams(season_id,code,name,discipline,age_label,status,created_by_trainer_id) VALUES(1,'LEGACY','Legacy','Draha','Open','active',7)");
        $policy=require dirname(__DIR__,2).'/migrations/20260804110000_kis_roster_policies.php';$policy['up']($pdo);$types=$pdo->query('SELECT season_type FROM club_seasons ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);self::assertSame(['calendar_year','school_year'],$types);self::assertNull($pdo->query('SELECT series_id FROM club_teams WHERE id=1')->fetchColumn());
    }

    private function database():PDO
    {
        $pdo=new PDO('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);$pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT)');$pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,narozeni TEXT,uciid TEXT,stav_clenstvi TEXT)');$pdo->exec("INSERT INTO treneri VALUES(7,'Admin')");$pdo->exec("INSERT INTO sportovci VALUES(10,'Anna','Test','2012-01-01','UCI-10','aktivni'),(11,'Archiv','Test','2000-01-01',NULL,'archiv')");
        $migration=require dirname(__DIR__,2).'/migrations/20260804090000_kis_teams_rosters.php';$migration['up']($pdo);$migration['up']($pdo);self::assertTrue($migration['verify']($pdo));$policy=require dirname(__DIR__,2).'/migrations/20260804110000_kis_roster_policies.php';$policy['up']($pdo);$policy['up']($pdo);self::assertTrue($policy['verify']($pdo));return$pdo;
    }
}
