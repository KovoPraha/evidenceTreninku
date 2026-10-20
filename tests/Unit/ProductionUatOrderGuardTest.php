<?php
declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once dirname(__DIR__, 2) . '/bin/production-uat-order-guard.php';

final class ProductionUatOrderGuardTest extends TestCase
{
    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $pdo->exec("CREATE TABLE verejni_uzivatele(id INTEGER,email TEXT,jmeno TEXT,prijmeni TEXT,aktivni INTEGER,email_overeno INTEGER);
            CREATE TABLE sportovci(id INTEGER,jmeno TEXT,prijmeni TEXT,narozeni TEXT);
            CREATE TABLE shop_products(id INTEGER,name TEXT,offer_type TEXT,catalog_status TEXT);
            CREATE TABLE shop_product_publications(product_id INTEGER,status TEXT);
            CREATE TABLE shop_variants(id INTEGER,product_id INTEGER,sku TEXT,catalog_status TEXT,visible INTEGER,price_mode TEXT,amount_minor INTEGER,currency TEXT);
            CREATE TABLE club_programs(id INTEGER,code TEXT);
            CREATE TABLE club_teams(id INTEGER,code TEXT);
            CREATE TABLE club_program_offers(id INTEGER,variant_id INTEGER,product_id INTEGER,program_id INTEGER,team_id INTEGER,status TEXT);
            CREATE TABLE account_person_roles(account_id INTEGER,sportovec_id INTEGER,relation_role TEXT,status TEXT,valid_from TEXT,valid_to TEXT);
            CREATE TABLE shop_orders(id INTEGER,account_id INTEGER,status TEXT,payment_status TEXT,total_minor INTEGER,currency TEXT);
            CREATE TABLE shop_order_items(id INTEGER,order_id INTEGER,variant_id INTEGER,beneficiary_sportovec_id INTEGER,quantity INTEGER,line_amount_minor INTEGER);
            CREATE TABLE payments(id INTEGER,payable_type TEXT,payable_id INTEGER,status TEXT,method TEXT,amount_minor INTEGER);
            INSERT INTO verejni_uzivatele VALUES(7,'tester.karel@velocota.com','Tester','Karel',1,1);
            INSERT INTO sportovci VALUES(8,'Ema','Tester','2018-04-12');
            INSERT INTO shop_products VALUES(9,'TEST - Cyklistický kroužek','program','active');
            INSERT INTO shop_product_publications VALUES(9,'active');
            INSERT INTO shop_variants VALUES(10,9,'KP-TEST-UAT-KROUZEK','active',1,'fixed',10000,'CZK');
            INSERT INTO club_programs VALUES(11,'TEST-UAT-KROUZEK');
            INSERT INTO club_teams VALUES(12,'TEST-UAT-DETI');
            INSERT INTO club_program_offers VALUES(13,10,9,11,12,'active');
            INSERT INTO account_person_roles VALUES(7,8,'guardian','approved','2000-01-01',NULL);");
        return $pdo;
    }

    private function order(PDO $pdo, string $status = 'placed', string $payment = 'pending'): void
    {
        $statement = $pdo->prepare('INSERT INTO shop_orders VALUES(14,7,?,?,10000,\'CZK\')');
        $statement->execute([$status, $payment]);
        $pdo->exec("INSERT INTO shop_order_items VALUES(15,14,10,8,1,10000);
            INSERT INTO payments VALUES(16,'shop_order',14,'$payment','bank_transfer',10000)");
    }

    public function testBaselineBindsExactFixtureAndRefusesEarlierPendingOrder(): void
    {
        $pdo = $this->database();
        self::assertSame(['ok'=>true,'baseline'=>0,'product_id'=>9,'variant_id'=>10], \kisUatOrderGuard($pdo, 'baseline', 0));
        $this->order($pdo);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('earlier_pending_test_order');
        \kisUatOrderGuard($pdo, 'baseline', 0);
    }

    public function testBaselineNamesExactFailingFixturePredicateWithoutReturningData(): void
    {
        $pdo = $this->database();
        $pdo->exec('UPDATE shop_variants SET amount_minor=9000 WHERE id=10');
        $this->expectException(\KisUatOrderGuardFailure::class);
        $this->expectExceptionMessage('fixture_price_100_czk');
        \kisUatOrderGuard($pdo, 'baseline', 0);
    }

    public function testBaselineNamesDisabledParentPredicate(): void
    {
        $pdo = $this->database();
        $pdo->exec('UPDATE verejni_uzivatele SET aktivni=0 WHERE id=7');
        $this->expectException(\KisUatOrderGuardFailure::class);
        $this->expectExceptionMessage('parent_active_verified');
        \kisUatOrderGuard($pdo, 'baseline', 0);
    }

    public function testCleanupRefusesOrderWithPaymentMarkedPaid(): void
    {
        $pdo = $this->database();
        $this->order($pdo, 'processing', 'paid');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no longer unpaid');
        \kisUatOrderGuard($pdo, 'cleanup', 0);
    }

    public function testCleanupIsIdempotentForAlreadyCancelledExactOrder(): void
    {
        $pdo = $this->database();
        $this->order($pdo, 'cancelled', 'cancelled');
        self::assertSame(['ok'=>true,'matched'=>1,'cancelled'=>1,'already_cancelled'=>true,'order_id'=>14], \kisUatOrderGuard($pdo, 'cleanup', 0));
    }

    public function testCleanupRefusesOrderWithAdditionalItem(): void
    {
        $pdo = $this->database();
        $this->order($pdo);
        $pdo->exec('INSERT INTO shop_order_items VALUES(17,14,99,8,1,10000)');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not match');
        \kisUatOrderGuard($pdo, 'cleanup', 0);
    }
}
