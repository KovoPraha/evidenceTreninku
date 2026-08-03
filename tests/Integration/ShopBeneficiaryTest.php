<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/shop_checkout.php';

final class ShopBeneficiaryTest extends TestCase
{
    private const BANK = ['iban'=>'CZ6508000000192000145399','bic'=>'GIBACZPX','account_label'=>'KOVO Praha','due_days'=>7];

    public function testMigrationIsRepeatableAndKeepsExistingGoodsNullable(): void
    {
        $pdo = $this->database();
        $migration = require dirname(__DIR__, 2) . '/migrations/20260804120000_shop_item_beneficiaries.php';
        $migration['up']($pdo);
        self::assertTrue($migration['verify']($pdo));
        \shopCartSetQuantity($pdo, 10, 601, 1);
        self::assertNull($pdo->query('SELECT beneficiary_sportovec_id FROM shop_cart_items')->fetchColumn() ?: null);
        try {
            $pdo->exec('UPDATE shop_cart_items SET beneficiary_sportovec_id=999');
            self::fail('SQLite beneficiary foreign key must reject a missing sportovec.');
        } catch (\PDOException) {
        }
        $order = \shopCheckoutPlace($pdo, 10, bin2hex(random_bytes(16)), self::BANK, \shopCartDetail($pdo, 10)['fingerprint']);
        self::assertNull($order['items'][0]['beneficiary_sportovec_id']);
    }

    public function testGuardianCanSelectEitherChildAndSelfSeesOnlyOwnSnapshot(): void
    {
        $pdo = $this->database();
        \shopCartSetQuantity($pdo, 10, 601, 1);
        $itemId = (int)$pdo->query('SELECT id FROM shop_cart_items')->fetchColumn();
        \shopCartSetBeneficiary($pdo, 10, $itemId, 102);
        self::assertSame(102, (int)\shopCartDetail($pdo, 10)['items'][0]['beneficiary_sportovec_id']);
        \shopCartSetBeneficiary($pdo, 10, $itemId, null);
        self::assertNull(\shopCartDetail($pdo, 10)['items'][0]['beneficiary_sportovec_id']);
        \shopCartSetBeneficiary($pdo, 10, $itemId, 101);
        $order = \shopCheckoutPlace($pdo, 10, bin2hex(random_bytes(16)), self::BANK, \shopCartDetail($pdo, 10)['fingerprint']);
        self::assertSame(101, (int)$order['items'][0]['beneficiary_sportovec_id']);
        self::assertCount(1, \shopBeneficiaryOrderItemsForAccount($pdo, 10));
        self::assertCount(1, \shopBeneficiaryOrderItemsForAccount($pdo, 12));
        self::assertSame('self', \shopBeneficiaryOrderItemsForAccount($pdo, 12)[0]['viewer_relation_role']);
        self::assertSame([], \shopBeneficiaryOrderItemsForAccount($pdo, 14));
        self::assertSame([], \shopBeneficiaryOrderItemsForAccount($pdo, 13));
    }

    public function testForeignRevokedAndIdorAssignmentsFailWithoutChangingCart(): void
    {
        $pdo = $this->database();
        \shopCartSetQuantity($pdo, 10, 601, 1);
        \shopCartSetQuantity($pdo, 11, 601, 1);
        $parentItem = (int)$pdo->query('SELECT ci.id FROM shop_cart_items ci JOIN shop_carts c ON c.id=ci.cart_id WHERE c.account_id=10')->fetchColumn();
        $foreignItem = (int)$pdo->query('SELECT ci.id FROM shop_cart_items ci JOIN shop_carts c ON c.id=ci.cart_id WHERE c.account_id=11')->fetchColumn();
        foreach ([[10, $parentItem, 103], [13, $parentItem, 102], [10, $foreignItem, 101]] as [$account, $item, $beneficiary]) {
            try {
                \shopCartSetBeneficiary($pdo, $account, $item, $beneficiary);
                self::fail('Unauthorized beneficiary assignment must fail.');
            } catch (\ShopCheckoutException) {
            }
        }
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM shop_cart_items WHERE beneficiary_sportovec_id IS NOT NULL')->fetchColumn());
        self::assertFalse($pdo->inTransaction());
    }

    public function testBeneficiaryChangeInvalidatesFingerprintAndRevocationRollsBackCheckout(): void
    {
        $pdo = $this->database();
        \shopCartSetQuantity($pdo, 10, 601, 1);
        $itemId = (int)$pdo->query('SELECT id FROM shop_cart_items')->fetchColumn();
        $withoutBeneficiary = \shopCartDetail($pdo, 10)['fingerprint'];
        \shopCartSetBeneficiary($pdo, 10, $itemId, 101);
        self::assertNotSame($withoutBeneficiary, \shopCartDetail($pdo, 10)['fingerprint']);
        $fingerprint = \shopCartDetail($pdo, 10)['fingerprint'];
        $pdo->exec("UPDATE account_person_roles SET status='revoked',valid_to=CURRENT_TIMESTAMP WHERE account_id=10 AND sportovec_id=101");
        try {
            \shopCheckoutPlace($pdo, 10, bin2hex(random_bytes(16)), self::BANK, $fingerprint);
            self::fail('Checkout must revalidate a revoked beneficiary.');
        } catch (\ShopCheckoutException) {
        }
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM shop_orders')->fetchColumn());
        self::assertSame(5.0, (float)$pdo->query('SELECT stock_quantity_decimal FROM shop_variants WHERE id=601')->fetchColumn());
        self::assertSame('active', $pdo->query('SELECT status FROM shop_carts')->fetchColumn());
        self::assertFalse($pdo->inTransaction());
    }

    public function testSnapshotSurvivesRelationRevocationButAccessDoesNot(): void
    {
        $pdo = $this->database();
        \shopCartSetQuantity($pdo, 10, 601, 1);
        $itemId = (int)$pdo->query('SELECT id FROM shop_cart_items')->fetchColumn();
        \shopCartSetBeneficiary($pdo, 10, $itemId, 101);
        \shopCheckoutPlace($pdo, 10, bin2hex(random_bytes(16)), self::BANK, \shopCartDetail($pdo, 10)['fingerprint']);
        $pdo->exec("UPDATE account_person_roles SET status='revoked',valid_to=CURRENT_TIMESTAMP WHERE account_id=10 AND sportovec_id=101");
        self::assertSame(101, (int)$pdo->query('SELECT beneficiary_sportovec_id FROM shop_order_items')->fetchColumn());
        self::assertSame([], \shopBeneficiaryOrderItemsForAccount($pdo, 10));
        self::assertCount(1, \shopBeneficiaryOrderItemsForAccount($pdo, 12));
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,email TEXT,aktivni INTEGER,email_overeno INTEGER)');
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT)');
        $pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT)');
        $pdo->exec("INSERT INTO treneri VALUES(7,'Admin')");
        $pdo->exec("INSERT INTO verejni_uzivatele VALUES(10,'Rodič','Test','parent@example.test',1,1),(11,'Cizí','Účet','foreign@example.test',1,1),(12,'Dítě','Jedna','child@example.test',1,1),(13,'Bývalý','Rodič','revoked@example.test',1,1),(14,'Bez','Vazby','none@example.test',1,1)");
        $pdo->exec("INSERT INTO sportovci VALUES(101,'Dítě','Jedna'),(102,'Dítě','Dvě'),(103,'Cizí','Osoba')");
        $roleMigration = require dirname(__DIR__, 2) . '/migrations/20260802230000_account_person_roles.php';
        $roleMigration['up']($pdo);
        $insertRole = $pdo->prepare('INSERT INTO account_person_roles(account_id,sportovec_id,relation_role,status,source,valid_from,valid_to,created_by_trainer_id,approved_by_trainer_id,decision_note) VALUES (?,?,?,?,\'admin\',CURRENT_TIMESTAMP,?,?,7,\'test\')');
        $insertRole->execute([10,101,'guardian','approved',null,7]);
        $insertRole->execute([10,102,'guardian','approved',null,7]);
        $insertRole->execute([11,103,'guardian','approved',null,7]);
        $insertRole->execute([12,101,'self','approved',null,7]);
        $insertRole->execute([13,102,'guardian','revoked','2026-01-01 00:00:00',7]);
        $pdo->exec('CREATE TABLE shop_products(id INTEGER PRIMARY KEY,offer_type TEXT,catalog_status TEXT)');
        $pdo->exec("INSERT INTO shop_products VALUES(501,'goods','active')");
        $pdo->exec('CREATE TABLE shop_variants(id INTEGER PRIMARY KEY,product_id INTEGER,sku TEXT,attributes_json TEXT,price_mode TEXT,amount_minor INTEGER,currency TEXT,includes_vat INTEGER,vat_rate_basis_points INTEGER,stock_quantity_decimal TEXT,visible INTEGER,catalog_status TEXT,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec("INSERT INTO shop_variants VALUES(601,501,'TRIKO-M','{\"size\":\"M\"}','fixed',12500,'CZK',1,2100,'5.000000',1,'active',CURRENT_TIMESTAMP)");
        $pdo->exec('CREATE TABLE shop_product_publications(product_id INTEGER PRIMARY KEY,status TEXT,public_name TEXT,public_summary TEXT)');
        $pdo->exec("INSERT INTO shop_product_publications VALUES(501,'active','Tričko KOVO','Klubové tričko.')");
        foreach (['20260803230000_shop_checkout.php','20260804010000_shop_order_fulfillment.php','20260804030000_shop_order_refunds.php','20260804050000_shop_coupons.php','20260804120000_shop_item_beneficiaries.php'] as $filename) {
            $migration = require dirname(__DIR__, 2) . '/migrations/' . $filename;
            $migration['up']($pdo);
            self::assertTrue($migration['verify']($pdo));
        }
        return $pdo;
    }
}
