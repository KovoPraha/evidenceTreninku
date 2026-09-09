<?php
declare(strict_types=1);

namespace Tests\Integration;

use FioImportException;
use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/includes/fio_readonly_import.php';

final class FioBankReconciliationTest extends TestCase
{
    public function testExactShopMovementIsConfirmedOnceThroughCanonicalLifecycle():void
    {
        $pdo=$this->database();
        $pdo->exec("INSERT INTO shop_orders VALUES(11,'OBJ-FIO-11',10,'placed','pending','Testovací plátce','fio@example.test',25900,'CZK',CURRENT_TIMESTAMP)");
        $pdo->exec("INSERT INTO shop_order_items VALUES(1,11,601,'Testovací položka',1)");
        $pdo->exec("INSERT INTO payments(id,payable_type,payable_id,method,payment_source,status,amount_minor,currency,variable_symbol,paid_at,confirmed_by_trainer_id,confirmation_note,updated_at) VALUES(31,'shop_order',11,'bank_transfer','bank_transfer','pending',25900,'CZK','0000000011',NULL,NULL,NULL,CURRENT_TIMESTAMP)");
        $movementId=$this->import($pdo,'fio-shop-1','259.00','11');

        $first=\fioAdminConfirmExactMovement($pdo,$movementId,7,'Ověřeno proti výpisu Fio.',true);
        $second=\fioAdminConfirmExactMovement($pdo,$movementId,7,'Opakovaná kontrola výpisu.',true);

        self::assertTrue($first['changed']);self::assertFalse($second['changed']);
        self::assertSame(['status'=>'paid','payment_source'=>'bank_transfer','bank_movement_id'=>'fio-shop-1','bank_booked_on'=>'2026-08-03','paid_at'=>'2026-08-03 12:00:00'],self::selectOne($pdo,'SELECT status,payment_source,bank_movement_id,bank_booked_on,paid_at FROM payments WHERE id=31'));
        self::assertSame(['status'=>'processing','payment_status'=>'paid'],self::selectOne($pdo,'SELECT status,payment_status FROM shop_orders WHERE id=11'));
        self::assertSame('confirmed',$pdo->query("SELECT match_status FROM fio_account_movements WHERE fio_movement_id='fio-shop-1'")->fetchColumn());
        self::assertSame(1,(int)$pdo->query("SELECT COUNT(*) FROM shop_order_events WHERE action='confirm_bank_payment'")->fetchColumn());
        self::assertSame(1,(int)$pdo->query("SELECT COUNT(*) FROM club_event_notifications WHERE notification_type='shop_payment_received'")->fetchColumn());
    }

    public function testExactMemberChargeMovementIsConfirmedAndAudited():void
    {
        $pdo=$this->database();
        $pdo->exec("INSERT INTO club_member_charges(id,public_code,title_snapshot,amount_minor,currency,status,updated_at) VALUES(51,'MC-FIO-51','Členský příspěvek',125000,'CZK','pending',CURRENT_TIMESTAMP)");
        $pdo->exec("INSERT INTO payments(id,payable_type,payable_id,method,payment_source,status,amount_minor,currency,variable_symbol,paid_at,confirmed_by_trainer_id,confirmation_note,updated_at) VALUES(61,'member_charge',51,'bank_transfer','bank_transfer','pending',125000,'CZK','9000000051',NULL,NULL,NULL,CURRENT_TIMESTAMP)");
        $movementId=$this->import($pdo,'fio-charge-1','1250.00','9000000051');

        $result=\fioAdminConfirmExactMovement($pdo,$movementId,7,'Ověřeno proti výpisu Fio.',true);

        self::assertTrue($result['changed']);self::assertSame('member_charge',$result['payable_type']);
        self::assertSame('paid',$pdo->query('SELECT status FROM club_member_charges WHERE id=51')->fetchColumn());
        self::assertSame(['status'=>'paid','bank_movement_id'=>'fio-charge-1','bank_booked_on'=>'2026-08-03'],self::selectOne($pdo,'SELECT status,bank_movement_id,bank_booked_on FROM payments WHERE id=61'));
        self::assertSame(['action'=>'confirm_fio_payment','actor_type'=>'trainer','actor_id'=>7],self::selectOne($pdo,'SELECT action,actor_type,actor_id FROM club_member_charge_events WHERE charge_id=51'));
    }

    public function testChangedAmountCannotBeConfirmed():void
    {
        $pdo=$this->database();
        $pdo->exec("INSERT INTO shop_orders VALUES(11,'OBJ-FIO-11',10,'placed','pending','Testovací plátce','fio@example.test',25900,'CZK',CURRENT_TIMESTAMP)");
        $pdo->exec("INSERT INTO payments(id,payable_type,payable_id,method,payment_source,status,amount_minor,currency,variable_symbol,paid_at,confirmed_by_trainer_id,confirmation_note,updated_at) VALUES(31,'shop_order',11,'bank_transfer','bank_transfer','pending',25900,'CZK','0000000011',NULL,NULL,NULL,CURRENT_TIMESTAMP)");
        $movementId=$this->import($pdo,'fio-shop-wrong','1.00','11');
        $this->expectException(FioImportException::class);$this->expectExceptionMessage('už nesplňuje přesnou shodu');
        try{\fioAdminConfirmExactMovement($pdo,$movementId,7,'Ověřeno proti výpisu Fio.',true);}
        finally{self::assertSame('pending',$pdo->query('SELECT status FROM payments WHERE id=31')->fetchColumn());self::assertNull($pdo->query('SELECT bank_movement_id FROM payments WHERE id=31')->fetchColumn());}
    }

    private function database():PDO
    {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE shop_orders(id INTEGER PRIMARY KEY,public_code TEXT,account_id INTEGER,status TEXT,payment_status TEXT,customer_name_snapshot TEXT,customer_email_snapshot TEXT,total_minor INTEGER,currency TEXT,updated_at TEXT)');
        $pdo->exec('CREATE TABLE shop_order_items(id INTEGER PRIMARY KEY,order_id INTEGER,variant_id INTEGER,product_name_snapshot TEXT,quantity INTEGER)');
        $pdo->exec("CREATE TABLE club_event_notifications(id INTEGER PRIMARY KEY AUTOINCREMENT,registration_id INTEGER NULL,registration_event_id INTEGER NULL,order_id INTEGER NULL,notification_type TEXT NOT NULL,recipient_email TEXT NOT NULL,recipient_name TEXT NOT NULL,subject_plain TEXT NOT NULL,body_plain TEXT NOT NULL,status TEXT NOT NULL DEFAULT 'pending',attempts INTEGER NOT NULL DEFAULT 0,available_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,claimed_at TEXT NULL,claim_token TEXT NULL,sent_at TEXT NULL,last_error TEXT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE(order_id,notification_type))");
        $pdo->exec('CREATE TABLE payments(id INTEGER PRIMARY KEY,payable_type TEXT,payable_id INTEGER,method TEXT,payment_source TEXT,status TEXT,amount_minor INTEGER,currency TEXT,variable_symbol TEXT,paid_at TEXT,confirmed_by_trainer_id INTEGER,confirmation_note TEXT,updated_at TEXT)');
        $pdo->exec("CREATE TABLE shop_order_events(id INTEGER PRIMARY KEY AUTOINCREMENT,order_id INTEGER,actor_type TEXT,actor_id INTEGER,action TEXT,from_status TEXT,to_status TEXT,note TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP)");
        $pdo->exec('CREATE TABLE club_member_charges(id INTEGER PRIMARY KEY,public_code TEXT,title_snapshot TEXT,amount_minor INTEGER,currency TEXT,status TEXT,updated_at TEXT)');
        $pdo->exec('CREATE TABLE club_member_charge_events(id INTEGER PRIMARY KEY AUTOINCREMENT,charge_id INTEGER,action TEXT,from_status TEXT,to_status TEXT,actor_type TEXT,actor_id INTEGER NULL,reason TEXT,snapshot_json TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $shadow=require dirname(__DIR__,2).'/migrations/20260804070000_fio_readonly_import.php';$shadow['up']($pdo);
        $reconciliation=require dirname(__DIR__,2).'/migrations/20260909170000_fio_bank_reconciliation.php';$reconciliation['up']($pdo);$reconciliation['up']($pdo);self::assertTrue($reconciliation['verify']($pdo));
        return$pdo;
    }

    private function import(PDO $pdo,string $id,string $amount,string $vs):int
    {
        $movement=['column0'=>['value'=>1785715200000],'column1'=>['value'=>$amount],'column5'=>['value'=>$vs],'column8'=>['value'=>'Vklad převodem'],'column14'=>['value'=>'CZK'],'column22'=>['value'=>$id]];
        $payload=json_encode(['accountStatement'=>['info'=>['iban'=>'CZ65 0800 0000 1920 0014 5399'],'transactionList'=>['transaction'=>[$movement]]]],JSON_THROW_ON_ERROR);
        \fioImportJson($pdo,$payload,'2026-08-03','2026-08-03','CZ6508000000192000145399');
        return(int)$pdo->query('SELECT id FROM fio_account_movements ORDER BY id DESC LIMIT 1')->fetchColumn();
    }

    /** @return array<string,mixed> */
    private static function selectOne(PDO $pdo,string $sql):array{return$pdo->query($sql)->fetch(PDO::FETCH_ASSOC);}
}
