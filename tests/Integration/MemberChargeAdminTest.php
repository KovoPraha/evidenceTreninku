<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/includes/member_charge_admin.php';

final class MemberChargeAdminTest extends TestCase
{
    public function testManualChargeCanBeCreatedCorrectedAndPaidWithAudit():void
    {
        $pdo=$this->database();$input=$this->input();$created=\memberChargeAdminCreate($pdo,7,$input,'Ruční předpis za sezonu.',true);
        self::assertMatchesRegularExpression('/^MC-[0-9]{8}-[A-F0-9]{8}$/',$created['public_code']);self::assertMatchesRegularExpression('/^9[0-9]{9}$/',$created['variable_symbol']);
        $input['amount_minor']=160000;$input['title_snapshot']='Členský příspěvek – oprava';self::assertTrue(\memberChargeAdminCorrect($pdo,$created['id'],7,$input,'Oprava výše podle rozhodnutí výboru.',true)['changed']);
        self::assertSame(160000,(int)$pdo->query('SELECT amount_minor FROM payments')->fetchColumn());
        self::assertTrue(\memberChargeAdminConfirmPaid($pdo,$created['id'],7,'2026-08-22','Platba ověřena na bankovním výpisu.',true)['changed']);
        self::assertSame('paid',$pdo->query('SELECT status FROM club_member_charges')->fetchColumn());self::assertSame('paid',$pdo->query('SELECT status FROM payments')->fetchColumn());self::assertSame(3,(int)$pdo->query('SELECT COUNT(*) FROM club_member_charge_events')->fetchColumn());
    }

    public function testPendingChargeCanBeCancelledButPaidChargeCannot():void
    {
        $pdo=$this->database();$first=\memberChargeAdminCreate($pdo,7,$this->input(),'Založení prvního předpisu.',true);self::assertTrue(\memberChargeAdminCancel($pdo,$first['id'],7,'Členství bylo ukončeno před sezonou.',true)['changed']);self::assertSame('cancelled',$pdo->query('SELECT status FROM payments WHERE payable_id='.$first['id'])->fetchColumn());
        $second=\memberChargeAdminCreate($pdo,7,$this->input(),'Založení druhého předpisu.',true);\memberChargeAdminConfirmPaid($pdo,$second['id'],7,'2026-08-22','Platba ověřena na výpisu.',true);
        $this->expectException(\MemberChargeAdminException::class);\memberChargeAdminCancel($pdo,$second['id'],7,'Chybné zrušení uhrazeného předpisu.',true);
    }

    private function input():array{return['sportovec_id'=>101,'payer_account_id'=>10,'title_snapshot'=>'Členský příspěvek 2026/27','period_from'=>'2026-09-01','period_to'=>'2027-08-31','amount_minor'=>150000,'currency'=>'CZK','due_on'=>'2026-09-15'];}

    private function database():PDO
    {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT)');$pdo->exec("INSERT INTO treneri VALUES(7,'Finance')");
        $pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT)');$pdo->exec("INSERT INTO sportovci VALUES(101,'Dítě','Test')");
        $pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,email TEXT,aktivni INTEGER)');$pdo->exec("INSERT INTO verejni_uzivatele VALUES(10,'Rodič','Test','rodic@test',1)");
        $pdo->exec('CREATE TABLE shop_bank_settings(id INTEGER PRIMARY KEY,iban TEXT,bic TEXT,account_label TEXT,due_days INTEGER,updated_by_trainer_id INTEGER,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');$pdo->exec("INSERT INTO shop_bank_settings(id,iban,bic,account_label,due_days,updated_by_trainer_id) VALUES(1,'CZ6508000000192000145399','GIBACZPX','KIS Kovo Praha',14,7)");
        $pdo->exec('CREATE TABLE club_member_charges(id INTEGER PRIMARY KEY AUTOINCREMENT,sportovec_id INTEGER,payer_account_id INTEGER,public_code TEXT UNIQUE,charge_type TEXT,title_snapshot TEXT,period_from TEXT,period_to TEXT,amount_minor INTEGER,currency TEXT,due_on TEXT,status TEXT,source_system TEXT,source_external_id TEXT,source_import_run_id INTEGER,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP,UNIQUE(source_system,source_external_id))');
        $pdo->exec('CREATE TABLE club_member_charge_events(id INTEGER PRIMARY KEY AUTOINCREMENT,charge_id INTEGER,action TEXT,from_status TEXT,to_status TEXT,actor_type TEXT,actor_id INTEGER,reason TEXT,snapshot_json TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec('CREATE TABLE payments(id INTEGER PRIMARY KEY AUTOINCREMENT,payable_type TEXT,payable_id INTEGER,method TEXT,status TEXT,amount_minor INTEGER,currency TEXT,variable_symbol TEXT UNIQUE,iban_snapshot TEXT,bic_snapshot TEXT,account_label_snapshot TEXT,spd_payload TEXT,due_at TEXT,paid_at TEXT,confirmed_by_trainer_id INTEGER,confirmation_note TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP,UNIQUE(payable_type,payable_id))');return$pdo;
    }
}
