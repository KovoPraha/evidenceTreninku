<?php
declare(strict_types=1);
namespace Tests\Integration;
use PDO;
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__,2).'/includes/accounting_adjustments.php';
require_once dirname(__DIR__,2).'/includes/accounting_ledger.php';

require_once dirname(__DIR__,2).'/includes/shop_payment_presentation.php';

final class AccountingAdjustmentsTest extends TestCase
{
    private PDO $pdo;
    private int $income;
    protected function setUp():void
    {
        $this->pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('PRAGMA foreign_keys=ON');
        $this->pdo->exec("CREATE TABLE treneri(id INTEGER PRIMARY KEY,aktivni INT NOT NULL);INSERT INTO treneri VALUES(7,1),(8,1),(9,0);CREATE TABLE staff_user_positions(trainer_id INT,position_code TEXT);INSERT INTO staff_user_positions VALUES(7,'finance_manager'),(9,'finance_manager');CREATE TABLE staff_superadmins(trainer_id INT);CREATE TABLE payments(id INTEGER PRIMARY KEY,payable_type TEXT,payable_id INT,status TEXT,refund_sent_at TEXT);INSERT INTO payments VALUES(1,'shop_order',4,'paid',NULL)");
        $this->pdo->exec("CREATE TABLE shop_orders(id INTEGER PRIMARY KEY,status TEXT,payment_status TEXT,updated_at TEXT,currency TEXT);INSERT INTO shop_orders VALUES(4,'placed','paid',NULL,'CZK')");
        foreach (['20261022220000_accounting_ledger','20261022221000_accounting_documents','20261022222000_accounting_adjustments'] as $id) {
            $migration=require dirname(__DIR__,2).'/migrations/'.$id.'.php';$migration['up']($this->pdo);self::assertTrue($migration['verify']($this->pdo));
        }
        \accountingLedgerCreateAccount($this->pdo,['code'=>'TEST','name'=>'LOCALHOST NEPLATIT','kind'=>'bank','currency'=>'CZK','opened_on'=>'2026-01-01','opening_minor'=>0,'evidence'=>'Synthetic fixture'],7,'Synthetic account proof.',true);
        $movement=\accountingLedgerRecord($this->pdo,['account_id'=>1,'kind'=>'income','amount_minor'=>10000,'currency'=>'CZK','effective_on'=>'2026-01-10','source_context'=>'payment','source_reference'=>'1','request_key'=>'original-payment','case_type'=>'shop_order','case_id'=>4,'evidence'=>'Synthetic income'],7,'Synthetic income proof.',true);
        $this->income=(int)$movement['id'];
        $snapshot=json_encode(['lines'=>[['line_key'=>'item:10','quantity'=>2,'amount_minor'=>10000]]],JSON_THROW_ON_ERROR);
        $s=$this->pdo->prepare("INSERT INTO accounting_documents(kind,source_type,source_id,idempotency_key,status,snapshot_json,snapshot_sha256,total_minor,currency,effective_on,created_by) VALUES('invoice','order',4,'original-doc','issued',?,?,10000,'CZK','2026-01-10',7)");$s->execute([$snapshot,hash('sha256',$snapshot)]);
    }
    private function decide(int $amount=4000,string $key='decision-one',array $extra=[]):array
    {
        return \accountingRefundDecide($this->pdo,$extra+['original_movement_id'=>$this->income,'original_document_id'=>1,'amount_minor'=>$amount,'decided_on'=>'2026-01-11','request_key'=>$key],7,'Customer refund agreed.',true);
    }
    private function pay(int $id,int $amount=2000,string $key='payment-one',array $extra=[]):array
    {
        return \accountingRefundPay($this->pdo,$id,$extra+['account_id'=>1,'amount_minor'=>$amount,'effective_on'=>'2026-01-12','source_reference'=>$key,'evidence'=>'Synthetic bank proof','request_key'=>$key],7,'Verified actual repayment.',true);
    }
    private function reject(callable $fn,string $contains=''):void
    {
        try {$fn();self::fail('Expected rejection.');} catch(\DomainException|\AccountingLedgerException $e) {if ($contains!=='') self::assertStringContainsString($contains,$e->getMessage());else self::assertNotEmpty($e->getMessage());}
    }
    public function testDecisionReservesButDoesNotMoveMoneyOrChangeCanonicalStatus():void
    {
        $d=$this->decide();
        self::assertSame(4000,(int)$d['amount_minor']);self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM accounting_movements')->fetchColumn());
        self::assertSame('paid',$this->pdo->query('SELECT status FROM payments')->fetchColumn());
        self::assertSame(4000,\accountingAdjustmentBalance($this->pdo,$this->income)['reserved_minor']);
        self::assertSame(6000,\accountingAdjustmentBalance($this->pdo,$this->income)['available_minor']);
    }
    public function testTwoPartialRepaymentsPreserveOriginalAndAreIdempotent():void
    {
        $d=$this->decide();$p=$this->pay((int)$d['id']);$repeat=$this->pay((int)$d['id']);
        self::assertSame($p['id'],$repeat['id']);$this->pay((int)$d['id'],2000,'payment-two');
        $b=\accountingAdjustmentBalance($this->pdo,$this->income);self::assertSame(4000,$b['refunded_minor']);self::assertSame(0,$b['reserved_minor']);self::assertSame(6000,$b['available_minor']);
        self::assertSame(10000,(int)$this->pdo->query('SELECT amount_minor FROM accounting_movements WHERE id=1')->fetchColumn());
        self::assertSame(6000,\accountingLedgerBalance($this->pdo,1,'2026-01-12')['closing_minor']);
        self::assertSame(2,(int)$this->pdo->query('SELECT COUNT(*) FROM accounting_refund_payments')->fetchColumn());
        $this->reject(fn()=>$this->pay((int)$d['id'],1,'payment-third'),'překračuje');
    }
    public function testReplayCannotChangeDecisionOrPayoutPayload():void
    {
        $d=$this->decide();self::assertSame($d['id'],$this->decide()['id']);$this->reject(fn()=>$this->decide(4500),'jinému');
        $this->pay((int)$d['id']);$this->reject(fn()=>$this->pay((int)$d['id'],2001),'jiné');
    }
    public function testDecisionReservationPreventsAnotherDecisionAllocationAndUnlinkedRefund():void
    {
        $this->decide(8000);$this->reject(fn()=>$this->decide(3000,'decision-two'),'volný');
        $this->reject(fn()=>\accountingLedgerAllocate($this->pdo,$this->income,'shop_order',4,3000,'allocation-try',7,'Allocate synthetic income.',true));
        $this->reject(fn()=>\accountingLedgerRecord($this->pdo,['account_id'=>1,'kind'=>'refund','amount_minor'=>-3000,'currency'=>'CZK','effective_on'=>'2026-01-12','source_context'=>'manual-refund','source_reference'=>'without-decision','request_key'=>'without-decision','reversal_of_id'=>$this->income,'evidence'=>'Synthetic bank proof'],7,'Attempt to consume reservation.',true));
        self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM accounting_movements')->fetchColumn());
    }
    public function testAllocationReleaseIsSeparateFromMoneyAndCorrectionClaim():void
    {
        $allocation=\accountingLedgerAllocate($this->pdo,$this->income,'shop_order',4,10000,'allocation-one',7,'Allocate synthetic receipt.',true,null,'goods','club','2026-01-10');
        $this->reject(fn()=>$this->decide(),'uvolněte');
        $this->correction(4000,1);
        \accountingLedgerAllocate($this->pdo,$this->income,'shop_order',4,-4000,'release-first',7,'Release returned item allocation.',true,(int)$allocation['id'],'goods','club','2026-01-11');
        self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM accounting_movements')->fetchColumn());
        $d=$this->decide(4000,'decision-one',['correction_document_id'=>2]);$this->pay((int)$d['id'],4000);
        $b=\accountingAdjustmentDocumentBalance($this->pdo,1);self::assertSame(6000,$b['claim_minor']);self::assertSame(6000,$b['allocated_minor']);self::assertSame(0,$b['outstanding_minor']);
        self::assertSame(0,\accountingAdjustmentBalance($this->pdo,$this->income)['available_minor']);
        $this->reject(fn()=>\accountingLedgerAllocate($this->pdo,$this->income,'shop_order',4,-7000,'release-too-much',7,'Invalid duplicate release.',true,(int)$allocation['id'],'goods','club','2026-01-11'));
    }
    public function testCancellationIsAuditedAndCannotErasePaidRefund():void
    {
        $d=$this->decide();\accountingRefundCancel($this->pdo,(int)$d['id'],7,'Customer withdrew request.',true,'cancel-decision');
        self::assertSame(10000,\accountingAdjustmentBalance($this->pdo,$this->income)['available_minor']);$this->reject(fn()=>$this->pay((int)$d['id']),'Zrušené');
        $d2=$this->decide(4000,'decision-two');$this->pay((int)$d2['id']);$this->reject(fn()=>\accountingRefundCancel($this->pdo,(int)$d2['id'],7,'Try cancel paid refund.',true,'cancel-second'),'vyplácenou');
        self::assertSame(2,(int)$this->pdo->query('SELECT COUNT(*) FROM accounting_refund_decisions')->fetchColumn());
    }
    public function testRevokedRoleInactiveActorMissingConfirmationAndDatesFailClosed():void
    {
        $input=['original_movement_id'=>$this->income,'amount_minor'=>1000,'decided_on'=>'2026-01-11','request_key'=>'bad-decision'];
        foreach([8,9] as $actor) $this->reject(fn()=>\accountingRefundDecide($this->pdo,$input,$actor,'Synthetic refund reason.',true),'oprávnění');
        $this->reject(fn()=>\accountingRefundDecide($this->pdo,$input,7,'Synthetic refund reason.',false),'Potvrďte');
        $this->reject(fn()=>$this->decide(1000,'bad-dating',['decided_on'=>'2025-01-01']),'předcházet');
        $this->reject(fn()=>$this->decide(1000,'bad-future',['decided_on'=>'2999-01-01']),'budoucnu');
        $this->pdo->exec("DELETE FROM staff_user_positions WHERE trainer_id=7");$this->reject(fn()=>$this->decide(),'oprávnění');
        self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM accounting_refund_decisions')->fetchColumn());
    }
    public function testClosedAccountCannotGainBackdatedRefund():void
    {
        $d=$this->decide();\accountingLedgerReconcileAccount($this->pdo,1,'2026-01-12',10000,'Closing proof.',true,7,'Closing synthetic account.',true);
        $this->reject(fn()=>$this->pay((int)$d['id']),'uzavřené');
        self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM accounting_refund_payments')->fetchColumn());
        $this->pay((int)$d['id'],2000,'later-refund',['effective_on'=>'2026-01-13']);
        self::assertSame(8000,\accountingLedgerBalance($this->pdo,1,'2026-01-13')['closing_minor']);
    }
    public function testBothLegacyAndNewRefundPathsCannotDuplicateRepayment():void
    {
        $this->pdo->exec("UPDATE payments SET status='refunded',refund_sent_at='2026-01-11 10:00:00'");$this->reject(fn()=>$this->decide(),'celou vratku');
        $this->pdo->exec("UPDATE payments SET status='paid',refund_sent_at=NULL");$this->decide();
        $this->pdo->beginTransaction();$this->reject(fn()=>\accountingAdjustmentAssertLegacyRefundAllowed($this->pdo,4),'rozpracovanou');$this->pdo->rollBack();
    }
    private function receiptLinkedOnlyByAllocation():void
    {
        $this->pdo->exec("UPDATE accounting_movements SET case_type=NULL,case_id=NULL,source_context='bank-reference',source_reference='LOCALHOST-SPLIT' WHERE id=".$this->income);
        $allocation=\accountingLedgerAllocate($this->pdo,$this->income,'shop_order',4,10000,'split-allocation',7,'LOCALHOST receipt allocation.',true,null,'','','2026-01-10');
        \accountingLedgerAllocate($this->pdo,$this->income,'shop_order',4,-4000,'split-release',7,'LOCALHOST refund release.',true,(int)$allocation['id'],'','','2026-01-11');
    }
    public function testReceiptAllocationBlocksLegacyRefundAfterAccountingReservationAndPayout():void
    {
        $this->receiptLinkedOnlyByAllocation();$decision=$this->decide();
        $this->pdo->beginTransaction();
        try {$this->reject(fn()=>\accountingAdjustmentAssertLegacyRefundAllowed($this->pdo,4),'rozpracovanou');}
        finally {$this->pdo->rollBack();}
        $this->pay((int)$decision['id'],4000);
        $this->pdo->beginTransaction();
        try {$this->reject(fn()=>\accountingAdjustmentAssertLegacyRefundAllowed($this->pdo,4),'rozpracovanou');}
        finally {$this->pdo->rollBack();}
    }
    public function testReceiptAllocationBlocksAccountingRefundAfterLegacyFullRefund():void
    {
        $this->receiptLinkedOnlyByAllocation();
        $this->pdo->exec("UPDATE payments SET status='refunded',refund_sent_at='2026-01-11 12:00:00'");
        $this->reject(fn()=>$this->decide(),'celou vratku');
        self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM accounting_refund_decisions')->fetchColumn());
    }
    public function testReceiptAllocationBlocksDirectLedgerRefundAfterLegacyFullRefund():void
    {
        $this->receiptLinkedOnlyByAllocation();
        $this->pdo->exec("UPDATE payments SET status='refunded',refund_sent_at='2026-01-11 12:00:00'");
        $this->reject(fn()=>\accountingLedgerRecord($this->pdo,['account_id'=>1,'kind'=>'refund','amount_minor'=>-4000,'currency'=>'CZK','effective_on'=>'2026-01-12','source_context'=>'manual-refund','source_reference'=>'LOCALHOST-DOUBLE','request_key'=>'split-double-refund','reversal_of_id'=>$this->income,'evidence'=>'LOCALHOST synthetic bank proof'],7,'LOCALHOST duplicate receipt refund.',true),'celou vratku');
        self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM accounting_movements')->fetchColumn());
    }

    public function testDocumentLinkAndCumulativeCorrectionRefundLimit():void
    {
        $this->correction(4000,1);$this->decide(3000,'decision-one',['correction_document_id'=>2]);
        $this->reject(fn()=>$this->decide(2000,'decision-two',['correction_document_id'=>2]),'opravného');
        $this->reject(fn()=>$this->decide(1000,'wrong-original',['original_document_id'=>2]),'původní');
        $this->pdo->exec('UPDATE accounting_documents SET source_id=88 WHERE id=1');$this->reject(fn()=>$this->decide(1000,'wrong-link'),'navázán');
    }
    public function testPhysicalReturnHistoryDoesNotMoveMoneyOrStockAndIsIdempotent():void
    {
        $this->correction(4000,1);
        $id=\accountingReturnRecord($this->pdo,2,'item:10',1,'awaiting_return',7,'Waiting for returned shirt.',true,'return-first');
        self::assertSame($id,\accountingReturnRecord($this->pdo,2,'item:10',1,'awaiting_return',7,'Waiting for returned shirt.',true,'return-first'));
        \accountingReturnRecord($this->pdo,2,'item:10',1,'received_damaged',7,'Returned shirt has damage.',true,'return-damaged');
        $this->reject(fn()=>\accountingReturnRecord($this->pdo,2,'item:10',2,'received_inspection',7,'Over quantity invalid.',true,'return-invalid'),'Množství');
        self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM accounting_movements')->fetchColumn());self::assertSame(2,(int)$this->pdo->query('SELECT COUNT(*) FROM accounting_adjustment_events')->fetchColumn());
    }
    public function testFinalInstallmentCompletesCanonicalCancelledOrderWithoutSecondMoney():void
    {
        foreach(['amount_minor INT DEFAULT 10000',"currency TEXT DEFAULT 'CZK'","method TEXT DEFAULT 'bank_transfer'",'refund_reference TEXT','refund_confirmed_by_trainer_id INT','refund_confirmation_note TEXT','updated_at TEXT'] as $definition) $this->pdo->exec('ALTER TABLE payments ADD COLUMN '.$definition);
        $this->pdo->exec("UPDATE shop_orders SET status='cancelled',payment_status='refund_required';CREATE TABLE shop_order_events(id INTEGER PRIMARY KEY,order_id INT,actor_type TEXT,actor_id INT,action TEXT,from_status TEXT,to_status TEXT,note TEXT);UPDATE payments SET status='refund_required'");
        $d=$this->decide(10000);$this->pay((int)$d['id'],4000,'partial-one');
        self::assertSame('refund_required',$this->pdo->query('SELECT status FROM payments')->fetchColumn());
        $last=$this->pay((int)$d['id'],6000,'partial-two');
        self::assertSame('refunded',$this->pdo->query('SELECT status FROM payments')->fetchColumn());
        self::assertSame('refunded',$this->pdo->query('SELECT payment_status FROM shop_orders')->fetchColumn());
        self::assertSame('2026-01-12 00:00:00',$this->pdo->query('SELECT refund_sent_at FROM payments')->fetchColumn());
        self::assertSame('accounting-ledger:1',$this->pdo->query('SELECT refund_reference FROM payments')->fetchColumn());
        self::assertTrue(\accountingLedgerRefundDerived($this->pdo,$this->pdo->query('SELECT * FROM payments')->fetch(PDO::FETCH_ASSOC)));
        $this->reject(fn()=>\accountingLedgerSource($this->pdo,'legacy_refund',1));
        self::assertSame($last['id'],$this->pay((int)$d['id'],6000,'partial-two')['id']);
        $legacy=\shopOrderAdminConfirmRefund($this->pdo,4,7,'another-reference','Repeated complete confirmation.',true);
        self::assertFalse($legacy['changed']);
        self::assertSame(3,(int)$this->pdo->query('SELECT COUNT(*) FROM accounting_movements')->fetchColumn());
        self::assertSame(1,(int)$this->pdo->query("SELECT COUNT(*) FROM shop_order_events WHERE action='confirm_refund'")->fetchColumn());
    }
    public function testMultipleReceiptsCompleteRefundOnlyWhenOrderIsFullyRepaidExcludingOverpayment():void
    {
        foreach(['amount_minor INT DEFAULT 10000',"currency TEXT DEFAULT 'CZK'","method TEXT DEFAULT 'bank_transfer'",'refund_reference TEXT','refund_confirmed_by_trainer_id INT','refund_confirmation_note TEXT','updated_at TEXT'] as $definition)$this->pdo->exec('ALTER TABLE payments ADD COLUMN '.$definition);
        $this->pdo->exec("UPDATE shop_orders SET status='cancelled',payment_status='refund_required';UPDATE payments SET status='refund_required';CREATE TABLE shop_order_events(id INTEGER PRIMARY KEY,order_id INT,actor_type TEXT,actor_id INT,action TEXT,from_status TEXT,to_status TEXT,note TEXT);UPDATE accounting_movements SET case_type=NULL,case_id=NULL,amount_minor=4500 WHERE id=1");
        $second=\accountingLedgerRecord($this->pdo,['account_id'=>1,'kind'=>'income','amount_minor'=>6000,'currency'=>'CZK','effective_on'=>'2026-01-10','source_context'=>'bank-reference','source_reference'=>'LOCALHOST-SECOND','request_key'=>'family-second-income','evidence'=>'LOCALHOST receipt'],7,'LOCALHOST second receipt.',true);
        foreach([[$this->income,4000],[(int)$second['id'],6000]] as [$movement,$amount]){
            $a=\accountingLedgerAllocate($this->pdo,$movement,'shop_order',4,$amount,'family-allocate-'.$movement,7,'LOCALHOST allocation.',true,null,'','','2026-01-10');
            \accountingLedgerAllocate($this->pdo,$movement,'shop_order',4,-$amount,'family-release-'.$movement,7,'LOCALHOST release.',true,(int)$a['id'],'','','2026-01-11');
        }
        $overpayment=$this->decide(500,'overpayment-refund',['original_document_id'=>0]);$this->pay((int)$overpayment['id'],500,'overpayment-paid');
        $first=$this->decide(4000,'family-refund-first');$this->pay((int)$first['id'],4000,'family-paid-first');
        self::assertSame(4000,\accountingLedgerOrderRefundCoverage($this->pdo,4,'CZK')['total']);
        self::assertSame('refund_required',$this->pdo->query('SELECT status FROM payments')->fetchColumn());
        $last=$this->decide(6000,'family-refund-second',['original_movement_id'=>(int)$second['id']]);$paid=$this->pay((int)$last['id'],6000,'family-paid-second');
        $payment=$this->pdo->query('SELECT * FROM payments')->fetch(PDO::FETCH_ASSOC);
        self::assertSame('refunded',$payment['status']);self::assertSame('accounting-order:4',$payment['refund_reference']);
        self::assertTrue(\accountingLedgerRefundDerived($this->pdo,$payment));
        $this->reject(fn()=>\accountingLedgerSource($this->pdo,'legacy_refund',1),'odvozené');
        self::assertSame($paid['id'],$this->pay((int)$last['id'],6000,'family-paid-second')['id']);
        self::assertSame(5,(int)$this->pdo->query('SELECT COUNT(*) FROM accounting_movements')->fetchColumn());
        self::assertSame(1,(int)$this->pdo->query("SELECT COUNT(*) FROM shop_order_events WHERE action='confirm_refund'")->fetchColumn());
    }

    public function testCustomerViewShowsOnlyPaidRefundsAndPreservesOperationalState(): void
    {
        $order=['id'=>4,'payment_record_status'=>'paid','total_minor'=>10000,'currency'=>'CZK','refund_sent_at'=>null];
        $decision=$this->decide(10000);
        self::assertSame('paid',\shopOrderPaymentPresentation($this->pdo,$order)['status']);
        $this->pay((int)$decision['id'],4000,'view-partial');
        $view=\shopOrderPaymentPresentation($this->pdo,$order);
        self::assertSame('partially_refunded',$view['status']);self::assertSame(4000,$view['refunded_minor']);
        self::assertSame('paid',\shopOrderPaymentPresentation($this->pdo,array_replace($order,['id'=>99]))['status']);
        self::assertSame('paid',\shopOrderPaymentPresentation($this->pdo,array_replace($order,['currency'=>'EUR']))['status']);
        $this->pay((int)$decision['id'],6000,'view-complete');
        $before=$this->pdo->query('SELECT * FROM payments')->fetchAll(PDO::FETCH_ASSOC);
        $view=\shopOrderPaymentPresentation($this->pdo,$order);
        self::assertSame('refunded',$view['status']);self::assertSame(10000,$view['refunded_minor']);self::assertSame('2026-01-12',$view['refunded_on']);
        self::assertSame($before,$this->pdo->query('SELECT * FROM payments')->fetchAll(PDO::FETCH_ASSOC));
        self::assertSame('paid',$before[0]['status']);
    }

    private function correction(int $amount,int $quantity):void
    {
        $snapshot=json_encode(['lines'=>[['line_key'=>'item:10','quantity'=>$quantity,'amount_minor'=>-$amount]]],JSON_THROW_ON_ERROR);
        $s=$this->pdo->prepare("INSERT INTO accounting_documents(kind,source_type,source_id,original_document_id,idempotency_key,status,snapshot_json,snapshot_sha256,total_minor,currency,effective_on,created_by) VALUES('correction','order',4,1,'correction-doc','issued',?,?,?,'CZK','2026-01-11',7)");$s->execute([$snapshot,hash('sha256',$snapshot),-$amount]);
    }
}
