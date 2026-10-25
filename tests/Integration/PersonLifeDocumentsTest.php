<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__,2).'/includes/person_lifecycle.php';
require_once dirname(__DIR__,2).'/includes/medical_activity.php';
require_once dirname(__DIR__,2).'/includes/family_tasks.php';

final class PersonLifeDocumentsTest extends TestCase
{
    private function fixture():PDO
    {
        $p=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $p->exec("CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT,aktivni INTEGER);INSERT INTO treneri VALUES(1,'LOCALHOST Registrar',1),(2,'LOCALHOST Lead',1);
        CREATE TABLE staff_user_positions(trainer_id INTEGER,position_code TEXT);INSERT INTO staff_user_positions VALUES(1,'registrar'),(2,'sports_lead');CREATE TABLE staff_superadmins(trainer_id INTEGER);
        CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,narozeni TEXT,stav_clenstvi TEXT,stav_manualni INTEGER,stav_duvod TEXT,stav_aktualizovan TEXT);
        INSERT INTO sportovci VALUES(1,'Anna','Own','2015-01-01','aktivni',1,'LOCALHOST existing decision','2020-01-01'),(2,'Secret','Foreign','2015-01-01','aktivni',0,NULL,NULL);
        CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,aktivni INTEGER,email_overeno INTEGER);INSERT INTO verejni_uzivatele VALUES(10,'LOCALHOST','Parent',1,1),(20,'LOCALHOST','Other',1,1);
        CREATE TABLE account_person_roles(id INTEGER PRIMARY KEY,account_id INTEGER,sportovec_id INTEGER,relation_role TEXT,status TEXT,valid_from TEXT,valid_to TEXT);
        INSERT INTO account_person_roles VALUES(1,10,1,'guardian','approved','2000-01-01',NULL),(2,20,2,'guardian','approved','2000-01-01',NULL);
        CREATE TABLE account_person_claim_requests(id INTEGER PRIMARY KEY,account_id INTEGER,status TEXT);
        CREATE TABLE sportovec_history(id INTEGER PRIMARY KEY,sportovec_id INTEGER,created_at TEXT,created_by INTEGER,event_type TEXT,title TEXT,new_json TEXT);");
        foreach(['20261022213000_operational_cases','20261023040000_medical_clearance'] as $name){$m=require dirname(__DIR__,2).'/migrations/'.$name.'.php';$m['up']($p);}
        $p->exec("INSERT INTO medical_clearance_policies(id,purpose,legal_basis,retention_rule,activity_rule,reminder_days,approved_by) VALUES(1,'LOCALHOST sport','LOCALHOST','LOCALHOST','LOCALHOST',30,1)");
        $_SESSION=['trener_id'=>1,'staff_positions'=>['registrar'],'staff_active_position'=>'registrar'];
        return $p;
    }
    protected function tearDown():void {$_SESSION=[];}
    private function watch(PDO $p,int $id,int $person,string $state,?int $replaces=null):void
    {
        $p->prepare('INSERT INTO medical_clearance_watches(id,sportovec_id,purpose,policy_id,next_review_on,created_by) VALUES(?,?,?,1,?,1)')->execute([$id,$person,'LOCALHOST sport '.$id,'2030-01-01']);
        $p->prepare('INSERT INTO medical_clearance_records(id,watch_id,issued_on,valid_until,original_location,state,recorded_by,replaces_id) VALUES(?,?,?, ?,?,?,1,?)')->execute([$id,$id,'2020-01-01','2030-01-15','PRIVATE SECRET CABINET',$state,$replaces]);
    }
    public function testManualFollowupDoesNotChangePersonAndRejectsStaleRevision():void
    {
        $p=$this->fixture();$before=$p->query('SELECT * FROM sportovci')->fetchAll();
        $input=['owner_trainer_id'=>1,'next_step'=>'LOCALHOST review departure','review_on'=>'2030-01-01','reason'=>'LOCALHOST confirmed personal visit','confirmed'=>'1','revision'=>0,'status'=>'open'];
        $id=personLifecycleSave($p,1,$input);self::assertGreaterThan(0,$id);
        try{personLifecycleSave($p,1,$input);self::fail('Stale write accepted');}catch(DomainException $e){self::assertStringContainsString('mezitím',$e->getMessage());}
        self::assertSame(1,(int)$p->query('SELECT COUNT(*) FROM operational_case_events')->fetchColumn());
        personLifecycleSave($p,1,array_replace($input,['revision'=>1,'status'=>'closed']));
        self::assertSame('closed',$p->query('SELECT status FROM operational_cases')->fetchColumn());
        self::assertSame($before,$p->query('SELECT * FROM sportovci')->fetchAll());
        self::assertSame(2,(int)$p->query('SELECT COUNT(*) FROM account_person_roles')->fetchColumn());
        self::assertSame(2,(int)$p->query('SELECT COUNT(*) FROM operational_case_events')->fetchColumn());
        personLifecycleSave($p,1,array_replace($input,['revision'=>2,'next_step'=>'LOCALHOST review return']));
        self::assertSame(1,(int)$p->query('SELECT COUNT(*) FROM operational_cases')->fetchColumn());
        self::assertSame(3,(int)$p->query('SELECT COUNT(*) FROM operational_case_events')->fetchColumn());
        $open=operationalCaseList($p,'registrar');$found=array_values(array_filter($open['items'],static fn($r)=>$r['source_type']==='person_followup'));
        self::assertCount(1,$found);self::assertSame('person_lifecycle_admin.php?id=1',$found[0]['href']);
    }
    public function testLifecycleHistoryIsPaginatedWithoutInventingEffectiveDates():void
    {
        $p=$this->fixture();$q=$p->prepare('INSERT INTO sportovec_history VALUES(?,1,?,1,?,?,?)');
        for($i=1;$i<=31;$i++)$q->execute([$i,'2030-01-01','status_manual','LOCALHOST decision',json_encode(['stav_clenstvi'=>'aktivni','stav_manualni'=>1,'stav_duvod'=>'LOCALHOST reason','rc'=>'SECRET'])]);
        $changes=(int)$p->query('SELECT total_changes()')->fetchColumn();
        $model=personLifecycleRead($p,1,['history_page'=>99]);
        self::assertSame(31,$model['history']['total']);self::assertSame(2,$model['history']['page']);self::assertCount(6,$model['history']['rows']);
        self::assertStringNotContainsString('SECRET',json_encode($model));self::assertArrayNotHasKey('effective_from',$model['history']['rows'][0]);
        self::assertContains('Soupisky',$model['missing']);self::assertSame($changes,(int)$p->query('SELECT total_changes()')->fetchColumn());
        self::assertSame(0,(int)$p->query('SELECT COUNT(*) FROM operational_cases')->fetchColumn());
    }
    public function testMedicalContextKeepsCanonicalStateAndSeparatesExpiryWarning():void
    {
        $p=$this->fixture();$this->watch($p,1,1,'verified');
        $p->exec("INSERT INTO medical_clearance_records(id,watch_id,issued_on,valid_until,original_location,state,recorded_by) VALUES(9,1,'2029-12-01','2031-01-01','SECRET NEW','pending',1)");
        $rows=medicalActivityPersonRows($p,1,'2030-01-01');
        self::assertSame('valid',$rows[0]['status']);self::assertSame('expiring',$rows[0]['attention_status']);
        self::assertSame('expired',medicalActivityPersonRows($p,1,'2030-01-16')[0]['status']);
        self::assertStringNotContainsString('SECRET',json_encode($rows));
        self::assertSame('valid',medicalActivityPersonRows($p,1,'2029-01-01')[0]['attention_status']);
    }
    public function testFamilyTasksRespectCurrentAuthorizationAndDoNotPublishPrivateData():void
    {
        $p=$this->fixture();$this->watch($p,1,1,'verified');$this->watch($p,2,2,'revoked');
        $before=(int)$p->query('SELECT total_changes()')->fetchColumn();
        $view=familyTasksRead($p,10,'2030-01-01');$json=json_encode($view,JSON_UNESCAPED_UNICODE);
        self::assertStringContainsString('Končí platnost',$json);self::assertStringNotContainsString('Foreign',$json);self::assertStringNotContainsString('SECRET',$json);
        self::assertSame($before,(int)$p->query('SELECT total_changes()')->fetchColumn());
        $p->exec("UPDATE account_person_roles SET status='revoked' WHERE id=1");
        self::assertStringNotContainsString('Own',json_encode(familyTasksRead($p,10,'2030-01-01')));
        $p->exec('UPDATE verejni_uzivatele SET email_overeno=0 WHERE id=10');
        $this->expectException(FamilyPortalAccessDenied::class);familyTasksRead($p,10,'2030-01-01');
    }
    public function testPendingEvidenceAndClaimWaitForClubRatherThanDuplicateSubmission():void
    {
        $p=$this->fixture();$this->watch($p,1,1,'pending');
        $view=familyTasksRead($p,10,'2030-01-01');self::assertSame('club',$view['tasks'][0]['kind']);
        $p->exec("UPDATE account_person_roles SET status='revoked' WHERE id=1;INSERT INTO account_person_claim_requests VALUES(1,10,'pending')");
        $view=familyTasksRead($p,10,'2030-01-01');self::assertCount(1,$view['tasks']);self::assertSame('club',$view['tasks'][0]['kind']);
        self::assertStringContainsString('blokované',familyTasksDeliveryNotice('curl_exec,mail,exec'));
        self::assertStringContainsString('nelze ověřit',familyTasksDeliveryNotice(''));
        self::assertStringNotContainsString('není aktivní',familyTasksDeliveryNotice(''));
    }
    public function testRevokedRegistrarCannotSaveFollowup():void
    {
        $p=$this->fixture();$p->exec('DELETE FROM staff_user_positions WHERE trainer_id=1');
        $this->expectException(DomainException::class);personLifecycleSave($p,1,['confirmed'=>1]);
    }
    public function testGroupPaginationDoesNotInventRequiredMedicalDocuments():void
    {
        $p=$this->fixture();$_SESSION=['trener_id'=>2,'staff_positions'=>['sports_lead'],'staff_active_position'=>'sports_lead'];
        $p->exec("CREATE TABLE club_teams(id INTEGER PRIMARY KEY,name TEXT);INSERT INTO club_teams VALUES(1,'LOCALHOST Group');
            CREATE TABLE club_roster_members(team_id INTEGER,sportovec_id INTEGER,status TEXT,valid_from TEXT,valid_to TEXT);
            INSERT INTO club_roster_members VALUES(1,1,'active','2020-01-01',NULL)");
        for($i=3;$i<=33;$i++){$p->prepare('INSERT INTO sportovci(id,jmeno,prijmeni) VALUES(?,?,?)')->execute([$i,'LOCALHOST','Person '.$i]);$p->prepare("INSERT INTO club_roster_members VALUES(1,?,'active','2020-01-01',NULL)")->execute([$i]);}
        $this->watch($p,1,1,'verified');
        $all=medicalActivityRead($p,['team_id'=>1,'on'=>'2030-01-01','page'=>99]);
        self::assertSame(32,$all['total']);self::assertSame(2,$all['page']);self::assertCount(7,$all['rows']);
        $untracked=medicalActivityRead($p,['team_id'=>1,'on'=>'2030-01-01','state'=>'untracked']);self::assertSame(31,$untracked['total']);
        self::assertSame(0,medicalActivityRead($p,['team_id'=>1,'on'=>'2030-01-01','state'=>'missing'])['total']);
        self::assertStringNotContainsString('Foreign',json_encode($all));
    }
    public function testUnverifiedPaperVersionRemainsActionableAfterAcceptance():void
    {
        $p=$this->fixture();$p->exec("CREATE TABLE club_paper_records(id INTEGER PRIMARY KEY,sportovec_id INTEGER,kind TEXT,term_version_id INTEGER,version_label TEXT);
            CREATE TABLE club_paper_record_events(id INTEGER PRIMARY KEY,record_id INTEGER,event_type TEXT);
            INSERT INTO club_paper_records VALUES(1,1,'application',NULL,'verze neověřena');INSERT INTO club_paper_record_events VALUES(1,1,'recorded'),(2,1,'accepted'),(3,1,'file_viewed')");
        $model=personLifecycleRead($p,1);$step=array_column($model['steps'],null,'title')['Přihlášky a souhlasy'];
        self::assertTrue($step['attention']);self::assertStringContainsString('neověřeným zněním: 1',$step['detail']);
        self::assertSame('accepted',$model['paper'][0]['state']);
    }
}
