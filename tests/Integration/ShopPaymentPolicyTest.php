<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2) . '/includes/venue_operations.php';
require_once dirname(__DIR__,2) . '/includes/shop_payment_policy.php';

final class ShopPaymentPolicyTest extends TestCase
{
    public function testMigrationDefaultsExistingBusinessDataToBankOnlyAndIsIdempotent(): void
    {
        $pdo = $this->database();
        $migration = require dirname(__DIR__,2) . '/migrations/20260909120000_payment_method_policy.php';
        $migration['up']($pdo);$migration['up']($pdo);
        self::assertTrue($migration['verify']($pdo));
        self::assertSame('bank_transfer',$pdo->query('SELECT payment_method_policy FROM shop_products')->fetchColumn());
        self::assertSame('bank_transfer',$pdo->query('SELECT payment_method_policy FROM individualni_lekce')->fetchColumn());
        self::assertSame('bank_transfer',$pdo->query('SELECT accepted_payment_methods FROM payments')->fetchColumn());
    }

    public function testOrderPolicyAllowsSumUpOnlyWhenEveryItemAllowsIt(): void
    {
        self::assertSame(
            \SHOP_PAYMENT_POLICY_SUMUP_AND_BANK,
            \shopPaymentPolicyForItemGroups(
                [['payment_method_policy'=>\SHOP_PAYMENT_POLICY_SUMUP_AND_BANK]],
                [['payment_method_policy'=>\SHOP_PAYMENT_POLICY_SUMUP_AND_BANK]]
            )
        );
        self::assertSame(
            \SHOP_PAYMENT_POLICY_BANK_ONLY,
            \shopPaymentPolicyForItemGroups(
                [['payment_method_policy'=>\SHOP_PAYMENT_POLICY_SUMUP_AND_BANK]],
                [['payment_method_policy'=>\SHOP_PAYMENT_POLICY_BANK_ONLY]]
            )
        );
    }

    public function testProductAndVelodromeChangesAreAudited(): void
    {
        $pdo = $this->database();
        $migration = require dirname(__DIR__,2) . '/migrations/20260909120000_payment_method_policy.php';
        $migration['up']($pdo);
        self::assertTrue(\shopPaymentPolicySetProduct($pdo,7,1,\SHOP_PAYMENT_POLICY_SUMUP_AND_BANK,'Povolena online karta.',true)['changed']);
        self::assertTrue(\shopPaymentPolicySetVelodromeSlot($pdo,7,2,\SHOP_PAYMENT_POLICY_SUMUP_AND_BANK,'Povolena online karta pro termin.',true)['changed']);
        self::assertSame('update_payment_policy',$pdo->query('SELECT action FROM shop_catalog_admin_events')->fetchColumn());
        self::assertSame('update_payment_policy',$pdo->query('SELECT action FROM venue_operation_events')->fetchColumn());
        self::assertSame(\SHOP_PAYMENT_POLICY_SUMUP_AND_BANK,$pdo->query('SELECT payment_method_policy FROM shop_products')->fetchColumn());
        self::assertSame(\SHOP_PAYMENT_POLICY_SUMUP_AND_BANK,$pdo->query('SELECT payment_method_policy FROM individualni_lekce')->fetchColumn());
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $pdo->exec('CREATE TABLE shop_products(id INTEGER PRIMARY KEY,name TEXT,catalog_status TEXT,updated_at TEXT)');
        $pdo->exec("INSERT INTO shop_products VALUES(1,'Kroužek','active',CURRENT_TIMESTAMP)");
        $pdo->exec('CREATE TABLE individualni_lekce(id INTEGER PRIMARY KEY,sportoviste_id INTEGER,nazev TEXT,cena_kc REAL,datum TEXT,cas_od TEXT,cas_do TEXT,stav TEXT,public_exclusive_booking INTEGER)');
        $pdo->exec("INSERT INTO individualni_lekce VALUES(2,3,'Velodrom',250,'2030-01-01','10:00','11:00','aktivni',0)");
        $pdo->exec('CREATE TABLE payments(id INTEGER PRIMARY KEY,method TEXT)');
        $pdo->exec("INSERT INTO payments VALUES(4,'bank_transfer')");
        $pdo->exec('CREATE TABLE sportovist(id INTEGER PRIMARY KEY,kod TEXT)');
        $pdo->exec("INSERT INTO sportovist VALUES(3,'velodrom')");
        $pdo->exec('CREATE TABLE shop_product_publications(product_id INTEGER PRIMARY KEY,status TEXT,public_name TEXT)');
        $pdo->exec("INSERT INTO shop_product_publications VALUES(1,'active','Kroužek')");
        $pdo->exec('CREATE TABLE shop_catalog_admin_events(id INTEGER PRIMARY KEY AUTOINCREMENT,product_id INTEGER,variant_id INTEGER,actor_type TEXT,actor_id INTEGER,action TEXT,before_json TEXT,after_json TEXT,reason TEXT)');
        $pdo->exec('CREATE TABLE venue_operation_events(id INTEGER PRIMARY KEY AUTOINCREMENT,target_type TEXT,target_id INTEGER,actor_trainer_id INTEGER,action TEXT,reason TEXT,payload_json TEXT)');
        return $pdo;
    }
}
