<?php
declare(strict_types=1);
namespace Tests\Integration;
use PDO;
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__,2).'/includes/accounting_ledger.php';

final class AccountingLedgerTest extends TestCase
{
    private function db():PDO
    {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,aktivni INT)');$pdo->exec('CREATE TABLE staff_user_positions(trainer_id INT,position_code TEXT)');$pdo->exec('CREATE TABLE staff_superadmins(trainer_id INT)');
        $pdo->exec("INSERT INTO treneri VALUES(7,1),(8,1);INSERT INTO staff_user_positions VALUES(7,'finance_manager'),(8,'coach')");
        $migration=require dirname(__DIR__,2).'/migrations/20261022220000_accounting_ledger.php';$migration['up']($pdo);self::assertTrue($migration['verify']($pdo));
        $pdo->exec('CREATE TABLE payments(id INTEGER PRIMARY KEY,payable_type TEXT,payable_id INT,status TEXT,amount_minor INT,currency TEXT,bank_movement_id TEXT,paid_at TEXT,refund_sent_at TEXT)');
        $pdo->exec('CREATE TABLE fio_account_movements(id INTEGER PRIMARY KEY,fio_movement_id TEXT,amount_minor INT,currency TEXT,booked_on TEXT)');
        $pdo->exec('CREATE TABLE club_finance_receipts(id INTEGER PRIMARY KEY,reference TEXT,amount_minor INT,currency TEXT,paid_on TEXT)');
        $pdo->exec('CREATE TABLE member_payer_receipts(id INTEGER PRIMARY KEY,reference TEXT,amount_minor INT,currency TEXT,paid_on TEXT)');
        $pdo->exec('CREATE TABLE club_finance_allocations(receipt_id INT,charge_id INT,order_id INT,amount_minor INT)');
        $pdo->exec('CREATE TABLE member_payer_receipt_allocations(receipt_id INT,charge_id INT,amount_minor INT)');
        $pdo->exec('CREATE TABLE shop_orders(id INT PRIMARY KEY,currency TEXT)');$pdo->exec("INSERT INTO shop_orders VALUES(10,'CZK'),(11,'CZK'),(20,'CZK'),(51,'CZK'),(52,'CZK'),(101,'CZK')");
        $pdo->exec('CREATE TABLE club_member_charges(id INT PRIMARY KEY,currency TEXT)');$pdo->exec("INSERT INTO club_member_charges VALUES(51,'CZK')");
        $pdo->exec('CREATE TABLE accounting_rules(id INTEGER PRIMARY KEY,category_code TEXT,activity TEXT,title TEXT,valid_from TEXT,approved_by INT,approval_evidence TEXT)');
        $pdo->exec("INSERT INTO accounting_rules VALUES(1,'CLOTHING','club','Oblečení','2026-01-01',7,'Approved TEST rule'),(2,'MEMBERS','club','Příspěvky','2026-01-01',7,'Approved TEST rule')");
        \accountingLedgerCreateAccount($pdo,['code'=>'TEST-BANK-BASE','name'=>'LOCALHOST TEST účet','kind'=>'bank','opened_on'=>'2026-01-01','opening_minor'=>50000,'evidence'=>'LOCALHOST TEST opening count'],7,'LOCALHOST test opening evidence.',true);
        return $pdo;
    }
    private function input(array $overrides=[]):array{return array_replace(['account_id'=>1,'kind'=>'income','amount_minor'=>10000,'currency'=>'CZK','effective_on'=>'2026-10-01','source_context'=>'test-cash','source_reference'=>'receipt-1','request_key'=>'movement-1','evidence'=>'LOCALHOST TEST paper receipt'],$overrides);}
    private function record(PDO $pdo,array $overrides=[]):array{return \accountingLedgerRecord($pdo,$this->input($overrides),7,'LOCALHOST confirmed movement.',true);}
    private function denied(callable $fn,string $contains=''):void{try{$fn();self::fail('Expected refusal');}catch(\AccountingLedgerException $e){if($contains!=='')self::assertStringContainsString($contains,$e->getMessage());else self::assertNotSame('',$e->getMessage());}}

    public function testExchangeAllocationCannotExceedDeltaAndAllowsAuditedRelease():void
    {
        $p=$this->db();$p->exec("CREATE TABLE shop_return_cases(id INT PRIMARY KEY,currency TEXT,state TEXT,exchange_delta_minor INT,exchange_reservation_id INT);INSERT INTO shop_return_cases VALUES(1,'CZK','open',2000,1)");$m=$this->record($p);
        $a=\accountingLedgerAllocate($p,(int)$m['id'],'shop_return',1,2000,'exchange-alloc',7,'LOCALHOST actual delta payment.',true,null,'','',date('Y-m-d'));
        $this->denied(fn()=>\accountingLedgerAllocate($p,(int)$m['id'],'shop_return',1,1,'exchange-over',7,'LOCALHOST excess delta payment.',true));
        $p->exec("UPDATE shop_return_cases SET state='cancelled'");
        \accountingLedgerAllocate($p,(int)$m['id'],'shop_return',1,-2000,'exchange-release',7,'LOCALHOST released mistaken payment.',true,(int)$a['id']);
        self::assertSame(0,(int)$p->query("SELECT SUM(amount_minor) FROM accounting_allocations WHERE case_type='shop_return'")->fetchColumn());
        $p->exec("UPDATE shop_return_cases SET state='open'");
        $expense=$this->record($p,['kind'=>'expense','amount_minor'=>-5000,'source_reference'=>'exchange-expense','request_key'=>'exchange-expense']);
        $this->denied(fn()=>\accountingLedgerAllocate($p,(int)$expense['id'],'shop_return',1,2000,'expense-cannot-fund',7,'LOCALHOST expense is not payment.',true),'kladným příjmem');
    }

    public function testStableIdentityAndConflictingRetryCannotDuplicateMoney():void
    {
        $pdo=$this->db();$first=$this->record($pdo);self::assertSame($first,$this->record($pdo));
        $this->denied(fn()=>$this->record($pdo,['amount_minor'=>10001]),'jinými údaji');
        $this->denied(fn()=>$this->record($pdo,['request_key'=>'other-key']),'jinými údaji');
        self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM accounting_movements')->fetchColumn());self::assertSame(60000,\accountingLedgerBalance($pdo,1,'2026-10-01')['closing_minor']);
    }
    public function testReversalCannotBypassAllocationProtectionOnOutgoingMovement():void
    {
        $p=$this->db();
        $m=$this->record($p,['kind'=>'expense','amount_minor'=>-10000]);
        $allocation=\accountingLedgerAllocate($p,(int)$m['id'],'shop_order',10,6000,'expense-part',7,'LOCALHOST assigned actual expenditure.',true,null,'','',date('Y-m-d'));
        foreach(['reversal','income'] as $kind){
            $this->denied(fn()=>$this->record($p,['kind'=>$kind,'amount_minor'=>5000,'reversal_of_id'=>(int)$m['id'],'source_reference'=>'blocked-'.$kind,'request_key'=>'blocked-'.$kind,'effective_on'=>date('Y-m-d')]),'uvolnění');
        }
        $this->record($p,['kind'=>'reversal','amount_minor'=>4000,'reversal_of_id'=>(int)$m['id'],'source_reference'=>'unassigned-return','request_key'=>'unassigned-return','effective_on'=>date('Y-m-d')]);
        $this->denied(fn()=>$this->record($p,['kind'=>'reversal','amount_minor'=>1,'reversal_of_id'=>(int)$m['id'],'source_reference'=>'extra-return','request_key'=>'extra-return','effective_on'=>date('Y-m-d')]),'uvolnění');
        \accountingLedgerAllocate($p,(int)$m['id'],'shop_order',10,-6000,'expense-release',7,'LOCALHOST release erroneous assignment.',true,(int)$allocation['id'],'','',date('Y-m-d'));
        $this->record($p,['kind'=>'reversal','amount_minor'=>6000,'reversal_of_id'=>(int)$m['id'],'source_reference'=>'released-return','request_key'=>'released-return','effective_on'=>date('Y-m-d')]);
        self::assertSame(0,(int)$p->query('SELECT SUM(amount_minor) FROM accounting_movements')->fetchColumn());
    }
    public function testRoleIsVerifiedAgainInsideMutationTransaction():void
    {
        $pdo=$this->db();$this->denied(fn()=>\accountingLedgerRecord($pdo,$this->input(),8,'LOCALHOST unauthorized actor.',true),'oprávnění');
        $pdo->exec('DELETE FROM staff_user_positions WHERE trainer_id=7');$this->denied(fn()=>$this->record($pdo),'oprávnění');
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM accounting_movements')->fetchColumn());
    }
    public function testSourceAliasesMergeFioPaymentAndReceiptExactlyOnce():void
    {
        $pdo=$this->db();$pdo->exec("INSERT INTO payments VALUES(11,'shop_order',101,'paid',10000,'CZK','BANK-TEST-1','2026-10-02',NULL);INSERT INTO fio_account_movements VALUES(1,'BANK-TEST-1',10000,'CZK','2026-10-01');INSERT INTO club_finance_receipts VALUES(5,'BANK-TEST-1',10000,'CZK','2026-10-01')");
        $pending=\accountingLedgerPendingSources($pdo);self::assertCount(1,$pending);self::assertSame('2026-10-01',$pending[0]['effective_on']);
        $a=\accountingLedgerReconcileSource($pdo,'payments',11,1,'2026-10-01','LOCALHOST bank statement',7,'LOCALHOST reconcile bank receipt.',true);
        $b=\accountingLedgerReconcileSource($pdo,'fio_account_movements',1,1,'2026-10-01','LOCALHOST second view',7,'LOCALHOST same imported receipt.',true);
        self::assertSame($a['id'],$b['id']);self::assertCount(0,\accountingLedgerPendingSources($pdo));self::assertSame(3,(int)$pdo->query('SELECT COUNT(*) FROM accounting_movement_sources')->fetchColumn());
    }
    public function testDerivedPaidStatesNeverCreateAnotherReceipt():void
    {
        $pdo=$this->db();$pdo->exec("INSERT INTO payments VALUES(1,'member_charge',51,'paid',6000,'CZK',NULL,'2026-10-01',NULL),(2,'shop_order',52,'paid',4000,'CZK',NULL,'2026-10-01',NULL);INSERT INTO member_payer_receipt_allocations VALUES(7,51,6000);INSERT INTO club_finance_allocations VALUES(7,NULL,52,4000);INSERT INTO member_payer_receipts VALUES(7,'BANK-FAMILY',10000,'CZK','2026-10-01')");
        $pending=\accountingLedgerPendingSources($pdo);self::assertCount(1,$pending);self::assertSame('member_payer_receipts',$pending[0]['source_type']);
        $this->denied(fn()=>\accountingLedgerSource($pdo,'payments',1),'Odvozená');$this->denied(fn()=>\accountingLedgerSource($pdo,'payments',2),'Odvozená');
    }
    public function testConfirmationTimestampDoesNotBecomeEffectiveDate():void
    {
        $pdo=$this->db();$pdo->exec("INSERT INTO payments VALUES(1,'shop_order',51,'paid',10000,'CZK',NULL,'2026-10-01 12:15:00',NULL)");
        $source=\accountingLedgerSource($pdo,'payments',1);self::assertNull($source['effective_on']);self::assertContains('unknown_date',$source['flags']);
        $row=\accountingLedgerReconcileSource($pdo,'payments',1,1,'2026-09-30','LOCALHOST dated cash receipt',7,'LOCALHOST actual receipt verified.',true);
        self::assertSame('2026-09-30',$row['effective_on']);self::assertSame(60000,\accountingLedgerBalance($pdo,1,'2026-09-30')['closing_minor']);
    }
    public function testConflictingSourceDateOrAmountRemainsException():void
    {
        $pdo=$this->db();$pdo->exec("INSERT INTO fio_account_movements VALUES(1,'BANK-CONFLICT',10000,'CZK','2026-10-01');INSERT INTO club_finance_receipts VALUES(7,'BANK-CONFLICT',9000,'CZK','2026-10-01')");
        $this->denied(fn()=>\accountingLedgerReconcileSource($pdo,'fio_account_movements',1,1,'2026-10-01','LOCALHOST conflict',7,'LOCALHOST conflicting sources.',true),'rozporné');
        self::assertContains('source_conflict',\accountingLedgerPendingSources($pdo)[0]['flags']);self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM accounting_movements')->fetchColumn());
    }
    public function testClaimAllocationReleaseAndReassignmentKeepAppendOnlyHistory():void
    {
        $pdo=$this->db();$m=$this->record($pdo);$a=\accountingLedgerAllocate($pdo,(int)$m['id'],'shop_order',10,8000,'alloc-001',7,'LOCALHOST claim allocation.',true,null,'CLOTH','CLUB','2026-10-02');
        $this->denied(fn()=>\accountingLedgerAllocate($pdo,1,'shop_order',11,3000,'alloc-over',7,'LOCALHOST excessive amount.',true),'přesahuje');
        \accountingLedgerAllocate($pdo,1,'shop_order',10,-3000,'release-001',7,'LOCALHOST release before move.',true,(int)$a['id'],'CLOTH','CLUB','2026-10-03');
        \accountingLedgerAllocate($pdo,1,'shop_order',11,5000,'alloc-002',7,'LOCALHOST move to valid claim.',true,null,'CLOTH','CLUB','2026-10-03');
        self::assertSame(10000,(int)$pdo->query('SELECT SUM(amount_minor) FROM accounting_allocations')->fetchColumn());self::assertSame(8000,(int)$pdo->query('SELECT amount_minor FROM accounting_allocations WHERE id=1')->fetchColumn());
        $this->denied(fn()=>\accountingLedgerAllocate($pdo,1,'shop_order',10,-6000,'release-over',7,'LOCALHOST excessive release.',true,1,'CLOTH','CLUB','2026-10-04'),'uvolnit více');
    }
    public function testRefundCannotConsumeAllocatedMoneyAndReleaseIsNotOutflow():void
    {
        $pdo=$this->db();$this->record($pdo);\accountingLedgerAllocate($pdo,1,'shop_order',10,10000,'alloc-001',7,'LOCALHOST fully allocated receipt.',true,null,'','','2026-10-02');
        $refund=['kind'=>'refund','amount_minor'=>-4000,'reversal_of_id'=>1,'source_reference'=>'refund-1','request_key'=>'refund-1','effective_on'=>'2026-10-03'];
        $this->denied(fn()=>$this->record($pdo,$refund),'uvolnění');
        \accountingLedgerAllocate($pdo,1,'shop_order',10,-4000,'release-001',7,'LOCALHOST customer refund release.',true,1,'','','2026-10-03');
        self::assertSame(60000,\accountingLedgerBalance($pdo,1,'2026-10-03')['closing_minor']);$this->record($pdo,$refund);
        self::assertSame(56000,\accountingLedgerBalance($pdo,1,'2026-10-03')['closing_minor']);
        $this->denied(fn()=>\accountingLedgerAllocate($pdo,1,'shop_order',20,1000,'steal-refund',7,'LOCALHOST allocated refunded cash.',true),'přesahuje');
    }
    public function testRefundBoundsOriginalAndPreservesReceipt():void
    {
        $pdo=$this->db();$this->record($pdo);$this->record($pdo,['kind'=>'refund','amount_minor'=>-7000,'reversal_of_id'=>1,'source_reference'=>'refund-1','request_key'=>'refund-1']);
        $this->denied(fn()=>$this->record($pdo,['kind'=>'refund','amount_minor'=>-4000,'reversal_of_id'=>1,'source_reference'=>'refund-2','request_key'=>'refund-2']),'přesahuje');
        self::assertSame(10000,(int)$pdo->query('SELECT amount_minor FROM accounting_movements WHERE id=1')->fetchColumn());
    }
    public function testCountDifferenceBlocksClosureAndClosedDateBlocksLateWrites():void
    {
        $pdo=$this->db();$this->record($pdo);
        $count=\accountingLedgerReconcileAccount($pdo,1,'2026-10-01',59000,'LOCALHOST physical count',false,7,'LOCALHOST discrepancy to resolve.',true);self::assertSame(-1000,$count['difference_minor']);
        $this->denied(fn()=>\accountingLedgerReconcileAccount($pdo,1,'2026-10-01',59000,'LOCALHOST count',true,7,'LOCALHOST cannot close difference.',true),'Rozdíl');
        \accountingLedgerReconcileAccount($pdo,1,'2026-10-01',60000,'LOCALHOST verified count',true,7,'LOCALHOST balanced cash close.',true);
        $this->denied(fn()=>$this->record($pdo,['request_key'=>'late','source_reference'=>'late']),'uzavřené');
        $this->denied(fn()=>\accountingLedgerClassify($pdo,1,[['rule_id'=>1,'amount_minor'=>10000]],'late-classify',7,'LOCALHOST late reclassification.',true),'uzavřené');
        $this->record($pdo,['request_key'=>'next','source_reference'=>'next','effective_on'=>'2026-10-02']);self::assertSame(70000,\accountingLedgerBalance($pdo,1,'2026-10-02')['closing_minor']);
    }
    public function testSettlementTransfersNetAndFeeWithoutSecondRevenue():void
    {
        $pdo=$this->db();\accountingLedgerCreateAccount($pdo,['code'=>'TEST-BANK','name'=>'TEST bank','kind'=>'bank','opened_on'=>'2026-01-01','opening_minor'=>0,'evidence'=>'LOCALHOST TEST opening'],7,'LOCALHOST second account.',true);
        $this->record($pdo);$result=\accountingLedgerTransfer($pdo,1,2,9800,200,'2026-10-02','SETTLEMENT-1','LOCALHOST settlement report',7,'LOCALHOST actual bank settlement.',true);
        self::assertSame(9800,(int)$result['in']['amount_minor']);self::assertSame(-200,(int)$result['fee']['amount_minor']);
        self::assertSame(50000,\accountingLedgerBalance($pdo,1,'2026-10-02')['closing_minor']);self::assertSame(9800,\accountingLedgerBalance($pdo,2,'2026-10-02')['closing_minor']);
        self::assertSame(10000,(int)$pdo->query("SELECT SUM(amount_minor) FROM accounting_movements WHERE kind='income'")->fetchColumn());
        $this->denied(fn()=>\accountingLedgerTransfer($pdo,1,2,9800,300,'2026-10-02','SETTLEMENT-1','LOCALHOST settlement report',7,'LOCALHOST changed fee under retry.',true),'jinými údaji');
        $pdo->exec("INSERT INTO fio_account_movements VALUES(1,'BANK-SETTLEMENT-1',9800,'CZK','2026-10-02')");
        \accountingLedgerLinkSource($pdo,(int)$result['in']['id'],'fio_account_movements',1,7,'LOCALHOST imported bank counterpart.',true);
        self::assertCount(0,\accountingLedgerPendingSources($pdo));self::assertSame(4,(int)$pdo->query('SELECT COUNT(*) FROM accounting_movements')->fetchColumn());
    }
    public function testTransferRollsBackBothSidesWhenDestinationClosed():void
    {
        $pdo=$this->db();\accountingLedgerCreateAccount($pdo,['code'=>'TEST-BANK','name'=>'TEST bank','kind'=>'bank','opened_on'=>'2026-01-01','opening_minor'=>0,'evidence'=>'LOCALHOST TEST opening'],7,'LOCALHOST second account.',true);
        \accountingLedgerReconcileAccount($pdo,2,'2026-10-02',0,'LOCALHOST closing',true,7,'LOCALHOST close destination.',true);
        $this->denied(fn()=>\accountingLedgerTransfer($pdo,1,2,10000,0,'2026-10-01','CLOSED','LOCALHOST transfer',7,'LOCALHOST transfer closed account.',true),'uzavřené');
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM accounting_movements')->fetchColumn());
    }
    public function testAccountingClassificationIsIndependentOfClaimAllocationsAndVersioned():void
    {
        $pdo=$this->db();$this->record($pdo);
        $v1=\accountingLedgerClassify($pdo,1,[['rule_id'=>1,'amount_minor'=>6000],['rule_id'=>2,'amount_minor'=>4000]],'class-001',7,'LOCALHOST approved mixed split.',true);
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM accounting_allocations')->fetchColumn());
        $v2=\accountingLedgerClassify($pdo,1,[['rule_id'=>2,'amount_minor'=>10000]],'class-002',7,'LOCALHOST approved corrected split.',true);
        self::assertSame((int)$v1['id'],(int)$v2['replaces_id']);self::assertCount(2,json_decode($v1['splits_json'],true));
        $this->denied(fn()=>\accountingLedgerClassify($pdo,1,[['rule_id'=>1,'amount_minor'=>9999]],'class-bad',7,'LOCALHOST rounding difference.',true),'na haléř');
        self::assertSame(2,(int)$pdo->query('SELECT COUNT(*) FROM accounting_classifications')->fetchColumn());
    }
    public function testLegacyRefundKeepsOriginalAndNeedsActualDate():void
    {
        $pdo=$this->db();$pdo->exec("INSERT INTO payments VALUES(1,'shop_order',51,'refunded',10000,'CZK',NULL,'2026-09-01','2026-10-01')");
        $pending=\accountingLedgerPendingSources($pdo);self::assertCount(2,$pending);self::assertSame(10000,$pending[0]['amount_minor']);self::assertSame(-10000,$pending[1]['amount_minor']);self::assertNull($pending[1]['effective_on']);
        $this->denied(fn()=>\accountingLedgerReconcileSource($pdo,'legacy_refund',1,1,'2026-10-01','LOCALHOST refunded receipt',7,'LOCALHOST refund missing original.',true),'původní příjem');
        \accountingLedgerReconcileSource($pdo,'payments',1,1,'2026-09-01','LOCALHOST initial receipt',7,'LOCALHOST proven original money.',true);
        $refund=\accountingLedgerReconcileSource($pdo,'legacy_refund',1,1,'2026-10-01','LOCALHOST actual bank refund',7,'LOCALHOST proven historical refund.',true);
        self::assertSame(1,(int)$refund['reversal_of_id']);self::assertSame(0,(int)$pdo->query('SELECT SUM(amount_minor) FROM accounting_allocations')->fetchColumn());self::assertSame(50000,\accountingLedgerBalance($pdo,1,'2026-10-01')['closing_minor']);self::assertCount(0,\accountingLedgerPendingSources($pdo));
    }
    public function testNoFloatFutureDateOrUnconfirmedMovementCanBeRecorded():void
    {
        $pdo=$this->db();$this->denied(fn()=>$this->record($pdo,['amount_minor'=>10.5]));$this->denied(fn()=>$this->record($pdo,['effective_on'=>'2026-02-31']));$this->denied(fn()=>$this->record($pdo,['effective_on'=>'2999-01-01']));
        $this->denied(fn()=>\accountingLedgerRecord($pdo,$this->input(),7,'LOCALHOST missing confirmation.',false));self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM accounting_movements')->fetchColumn());
    }
    public function testKnownAtCutoffExcludesLaterRecordedReceipt():void
    {
        $pdo=$this->db();$this->record($pdo);$pdo->exec("UPDATE accounting_accounts SET recorded_at='2026-01-01 00:00:00' WHERE id=1;UPDATE accounting_movements SET recorded_at='2026-10-03 12:00:00' WHERE id=1");
        self::assertSame(50000,\accountingLedgerBalance($pdo,1,'2026-10-01','2026-10-02 23:59:59')['closing_minor']);self::assertSame(60000,\accountingLedgerBalance($pdo,1,'2026-10-01','2026-10-03 12:00:00')['closing_minor']);
    }
    public function testCrossMonthTransferUsesActualDatesOnEachAccount():void
    {
        $pdo=$this->db();\accountingLedgerCreateAccount($pdo,['code'=>'TEST-BANK','name'=>'TEST bank','kind'=>'bank','opened_on'=>'2026-01-01','opening_minor'=>0,'evidence'=>'LOCALHOST TEST opening'],7,'LOCALHOST second account.',true);
        $this->record($pdo,['effective_on'=>'2026-09-30']);\accountingLedgerTransfer($pdo,1,2,9800,200,'2026-09-30','SEP-OCT','LOCALHOST settlement report',7,'LOCALHOST cross month settlement.',true,'2026-10-01');
        self::assertSame(50000,\accountingLedgerBalance($pdo,1,'2026-09-30')['closing_minor']);self::assertSame(0,\accountingLedgerBalance($pdo,2,'2026-09-30')['closing_minor']);self::assertSame(9800,\accountingLedgerBalance($pdo,2,'2026-10-01')['closing_minor']);
    }
    public function testCanonicalFamilyAllocationIsProjectedAndLaterChangesBecomePending():void
    {
        $pdo=$this->db();$pdo->exec("INSERT INTO member_payer_receipts VALUES(7,'BANK-FAMILY',10000,'CZK','2026-10-01');INSERT INTO member_payer_receipt_allocations VALUES(7,51,6000)");
        $this->denied(fn()=>\accountingLedgerReconcileSource($pdo,'member_payer_receipts',7,1,'2026-10-01','LOCALHOST bank report',7,'LOCALHOST unknown allocation date.',true),'Datum původního rozdělení');
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM accounting_movements')->fetchColumn());
        \accountingLedgerReconcileSource($pdo,'member_payer_receipts',7,1,'2026-10-01','LOCALHOST bank report',7,'LOCALHOST date separately verified.',true,'2026-10-02');
        self::assertSame(6000,(int)$pdo->query('SELECT SUM(amount_minor) FROM accounting_allocations')->fetchColumn());self::assertCount(0,\accountingLedgerPendingSources($pdo));
        $pdo->exec("INSERT INTO club_member_charges VALUES(52,'CZK');INSERT INTO member_payer_receipt_allocations VALUES(7,52,2000)");
        $pending=\accountingLedgerPendingSources($pdo);self::assertCount(1,$pending);self::assertContains('allocation_sync_required',$pending[0]['flags']);
        \accountingLedgerReconcileSource($pdo,'member_payer_receipts',7,1,'2026-10-01','LOCALHOST bank report',7,'LOCALHOST new allocation verified.',true,'2026-10-02');
        self::assertSame(8000,(int)$pdo->query('SELECT SUM(amount_minor) FROM accounting_allocations')->fetchColumn());self::assertCount(0,\accountingLedgerPendingSources($pdo));
    }
    public function testMissingClaimAndCurrencyMismatchAreRejected():void
    {
        $pdo=$this->db();$this->record($pdo);$this->denied(fn()=>\accountingLedgerAllocate($pdo,1,'shop_order',999,1000,'missing-target',7,'LOCALHOST nonexistent target.',true),'nebyl nalezen');
        $pdo->exec("UPDATE shop_orders SET currency='EUR' WHERE id=10");$this->denied(fn()=>\accountingLedgerAllocate($pdo,1,'shop_order',10,1000,'wrong-currency',7,'LOCALHOST mismatched currency.',true),'jinou měnu');
    }
}
