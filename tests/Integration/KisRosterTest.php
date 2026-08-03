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

    private function database():PDO
    {
        $pdo=new PDO('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);$pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT)');$pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,narozeni TEXT,uciid TEXT,stav_clenstvi TEXT)');$pdo->exec("INSERT INTO treneri VALUES(7,'Admin')");$pdo->exec("INSERT INTO sportovci VALUES(10,'Anna','Test','2012-01-01','UCI-10','aktivni'),(11,'Archiv','Test','2000-01-01',NULL,'archiv')");
        $migration=require dirname(__DIR__,2).'/migrations/20260804090000_kis_teams_rosters.php';$migration['up']($pdo);$migration['up']($pdo);self::assertTrue($migration['verify']($pdo));return$pdo;
    }
}
