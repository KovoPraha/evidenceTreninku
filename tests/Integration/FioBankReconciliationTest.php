<?php
declare(strict_types=1);

namespace Tests\Integration;

use FioImportException;
use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/includes/fio_sync.php';

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

    public function testAutomaticShopConfirmationIsAuditedAndIdempotent():void
    {
        $pdo=$this->database();$this->shopPayment($pdo);
        $this->import($pdo,'auto-shop','259.00','11');
        self::assertSame(['confirmed'=>1,'review'=>0],\fioAutoConfirmPending($pdo,'CZ6508000000192000145399'));
        self::assertSame(['confirmed'=>0,'review'=>0],\fioAutoConfirmPending($pdo,'CZ6508000000192000145399'));
        $this->import($pdo,'auto-shop','259.00','11');
        self::assertSame('confirmed',$pdo->query('SELECT match_status FROM fio_account_movements')->fetchColumn());
        self::assertSame(['actor_type'=>'system','actor_id'=>null],self::selectOne($pdo,'SELECT actor_type,actor_id FROM shop_order_events'));
        self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM club_event_notifications')->fetchColumn());
        self::assertSame('2026-08-03 12:00:00',$pdo->query('SELECT paid_at FROM payments')->fetchColumn());
        self::assertNull($pdo->query('SELECT confirmed_by_trainer_id FROM payments')->fetchColumn());
    }

    public function testAutomaticMemberChargeConfirmationUsesSystemActor():void
    {
        $pdo=$this->database();
        $pdo->exec("INSERT INTO club_member_charges(id,public_code,title_snapshot,amount_minor,currency,status,updated_at) VALUES(51,'MC-51','Příspěvek',125000,'CZK','pending',CURRENT_TIMESTAMP)");
        $pdo->exec("INSERT INTO payments(id,payable_type,payable_id,method,status,amount_minor,currency,variable_symbol) VALUES(61,'member_charge',51,'bank_transfer','pending',125000,'CZK','9000000051')");
        $this->import($pdo,'auto-charge','1250.00','9000000051');
        self::assertSame(1,\fioAutoConfirmPending($pdo,'CZ6508000000192000145399')['confirmed']);
        self::assertSame('paid',$pdo->query('SELECT status FROM club_member_charges')->fetchColumn());
        self::assertSame(['actor_type'=>'system','actor_id'=>null],self::selectOne($pdo,'SELECT actor_type,actor_id FROM club_member_charge_events'));
    }

    public function testAutomaticConfirmationLeavesCompetingMovementsForReview():void
    {
        $pdo=$this->database();$this->shopPayment($pdo);
        $this->import($pdo,'duplicate-a','259.00','11');
        $this->import($pdo,'duplicate-b','259.00','11');
        self::assertSame(['confirmed'=>0,'review'=>2],\fioAutoConfirmPending($pdo,'CZ6508000000192000145399'));
        self::assertSame('pending',$pdo->query('SELECT status FROM payments')->fetchColumn());
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM shop_order_events')->fetchColumn());
    }

    public function testAutomaticConfirmationLeavesOldAccountSnapshotForReview():void
    {
        $pdo=$this->database();$this->shopPayment($pdo);
        $pdo->exec("UPDATE payments SET iban_snapshot='CZ0000000000000000000000' WHERE id=31");
        $this->import($pdo,'old-account','259.00','11');
        self::assertSame(['confirmed'=>0,'review'=>1],\fioAutoConfirmPending($pdo,'CZ6508000000192000145399'));
        self::assertSame('review_auto_conflict',$pdo->query('SELECT match_status FROM fio_account_movements')->fetchColumn());
        self::assertSame('pending',$pdo->query('SELECT status FROM payments WHERE id=31')->fetchColumn());
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM shop_order_events')->fetchColumn());
    }

    public function testRevalidationRejectsChangedStateOrReusedVsOrPartialAllocation():void
    {
        foreach(['cancelled','amount','currency','reused_vs','partial','receipt'] as $case){
            $pdo=$this->database();$this->shopPayment($pdo);
            $this->import($pdo,'revalidate','259.00','11');
            if($case==='cancelled')$pdo->exec("UPDATE shop_orders SET status='cancelled'");
            if($case==='amount')$pdo->exec("UPDATE shop_orders SET total_minor=30000");
            if($case==='currency')$pdo->exec("UPDATE payments SET currency='EUR'");
            if($case==='reused_vs')$pdo->exec("INSERT INTO payments(id,payable_type,payable_id,method,status,amount_minor,currency,variable_symbol) VALUES(32,'shop_order',11,'bank_transfer','pending',25900,'CZK','0000000011')");
            if($case==='partial')$pdo->exec("CREATE TABLE club_finance_allocations(order_id INTEGER,amount_minor INTEGER);INSERT INTO club_finance_allocations VALUES(11,100)");
            if($case==='receipt')$pdo->exec("CREATE TABLE club_finance_receipts(id INTEGER,reference TEXT);INSERT INTO club_finance_receipts VALUES(1,'revalidate')");
            self::assertSame(['confirmed'=>0,'review'=>1],\fioAutoConfirmPending($pdo,'CZ6508000000192000145399'),$case);
            self::assertSame('pending',$pdo->query('SELECT status FROM payments WHERE id=31')->fetchColumn(),$case);
        }
    }

    public function testWrongAmountMissingVsAndWrongAccountCannotAutoConfirm():void
    {
        $pdo=$this->database();$this->shopPayment($pdo);
        $this->import($pdo,'wrong-amount','258.00','11');
        $this->import($pdo,'missing-vs','259.00','');
        self::assertSame(0,\fioAutoConfirmPending($pdo,'CZ6508000000192000145399')['confirmed']);
        $this->import($pdo,'right-amount','259.00','11');
        self::assertSame(0,\fioAutoConfirmPending($pdo,'CZ0000000000000000000000')['confirmed']);
        self::assertSame('pending',$pdo->query('SELECT status FROM payments')->fetchColumn());
    }

    public function testAutomaticFailureRollsBackPaymentAndCanBeRetried():void
    {
        $pdo=$this->database();$this->shopPayment($pdo);$this->import($pdo,'rollback','259.00','11');
        $pdo->exec("CREATE TRIGGER reject_fio_confirmation BEFORE UPDATE ON fio_account_movements WHEN NEW.match_status='confirmed' BEGIN SELECT RAISE(ABORT,'injected failure'); END");
        try{\fioAutoConfirmPending($pdo,'CZ6508000000192000145399');self::fail('Expected rollback.');}
        catch(FioImportException){
            self::assertSame('pending',$pdo->query('SELECT status FROM payments')->fetchColumn());
            self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM shop_order_events')->fetchColumn());
            self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM club_event_notifications')->fetchColumn());
        }
        $pdo->exec('DROP TRIGGER reject_fio_confirmation');
        self::assertSame(1,\fioAutoConfirmPending($pdo,'CZ6508000000192000145399')['confirmed']);
    }

    public function testRecoveryKeepsFailedWindowAndDefersAutomaticConfirmationUntilCaughtUp():void
    {
        $pdo=$this->database();$this->shopPayment($pdo);$windows=[];
        $fetch=function(string $from,string $to)use(&$windows):string{
            $windows[]=[$from,$to];$movements=[];
            if($from<='2026-08-03'&&$to>='2026-08-03')$movements[]=$this->movement('recovery','259.00','11');
            return $this->payload($movements);
        };
        $today=new \DateTimeImmutable('2026-08-20',new \DateTimeZone('Europe/Prague'));
        $first=\fioSynchronize($pdo,'CZ6508000000192000145399',$fetch,$today,true,3,'2026-08-01',1000);
        self::assertSame('2026-08-07',$first['period_to']);self::assertFalse($first['caught_up']);
        self::assertSame('pending',$pdo->query('SELECT status FROM payments')->fetchColumn());
        try{\fioSynchronize($pdo,'CZ6508000000192000145399',static fn()=>throw new FioImportException('fio_http_failed_500'),$today,true,3,null,1031);self::fail('Expected failure.');}
        catch(FioImportException){self::assertSame('2026-08-07',$pdo->query('SELECT covered_through FROM fio_sync_state')->fetchColumn());}
        // A failed HTTP call consumes the rate limit too.
        try{\fioSynchronize($pdo,'CZ6508000000192000145399',$fetch,$today,true,3,null,1040);self::fail('Expected rate limit.');}
        catch(FioImportException $e){self::assertSame('fio_import_rate_limited',$e->getMessage());}
        for($clock=1062;$clock<=1186;$clock+=31){
            $last=\fioSynchronize($pdo,'CZ6508000000192000145399',$fetch,$today,true,3,null,$clock);
            if($last['caught_up'])break;
        }
        self::assertSame(['2026-08-05','2026-08-11'],$windows[1]);
        self::assertTrue($last['caught_up']);self::assertSame(1,$last['automatic']['confirmed']);
        self::assertSame('paid',$pdo->query('SELECT status FROM payments')->fetchColumn());
        self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM fio_account_movements')->fetchColumn());
        // A longer outage resumes from the checkpoint, not from today's last three days.
        $later=\fioSynchronize($pdo,'CZ6508000000192000145399',$fetch,$today->modify('+20 days'),false,3,null,2000);
        self::assertSame('2026-08-18',$later['period_from']);self::assertFalse($later['caught_up']);
    }

    public function testFailedInitialFetchPreservesStartAndHistoricalUnlockNeverSkipsData():void
    {
        $pdo=$this->database();$today=new \DateTimeImmutable('2026-10-20');
        try{\fioSynchronize($pdo,'CZ6508000000192000145399',static fn()=>throw new FioImportException('fio_http_failed_422'),$today,false,3,'2026-01-01',1000);self::fail('Expected history lock.');}
        catch(FioImportException){
            self::assertSame(['start_date'=>'2026-01-01','covered_through'=>null,'last_error'=>'fio_http_failed_422'],self::selectOne($pdo,'SELECT start_date,covered_through,last_error FROM fio_sync_state'));
        }
        $result=\fioSynchronize($pdo,'CZ6508000000192000145399',fn()=>$this->payload([]),$today->modify('+1 day'),false,3,null,1031);
        self::assertSame('2026-01-01',$result['period_from']);
        self::assertSame('2026-01-07',$result['period_to']);
    }

    public function testMalformedPayloadDoesNotAdvanceAndDefaultModeDoesNotConfirm():void
    {
        $pdo=$this->database();$this->shopPayment($pdo);$today=new \DateTimeImmutable('2026-08-03');
        try{\fioSynchronize($pdo,'CZ6508000000192000145399',static fn()=>'{"accountStatement":{"info":{"iban":"CZ6508000000192000145399"}}}',$today,false,3,'2026-08-03',1000);self::fail('Expected invalid list.');}
        catch(FioImportException){self::assertNull($pdo->query('SELECT covered_through FROM fio_sync_state')->fetchColumn());}
        $result=\fioSynchronize($pdo,'CZ6508000000192000145399',fn()=>$this->payload([$this->movement('default','259.00','11')]),$today,now:1031);
        self::assertTrue($result['caught_up']);self::assertSame(0,$result['automatic']['confirmed']);
        self::assertSame('pending',$pdo->query('SELECT status FROM payments')->fetchColumn());
    }

    public function testCrashAfterImportBeforeCheckpointCanReplayWithoutDoublePayment():void
    {
        $pdo=$this->database();$this->shopPayment($pdo);$today=new \DateTimeImmutable('2026-08-03');
        $pdo->exec("CREATE TRIGGER reject_checkpoint BEFORE UPDATE ON fio_sync_state WHEN NEW.covered_through IS NOT NULL BEGIN SELECT RAISE(ABORT,'injected checkpoint failure'); END");
        $fetch=fn()=>$this->payload([$this->movement('crash-replay','259.00','11')]);
        try{\fioSynchronize($pdo,'CZ6508000000192000145399',$fetch,$today,true,3,'2026-08-03',1000);self::fail('Expected failure.');}
        catch(FioImportException){
            self::assertNull($pdo->query('SELECT covered_through FROM fio_sync_state')->fetchColumn());
            self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM fio_account_movements')->fetchColumn());
            self::assertSame('pending',$pdo->query('SELECT status FROM payments')->fetchColumn());
        }
        $pdo->exec('DROP TRIGGER reject_checkpoint');
        $again=\fioSynchronize($pdo,'CZ6508000000192000145399',$fetch,$today,true,3,null,1031);
        self::assertSame(1,$again['duplicates']);self::assertSame(1,$again['automatic']['confirmed']);
        self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM shop_order_events')->fetchColumn());
    }

    public function testBackfillSeesCompetingMovementFromLaterWindowBeforeConfirmation():void
    {
        $pdo=$this->database();$this->shopPayment($pdo);$today=new \DateTimeImmutable('2026-08-12');
        $fetch=function(string $from,string $to):string{
            $rows=[];
            foreach(['2026-08-03','2026-08-11']as$date){
                if($date<$from||$date>$to)continue;
                $row=$this->movement('backfill-'.$date,'259.00','11');$row['column0']['value']=$date;$rows[]=$row;
            }
            return $this->payload($rows);
        };
        for($now=1000;$now<=1124;$now+=31){
            $result=\fioSynchronize($pdo,'CZ6508000000192000145399',$fetch,$today,true,3,'2026-08-01',$now);
            if($result['caught_up'])break;
        }
        self::assertTrue($result['caught_up']);self::assertSame(['confirmed'=>0,'review'=>2],$result['automatic']);
        self::assertSame('pending',$pdo->query('SELECT status FROM payments')->fetchColumn());
    }

    public function testUpgradeBackfillsGapsAndDoesNotUseAnotherAccountsCursor():void
    {
        $pdo=$this->database();$today=new \DateTimeImmutable('2026-08-20');
        \fioImportJson($pdo,$this->payload([]),'2026-06-01','2026-06-07','CZ6508000000192000145399');
        \fioImportJson($pdo,$this->payload([]),'2026-08-18','2026-08-20','CZ6508000000192000145399');
        $result=\fioSynchronize($pdo,'CZ6508000000192000145399',fn()=>$this->payload([]),$today,false,3,null,1000);
        self::assertSame('2026-06-01',$result['period_from']);
        $other='CZ0000000000000000000000';
        $result=\fioSynchronize($pdo,$other,fn()=>str_replace('CZ65 0800 0000 1920 0014 5399',$other,$this->payload([])),$today,false,3,null,1000);
        self::assertSame('2026-07-22',$result['period_from']);
        self::assertSame(2,(int)$pdo->query('SELECT COUNT(*) FROM fio_sync_state')->fetchColumn());
    }

    private function shopPayment(PDO $pdo):void
    {
        $pdo->exec("INSERT INTO shop_orders VALUES(11,'OBJ-FIO-11',10,'placed','pending','Testovací plátce','fio@example.test',25900,'CZK',CURRENT_TIMESTAMP)");
        $pdo->exec("INSERT INTO shop_order_items VALUES(1,11,601,'Položka',1)");
        $pdo->exec("INSERT INTO payments(id,payable_type,payable_id,method,payment_source,status,amount_minor,currency,variable_symbol,updated_at) VALUES(31,'shop_order',11,'bank_transfer','bank_transfer','pending',25900,'CZK','0000000011',CURRENT_TIMESTAMP)");
    }

    private function database():PDO
    {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE shop_orders(id INTEGER PRIMARY KEY,public_code TEXT,account_id INTEGER,status TEXT,payment_status TEXT,customer_name_snapshot TEXT,customer_email_snapshot TEXT,total_minor INTEGER,currency TEXT,updated_at TEXT)');
        $pdo->exec('CREATE TABLE shop_order_items(id INTEGER PRIMARY KEY,order_id INTEGER,variant_id INTEGER,product_name_snapshot TEXT,quantity INTEGER)');
        $pdo->exec("CREATE TABLE club_event_notifications(id INTEGER PRIMARY KEY AUTOINCREMENT,registration_id INTEGER NULL,registration_event_id INTEGER NULL,order_id INTEGER NULL,notification_type TEXT NOT NULL,recipient_email TEXT NOT NULL,recipient_name TEXT NOT NULL,subject_plain TEXT NOT NULL,body_plain TEXT NOT NULL,status TEXT NOT NULL DEFAULT 'pending',attempts INTEGER NOT NULL DEFAULT 0,available_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,claimed_at TEXT NULL,claim_token TEXT NULL,sent_at TEXT NULL,last_error TEXT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE(order_id,notification_type))");
        $pdo->exec("CREATE TABLE payments(id INTEGER PRIMARY KEY,payable_type TEXT,payable_id INTEGER,method TEXT,payment_source TEXT,status TEXT,amount_minor INTEGER,currency TEXT,variable_symbol TEXT,iban_snapshot TEXT DEFAULT 'CZ6508000000192000145399',paid_at TEXT,confirmed_by_trainer_id INTEGER,confirmation_note TEXT,updated_at TEXT)");
        $pdo->exec("CREATE TABLE shop_order_events(id INTEGER PRIMARY KEY AUTOINCREMENT,order_id INTEGER,actor_type TEXT,actor_id INTEGER,action TEXT,from_status TEXT,to_status TEXT,note TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP)");
        $pdo->exec('CREATE TABLE club_member_charges(id INTEGER PRIMARY KEY,public_code TEXT,title_snapshot TEXT,amount_minor INTEGER,currency TEXT,status TEXT,updated_at TEXT)');
        $pdo->exec('CREATE TABLE club_member_charge_events(id INTEGER PRIMARY KEY AUTOINCREMENT,charge_id INTEGER,action TEXT,from_status TEXT,to_status TEXT,actor_type TEXT,actor_id INTEGER NULL,reason TEXT,snapshot_json TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $shadow=require dirname(__DIR__,2).'/migrations/20260804070000_fio_readonly_import.php';$shadow['up']($pdo);
        $reconciliation=require dirname(__DIR__,2).'/migrations/20260909170000_fio_bank_reconciliation.php';$reconciliation['up']($pdo);$reconciliation['up']($pdo);self::assertTrue($reconciliation['verify']($pdo));
        $sync=require dirname(__DIR__,2).'/migrations/20261020170000_fio_sync_state.php';$sync['up']($pdo);$sync['up']($pdo);self::assertTrue($sync['verify']($pdo));
        return$pdo;
    }

    private function import(PDO $pdo,string $id,string $amount,string $vs):int
    {
        $payload=$this->payload([$this->movement($id,$amount,$vs)]);
        \fioImportJson($pdo,$payload,'2026-08-03','2026-08-03','CZ6508000000192000145399');
        return(int)$pdo->query('SELECT id FROM fio_account_movements ORDER BY id DESC LIMIT 1')->fetchColumn();
    }

    private function movement(string $id,string $amount,string $vs):array
    {
        return ['column0'=>['value'=>'2026-08-03+02:00'],'column1'=>['value'=>$amount],'column5'=>['value'=>$vs],'column8'=>['value'=>'Vklad převodem'],'column14'=>['value'=>'CZK'],'column22'=>['value'=>$id]];
    }

    private function payload(array $movements):string
    {
        return json_encode(['accountStatement'=>['info'=>['iban'=>'CZ65 0800 0000 1920 0014 5399'],'transactionList'=>['transaction'=>$movements]]],JSON_THROW_ON_ERROR);
    }
    /** @return array<string,mixed> */
    private static function selectOne(PDO $pdo,string $sql):array{return$pdo->query($sql)->fetch(PDO::FETCH_ASSOC);}
}
