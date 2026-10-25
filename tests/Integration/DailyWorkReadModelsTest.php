<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__,2).'/includes/staff_agenda_lists.php';
require_once dirname(__DIR__,2).'/includes/medical_clearance.php';
require_once dirname(__DIR__,2).'/includes/sports_activity_form.php';
require_once dirname(__DIR__,2).'/includes/person_workflow.php';

final class DailyWorkReadModelsTest extends TestCase
{
    private function db():PDO
    {
        $p=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $p->exec("CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT,aktivni INTEGER);CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT);CREATE TABLE staff_user_positions(trainer_id INTEGER,position_code TEXT);CREATE TABLE staff_superadmins(trainer_id INTEGER);
            INSERT INTO treneri VALUES(1,'LOCALHOST Lead',1),(2,'LOCALHOST Coach',1),(3,'LOCALHOST Foreign',1);
            INSERT INTO sportovci VALUES(1,'Anna','Testová');INSERT INTO staff_user_positions VALUES(1,'sports_lead'),(1,'registrar'),(2,'coach'),(3,'coach');");
        $_SESSION=['trener_id'=>1,'staff_positions'=>['sports_lead','registrar'],'staff_active_position'=>'sports_lead'];
        return $p;
    }
    protected function tearDown():void { $_SESSION=[]; }
    private function migrate(PDO $p,string $name):void {$m=require dirname(__DIR__,2).'/migrations/'.$name.'.php';$m['up']($p);}

    public function testOlderExpensesRemainSearchableAndFiltersDoNotBecomeSql():void
    {
        $p=$this->db();$this->migrate($p,'20261023050000_club_expenses');
        $insert=$p->prepare('INSERT INTO club_expense_cases(id,title,leader_trainer_id,due_on,evidence,created_by,state) VALUES(?,?,1,?,?,1,?)');
        for($i=1;$i<=231;$i++)$insert->execute([$i,$i===1?'100%_ školení':'Akce '.$i,$i===1?'2020-01-01':'2026-10-24','LOCALHOST',$i===1?'closed':'open']);
        $first=staffExpenseList($p,'case',[]);$last=staffExpenseList($p,'case',['case_page'=>'999999']);
        self::assertSame(231,$first['total']);self::assertCount(25,$first['rows']);self::assertSame(10,$last['page']);self::assertSame(1,(int)end($last['rows'])['id']);
        $old=staffExpenseList($p,'case',['case_q'=>'100%_','case_state'=>'closed','case_to'=>'2020-01-01']);self::assertSame(1,$old['total']);
        self::assertSame(0,staffExpenseList($p,'case',['case_q'=>"%' OR 1=1 --"])['total']);
        self::assertSame(0,staffExpenseList($p,'case',['case_from'=>'2026-02-31'])['total']);
        self::assertNotSame('',staffExpenseList($p,'case',['case_from'=>'2026-12-01','case_to'=>'2026-01-01'])['filters']['error']);
        self::assertSame(231,(int)$p->query('SELECT COUNT(*) FROM club_expense_cases')->fetchColumn());
    }

    public function testRewardFilteringAlwaysKeepsCoachOwnership():void
    {
        $p=$this->db();$p->exec("CREATE TABLE club_reward_claims(id INTEGER PRIMARY KEY,kind TEXT,subject_id INTEGER,date_from TEXT,current_version_id INTEGER);
            CREATE TABLE club_reward_decisions(version_id INTEGER,outcome TEXT);
            INSERT INTO club_reward_claims VALUES(1,'coach',2,'2020-01-01',11),(2,'coach',3,'2020-01-01',12),(3,'athlete',1,'2020-01-01',13);
            INSERT INTO club_reward_decisions VALUES(11,'approved'),(12,'approved');");
        $own=staffRewardList($p,['reward_state'=>'approved','reward_from'=>'2020-01-01','reward_to'=>'2020-01-01'],'coach',2);
        self::assertSame([1],array_map('intval',array_column($own['rows'],'id')));
        self::assertSame(0,staffRewardList($p,['reward_q'=>'Foreign'],'coach',2)['total']);
        $lead=staffRewardList($p,['reward_q'=>'Anna Testová','reward_state'=>'pending'],'sports_lead',1);
        self::assertSame([3],array_map('intval',array_column($lead['rows'],'id')));
    }

    public function testMedicalPaginationUsesCanonicalStatusAndDoesNotExposeDocuments():void
    {
        $p=$this->db();$this->migrate($p,'20261023040000_medical_clearance');
        $p->exec("INSERT INTO medical_clearance_policies(id,purpose,legal_basis,retention_rule,activity_rule,reminder_days,approved_by) VALUES(1,'LOCALHOST','LOCALHOST','LOCALHOST','LOCALHOST',30,1)");
        $q=$p->prepare('INSERT INTO medical_clearance_watches(id,sportovec_id,purpose,policy_id,next_review_on,created_by) VALUES(?,1,?,1,?,1)');
        for($i=1;$i<=231;$i++)$q->execute([$i,'Účel '.$i,$i===231?'2099-01-01':'2026-01-01']);
        $p->exec("INSERT INTO medical_clearance_records(id,watch_id,issued_on,valid_until,original_location,state,recorded_by) VALUES(1,231,'2000-01-01','2099-01-01','SECRET','verified',1),(2,231,'2001-01-01','2099-01-01','SECRET','pending',1)");
        $changes=(int)$p->query('SELECT total_changes()')->fetchColumn();
        $all=medicalClearanceList($p,[]);self::assertSame(231,$all['total']);self::assertSame(10,$all['pages']);
        $valid=medicalClearanceList($p,['person_id'=>1,'medical_q'=>'Anna Testová','medical_state'=>'valid']);
        self::assertSame(1,$valid['total']);self::assertSame(231,(int)$valid['rows'][0]['id']);
        self::assertSame(medicalClearanceStatus($p,1,'Účel 231',date('Y-m-d')),$valid['rows'][0]['evaluated_status']);
        self::assertStringNotContainsString('SECRET',json_encode($valid));
        self::assertSame(0,medicalClearanceList($p,['person_id'=>2])['total']);
        self::assertSame($changes,(int)$p->query('SELECT total_changes()')->fetchColumn());
    }

    public function testSportsCorrectionKeepsOriginalVersionWhenPostFails():void
    {
        $p=$this->db();$p->exec("CREATE TABLE club_events(id INTEGER PRIMARY KEY,name TEXT);INSERT INTO club_events VALUES(1,'LOCALHOST Race');
            CREATE TABLE sports_event_participation(id INTEGER,event_id INTEGER,sportovec_id INTEGER,current_revision_id INTEGER);
            CREATE TABLE sports_event_revisions(id INTEGER,version INTEGER,snapshot_json TEXT);
            INSERT INTO sports_event_participation VALUES(1,1,1,10);");
        $p->prepare('INSERT INTO sports_event_revisions VALUES(10,4,?)')->execute([json_encode(['stage'=>'started','actual_date'=>'2020-01-01','result_value'=>'12','result_status'=>'FINISHED','source_reference'=>'LOCALHOST proof'])]);
        $get=sportsActivityFormContext($p,1,1,1);self::assertSame(4,$get['form']['expected_version']);self::assertSame('12',$get['form']['result_value']);self::assertSame('',$get['form']['reason']);
        $post=sportsActivityFormContext($p,1,1,1,['expected_version'=>2,'result_value'=>'13','reason'=>'LOCALHOST stale correction','request_key'=>'original']);
        self::assertSame(2,$post['form']['expected_version']);self::assertSame('13',$post['form']['result_value']);self::assertSame('original',$post['form']['request_key']);
        self::assertSame(4,(int)$p->query('SELECT version FROM sports_event_revisions')->fetchColumn());
    }

    public function testPersonChecklistDoesNotInventOnlineOrMedicalRequirements():void
    {
        $p=$this->db();$_SESSION['staff_active_position']='registrar';
        $steps=personWorkflowSteps($p,['id'=>1,'stav_clenstvi'=>'cekajici','stav_manualni'=>0],['roles'=>[],'rosters'=>['current'=>[],'planned'=>[]]]);
        $byTitle=array_column($steps,null,'title');self::assertFalse($byTitle['Rodiče a přístupy']['attention']);
        self::assertTrue($byTitle['Členství']['attention']);self::assertTrue($byTitle['Zdravotní podklady']['attention']);
        self::assertStringContainsString('není dostupný',$byTitle['Zdravotní podklady']['detail']);
        self::assertStringContainsString('person_id=1',$byTitle['Osobní přijetí do programu']['href']);
        $now=(string)$p->query('SELECT CURRENT_TIMESTAMP')->fetchColumn();
        $roles=[['status'=>'approved','aktivni'=>1,'email_overeno'=>1,'valid_from'=>$now,'valid_to'=>null],['status'=>'approved','aktivni'=>1,'email_overeno'=>1,'valid_from'=>'2000-01-01','valid_to'=>$now]];
        $steps=personWorkflowSteps($p,['id'=>1,'stav_clenstvi'=>'aktivni','stav_manualni'=>1],['roles'=>$roles]);
        self::assertStringContainsString('vazby: 1.',array_column($steps,null,'title')['Rodiče a přístupy']['detail']);
    }
}
