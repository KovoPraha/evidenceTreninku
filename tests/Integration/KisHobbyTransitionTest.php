<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/kis_hobby_transition.php';

final class KisHobbyTransitionTest extends TestCase
{
    public function testPreviewIsReadOnlyAndExecutionKeepsIdentityAndCanCloseHobby(): void
    {
        [$pdo,$source,$target] = $this->database();
        $before = (int)$pdo->query('SELECT COUNT(*) FROM club_roster_members')->fetchColumn();
        $preview = kisHobbyTransitionPreview($pdo, $source, $target, '2027-01-15', true);
        self::assertSame(0, $preview['mutation_count']);
        self::assertSame($before, (int)$pdo->query('SELECT COUNT(*) FROM club_roster_members')->fetchColumn());
        $result = kisHobbyTransitionExecute($pdo, $source, $target, '2027-01-15', true, 7, 'Schválený přechod.', true, $preview['fingerprint']);
        self::assertFalse($result['idempotent']);
        self::assertSame(10, $result['sportovec_id']);
        self::assertSame('removed', $pdo->query("SELECT status FROM club_roster_members WHERE id=$source")->fetchColumn());
        self::assertSame('2027-01-15', $pdo->query("SELECT valid_to FROM club_roster_members WHERE id=$source")->fetchColumn());
        self::assertSame(10, (int)$pdo->query("SELECT sportovec_id FROM club_roster_members WHERE team_id=$target")->fetchColumn());
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM club_roster_rollover_runs')->fetchColumn());
        self::assertSame('hobby_to_race_end_hobby', $pdo->query('SELECT action FROM club_roster_rollover_run_items')->fetchColumn());
        self::assertSame(2, (int)$pdo->query('SELECT COUNT(*) FROM club_roster_events')->fetchColumn());
    }

    public function testKeepHobbyAndRepeatExactSubmissionAreIdempotent(): void
    {
        [$pdo,$source,$target] = $this->database();
        $preview = kisHobbyTransitionPreview($pdo, $source, $target, '2027-02-01', false);
        kisHobbyTransitionExecute($pdo, $source, $target, '2027-02-01', false, 7, 'Současné členství.', true, $preview['fingerprint']);
        $again = kisHobbyTransitionExecute($pdo, $source, $target, '2027-02-01', false, 7, 'Opakovaný požadavek.', true, $preview['fingerprint']);
        self::assertTrue($again['idempotent']);
        self::assertSame('active', $pdo->query("SELECT status FROM club_roster_members WHERE id=$source")->fetchColumn());
        self::assertSame(2, (int)$pdo->query('SELECT COUNT(*) FROM club_roster_members')->fetchColumn());
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM club_roster_rollover_runs')->fetchColumn());
        self::assertSame('hobby_to_race_keep_hobby', $pdo->query('SELECT action FROM club_roster_rollover_run_items')->fetchColumn());
    }

    public function testStalePreviewAndWrongAgeAreRejectedWithoutPartialWrite(): void
    {
        [$pdo,$source,$target] = $this->database();
        $preview = kisHobbyTransitionPreview($pdo, $source, $target, '2027-03-01', true);
        $pdo->exec("UPDATE club_roster_members SET valid_from='2026-10-01' WHERE id=$source");
        try {
            kisHobbyTransitionExecute($pdo, $source, $target, '2027-03-01', true, 7, 'Starý náhled.', true, $preview['fingerprint']);
            self::fail('Stale preview accepted.');
        } catch (KisRosterException $e) {
            self::assertStringContainsString('Náhled se mezitím změnil', $e->getMessage());
        }
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM club_roster_members')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM club_roster_rollover_runs')->fetchColumn());
        $pdo->exec("UPDATE sportovci SET narozeni='2018-01-01' WHERE id=10");
        $this->expectException(KisRosterException::class);
        $this->expectExceptionMessage('nepatří do vybrané věkové soupisky');
        kisHobbyTransitionPreview($pdo, $source, $target, '2027-03-01', false);
    }

    public function testInjectedAuditFailureRollsEverythingBack(): void
    {
        [$pdo,$source,$target] = $this->database();
        $preview = kisHobbyTransitionPreview($pdo, $source, $target, '2027-04-01', true);
        $pdo->exec("CREATE TRIGGER fail_transition_audit BEFORE INSERT ON club_roster_rollover_run_items BEGIN SELECT RAISE(ABORT,'simulated'); END");
        try {
            kisHobbyTransitionExecute($pdo, $source, $target, '2027-04-01', true, 7, 'Rollback audit.', true, $preview['fingerprint']);
            self::fail('Injected audit failure ignored.');
        } catch (KisRosterException $e) {
            self::assertStringContainsString('bez částečného zápisu', $e->getMessage());
        }
        self::assertSame('active', $pdo->query("SELECT status FROM club_roster_members WHERE id=$source")->fetchColumn());
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM club_roster_members')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM club_roster_events')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM club_roster_rollover_runs')->fetchColumn());
    }

    public function testAnotherAgeRosterInSameSeasonIsRejected(): void
    {
        [$pdo,$source,$target] = $this->database();
        $seasonId = (int)$pdo->query("SELECT season_id FROM club_teams WHERE id=$target")->fetchColumn();
        $series = kisRosterCreateSeries($pdo,7,'U15-B-X','U15 B','age','calendar_year','manual',null,15,15);
        $other = kisRosterCreateSeriesTeam($pdo,(int)$series['id'],$seasonId,7,'U15-B-TEAM-X','U15 B','Dráha','U15','Test.');
        kisRosterAddMember($pdo,(int)$other['id'],10,7,'manual','2027-01-01','Jiná věková soupiska.');
        $this->expectException(KisRosterException::class);
        $this->expectExceptionMessage('jiné věkové soupisce');
        kisHobbyTransitionPreview($pdo,$source,$target,'2027-02-01',false);
    }

    /** @return array{PDO,int,int} */
    private function database(): array
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT)');
        $pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,narozeni TEXT,uciid TEXT,stav_clenstvi TEXT)');
        $pdo->exec("INSERT INTO treneri VALUES(7,'Admin')");
        $pdo->exec("INSERT INTO sportovci VALUES(10,'Anna','Test','2012-05-01','UCI-10','aktivni')");
        foreach (['20260804090000_kis_teams_rosters.php','20260804110000_kis_roster_policies.php','20260804170000_kis_roster_rollover_execution.php'] as $file) {
            $migration = require dirname(__DIR__, 2) . '/migrations/' . $file;
            $migration['up']($pdo);
        }
        $school = kisRosterCreateSeason($pdo,7,'SCHOOL-X','Školní rok','2026-09-01','2027-08-31','school_year');
        $raceSeason = kisRosterCreateSeason($pdo,7,'RACE-2027-X','Závodní 2027','2027-01-01','2027-12-31','calendar_year');
        $hobbySeries = kisRosterCreateSeries($pdo,7,'HOBBY-X','Kroužek','hobby','school_year','renewal_required');
        $raceSeries = kisRosterCreateSeries($pdo,7,'U15-X','U15','age','calendar_year','manual',null,15,15);
        $hobbyTeam = kisRosterCreateSeriesTeam($pdo,(int)$hobbySeries['id'],(int)$school['id'],7,'HOBBY-TEAM-X','Kroužek','Obecná','Mix','Test.');
        $raceTeam = kisRosterCreateSeriesTeam($pdo,(int)$raceSeries['id'],(int)$raceSeason['id'],7,'U15-TEAM-X','U15 závodní','Silnice','U15','Test.');
        $source = kisRosterAddMember($pdo,(int)$hobbyTeam['id'],10,7,'manual','2026-09-01','Test.');
        $pdo->exec('DELETE FROM club_roster_events');
        return [$pdo,(int)$source['id'],(int)$raceTeam['id']];
    }
}
