<?php
declare(strict_types=1);
namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/includes/roster_hierarchy.php';
require_once dirname(__DIR__,2).'/includes/sportovec_editor_details.php';
require_once dirname(__DIR__,2).'/includes/person_sensitive.php';

final class RosterHierarchyTemporalTest extends TestCase
{
    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT)');
        $pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,narozeni TEXT,uciid TEXT,stav_clenstvi TEXT)');
        $pdo->exec("INSERT INTO treneri VALUES(7,'Admin'); INSERT INTO sportovci VALUES(10,'Anna','První','2012-01-01',NULL,'aktivni'),(11,'Borek','Druhý','2013-01-01',NULL,'aktivni')");
        foreach (['20260804090000_kis_teams_rosters','20260804110000_kis_roster_policies','20260822170000_club_roster_structure_events'] as $name) {
            $migration = require dirname(__DIR__,2).'/migrations/'.$name.'.php';
            $migration['up']($pdo);
        }
        $pdo->exec("INSERT INTO club_seasons(code,name,starts_on,ends_on,status,created_by_trainer_id) VALUES('SCHOOL-26','Školní rok 2026/27','2026-09-01','2027-08-31','active',7)");
        $pdo->exec("INSERT INTO club_teams(season_id,code,name,discipline,age_label,status,created_by_trainer_id) VALUES(1,'ALIGATORI','Aligátoři','vše','děti','active',7),(1,'KADETI','Kadeti','vše','děti','active',7),(1,'DRAHA','Dráha','vše','děti','active',7)");
        $pdo->exec("INSERT INTO club_roster_members(team_id,sportovec_id,status,source,valid_from,valid_to,created_by_trainer_id) VALUES(1,10,'removed','manual','2026-09-01','2027-01-31',7),(2,11,'active','manual','2026-09-01',NULL,7),(3,11,'active','manual','2026-09-01',NULL,7)");
        $migration = require dirname(__DIR__,2).'/migrations/20261021090000_roster_hierarchy_temporal.php';
        $migration['up']($pdo);
        $migration['up']($pdo);
        self::assertTrue($migration['verify']($pdo));
        return $pdo;
    }

    public function testDatedMembershipAndHierarchyPreserveHistoryAndDeduplicatePaths(): void
    {
        $pdo = $this->database();
        $hobby = \kisRosterCreateStandingTeam($pdo,7,'KROUZEK-TRE','Kroužek Třebešín','group','Nová skupina.');
        $racing = \kisRosterCreateStandingTeam($pdo,7,'ZAVODNI','Závodní tým','group','Nová skupina.');
        self::assertNull($pdo->query('SELECT season_id FROM club_teams WHERE id='.(int)$hobby['id'])->fetchColumn());
        $p = \kisRosterPreviewHierarchy($pdo,(int)$hobby['id'],[1],'2026-10-01');
        self::assertSame(1,$p['after_count']);
        self::assertSame(1,\kisRosterAttachChildren($pdo,(int)$hobby['id'],[1],'2026-10-01',7,'Patří do Třebešína.',$p['fingerprint'])['created_count']);
        $p = \kisRosterPreviewHierarchy($pdo,(int)$racing['id'],[2,3],'2026-10-01');
        self::assertSame(1,$p['after_count']);
        self::assertSame(1,$p['overlap_count']);
        \kisRosterAttachChildren($pdo,(int)$racing['id'],[2,3],'2026-10-01',7,'Dvě větve závodního týmu.',$p['fingerprint']);
        $selectable = \kisRosterSelectableTeams($pdo,'2026-11-01');
        self::assertContains(['id'=>(int)$racing['id'],'name'=>'Závodní tým','group_id'=>(int)$racing['id'],'group_name'=>'Závodní tým','is_group'=>true],$selectable);
        self::assertContains(['id'=>2,'name'=>'Kadeti','group_id'=>(int)$racing['id'],'group_name'=>'Závodní tým','is_group'=>false],$selectable);
        self::assertSame([11],\kisRosterEffectiveMemberIds($pdo,(int)$racing['id'],'2026-11-01'));
        self::assertSame([(int)$racing['id']],\kisRosterPersonTargetIds($pdo,11,[(int)$racing['id']],'2026-11-01'));
        self::assertSame([10],\kisRosterEffectiveMemberIds($pdo,(int)$hobby['id'],'2026-11-01'));
        self::assertSame([],\kisRosterEffectiveMemberIds($pdo,(int)$hobby['id'],'2027-03-01'));
        $new = \kisRosterGrantPeriod($pdo,1,10,7,'shop','2027-02-01','2027-06-30','Druhé pololetí.','order-item-2');
        self::assertTrue($new['created']);
        self::assertSame(2,(int)$pdo->query('SELECT COUNT(*) FROM club_roster_members WHERE team_id=1 AND sportovec_id=10')->fetchColumn());
        self::assertSame([10],\kisRosterEffectiveMemberIds($pdo,(int)$hobby['id'],'2027-03-01'));
        self::assertFalse(\kisRosterGrantPeriod($pdo,1,10,7,'shop','2027-02-01','2027-06-30','Zopakováno.','order-item-2')['created']);
        \kisRosterEndPeriod($pdo,(int)$new['id'],7,'2027-03-31','Ukončení.');
        self::assertSame([],\kisRosterEffectiveMemberIds($pdo,(int)$hobby['id'],'2027-04-01'));
        self::assertSame([10],\kisRosterEffectiveMemberIds($pdo,(int)$hobby['id'],'2027-03-15'));
        $returned = \kisRosterAddMember($pdo,1,10,7,'manual','2027-09-01','Návrat v dalším roce.');
        self::assertTrue($returned['created']);
        self::assertSame(3,(int)$pdo->query('SELECT COUNT(*) FROM club_roster_members WHERE team_id=1 AND sportovec_id=10')->fetchColumn());
        self::assertSame([10],\kisRosterEffectiveMemberIds($pdo,(int)$hobby['id'],'2027-11-01'));
    }

    public function testEdgeEndPreservesEarlierCompositionAndCycleIsRejected(): void
    {
        $pdo = $this->database();
        $a = \kisRosterCreateStandingTeam($pdo,7,'GROUP-A','Skupina A','group','Založení.');
        $b = \kisRosterCreateStandingTeam($pdo,7,'GROUP-B','Skupina B','group','Založení.');
        $preview = \kisRosterPreviewHierarchy($pdo,(int)$a['id'],[(int)$b['id']],'2026-10-01');
        \kisRosterAttachChildren($pdo,(int)$a['id'],[(int)$b['id']],'2026-10-01',7,'Vazba.',$preview['fingerprint']);
        $this->expectException(\KisRosterException::class);
        \kisRosterPreviewHierarchy($pdo,(int)$b['id'],[(int)$a['id']],'2026-10-01');
    }

    public function testChangingParentEdgeDoesNotRewritePastMembership(): void
    {
        $pdo = $this->database();
        $group = \kisRosterCreateStandingTeam($pdo,7,'KROUZEK-HISTORY','Kroužek','group','Založení.');
        $preview = \kisRosterPreviewHierarchy($pdo,(int)$group['id'],[1],'2026-09-01');
        \kisRosterAttachChildren($pdo,(int)$group['id'],[1],'2026-09-01',7,'Zařazení.',$preview['fingerprint']);
        $edgeId = (int)$pdo->query('SELECT id FROM club_team_edges')->fetchColumn();
        self::assertSame([10],\kisRosterEffectiveMemberIds($pdo,(int)$group['id'],'2026-11-01'));
        $endPreview = \kisRosterPreviewEdgeEnd($pdo,$edgeId,'2027-01-31');
        \kisRosterEndHierarchyEdge($pdo,$edgeId,7,'2027-01-31','Reorganizace.',$endPreview['fingerprint']);
        self::assertSame([10],\kisRosterEffectiveMemberIds($pdo,(int)$group['id'],'2026-11-01'));
        self::assertSame([],\kisRosterEffectiveMemberIds($pdo,(int)$group['id'],'2027-03-01'));
        self::assertSame(2,(int)$pdo->query("SELECT COUNT(*) FROM club_roster_structure_events WHERE subject_type='edge'")->fetchColumn());
    }

    public function testHierarchyChangesRequirePreviewAndBlockAnsweredFutureTraining(): void
    {
        $pdo = $this->database();
        $group = \kisRosterCreateStandingTeam($pdo,7,'ANSWERED-GROUP','Odpovězená skupina','group','Založení.');
        $pdo->exec('CREATE TABLE planovane_treninky(id INTEGER PRIMARY KEY,nazev TEXT,datum TEXT,stav TEXT)');
        $pdo->exec('CREATE TABLE training_roster_links(plan_id INTEGER,team_id INTEGER)');
        $pdo->exec('CREATE TABLE training_rsvps(plan_id INTEGER)');
        $pdo->exec("INSERT INTO planovane_treninky VALUES(1,'Budoucí trénink','2028-03-01','planovany')");
        $pdo->exec('INSERT INTO training_roster_links VALUES(1,'.(int)$group['id'].')');
        $pdo->exec('INSERT INTO training_rsvps VALUES(1)');
        $preview = \kisRosterPreviewHierarchy($pdo,(int)$group['id'],[1],'2028-02-01');
        self::assertTrue($preview['impact']['blocked']);
        self::assertSame(1,$preview['impact']['plans'][0]['rsvp_count']);
        try {
            \kisRosterAttachChildren($pdo,(int)$group['id'],[1],'2028-02-01',7,'Zařazení.',$preview['fingerprint']);
            self::fail('Potvrzený budoucí trénink musí změnu blokovat.');
        } catch (\KisRosterException $expected) {
            self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM club_team_edges')->fetchColumn());
        }
        $pdo->exec('DELETE FROM training_rsvps');
        $preview = \kisRosterPreviewHierarchy($pdo,(int)$group['id'],[1],'2028-02-01');
        \kisRosterAttachChildren($pdo,(int)$group['id'],[1],'2028-02-01',7,'Zařazení.',$preview['fingerprint']);
        $edgeId = (int)$pdo->query('SELECT id FROM club_team_edges')->fetchColumn();
        $pdo->exec('INSERT INTO training_rsvps VALUES(1)');
        $end = \kisRosterPreviewEdgeEnd($pdo,$edgeId,'2028-02-28');
        self::assertTrue($end['impact']['blocked']);
        $this->expectException(\KisRosterException::class);
        \kisRosterEndHierarchyEdge($pdo,$edgeId,7,'2028-02-28','Ukončení.',$end['fingerprint']);
    }

    public function testFutureEventRegistrationBlocksHierarchyChange(): void
    {
        $pdo = $this->database();
        $group = \kisRosterCreateStandingTeam($pdo,7,'EVENT-GROUP','Akční skupina','group','Založení.');
        $pdo->exec('CREATE TABLE club_events(id INTEGER PRIMARY KEY,name TEXT)');
        $pdo->exec('CREATE TABLE club_event_sessions(event_id INTEGER,starts_at TEXT,status TEXT)');
        $pdo->exec('CREATE TABLE club_event_roster_targets(event_id INTEGER,team_id INTEGER)');
        $pdo->exec('CREATE TABLE club_event_registrations(event_id INTEGER,status TEXT)');
        $pdo->exec("INSERT INTO club_events VALUES(1,'Budoucí akce')");
        $pdo->exec("INSERT INTO club_event_sessions VALUES(1,'2028-04-01 10:00:00','scheduled')");
        $pdo->exec('INSERT INTO club_event_roster_targets VALUES(1,'.(int)$group['id'].')');
        $pdo->exec("INSERT INTO club_event_registrations VALUES(1,'registered')");
        $preview = \kisRosterPreviewHierarchy($pdo,(int)$group['id'],[1],'2028-03-01');
        self::assertTrue($preview['impact']['blocked']);
        self::assertSame(1,$preview['impact']['events'][0]['registration_count']);
    }

    public function testGroupMembershipCanOnlyBeDerivedFromChildren(): void
    {
        $pdo = $this->database();
        $group = \kisRosterCreateStandingTeam($pdo,7,'DERIVED-GROUP','Odvozená skupina','group','Založení.');
        $this->expectException(\KisRosterException::class);
        \kisRosterGrantPeriod($pdo,(int)$group['id'],10,7,'manual','2026-10-01',null,'Pokus o přímé zařazení.');
    }

    public function testPreMigrationFixtureUsesDirectMembership(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE club_roster_members(team_id INTEGER,sportovec_id INTEGER,status TEXT,valid_from TEXT,valid_to TEXT)');
        $pdo->exec("INSERT INTO club_roster_members VALUES(10,1,'removed','2025-09-01','2026-01-31'),(10,2,'active','2026-02-01',NULL)");
        self::assertSame([1],\kisRosterEffectiveMemberIds($pdo,10,'2025-11-01'));
        self::assertSame([2],\kisRosterEffectiveMemberIds($pdo,10,'2026-03-01'));
    }

    public function testFutureCancellationNeverCreatesOneDayEntitlement(): void
    {
        $pdo = $this->database();
        $member = \kisRosterGrantPeriod($pdo,1,11,7,'shop','2028-02-01','2028-06-30','Budoucí pololetí.','order-item-future');
        $ended = \kisRosterEndPeriod($pdo,(int)$member['id'],7,'2027-12-01','Storno před začátkem.');
        self::assertSame('cancelled',$ended['status']);
        self::assertSame([],\kisRosterEffectiveMemberIds($pdo,1,'2028-02-01'));
        self::assertSame('cancelled',$pdo->query('SELECT status FROM club_roster_members WHERE id='.(int)$member['id'])->fetchColumn());
        self::assertSame('2028-02-01',$pdo->query('SELECT valid_to FROM club_roster_members WHERE id='.(int)$member['id'])->fetchColumn());
    }

    public function testAthleteCardCanAssignAndDisplayStandingRoster(): void
    {
        $pdo = $this->database();
        $standing = \kisRosterCreateStandingTeam($pdo,7,'STANDING-RACE','Závodní tým','roster','Založení.');
        $sessionBefore = $_SESSION ?? [];
        $_SESSION['trener_id'] = 7;
        $_SESSION['role'] = 'admin';
        try { \sportovecEditorAddRoster($pdo,10,(int)$standing['id'],7,'2026-10-01','Zařazení.'); }
        finally { $_SESSION = $sessionBefore; }
        $card = \sportovecEditorRosters($pdo,10,'2026-10-20');
        self::assertCount(1,$card['current']);
        self::assertSame('Závodní tým',$card['current'][0]['team_name']);
        self::assertSame('Průběžně',$card['current'][0]['season_name']);
        self::assertContains((int)$standing['id'],array_map('intval',array_column($card['choices'],'id')));
    }
}
