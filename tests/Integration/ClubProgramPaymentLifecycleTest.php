<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2) . '/includes/shop_checkout.php';

final class ClubProgramPaymentLifecycleTest extends TestCase
{
    private const BANK=['iban'=>'CZ6508000000192000145399','bic'=>'GIBACZPX','account_label'=>'KOVO Praha','due_days'=>7];

    public function testPaidConfirmationAutomaticallyActivatesExactlyOnce():void
    {
        $pdo=$this->database();$this->offer($pdo,601,501,10,2,'OFFER-A');$order=$this->checkout($pdo,10,101,601);
        $result=\shopOrderAdminConfirmBankPayment($pdo,(int)$order['payment_id'],7,'Platba ověřena.',true);
        self::assertTrue($result['changed']);self::assertSame(1,$result['program_items']);self::assertSame(1,$result['created']);
        self::assertSame('active',$pdo->query('SELECT status FROM club_program_enrollments')->fetchColumn());self::assertSame('active',$pdo->query('SELECT status FROM club_roster_members')->fetchColumn());
        self::assertSame('trainer',$pdo->query('SELECT actor_type FROM club_program_enrollment_events')->fetchColumn());
        $repeat=\shopOrderAdminConfirmBankPayment($pdo,(int)$order['payment_id'],7,'Opakované ověření.',true);
        self::assertFalse($repeat['changed']);self::assertSame(0,$repeat['created']);self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM club_program_enrollments')->fetchColumn());self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM club_program_enrollment_events')->fetchColumn());
    }

    public function testUnauthorizedOrFullOfferRollsBackPaymentConfirmation():void
    {
        $pdo=$this->database();$this->offer($pdo,601,501,10,1,'OFFER-A');$first=$this->checkout($pdo,10,101,601);$second=$this->checkout($pdo,11,103,601);
        \shopOrderAdminConfirmBankPayment($pdo,(int)$first['payment_id'],7,'První platba.',true);
        try{\shopOrderAdminConfirmBankPayment($pdo,(int)$second['payment_id'],7,'Druhá platba.',true);self::fail('Full capacity must roll back payment.');}catch(\ShopCheckoutException $exception){self::assertStringContainsString('Kapacita',$exception->getMessage());}
        $secondState=$pdo->query('SELECT o.status,o.payment_status,p.status AS payment_record_status FROM shop_orders o JOIN payments p ON p.payable_id=o.id AND p.payable_type=\'shop_order\' WHERE o.id='.(int)$second['id'])->fetch(PDO::FETCH_ASSOC);
        self::assertSame(['status'=>'placed','payment_status'=>'pending','payment_record_status'=>'pending'],$secondState);self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM club_program_enrollments')->fetchColumn());self::assertFalse($pdo->inTransaction());

        $pdo=$this->database();$this->offer($pdo,601,501,10,2,'OFFER-A');$order=$this->checkout($pdo,10,101,601);$pdo->exec("UPDATE account_person_roles SET status='revoked',valid_to=CURRENT_TIMESTAMP WHERE account_id=10 AND sportovec_id=101");
        try{\shopOrderAdminConfirmBankPayment($pdo,(int)$order['payment_id'],7,'Pozdní platba.',true);self::fail('Revoked beneficiary must roll back payment.');}catch(\ShopCheckoutException){}
        self::assertSame('pending',$pdo->query('SELECT status FROM payments')->fetchColumn());self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM club_program_enrollments')->fetchColumn());
    }

    public function testDuplicateOfferForSameChildCannotBecomeSecondPaidOrder():void
    {
        $pdo=$this->database();$this->offer($pdo,601,501,10,3,'OFFER-A');$first=$this->checkout($pdo,10,101,601);\shopOrderAdminConfirmBankPayment($pdo,(int)$first['payment_id'],7,'První platba.',true);$duplicate=$this->checkout($pdo,10,101,601);
        try{\shopOrderAdminConfirmBankPayment($pdo,(int)$duplicate['payment_id'],7,'Duplicitní platba.',true);self::fail('A second order must not alias the first enrollment.');}catch(\ShopCheckoutException $exception){self::assertStringContainsString('Duplicitní',$exception->getMessage());}
        $statement=$pdo->prepare('SELECT o.status,o.payment_status,p.status AS payment_record_status FROM shop_orders o JOIN payments p ON p.payable_id=o.id AND p.payable_type=\'shop_order\' WHERE o.id=?');$statement->execute([(int)$duplicate['id']]);self::assertSame(['status'=>'placed','payment_status'=>'pending','payment_record_status'=>'pending'],$statement->fetch(PDO::FETCH_ASSOC));self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM club_program_enrollments')->fetchColumn());
    }

    public function testPaidCancellationEndsEnrollmentAndRefundNeverChangesItAgain():void
    {
        $pdo=$this->database();$this->offer($pdo,601,501,10,2,'OFFER-A');$order=$this->checkout($pdo,10,101,601);\shopOrderAdminConfirmBankPayment($pdo,(int)$order['payment_id'],7,'Platba.',true);
        $cancel=\shopOrderAdminCancel($pdo,(int)$order['id'],7,'Rodina odstoupila.',true);self::assertSame('refund_required',$cancel['payment_status']);self::assertSame(1,$cancel['cancelled']);self::assertSame(1,$cancel['rosters_ended']);
        $enrollment=$pdo->query('SELECT status,ended_at,ended_reason,ended_by_trainer_id FROM club_program_enrollments')->fetch(PDO::FETCH_ASSOC);self::assertSame('cancelled',$enrollment['status']);self::assertNotEmpty($enrollment['ended_at']);self::assertSame('Rodina odstoupila.',$enrollment['ended_reason']);self::assertSame(7,(int)$enrollment['ended_by_trainer_id']);self::assertSame('removed',$pdo->query('SELECT status FROM club_roster_members')->fetchColumn());
        $eventsBefore=(int)$pdo->query('SELECT COUNT(*) FROM club_program_enrollment_events')->fetchColumn();$repeat=\shopOrderAdminCancel($pdo,(int)$order['id'],7,'Opakování.',true);self::assertFalse($repeat['changed']);self::assertSame($eventsBefore,(int)$pdo->query('SELECT COUNT(*) FROM club_program_enrollment_events')->fetchColumn());
        $refund=\shopOrderAdminConfirmRefund($pdo,(int)$order['id'],7,'REF-1','Vratka odeslána.',true);self::assertTrue($refund['changed']);$repeatRefund=\shopOrderAdminConfirmRefund($pdo,(int)$order['id'],7,'REF-2','Opakování.',true);self::assertFalse($repeatRefund['changed']);self::assertSame($eventsBefore,(int)$pdo->query('SELECT COUNT(*) FROM club_program_enrollment_events')->fetchColumn());self::assertSame('cancelled',$pdo->query('SELECT status FROM club_program_enrollments')->fetchColumn());
    }

    public function testCancelledAndRefundedProgramCanBeBoughtAgainSafely():void
    {
        $pdo=$this->database();$this->offer($pdo,601,501,10,2,'OFFER-A');
        $first=$this->checkout($pdo,10,101,601);\shopOrderAdminConfirmBankPayment($pdo,(int)$first['payment_id'],7,'První platba.',true);
        \shopOrderAdminCancel($pdo,(int)$first['id'],7,'Rodina odstoupila.',true);\shopOrderAdminConfirmRefund($pdo,(int)$first['id'],7,'REF-REBUY-1','Vratka odeslána.',true);
        $second=$this->checkout($pdo,10,101,601);$confirmed=\shopOrderAdminConfirmBankPayment($pdo,(int)$second['payment_id'],7,'Nová platba.',true);

        self::assertTrue($confirmed['changed']);self::assertSame(1,$confirmed['created']);
        self::assertSame(2,(int)$pdo->query('SELECT COUNT(*) FROM club_program_enrollments')->fetchColumn());
        self::assertSame(1,(int)$pdo->query("SELECT COUNT(*) FROM club_program_enrollments WHERE status='active' AND active_token='active'")->fetchColumn());
        self::assertSame('active',$pdo->query('SELECT status FROM club_roster_members')->fetchColumn());
        self::assertSame(1,(int)$pdo->query("SELECT COUNT(*) FROM club_roster_events WHERE action='restore'")->fetchColumn());
    }

    public function testRosterStaysWhileAnotherActiveProgramUsesSamePersonAndTeam():void
    {
        $pdo=$this->database();$program=(int)\clubProgramCreate($pdo,7,'BIKE-SCHOOL','Cyklistická škola')['id'];$this->offer($pdo,601,501,10,5,'OFFER-A',$program);$this->offer($pdo,602,502,10,5,'OFFER-B',$program,'2027-02-01','2027-06-30');
        $first=$this->checkout($pdo,10,101,601);\shopOrderAdminConfirmBankPayment($pdo,(int)$first['payment_id'],7,'První období.',true);$second=$this->checkout($pdo,10,101,602);\shopOrderAdminConfirmBankPayment($pdo,(int)$second['payment_id'],7,'Druhé období.',true);
        self::assertSame(2,(int)$pdo->query("SELECT COUNT(*) FROM club_program_enrollments WHERE status='active'")->fetchColumn());self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM club_roster_members')->fetchColumn());
        $firstCancel=\shopOrderAdminCancel($pdo,(int)$first['id'],7,'Storno prvního období.',true);self::assertSame(0,$firstCancel['rosters_ended']);self::assertSame('active',$pdo->query('SELECT status FROM club_roster_members')->fetchColumn());
        $secondCancel=\shopOrderAdminCancel($pdo,(int)$second['id'],7,'Storno druhého období.',true);self::assertSame(1,$secondCancel['rosters_ended']);self::assertSame('removed',$pdo->query('SELECT status FROM club_roster_members')->fetchColumn());
    }

    public function testLifecycleMigrationIsRepeatableOnSqlite():void
    {
        $pdo=$this->database();$migration=require dirname(__DIR__,2).'/migrations/20260804160000_club_program_lifecycle.php';$migration['up']($pdo);self::assertTrue($migration['verify']($pdo));
    }

    public function testPendingProgramOrderCanExpireWithoutInventingTrainerActor():void
    {
        $pdo=$this->database();$this->offer($pdo,601,501,10,2,'OFFER-A');$order=$this->checkout($pdo,10,101,601);
        $pdo->exec("UPDATE shop_orders SET payment_expires_at='2030-01-01 12:00:00'");
        $result=\shopOrderExpirePending($pdo,(int)$order['id'],new \DateTimeImmutable('2030-01-02 12:00:00'),true);
        self::assertTrue($result['changed']);self::assertSame('cancelled',$result['payment_status']);
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM club_program_enrollments')->fetchColumn());
        self::assertSame('system',$pdo->query("SELECT actor_type FROM shop_order_events WHERE action='expire'")->fetchColumn());
    }

    private function checkout(PDO $pdo,int $accountId,int $sportovecId,int $variantId):array
    {
        \shopCartSetQuantity($pdo,$accountId,$variantId,1);$cart=\shopCartDetail($pdo,$accountId);\shopCartSetBeneficiary($pdo,$accountId,(int)$cart['items'][0]['cart_item_id'],$sportovecId);$cart=\shopCartDetail($pdo,$accountId);return \shopCheckoutPlace($pdo,$accountId,bin2hex(random_bytes(16)),self::BANK,$cart['fingerprint']);
    }

    private function offer(PDO $pdo,int $variantId,int $productId,int $teamId,int $capacity,string $code,?int $programId=null,string $from='2026-09-01',string $to='2027-01-31'):int
    {
        $programId??=(int)\clubProgramCreate($pdo,7,'BIKE-SCHOOL','Cyklistická škola')['id'];return(int)\clubProgramCreateOffer($pdo,7,$programId,1,$teamId,$productId,$variantId,$code,'Období '.$code,$from,$to,null,null,$capacity,'active')['id'];
    }

    private function database():PDO
    {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,email TEXT,aktivni INTEGER,email_overeno INTEGER)');$pdo->exec("INSERT INTO verejni_uzivatele VALUES(10,'Rodič','A','a@test.test',1,1),(11,'Rodič','B','b@test.test',1,1)");
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT)');$pdo->exec("INSERT INTO treneri VALUES(7,'Admin')");$pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT)');$pdo->exec("INSERT INTO sportovci VALUES(101,'Dítě','Jedna'),(103,'Dítě','Cizí')");
        $roles=require dirname(__DIR__,2).'/migrations/20260802230000_account_person_roles.php';$roles['up']($pdo);$pdo->exec("INSERT INTO account_person_roles(account_id,sportovec_id,relation_role,status,source,valid_from,created_by_trainer_id,approved_by_trainer_id,decision_note) VALUES(10,101,'guardian','approved','admin','2020-01-01',7,7,'test'),(11,103,'guardian','approved','admin','2020-01-01',7,7,'test')");
        $pdo->exec('CREATE TABLE shop_products(id INTEGER PRIMARY KEY,name TEXT,offer_type TEXT,catalog_status TEXT)');$pdo->exec("INSERT INTO shop_products VALUES(501,'Kroužek A','goods','active'),(502,'Kroužek B','goods','active')");
        $pdo->exec('CREATE TABLE shop_variants(id INTEGER PRIMARY KEY,product_id INTEGER,sku TEXT,attributes_json TEXT,price_mode TEXT,amount_minor INTEGER,currency TEXT,includes_vat INTEGER,vat_rate_basis_points INTEGER,stock_quantity_decimal TEXT,visible INTEGER,catalog_status TEXT,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');$pdo->exec("INSERT INTO shop_variants VALUES(601,501,'A','{}','fixed',10000,'CZK',1,0,NULL,1,'active',CURRENT_TIMESTAMP),(602,502,'B','{}','fixed',12000,'CZK',1,0,NULL,1,'active',CURRENT_TIMESTAMP)");
        $pdo->exec('CREATE TABLE shop_product_publications(product_id INTEGER PRIMARY KEY,status TEXT,public_name TEXT,public_summary TEXT)');$pdo->exec("INSERT INTO shop_product_publications VALUES(501,'active','Kroužek A','A'),(502,'active','Kroužek B','B')");
        foreach(['20260803230000_shop_checkout.php','20260804010000_shop_order_fulfillment.php','20260804030000_shop_order_refunds.php','20260804050000_shop_coupons.php','20260804120000_shop_item_beneficiaries.php','20260804090000_kis_teams_rosters.php','20260809090000_stripe_checkout.php']as$file){$migration=require dirname(__DIR__,2).'/migrations/'.$file;$migration['up']($pdo);self::assertTrue($migration['verify']($pdo));}
        $pdo->exec("INSERT INTO club_seasons(id,code,name,starts_on,ends_on,status,created_by_trainer_id) VALUES(1,'SCHOOL','Školní rok','2026-09-01','2027-08-31','active',7)");$pdo->exec("INSERT INTO club_teams(id,season_id,code,name,discipline,age_label,status,created_by_trainer_id) VALUES(10,1,'A','Kroužek A','vše','děti','active',7)");
        foreach(['20260804140000_club_programs.php','20260804160000_club_program_lifecycle.php','20260804235000_club_program_repeat_enrollment.php']as$file){$migration=require dirname(__DIR__,2).'/migrations/'.$file;$migration['up']($pdo);self::assertTrue($migration['verify']($pdo));}
        $expiration=require dirname(__DIR__,2).'/migrations/20260804210000_shop_order_expiration.php';$expiration['up']($pdo);self::assertTrue($expiration['verify']($pdo));
        return$pdo;
    }
}
