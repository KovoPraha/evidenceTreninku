<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__,2).'/includes/coach_scope.php';
require_once dirname(__DIR__,2).'/includes/public_profile_token.php';

final class CoachScopeTest extends TestCase
{
    protected function tearDown():void { $_SESSION=[]; }

    private function database():PDO
    {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT,aktivni INTEGER)');
        $pdo->exec("INSERT INTO treneri VALUES(7,'LOCALHOST Lead',1),(8,'LOCALHOST Coach',1),(9,'LOCALHOST Other',1)");
        $pdo->exec('CREATE TABLE staff_user_positions(trainer_id INTEGER,position_code TEXT)');
        $pdo->exec("INSERT INTO staff_user_positions VALUES(7,'sports_lead'),(8,'coach'),(9,'coach')");
        $pdo->exec('CREATE TABLE staff_superadmins(trainer_id INTEGER)');
        $pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT)');$pdo->exec("INSERT INTO sportovci VALUES(1,'LOCALHOST Assigned'),(2,'LOCALHOST Foreign'),(3,'LOCALHOST Future')");
        $pdo->exec('CREATE TABLE podskupiny(id INTEGER PRIMARY KEY,nazev TEXT)');$pdo->exec("INSERT INTO podskupiny VALUES(10,'LOCALHOST A'),(11,'LOCALHOST B')");
        $pdo->exec('CREATE TABLE sportovec_podskupina(sportovec_id INTEGER,podskupina_id INTEGER)');$pdo->exec('INSERT INTO sportovec_podskupina VALUES(1,10),(2,11)');
        $pdo->exec('CREATE TABLE club_teams(id INTEGER PRIMARY KEY,status TEXT)');$pdo->exec("INSERT INTO club_teams VALUES(20,'active')");
        $pdo->exec('CREATE TABLE club_roster_members(team_id INTEGER,sportovec_id INTEGER,status TEXT,valid_from TEXT,valid_to TEXT)');
        $pdo->exec("INSERT INTO club_roster_members VALUES(20,2,'active','2020-01-01',NULL),(20,3,'active','2099-01-01',NULL)");
        $pdo->exec('CREATE TABLE treninky(id INTEGER PRIMARY KEY)');$pdo->exec('INSERT INTO treninky VALUES(30),(31)');
        $pdo->exec('CREATE TABLE trenink_trener(trenink_id INTEGER,trener_id INTEGER)');$pdo->exec('INSERT INTO trenink_trener VALUES(30,8),(31,9)');
        $pdo->exec('CREATE TABLE trenink_sportovec(trenink_id INTEGER,sportovec_id INTEGER)');$pdo->exec('INSERT INTO trenink_sportovec VALUES(30,1),(31,1)');
        $pdo->exec('CREATE TABLE mereni_zaznamy(id INTEGER PRIMARY KEY,sportovec_id INTEGER)');
        $pdo->exec('CREATE TABLE trenink_mereni(trenink_id INTEGER,mereni_id INTEGER)');
        $migration=require dirname(__DIR__,2).'/migrations/20261022214000_coach_scope.php';$migration['up']($pdo);self::assertTrue($migration['verify']($pdo));
        $this->lead();return $pdo;
    }

    private function lead():void {$_SESSION=['trener_id'=>7,'staff_positions'=>['sports_lead'],'staff_active_position'=>'sports_lead','trener_session_version'=>1];}
    private function coach():void {$_SESSION=['trener_id'=>8,'staff_positions'=>['coach'],'staff_active_position'=>'coach','trener_session_version'=>1];}
    private function grant(PDO $pdo,string $kind='subgroup',int $scopeId=10,?string $from=null,?string $to=null):int
    {
        $this->lead();return coachScopeGrant($pdo,8,$kind,$scopeId,$from??gmdate('Y-m-d H:i:s',time()-86400),$to,'LOCALHOST výslovné přidělení sportovní skupiny.',7);
    }

    public function testHistoricalTrainingAndCoachPositionDoNotGrantGlobalAccess():void
    {
        $pdo=$this->database();$this->coach();
        self::assertFalse(coachScopeCanViewPerson($pdo,1));self::assertFalse(coachScopeCanViewPerson($pdo,2));
        self::assertSame([], $pdo->query('SELECT t.id FROM treninky t WHERE '.coachScopeTrainingPredicate($pdo))->fetchAll(PDO::FETCH_COLUMN));
    }

    public function testExplicitSubgroupAndTeamGrantsOnlyIncludeCurrentMembers():void
    {
        $pdo=$this->database();$this->grant($pdo);$this->coach();
        self::assertTrue(coachScopeCanViewPerson($pdo,1));self::assertFalse(coachScopeCanViewPerson($pdo,2));
        self::assertSame([1],array_map('intval',$pdo->query('SELECT s.id FROM sportovci s WHERE '.coachScopePersonPredicate($pdo))->fetchAll(PDO::FETCH_COLUMN)));
        $this->grant($pdo,'team',20);$this->coach();
        self::assertTrue(coachScopeCanViewPerson($pdo,2));self::assertFalse(coachScopeCanViewPerson($pdo,3));
        $pdo->exec("UPDATE club_roster_members SET valid_to='2020-01-02' WHERE sportovec_id=2");
        self::assertFalse(coachScopeCanViewPerson($pdo,2));
        $pdo->exec('UPDATE sportovec_podskupina SET podskupina_id=11 WHERE sportovec_id=1');
        self::assertFalse(coachScopeCanViewPerson($pdo,1));
    }

    public function testFutureExpiredAndRevokedGrantCannotReadOrReuseProfileSession():void
    {
        $pdo=$this->database();$id=$this->grant($pdo);$this->coach();
        public_profile_access_grant_staff_session(1);
        self::assertSame(1,public_profile_access_session_athlete_id($pdo));
        $access=$_SESSION['public_profile_access'];
        $this->lead();coachScopeRevoke($pdo,$id,'LOCALHOST trenér již není přidělen skupině.',7);
        $this->coach();$_SESSION['public_profile_access']=$access;
        self::assertNull(public_profile_access_session_athlete_id($pdo));self::assertArrayNotHasKey('public_profile_access',$_SESSION);
        $this->grant($pdo,'subgroup',10,'2099-01-01 00:00:00');$this->coach();self::assertFalse(coachScopeCanViewPerson($pdo,1));
        $this->grant($pdo,'subgroup',10,'2020-01-01 00:00:00','2020-02-01 00:00:00');$this->coach();self::assertFalse(coachScopeCanViewPerson($pdo,1));
    }

    public function testDeactivatedStaffOrRemovedRoleImmediatelyLosesScope():void
    {
        $pdo=$this->database();$this->grant($pdo);$this->coach();
        $pdo->exec('UPDATE treneri SET aktivni=0 WHERE id=8');self::assertFalse(coachScopeCanViewPerson($pdo,1));
        $pdo->exec('UPDATE treneri SET aktivni=1 WHERE id=8');self::assertTrue(coachScopeCanViewPerson($pdo,1));
        $pdo->exec('DELETE FROM staff_user_positions WHERE trainer_id=8');self::assertFalse(coachScopeCanViewPerson($pdo,1));
    }

    public function testCoachCannotSelfGrantEvenWhenActorIdIsForged():void
    {
        $pdo=$this->database();$this->coach();
        try{coachScopeGrant($pdo,8,'subgroup',10,'2020-01-01 00:00:00',null,'LOCALHOST podvržená změna pracovníka.',7);self::fail('Coach self grant');}
        catch(RuntimeException){self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM coach_scope_grants')->fetchColumn());}
        $this->lead();$pdo->exec("DELETE FROM staff_user_positions WHERE trainer_id=7");
        try{coachScopeGrant($pdo,8,'subgroup',10,'2020-01-01 00:00:00',null,'LOCALHOST odebraná pozice vedoucího.',7);self::fail('Stale lead role');}
        catch(RuntimeException){self::assertFalse($pdo->inTransaction());}
    }

    public function testOverlapsRejectedAdjacentIntervalsAllowedAndRevocationIsAuditedOnce():void
    {
        $pdo=$this->database();$id=$this->grant($pdo,'subgroup',10,'2020-01-01 00:00:00','2021-01-01 00:00:00');
        try{$this->grant($pdo,'subgroup',10,'2020-06-01 00:00:00','2022-01-01 00:00:00');self::fail('Overlap');}
        catch(RuntimeException){self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM coach_scope_grants')->fetchColumn());}
        $this->grant($pdo,'subgroup',10,'2021-01-01 00:00:00','2022-01-01 00:00:00');
        coachScopeRevoke($pdo,$id,'LOCALHOST ukončení původního přidělení.',7);coachScopeRevoke($pdo,$id,'LOCALHOST opakované odeslání formuláře.',7);
        self::assertSame(3,(int)$pdo->query('SELECT COUNT(*) FROM coach_scope_events')->fetchColumn());
    }

    public function testAuditFailureRollsBackGrant():void
    {
        $pdo=$this->database();$pdo->exec("CREATE TRIGGER fail_scope_audit BEFORE INSERT ON coach_scope_events BEGIN SELECT RAISE(ABORT,'audit failure');END");
        try{$this->grant($pdo);self::fail('Audit failure');}catch(PDOException){self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM coach_scope_grants')->fetchColumn());}
    }

    public function testWorkspacesDoNotInheritSportsScopeFromFinanceRole():void
    {
        $pdo=$this->database();$this->grant($pdo);$this->coach();
        $_SESSION['staff_positions']=['coach','finance_manager'];$_SESSION['staff_active_position']='finance_manager';$_SESSION['role']='admin';
        self::assertFalse(coachScopeCanViewPerson($pdo,1));self::assertFalse(coachScopeCanViewPerson($pdo,2));
        $this->lead();self::assertTrue(coachScopeCanViewPerson($pdo,2));
    }

    public function testTrainingReadRequiresOwnershipAndCurrentScopeForAllMeasuredPeople():void
    {
        $pdo=$this->database();$this->grant($pdo);$this->coach();
        self::assertSame([30],array_map('intval',$pdo->query('SELECT t.id FROM treninky t WHERE '.coachScopeTrainingPredicate($pdo))->fetchAll(PDO::FETCH_COLUMN)));
        $pdo->exec('INSERT INTO mereni_zaznamy VALUES(1,2)');$pdo->exec('INSERT INTO trenink_mereni VALUES(30,1)');
        self::assertSame([],$pdo->query('SELECT t.id FROM treninky t WHERE '.coachScopeTrainingPredicate($pdo))->fetchAll(PDO::FETCH_COLUMN));
    }

    public function testMutationCannotSmuggleForeignParticipantOrTeam():void
    {
        $pdo=$this->database();$this->grant($pdo);$this->coach();
        self::assertSame([1,2],coachScopeParticipantIds('1:LOCALHOST',[['sportovec_id'=>2]]));
        try{coachScopeAssertPeople($pdo,coachScopeParticipantIds('1:LOCALHOST',[['sportovec_id'=>2]]));self::fail('Foreign measurement');}
        catch(RuntimeException){self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM mereni_zaznamy')->fetchColumn());}
        try{coachScopeAssertTeams($pdo,[20]);self::fail('Foreign team');}catch(RuntimeException){self::assertTrue(coachScopeCanViewPerson($pdo,1));}
    }

    public function testOwningPlanDoesNotAuthorizeItsRosterAnswersOrSubstitutes():void
    {
        $pdo=$this->database();
        $pdo->exec('CREATE TABLE planovane_treninky(id INTEGER PRIMARY KEY,trener_id INTEGER,podskupina_id INTEGER,skupina_id INTEGER,datum TEXT)');
        $pdo->exec("INSERT INTO planovane_treninky VALUES(50,8,NULL,NULL,CURRENT_DATE),(51,9,NULL,NULL,CURRENT_DATE)");
        $pdo->exec('CREATE TABLE training_roster_links(id INTEGER PRIMARY KEY,plan_id INTEGER,team_id INTEGER)');
        $pdo->exec('CREATE TABLE training_roster_expected(link_id INTEGER,sportovec_id INTEGER)');
        $pdo->exec('CREATE TABLE training_rsvps(plan_id INTEGER,sportovec_id INTEGER)');
        $pdo->exec('CREATE TABLE club_makeup_reservations(target_plan_id INTEGER,sportovec_id INTEGER)');
        $this->coach();coachScopeAssertPlan($pdo,50); // Own internal plan has no personal data.
        try{coachScopeAssertPlan($pdo,51);self::fail('Foreign owner');}catch(RuntimeException){self::assertTrue(true);}
        $pdo->exec('INSERT INTO training_roster_links VALUES(1,50,20)');
        try{coachScopeAssertPlan($pdo,50);self::fail('Ownership cannot self-authorize');}catch(RuntimeException){self::assertTrue(true);}
        $grant=$this->grant($pdo,'team',20);$this->coach();coachScopeAssertPlan($pdo,50);
        foreach(['training_roster_expected'=>'link_id','training_rsvps'=>'plan_id','club_makeup_reservations'=>'target_plan_id'] as $table=>$column){
            $pdo->exec("INSERT INTO $table($column,sportovec_id) VALUES(".($column==='link_id'?1:50).",1)");
            try{coachScopeAssertPlan($pdo,50);self::fail('Foreign person through '.$table);}catch(RuntimeException){self::assertTrue(true);}
            $pdo->exec("DELETE FROM $table");
        }
        $this->lead();coachScopeRevoke($pdo,$grant,'LOCALHOST plán již trenérovi nepřísluší.',7);$this->coach();
        $this->expectException(RuntimeException::class);coachScopeAssertPlan($pdo,50);
    }

    public function testSelectingGroupRequiresExplicitGrantForEveryExpandedRoster():void
    {
        $pdo=$this->database();$pdo->exec("INSERT INTO club_teams VALUES(21,'active')");
        $pdo->exec('CREATE TABLE club_team_edges(parent_team_id INTEGER,child_team_id INTEGER,valid_from TEXT,valid_to TEXT)');
        $pdo->exec("INSERT INTO club_team_edges VALUES(20,21,'2020-01-01',NULL)");
        $this->grant($pdo,'team',20);$this->coach();
        try{coachScopeAssertTeams($pdo,[20],date('Y-m-d'));self::fail('Parent scope is not inherited');}catch(RuntimeException){self::assertTrue(true);}
        $this->grant($pdo,'team',21);$this->coach();coachScopeAssertTeams($pdo,[20],date('Y-m-d'));
        self::assertFalse($pdo->inTransaction());
    }
}
