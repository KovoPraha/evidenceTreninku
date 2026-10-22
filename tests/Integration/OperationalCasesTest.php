<?php
declare(strict_types=1);
namespace Tests\Integration;
use PDO;
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__,2).'/includes/operational_cases.php';

final class OperationalCasesTest extends TestCase
{
    private function database():PDO
    {
        $p=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $p->exec('PRAGMA foreign_keys=ON');
        $p->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT,aktivni INTEGER)');
        $p->exec('CREATE TABLE staff_user_positions(trainer_id INTEGER,position_code TEXT)');
        $p->exec('CREATE TABLE staff_superadmins(trainer_id INTEGER)');
        $p->exec("INSERT INTO treneri VALUES(1,'Registrář',1),(2,'Druhý registrář',1),(3,'Trenér',1),(4,'Objednávky',1)");
        $p->exec("INSERT INTO staff_user_positions VALUES(1,'registrar'),(2,'registrar'),(3,'coach'),(4,'order_operator')");
        $p->exec('CREATE TABLE person_identity_reviews(id INTEGER PRIMARY KEY,status TEXT)');
        $p->exec("INSERT INTO person_identity_reviews VALUES(10,'review')");
        $p->exec('CREATE TABLE account_person_claim_requests(id INTEGER PRIMARY KEY,status TEXT)');
        $p->exec("INSERT INTO account_person_claim_requests VALUES(20,'pending')");
        $p->exec('CREATE TABLE club_event_notifications(id INTEGER PRIMARY KEY,status TEXT,attempts INTEGER)');
        $p->exec("INSERT INTO club_event_notifications VALUES(30,'failed',1)");
        $p->exec('CREATE TABLE club_event_notification_events(id INTEGER PRIMARY KEY,notification_id INTEGER,action TEXT,attempts_before INTEGER)');
        $m=require dirname(__DIR__,2).'/migrations/20261022213000_operational_cases.php';$m['up']($p);$m['up']($p);self::assertTrue($m['verify']($p));
        return $p;
    }
    private function input(array $replace=[]):array
    {
        return array_replace(['source_type'=>'identity_review','source_id'=>10,'owner_trainer_id'=>1,'next_step'=>'Ověřit původní přihlášku','review_on'=>'2026-10-30','status'=>'open','revision'=>0,'reason'=>'Telefonická domluva s rodičem'],$replace);
    }
    private function rejected(callable $action):void
    {
        try {$action();self::fail('Expected rejection');}catch(\DomainException $e){self::assertNotSame('',$e->getMessage());}
    }
    public function testReadOnlyScopeAndAbsentSourcesNeverPretendComplete():void
    {
        $p=$this->database();$view=\operationalCaseList($p,'registrar');
        self::assertSame(['identity_review','family_claim'],array_column($view['items'],'source_type'));
        self::assertSame(['Platnost zdravotního podkladu','Nedokončené přijetí do programu','Papírový dokument','Zpráva soupisce'],$view['unavailable']);
        self::assertSame(0,(int)$p->query('SELECT COUNT(*) FROM operational_cases')->fetchColumn());
        self::assertSame(['notification'],array_column(\operationalCaseList($p,'order_operator')['items'],'source_type'));
        self::assertSame([],\operationalCaseList($p,'coach')['items']);
    }
    public function testAssignHandoffConflictAndCloseCannotChangeCanonicalRecord():void
    {
        $p=$this->database();$id=\operationalCaseSave($p,1,'registrar',$this->input());
        $this->rejected(fn()=>\operationalCaseSave($p,2,'registrar',$this->input()));
        $this->rejected(fn()=>\operationalCaseSave($p,1,'registrar',$this->input(['revision'=>1,'status'=>'closed'])));
        self::assertSame('review',$p->query('SELECT status FROM person_identity_reviews WHERE id=10')->fetchColumn());
        \operationalCaseSave($p,1,'registrar',$this->input(['revision'=>1,'owner_trainer_id'=>2,'reason'=>'Předání zastupujícímu registráři']));
        $p->exec("UPDATE person_identity_reviews SET status='verified' WHERE id=10");
        \operationalCaseSave($p,2,'registrar',$this->input(['revision'=>2,'owner_trainer_id'=>2,'status'=>'closed']));
        self::assertSame(3,(int)$p->query('SELECT COUNT(*) FROM operational_case_events')->fetchColumn());
        $closed=\operationalCaseList($p,'registrar',1,true)['items'];self::assertCount(1,$closed);self::assertSame($id,(int)$closed[0]['case_id']);
        // A renewed source problem must reappear even if the work item was closed.
        $p->exec("UPDATE person_identity_reviews SET status='deferred' WHERE id=10");
        self::assertContains('identity_review',array_column(\operationalCaseList($p,'registrar')['items'],'source_type'));
    }
    public function testRevokedActorAndWrongOwnerOrDomainCannotWrite():void
    {
        $p=$this->database();
        $this->rejected(fn()=>\operationalCaseSave($p,3,'registrar',$this->input()));
        $this->rejected(fn()=>\operationalCaseSave($p,1,'registrar',$this->input(['owner_trainer_id'=>3])));
        $this->rejected(fn()=>\operationalCaseSave($p,1,'registrar',$this->input(['source_type'=>'notification','source_id'=>30])));
        $p->exec('UPDATE treneri SET aktivni=0 WHERE id=1');
        $this->rejected(fn()=>\operationalCaseSave($p,1,'registrar',$this->input()));
        self::assertSame(0,(int)$p->query('SELECT COUNT(*) FROM operational_cases')->fetchColumn());
    }
    public function testAuditFailureRollsBackEntireAssignment():void
    {
        $p=$this->database();$p->exec("CREATE TRIGGER fail_audit BEFORE INSERT ON operational_case_events BEGIN SELECT RAISE(ABORT,'Synthetic audit failure'); END");
        try {\operationalCaseSave($p,1,'registrar',$this->input());self::fail();}catch(\PDOException $e){self::assertStringContainsString('Synthetic audit failure',$e->getMessage());}
        self::assertFalse($p->inTransaction());self::assertSame(0,(int)$p->query('SELECT COUNT(*) FROM operational_cases')->fetchColumn());
        self::assertSame('review',$p->query('SELECT status FROM person_identity_reviews WHERE id=10')->fetchColumn());
    }
    public function testPaginationAndUntrustedInputAreBounded():void
    {
        $p=$this->database();for($i=100;$i<135;$i++)$p->exec("INSERT INTO person_identity_reviews VALUES($i,'review')");
        $first=\operationalCaseList($p,'registrar',1);$next=\operationalCaseList($p,'registrar',2);
        self::assertTrue($first['has_next']);self::assertFalse($next['has_next']);
        $a=array_filter($first['items'],fn($r)=>$r['source_type']==='identity_review');$b=array_filter($next['items'],fn($r)=>$r['source_type']==='identity_review');
        self::assertCount(30,$a);self::assertCount(6,$b);self::assertSame([],array_intersect(array_column($a,'source_id'),array_column($b,'source_id')));
        foreach ([['review_on'=>'2026-02-31'],['reason'=>''],['next_step'=>''],['source_type'=>'person_identity_reviews; DROP TABLE treneri'],['source_id'=>999999]] as $bad) {
            $this->rejected(fn()=>\operationalCaseSave($p,1,'registrar',$this->input($bad)));
        }
    }
    public function testSportsAndAdmissionsCasesRequireCanonicalResolution():void
    {
        $p=$this->database();
        $p->exec("INSERT INTO staff_user_positions VALUES(3,'sports_lead')");
        $p->exec('CREATE TABLE treninky(id INTEGER PRIMARY KEY,datum TEXT);CREATE TABLE training_closures(training_id INTEGER,status TEXT)');
        $p->exec("INSERT INTO treninky VALUES(41,'2020-01-01'),(42,'2099-01-01')");
        $p->exec('CREATE TABLE club_program_admissions(id INTEGER PRIMARY KEY,order_id INTEGER);CREATE TABLE shop_orders(id INTEGER PRIMARY KEY,status TEXT,payment_status TEXT)');
        $p->exec("INSERT INTO shop_orders VALUES(51,'placed','pending');INSERT INTO club_program_admissions VALUES(61,51)");
        $sports=\operationalCaseList($p,'sports_lead')['items'];self::assertSame([41],array_map('intval',array_column($sports,'source_id')));
        self::assertStringContainsString('training_closure_admin.php?id=41',$sports[0]['href']);
        $input=$this->input(['source_type'=>'training_attendance','source_id'=>41,'owner_trainer_id'=>3]);
        \operationalCaseSave($p,3,'sports_lead',$input);
        $this->rejected(fn()=>\operationalCaseSave($p,3,'sports_lead',array_replace($input,['revision'=>1,'status'=>'closed'])));
        $p->exec("INSERT INTO training_closures VALUES(41,'closed')");
        \operationalCaseSave($p,3,'sports_lead',array_replace($input,['revision'=>1,'status'=>'closed']));
        self::assertContains('program_admission',array_column(\operationalCaseList($p,'registrar')['items'],'source_type'));
        $input=$this->input(['source_type'=>'program_admission','source_id'=>61]);\operationalCaseSave($p,1,'registrar',$input);
        $this->rejected(fn()=>\operationalCaseSave($p,1,'registrar',array_replace($input,['revision'=>1,'status'=>'closed'])));
        self::assertSame('pending',$p->query('SELECT payment_status FROM shop_orders')->fetchColumn());
        $p->exec("UPDATE shop_orders SET status='cancelled'");\operationalCaseSave($p,1,'registrar',array_replace($input,['revision'=>1,'status'=>'closed']));
    }

    public function testManualContactEvidenceResolvesCaseWithoutPretendingEmailWasSent():void
    {
        $p=$this->database();$in=$this->input(['source_type'=>'notification','source_id'=>30,'owner_trainer_id'=>4]);
        \operationalCaseSave($p,4,'order_operator',$in);
        $this->rejected(fn()=>\operationalCaseSave($p,4,'order_operator',array_replace($in,['revision'=>1,'status'=>'closed'])));
        $p->exec("INSERT INTO club_event_notification_events VALUES(1,30,'manual_contact_resolved',1)");
        \operationalCaseSave($p,4,'order_operator',array_replace($in,['revision'=>1,'status'=>'closed']));
        self::assertSame('failed',$p->query('SELECT status FROM club_event_notifications')->fetchColumn());
        self::assertSame([],\operationalCaseList($p,'order_operator')['items']);
        $p->exec("INSERT INTO club_event_notification_events VALUES(2,30,'manual_retry',1)");
        self::assertCount(1,\operationalCaseList($p,'order_operator')['items']);
    }
}
