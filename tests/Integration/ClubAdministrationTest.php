<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__,2).'/includes/club_administration.php';
require_once dirname(__DIR__,2).'/includes/staff_agenda_lists.php';

final class ClubAdministrationTest extends TestCase
{
    private PDO $pdo;
    protected function setUp():void {
        $this->pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $this->pdo->exec("CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT,aktivni INT);INSERT INTO treneri VALUES(1,'Finance',1),(2,'Original coach',1);CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT);INSERT INTO sportovci VALUES(2,'Anna','Testová');CREATE TABLE staff_user_positions(trainer_id INT,position_code TEXT);INSERT INTO staff_user_positions VALUES(1,'finance_manager');CREATE TABLE staff_superadmins(trainer_id INT);");
        foreach(['20261022220000_accounting_ledger','20261023050000_club_expenses','20261023100000_club_payment_returns'] as $name){$m=require dirname(__DIR__,2).'/migrations/'.$name.'.php';$m['up']($this->pdo);}
        $_SESSION=['trener_id'=>1,'staff_positions'=>['finance_manager'],'staff_active_position'=>'finance_manager'];
    }
    protected function tearDown():void {$_SESSION=[];}
    private function input():array{return ['amount'=>'120,00','original_amount'=>'','payer_ref'=>'trainer:2','creditor_ref'=>'trainer:2','payer_label'=>'Forged name','creditor_label'=>'Forged name','supplier'=>'Synthetic supplier','document_number'=>'TEST-1','document_on'=>'2026-01-01','due_on'=>'2026-01-01','funding_source'=>'personal','payee_evidence'=>'Test handover','purpose'=>'Test purpose','document_evidence'=>'Test paper original','request_key'=>'test-original-key','lines'=>[['amount'=>'100','accepted'=>'1','activity'=>'klub','purpose'=>'Doprava'],['amount'=>'20','accepted'=>'0','activity'=>'osobní','purpose'=>'Soukromá část']]];}
    public function testNamedCounterpartiesDoNotAcceptForgedLabelsOrConfuseSameNumericIds():void {
        $v=clubAdminClaimInput($this->pdo,$this->input());self::assertSame('Original coach',$v['payer_label']);self::assertSame(12000,$v['amount_minor']);self::assertSame(2000,$v['items'][1]['amount_minor']);self::assertFalse($v['items'][1]['accepted']);
        $p=clubAdminPersonInput($this->pdo,['recipient_ref'=>'person:2','recipient_label'=>'Forged'],'recipient');self::assertSame('Testová Anna',$p['recipient_label']);self::assertSame('person',$p['recipient_kind']);
        $r=clubExpenseCreateClaim($this->pdo,$v,1,'Test verified immutable receipt',true);self::assertSame(10000,(int)$r['accepted_minor']);
    }
    public function testRenamedPersonKeepsHistoricCorrectionSnapshot():void {
        $v=clubAdminClaimInput($this->pdo,$this->input());$r=clubExpenseCreateClaim($this->pdo,$v,1,'Test recorded original claim',true);
        $this->pdo->exec("UPDATE treneri SET jmeno='Renamed coach' WHERE id=2");
        $new=clubAdminClaimInput($this->pdo,$this->input());self::assertSame('Renamed coach',$new['creditor_label']);
        $correction=clubAdminClaimInput($this->pdo,array_replace($this->input(),['previous_id'=>$r['id']]));self::assertSame('Original coach',$correction['creditor_label']);
        self::assertSame('Original coach',clubExpenseOne($this->pdo,'club_expense_claims',(int)$r['id'])['creditor_label']);
    }
    public function testRevokedFinanceRoleCannotResolveNewClaim():void {
        $this->pdo->exec('DELETE FROM staff_user_positions');$this->expectException(DomainException::class);clubAdminClaimInput($this->pdo,$this->input());
    }
    public function testMalformedItemDecisionIsNotSilentlyTreatedAsRejected():void {
        $v=$this->input();$v['lines'][0]['accepted']='yes';$this->expectException(ClubExpenseException::class);clubAdminClaimInput($this->pdo,$v);
    }
    public function testCaseDetailIncludesSplitOnlyClaimsAndTheirAuditHistory():void {
        $case=clubExpenseCreateCase($this->pdo,['title'=>'Test split event','leader_trainer_id'=>2,'due_on'=>'2026-01-01','evidence'=>'Test explicit case'],1,'Test verified new case',true);
        $v=$this->input();$v['lines'][0]['case_id']=$case['id'];$r=clubExpenseCreateClaim($this->pdo,clubAdminClaimInput($this->pdo,$v),1,'Test split-only evidence',true);
        $view=clubAdminCaseRead($this->pdo,(int)$case['id']);self::assertCount(1,$view['claims']);self::assertCount(1,$view['blockers']);self::assertContains('Test split-only evidence',array_column($view['history'],'reason'));self::assertSame((int)$r['id'],(int)$view['claims'][0]['id']);
    }
    public function testRewardSettlementFiltersUseApprovedAmountsAndRetainCoachBoundary():void {
        $this->pdo->exec("CREATE TABLE club_reward_claims(id INT,kind TEXT,subject_id INT,date_from TEXT,current_version_id INT,approved_version_id INT);CREATE TABLE club_reward_versions(id INT,amount_minor INT);CREATE TABLE club_reward_decisions(version_id INT,outcome TEXT);CREATE TABLE club_reward_payouts(claim_id INT,amount_minor INT);
        INSERT INTO club_reward_versions VALUES(1,10000),(2,20000),(3,10000),(4,99999),(5,5000);
        INSERT INTO club_reward_claims VALUES(1,'coach',2,'2026-01-01',1,1),(2,'coach',2,'2026-01-01',2,2),(3,'coach',2,'2026-01-01',3,3),(4,'coach',2,'2026-01-01',4,NULL),(5,'coach',1,'2026-01-01',5,5);
        INSERT INTO club_reward_payouts VALUES(1,4000),(2,25000),(3,10000),(5,1000);");
        $read=fn($state)=>staffRewardList($this->pdo,['reward_settlement'=>$state],'coach',2);
        self::assertSame([1],array_map('intval',array_column($read('due')['rows'],'id')));
        self::assertSame([2],array_map('intval',array_column($read('return')['rows'],'id')));
        self::assertSame([3],array_map('intval',array_column($read('settled')['rows'],'id')));
        self::assertSame(0,staffRewardList($this->pdo,['reward_id'=>5],'coach',2)['total']);
        self::assertSame(0,$read("' OR 1=1 --")['total']);
    }
}
