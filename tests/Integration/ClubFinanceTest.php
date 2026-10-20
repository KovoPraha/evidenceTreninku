<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/includes/member_fee_annual.php';

final class ClubFinanceTest extends TestCase
{
    public function testGraceAndAccountAttributionExcludeCancelledAndExpiredOrders():void
    {
        $pdo=$this->database();
        $pdo->exec("INSERT INTO club_member_charges(sportovec_id,payer_account_id,public_code,charge_type,title_snapshot,amount_minor,currency,due_on,status,source_system,source_external_id) VALUES(1,10,'MC1','membership','Příspěvek',200100,'CZK','2026-10-01','pending','manual','MC1')");
        $pdo->exec("INSERT INTO shop_orders(account_id,public_code,total_minor,currency,status,payment_status,payment_expires_at,finance_sportovec_id) VALUES(10,'O1',50000,'CZK','placed','pending','2026-12-01 23:59:59',1),(10,'O2',90000,'CZK','cancelled','cancelled','2026-12-01 23:59:59',1),(10,'O3',30000,'CZK','placed','pending','2026-10-01 23:59:59',1)");
        $pdo->exec("INSERT INTO payments(payable_type,payable_id,method,status,amount_minor,currency,variable_symbol,due_at) VALUES('shop_order',1,'bank_transfer','pending',50000,'CZK','1001','2026-10-01 23:59:59'),('shop_order',2,'bank_transfer','cancelled',90000,'CZK','1002','2026-10-01 23:59:59'),('shop_order',3,'bank_transfer','pending',30000,'CZK','1003','2026-10-01 23:59:59')");
        $before=\clubFinanceBalanceForAthlete($pdo,1,new \DateTimeImmutable('2026-10-15 12:00:00'));
        self::assertSame(250100,$before['open_minor']);
        self::assertSame(0,$before['overdue_minor']);
        $after=\clubFinanceBalanceForAthlete($pdo,1,new \DateTimeImmutable('2026-10-16 12:00:00'));
        self::assertSame(250100,$after['overdue_minor']);
        self::assertCount(2,$after['items']);
        self::assertTrue(\clubFinancePastGrace('2026-10-01','2026-10-16',14));
        self::assertFalse(\clubFinancePastGrace('2026-10-01','2026-10-15',14));
    }

    public function testManualReceiptsPartiallySettleThenCreateCredit():void
    {
        $pdo=$this->database();
        $pdo->exec("INSERT INTO club_member_charges(sportovec_id,payer_account_id,public_code,charge_type,title_snapshot,amount_minor,currency,due_on,status,source_system,source_external_id) VALUES(1,10,'MC2','membership','Příspěvek',100000,'CZK','2026-01-15','pending','manual','MC2')");
        $pdo->exec("INSERT INTO payments(payable_type,payable_id,method,status,amount_minor,currency,variable_symbol,due_at) VALUES('member_charge',1,'bank_transfer','pending',100000,'CZK','2001','2026-01-15 23:59:59')");
        $first=\clubFinanceRecordBankReceipt($pdo,1,40000,'BANK-RECEIPT-1','2026-10-01',7,'Ověřeno na bankovním výpisu.',true);
        self::assertSame(40000,$first['allocated_minor']);
        self::assertSame(60000,\clubFinanceBalanceForAthlete($pdo,1)['open_minor']);
        self::assertSame('pending',$pdo->query('SELECT status FROM club_member_charges WHERE id=1')->fetchColumn());
        $second=\clubFinanceRecordBankReceipt($pdo,1,80000,'BANK-RECEIPT-2','2026-10-02',7,'Ověřeno na bankovním výpisu.',true);
        self::assertSame(60000,$second['allocated_minor']);
        self::assertSame(20000,$second['credit_minor']);
        self::assertSame('paid',$pdo->query('SELECT status FROM club_member_charges WHERE id=1')->fetchColumn());
        self::assertSame(20000,\clubFinanceBalanceForAthlete($pdo,1)['credit_minor']);
        $this->expectException(\ClubFinanceException::class);
        \clubFinanceRecordBankReceipt($pdo,1,80000,'BANK-RECEIPT-2','2026-10-02',7,'Opakovaná reference nesmí projít.',true);
    }

    public function testPartiallyAllocatedChargeCannotBeCancelled():void
    {
        $pdo=$this->database();
        $charge=\memberChargeAdminCreate($pdo,7,['sportovec_id'=>1,'payer_account_id'=>10,'title_snapshot'=>'Členský příspěvek','period_from'=>'2026-10-01','period_to'=>'2026-10-31','amount_minor'=>100000,'currency'=>'CZK','due_on'=>'2026-10-15'],'Měsíční členský předpis.',true);
        \clubFinanceRecordBankReceipt($pdo,1,40000,'BANK-PARTIAL-CANCEL','2026-10-01',7,'Ověřeno na bankovním výpisu.',true);
        $this->expectException(\MemberChargeAdminException::class);
        \memberChargeAdminCancel($pdo,$charge['id'],7,'Pokusu o zrušení s platbou.',true);
    }

    public function testAnnualReceiptCreatesTwelvePaidMonthlyChargesWithoutDuplicatingRun():void
    {
        $pdo=$this->database();
        $plan=\memberFeePlanCreate($pdo,7,['team_id'=>10,'name'=>'Členské','charge_title'=>'Měsíční příspěvek','amount_minor'=>120000,'due_day'=>15,'starts_on'=>'2026-01-01','ends_on'=>'2027-12-31'],'Zavedení měsíčního plánu.',true);
        $quote=\memberFeeAnnualQuote($pdo,$plan['id'],1,'2026-11',1000);
        self::assertSame(108000,$quote['monthly_minor']);
        self::assertSame(1296000,$quote['total_minor']);
        $result=\memberFeeAnnualConfirm($pdo,$plan['id'],1,'2026-11',1000,1296000,'BANK-ANNUAL-001','2026-10-01',7,'Ověřená roční úhrada příspěvků.',true);
        self::assertCount(12,$result['charge_ids']);
        self::assertSame(12,(int)$pdo->query("SELECT COUNT(*) FROM club_member_charges WHERE status='paid'")->fetchColumn());
        self::assertSame(12,(int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status='paid'")->fetchColumn());
        self::assertSame(12,(int)$pdo->query('SELECT COUNT(*) FROM club_finance_allocations')->fetchColumn());
        self::assertSame(1,(int)$pdo->query('SELECT COUNT(DISTINCT variable_symbol) FROM payments')->fetchColumn());
        $preview=\memberFeePlanPreview($pdo,$plan['id'],'2026-11');
        self::assertContains('existing',array_column($preview['rows'],'result'));
        $this->expectException(\MemberFeePlanException::class);
        \memberFeeAnnualConfirm($pdo,$plan['id'],1,'2026-11',1000,1296000,'BANK-ANNUAL-002','2026-10-01',7,'Duplicitní roční platba nesmí projít.',true);
    }

    public function testCancelledPartialAllocationReleasesCreditAndNewMonthlyChargeIsPaid():void
    {
        $pdo=$this->database();
        $pdo->exec("INSERT INTO club_finance_receipts(sportovec_id,kind,reference,amount_minor,currency,paid_on,confirmed_by_trainer_id,reason) VALUES(1,'manual_bank','BANK-CREDIT-001',100000,'CZK','2026-10-01',7,'Ověřená příchozí platba.')");
        $pdo->exec("INSERT INTO club_member_charges(sportovec_id,payer_account_id,public_code,charge_type,title_snapshot,amount_minor,currency,due_on,status,source_system,source_external_id) VALUES(1,10,'CANCELLED','membership','Zrušený',40000,'CZK','2026-09-01','cancelled','manual','CANCELLED')");
        $pdo->exec('INSERT INTO club_finance_allocations(receipt_id,charge_id,amount_minor) VALUES(1,1,40000)');
        self::assertSame(100000,\clubFinanceBalanceForAthlete($pdo,1)['credit_minor']);
        $plan=\memberFeePlanCreate($pdo,7,['team_id'=>10,'name'=>'Členské','charge_title'=>'Měsíční příspěvek','amount_minor'=>60000,'due_day'=>15,'starts_on'=>'2026-01-01','ends_on'=>'2027-12-31'],'Zavedení měsíčního plánu.',true);
        $preview=\memberFeePlanPreview($pdo,$plan['id'],'2026-11');
        $run=\memberFeePlanGenerate($pdo,$plan['id'],'2026-11',7,'Generování listopadových předpisů.',$preview['fingerprint'],true);
        self::assertSame(1,$run['generated_count']);
        self::assertSame('paid',$pdo->query("SELECT status FROM club_member_charges WHERE source_system='member_fee_plan'")->fetchColumn());
        self::assertSame(40000,\clubFinanceBalanceForAthlete($pdo,1)['credit_minor']);
    }

    public function testSharedAccountNeedsExplicitAthlete():void
    {
        $pdo=$this->database();
        $pdo->exec("INSERT INTO account_person_roles VALUES(2,10,2,'guardian','approved','2026-01-01',NULL)");
        self::assertNull(\clubFinanceOnlyAthleteForAccount($pdo,10));
        $this->expectException(\ClubFinanceException::class);
        \clubFinanceOrderAthlete($pdo,10,null);
    }

    public function testRegistrationGateStartsDisabledAndUsesStrictLimitAfterGrace():void
    {
        $pdo=$this->database();
        $pdo->exec("INSERT INTO club_member_charges(sportovec_id,payer_account_id,public_code,charge_type,title_snapshot,amount_minor,currency,due_on,status,source_system,source_external_id) VALUES(1,10,'GATE-1','membership','Dlouho po splatnosti',200100,'CZK','2026-01-01','pending','manual','GATE-1')");
        self::assertTrue(\clubFinanceRegistrationDecision($pdo,1)['allowed']);
        $pdo->exec('UPDATE club_finance_settings SET enforcement_enabled=1 WHERE id=1');
        self::assertFalse(\clubFinanceRegistrationDecision($pdo,1)['allowed']);
        $pdo->exec('UPDATE club_member_charges SET amount_minor=200000 WHERE id=1');
        self::assertTrue(\clubFinanceRegistrationDecision($pdo,1)['allowed']);
    }

    public function testExistingCreditAndNewReceiptSettleOneChargeWithoutDoubleCounting():void
    {
        $pdo=$this->database();
        $pdo->exec("INSERT INTO club_finance_receipts(sportovec_id,kind,reference,amount_minor,currency,paid_on,confirmed_by_trainer_id,reason) VALUES(1,'manual_bank','BANK-OLDER-001',20000,'CZK','2026-09-01',7,'Ověřená starší platba.')");
        $pdo->exec("INSERT INTO club_member_charges(sportovec_id,payer_account_id,public_code,charge_type,title_snapshot,amount_minor,currency,due_on,status,source_system,source_external_id) VALUES(1,10,'NEW-CHARGE','membership','Příspěvek',100000,'CZK','2026-10-01','pending','manual','NEW-CHARGE')");
        $pdo->exec("INSERT INTO payments(payable_type,payable_id,method,status,amount_minor,currency,variable_symbol,due_at) VALUES('member_charge',1,'bank_transfer','pending',100000,'CZK','9001','2026-10-01 23:59:59')");
        $result=\clubFinanceRecordBankReceipt($pdo,1,80000,'BANK-NEW-001','2026-10-02',7,'Ověřeno na bankovním výpisu.',true);
        self::assertSame(80000,$result['allocated_minor']);
        self::assertSame(0,$result['credit_minor']);
        self::assertSame('paid',$pdo->query('SELECT status FROM club_member_charges WHERE id=1')->fetchColumn());
        self::assertSame(100000,(int)$pdo->query('SELECT SUM(amount_minor) FROM club_finance_allocations WHERE charge_id=1')->fetchColumn());
        self::assertSame(0,\clubFinanceBalanceForAthlete($pdo,1)['credit_minor']);
    }

    private function database():PDO
    {
        $pdo=new PDO('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
        $pdo->exec("CREATE TABLE treneri(id INTEGER PRIMARY KEY);CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT);CREATE TABLE club_teams(id INTEGER PRIMARY KEY,name TEXT,status TEXT);CREATE TABLE club_roster_members(team_id INTEGER,sportovec_id INTEGER,status TEXT,valid_from TEXT,valid_to TEXT);CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,email TEXT,aktivni INTEGER,email_overeno INTEGER);CREATE TABLE account_person_roles(id INTEGER PRIMARY KEY,account_id INTEGER,sportovec_id INTEGER,relation_role TEXT,status TEXT,valid_from TEXT,valid_to TEXT);CREATE TABLE club_member_charges(id INTEGER PRIMARY KEY AUTOINCREMENT,sportovec_id INTEGER,payer_account_id INTEGER,public_code TEXT UNIQUE,charge_type TEXT,title_snapshot TEXT,period_from TEXT,period_to TEXT,amount_minor INTEGER,currency TEXT,due_on TEXT,status TEXT,source_system TEXT,source_external_id TEXT,source_import_run_id INTEGER,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP,UNIQUE(source_system,source_external_id));CREATE TABLE club_member_charge_events(id INTEGER PRIMARY KEY AUTOINCREMENT,charge_id INTEGER,action TEXT,from_status TEXT,to_status TEXT,actor_type TEXT,actor_id INTEGER,reason TEXT,snapshot_json TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP);CREATE TABLE shop_orders(id INTEGER PRIMARY KEY AUTOINCREMENT,account_id INTEGER,public_code TEXT,total_minor INTEGER,currency TEXT,status TEXT,payment_status TEXT,payment_expires_at TEXT,updated_at TEXT DEFAULT CURRENT_TIMESTAMP);CREATE TABLE payments(id INTEGER PRIMARY KEY AUTOINCREMENT,payable_type TEXT,payable_id INTEGER,method TEXT,status TEXT,amount_minor INTEGER,currency TEXT,variable_symbol TEXT UNIQUE,iban_snapshot TEXT,bic_snapshot TEXT,account_label_snapshot TEXT,spd_payload TEXT,due_at TEXT,paid_at TEXT,confirmed_by_trainer_id INTEGER,confirmation_note TEXT,updated_at TEXT DEFAULT CURRENT_TIMESTAMP,UNIQUE(payable_type,payable_id));CREATE TABLE shop_bank_settings(id INTEGER PRIMARY KEY,iban TEXT,bic TEXT,account_label TEXT,due_days INTEGER,updated_by_trainer_id INTEGER,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)");
        $pdo->exec("INSERT INTO treneri VALUES(7);INSERT INTO sportovci VALUES(1,'Anna','První'),(2,'Borek','Druhý');INSERT INTO club_teams VALUES(10,'Závodní','active');INSERT INTO club_roster_members VALUES(10,1,'active','2026-01-01',NULL);INSERT INTO verejni_uzivatele VALUES(10,'Anna','První','anna@example.test',1,1);INSERT INTO account_person_roles VALUES(1,10,1,'self','approved','2026-01-01',NULL);INSERT INTO shop_bank_settings(id,iban,bic,account_label,due_days,updated_by_trainer_id) VALUES(1,'CZ6508000000192000145399','GIBACZPX','KIS Kovo Praha',14,7)");
        $m=require dirname(__DIR__,2).'/migrations/20260922120000_member_fee_plans.php';$m['up']($pdo);
        $m=require dirname(__DIR__,2).'/migrations/20260922190000_member_fee_standing_orders.php';$m['up']($pdo);
        $m=require dirname(__DIR__,2).'/migrations/20261021120000_member_payer_variable_symbols.php';$m['up']($pdo);self::assertTrue($m['verify']($pdo));
        $m=require dirname(__DIR__,2).'/migrations/20261015130000_club_financial_balance.php';$m['up']($pdo);self::assertTrue($m['verify']($pdo));
        return $pdo;
    }
}
