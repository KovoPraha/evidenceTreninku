<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/includes/member_payer_receipt.php';
require_once dirname(__DIR__,2).'/includes/fio_readonly_import.php';

final class MemberPayerReceiptTest extends TestCase
{
    public function testFioReceiptSplitsAcrossChildrenAndRemainingCanBeAllocatedLater(): void
    {
        $pdo=$this->database();
        $this->charges($pdo);
        $result=\memberPayerReceiptCreate($pdo,10,1,'FIO-FAMILY-1',100000,'2026-10-01',[1=>60000,2=>20000],7,'Ověřeno podle bankovního výpisu.',true);
        self::assertSame(['receipt_id'=>1,'allocated_minor'=>80000,'remaining_minor'=>20000],$result);
        self::assertSame('confirmed',$pdo->query('SELECT match_status FROM fio_account_movements WHERE id=1')->fetchColumn());
        self::assertSame('paid',$pdo->query('SELECT status FROM club_member_charges WHERE id=1')->fetchColumn());
        self::assertSame('pending',$pdo->query('SELECT status FROM club_member_charges WHERE id=2')->fetchColumn());
        self::assertSame(20000,(int)$pdo->query('SELECT SUM(amount_minor) FROM member_payer_receipt_allocations WHERE charge_id=2')->fetchColumn());
        self::assertSame(20000,\clubFinanceBalanceForAthlete($pdo,2)['open_minor']);
        $later=\memberPayerReceiptAllocateRemaining($pdo,1,[2=>20000],7,'Doplněno po kontrole druhého předpisu.',true);
        self::assertSame(0,$later['remaining_minor']);
        self::assertSame($later,\memberPayerReceiptAllocateRemaining($pdo,1,[2=>20000],7,'Doplněno po kontrole druhého předpisu.',true));
        self::assertSame('paid',$pdo->query('SELECT status FROM payments WHERE payable_id=2')->fetchColumn());
        self::assertSame(2,(int)$pdo->query('SELECT COUNT(*) FROM member_payer_receipt_events')->fetchColumn());
        self::assertSame(3,(int)$pdo->query('SELECT COUNT(*) FROM club_member_charge_events')->fetchColumn());
        try { \fioAdminConfirmExactMovement($pdo,1,7,'Pokus o dvojí potvrzení.',true);self::fail('Expected refusal.'); }
        catch (\FioImportException $e) {self::assertStringContainsString('rodinné platbě',$e->getMessage());}
        $this->expectException(\ClubFinanceException::class);
        \memberPayerReceiptCreate($pdo,10,1,'FIO-FAMILY-1',100000,'2026-10-01',[2=>10000],7,'Opakovaná bankovní platba.',true);
    }

    public function testOverAllocationAndWrongPayerRollbackEntireReceipt(): void
    {
        $pdo=$this->database();$this->charges($pdo);
        try { \memberPayerReceiptCreate($pdo,10,1,'FIO-FAMILY-1',100000,'2026-10-01',[1=>60000,2=>50000],7,'Nepřípustně vysoké přidělení.',true);self::fail('Expected refusal.'); }
        catch (\MemberPayerReceiptException $e) {self::assertStringContainsString('přesahuje',$e->getMessage());}
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM member_payer_receipts')->fetchColumn());
        try { \memberPayerReceiptCreate($pdo,10,1,'FIO-FAMILY-1',100000,'2026-10-01',[3=>10000],7,'Pokus o cizí předpis.',true);self::fail('Expected refusal.'); }
        catch (\MemberPayerReceiptException $e) {self::assertStringContainsString('tohoto plátce',$e->getMessage());}
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM member_payer_receipts')->fetchColumn());
        self::assertSame('review_unknown_vs',$pdo->query('SELECT match_status FROM fio_account_movements WHERE id=1')->fetchColumn());
    }

    public function testStandingPayerSymbolAlwaysNeedsReviewAndBankReferenceIsExclusive(): void
    {
        $pdo=$this->database();$this->charges($pdo);
        $proposal=\fioProposePaymentMatch($pdo,100000,'CZK','9000000010');
        self::assertSame('review_payer_split',$proposal['status']);
        $pdo->exec("INSERT INTO club_finance_receipts(sportovec_id,reference,amount_minor,currency,paid_on) VALUES(1,'FIO-FAMILY-1',100000,'CZK','2026-10-01')");
        $this->expectException(\ClubFinanceException::class);
        \memberPayerReceiptCreate($pdo,10,1,'FIO-FAMILY-1',100000,'2026-10-01',[1=>60000],7,'Bankovní reference již použita.',true);
    }

    public function testExistingAthleteCreditAndFamilyAllocationShareTheChargeLimit(): void
    {
        $pdo=$this->database();$this->charges($pdo);
        $pdo->exec("INSERT INTO club_finance_receipts(sportovec_id,reference,amount_minor,currency,paid_on) VALUES(2,'OLDER-CHILD-RECEIPT',20000,'CZK','2026-09-01')");
        $pdo->exec('INSERT INTO club_finance_allocations(receipt_id,charge_id,amount_minor) VALUES(1,2,20000)');
        $result=\memberPayerReceiptCreate($pdo,10,1,'FIO-FAMILY-1',100000,'2026-10-01',[1=>60000,2=>20000],7,'Rozdělení s dřívější dětskou úhradou.',true);
        self::assertSame(20000,$result['remaining_minor']);
        self::assertSame(40000,\clubFinanceChargeAllocatedMinor($pdo,2));
        self::assertSame('paid',$pdo->query('SELECT status FROM club_member_charges WHERE id=2')->fetchColumn());
        self::assertSame(0,\clubFinanceBalanceForAthlete($pdo,2)['open_minor']);
    }

    public function testFioMovementMustBelongToSelectedPayer(): void
    {
        $pdo=$this->database();$this->charges($pdo);
        $pdo->exec("UPDATE fio_account_movements SET variable_symbol='9000000020' WHERE id=1");
        $this->expectException(\MemberPayerReceiptException::class);
        \memberPayerReceiptCreate($pdo,10,1,'FIO-FAMILY-1',100000,'2026-10-01',[1=>60000],7,'Pohyb s cizím variabilním symbolem.',true);
    }

    public function testManualBankReferenceRemainsUniqueAcrossReceiptTypes(): void
    {
        $pdo=$this->database();$this->charges($pdo);
        $result=\memberPayerReceiptCreate($pdo,10,null,'BANK-OUTSIDE-FIO',30000,'2026-10-02',[1=>30000],7,'Ručně ověřený starší bankovní výpis.',true);
        self::assertSame(0,$result['remaining_minor']);
        self::assertSame(30000,\clubFinanceChargeAllocatedMinor($pdo,1));
        $this->expectException(\ClubFinanceException::class);
        \clubFinanceAssertBankReferenceUnused($pdo,'BANK-OUTSIDE-FIO',30000,'2026-10-02');
    }

    public function testAllocationRequestKeyIsIdempotentButNewRequestCanUseSameAmount(): void
    {
        $pdo=$this->database();$this->charges($pdo);
        \memberPayerReceiptCreate($pdo,10,null,'BANK-KEY-TEST',40000,'2026-10-02',[2=>10000],7,'První část bankovní platby.',true);
        $first=\memberPayerReceiptAllocateRemaining($pdo,1,[2=>10000],7,'Doplnění stejné částky.',true,str_repeat('a',32));
        self::assertSame($first,\memberPayerReceiptAllocateRemaining($pdo,1,[2=>10000],7,'Doplnění stejné částky.',true,str_repeat('a',32)));
        $second=\memberPayerReceiptAllocateRemaining($pdo,1,[2=>10000],7,'Doplnění stejné částky.',true,str_repeat('b',32));
        self::assertSame(10000,$second['remaining_minor']);
        self::assertSame(30000,\clubFinanceChargeAllocatedMinor($pdo,2));
        self::assertSame(3,(int)$pdo->query('SELECT COUNT(*) FROM member_payer_receipt_allocations')->fetchColumn());
    }

    private function charges(PDO $pdo): void
    {
        $pdo->exec("INSERT INTO club_member_charges(id,sportovec_id,payer_account_id,public_code,title_snapshot,amount_minor,currency,due_on,status) VALUES(1,1,10,'A','Příspěvek Anna',60000,'CZK','2026-10-15','pending'),(2,2,10,'B','Příspěvek Bára',40000,'CZK','2026-10-15','pending'),(3,3,20,'C','Jiný plátce',10000,'CZK','2026-10-15','pending')");
        $pdo->exec("INSERT INTO payments(payable_type,payable_id,method,status,amount_minor,currency,variable_symbol) VALUES('member_charge',1,'bank_transfer','pending',60000,'CZK','9000000010'),('member_charge',2,'bank_transfer','pending',40000,'CZK','9000000010'),('member_charge',3,'bank_transfer','pending',10000,'CZK','9000000020')");
    }

    private function database(): PDO
    {
        $pdo=new PDO('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
        $pdo->exec("CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,aktivni INTEGER,email_overeno INTEGER);CREATE TABLE treneri(id INTEGER PRIMARY KEY);CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT);CREATE TABLE club_member_charges(id INTEGER PRIMARY KEY,sportovec_id INTEGER,payer_account_id INTEGER,public_code TEXT,title_snapshot TEXT,amount_minor INTEGER,currency TEXT,due_on TEXT,status TEXT,updated_at TEXT DEFAULT CURRENT_TIMESTAMP);CREATE TABLE shop_orders(id INTEGER PRIMARY KEY,account_id INTEGER,public_code TEXT,total_minor INTEGER,currency TEXT,status TEXT,payment_status TEXT,payment_expires_at TEXT,finance_sportovec_id INTEGER);CREATE TABLE payments(id INTEGER PRIMARY KEY AUTOINCREMENT,payable_type TEXT,payable_id INTEGER,method TEXT,status TEXT,amount_minor INTEGER,currency TEXT,variable_symbol TEXT,bank_movement_id TEXT,payment_source TEXT,paid_at TEXT,due_at TEXT,confirmed_by_trainer_id INTEGER,confirmation_note TEXT,updated_at TEXT DEFAULT CURRENT_TIMESTAMP);CREATE TABLE club_member_charge_events(id INTEGER PRIMARY KEY AUTOINCREMENT,charge_id INTEGER,action TEXT,from_status TEXT,to_status TEXT,actor_type TEXT,actor_id INTEGER,reason TEXT,snapshot_json TEXT);CREATE TABLE club_finance_receipts(id INTEGER PRIMARY KEY AUTOINCREMENT,sportovec_id INTEGER,reference TEXT UNIQUE,amount_minor INTEGER,currency TEXT,paid_on TEXT);CREATE TABLE club_finance_allocations(id INTEGER PRIMARY KEY AUTOINCREMENT,receipt_id INTEGER,charge_id INTEGER,order_id INTEGER,amount_minor INTEGER);CREATE TABLE fio_account_movements(id INTEGER PRIMARY KEY,fio_movement_id TEXT UNIQUE,booked_on TEXT,amount_minor INTEGER,currency TEXT,variable_symbol TEXT,match_status TEXT,candidate_payment_id INTEGER,candidate_order_id INTEGER,match_reason TEXT,confirmed_at TEXT,confirmed_by_trainer_id INTEGER,confirmation_note TEXT)");
        $pdo->exec("INSERT INTO verejni_uzivatele VALUES(10,1,1),(20,1,1);INSERT INTO treneri VALUES(7);INSERT INTO sportovci VALUES(1,'Anna','První'),(2,'Bára','První'),(3,'Cizí','Dítě');INSERT INTO fio_account_movements(id,fio_movement_id,booked_on,amount_minor,currency,variable_symbol,match_status) VALUES(1,'FIO-FAMILY-1','2026-10-01',100000,'CZK','9000000010','review_unknown_vs')");
        $pdo->exec("CREATE TABLE member_payer_variable_symbols(payer_account_id INTEGER PRIMARY KEY,variable_symbol TEXT UNIQUE);INSERT INTO member_payer_variable_symbols VALUES(10,'9000000010'),(20,'9000000020');CREATE TABLE club_finance_settings(id INTEGER PRIMARY KEY,enforcement_enabled INTEGER,grace_days INTEGER,registration_limit_minor INTEGER,training_notice_minor INTEGER);INSERT INTO club_finance_settings VALUES(1,0,14,200000,500000)");
        $migration=require dirname(__DIR__,2).'/migrations/20261021130000_member_payer_receipts.php';$migration['up']($pdo);$migration['up']($pdo);self::assertTrue($migration['verify']($pdo));
        return $pdo;
    }
}
