<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/kis_a06_acceptance.php';

final class KisA06AcceptanceTest extends TestCase
{
    public function testCombinedPreviewExecutesAgeDisciplineAndSkipExactlyOnce(): void
    {
        $pdo = $this->database();
        $scenario = \kisA06Scenario($pdo);
        self::assertFalse($scenario['complete']);
        self::assertSame(['LOCAL-U15-2026','LOCAL-DRAHA-2026','LOCAL-U13-2026'], array_keys($scenario['pending_fingerprints']));

        $result = \kisA06Execute($pdo, 7, 'Integrační test roční obnovy.', true, $scenario['batch_fingerprint'], $scenario['pending_fingerprints']);
        self::assertSame(3, $result['moved_count']);
        self::assertSame(1, $result['skipped_count']);
        self::assertSame(3, (int)$pdo->query('SELECT COUNT(*) FROM club_roster_rollover_runs')->fetchColumn());
        self::assertSame(1, (int)$pdo->query("SELECT COUNT(*) FROM club_roster_rollover_exceptions WHERE exception_action='skip'")->fetchColumn());
        self::assertSame(1, (int)$pdo->query("SELECT COUNT(*) FROM club_roster_members m JOIN club_teams t ON t.id=m.team_id WHERE t.code='LOCAL-U17-2027' AND m.sportovec_id=10 AND m.status='active'")->fetchColumn());
        self::assertSame(1, (int)$pdo->query("SELECT COUNT(*) FROM club_roster_members m JOIN club_teams t ON t.id=m.team_id WHERE t.code='LOCAL-DRAHA-2027' AND m.sportovec_id=10 AND m.status='active'")->fetchColumn());
        self::assertSame(1, (int)$pdo->query("SELECT COUNT(*) FROM club_roster_members m JOIN club_teams t ON t.id=m.team_id WHERE t.code='LOCAL-U13-2026' AND m.sportovec_id=11 AND m.status='active'")->fetchColumn());

        $complete = \kisA06Scenario($pdo);
        self::assertTrue($complete['complete']);
        $again = \kisA06Execute($pdo, 7, 'Opakovaný test.', true, $scenario['batch_fingerprint'], $scenario['pending_fingerprints']);
        self::assertSame(0, $again['moved_count']);
        self::assertSame(3, (int)$pdo->query('SELECT COUNT(*) FROM club_roster_rollover_runs')->fetchColumn());
    }

    public function testBatchFingerprintFailsClosed(): void
    {
        $pdo = $this->database();
        $scenario = \kisA06Scenario($pdo);
        $this->expectException(\KisA06Exception::class);
        \kisA06Execute($pdo, 7, 'Stale test.', true, str_repeat('0', 64), $scenario['pending_fingerprints']);
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT)');
        $pdo->exec("INSERT INTO treneri VALUES(7,'Admin')");
        $pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,narozeni TEXT,uciid TEXT,stav_clenstvi TEXT)');
        $pdo->exec("INSERT INTO sportovci VALUES(10,'Anna','A','2012-01-01','U10','aktivni'),(11,'Bela','B','2013-01-01','U11','aktivni'),(12,'Cyril','C','2014-01-01','U12','aktivni')");
        foreach (['20260804090000_kis_teams_rosters.php','20260804110000_kis_roster_policies.php','20260804170000_kis_roster_rollover_execution.php'] as $file) {
            $migration = require dirname(__DIR__,2) . '/migrations/' . $file;
            $migration['up']($pdo);
        }
        $source = \kisRosterCreateSeason($pdo,7,'RACE-2026','2026','2026-01-01','2026-12-31','calendar_year');
        $target = \kisRosterCreateSeason($pdo,7,'RACE-2027','2027','2027-01-01','2027-12-31','calendar_year');
        $u17 = \kisRosterCreateSeries($pdo,7,'LOCAL-U17','U17','age','calendar_year','manual',null,15,16);
        $u15 = \kisRosterCreateSeries($pdo,7,'LOCAL-U15','U15','age','calendar_year','age_progression',(int)$u17['id'],13,14);
        $u13 = \kisRosterCreateSeries($pdo,7,'LOCAL-U13','U13','age','calendar_year','age_progression',(int)$u15['id'],11,12);
        $track = \kisRosterCreateSeries($pdo,7,'LOCAL-DRAHA','Dráha','discipline','calendar_year','carry_forward');
        $u15Source = \kisRosterCreateSeriesTeam($pdo,(int)$u15['id'],(int)$source['id'],7,'LOCAL-U15-2026','U15 2026','Silnice','U15','Test.');
        $u13Source = \kisRosterCreateSeriesTeam($pdo,(int)$u13['id'],(int)$source['id'],7,'LOCAL-U13-2026','U13 2026','Silnice','U13','Test.');
        $trackSource = \kisRosterCreateSeriesTeam($pdo,(int)$track['id'],(int)$source['id'],7,'LOCAL-DRAHA-2026','Dráha 2026','Dráha','Open','Test.');
        \kisRosterCreateSeriesTeam($pdo,(int)$u17['id'],(int)$target['id'],7,'LOCAL-U17-2027','U17 2027','Silnice','U17','Test.');
        \kisRosterCreateSeriesTeam($pdo,(int)$u15['id'],(int)$target['id'],7,'LOCAL-U15-2027','U15 2027','Silnice','U15','Test.');
        \kisRosterCreateSeriesTeam($pdo,(int)$track['id'],(int)$target['id'],7,'LOCAL-DRAHA-2027','Dráha 2027','Dráha','Open','Test.');
        \kisRosterAddMember($pdo,(int)$u15Source['id'],10,7,'manual','2026-01-01','Test.');
        \kisRosterAddMember($pdo,(int)$trackSource['id'],10,7,'manual','2026-01-01','Test.');
        \kisRosterAddMember($pdo,(int)$u13Source['id'],11,7,'manual','2026-01-01','Test.');
        \kisRosterAddMember($pdo,(int)$u13Source['id'],12,7,'manual','2026-01-01','Test.');
        \kisRosterSetRolloverException($pdo,(int)$u13Source['id'],(int)$target['id'],11,'skip',null,7,'Individuální odklad.',true);
        return $pdo;
    }
}
