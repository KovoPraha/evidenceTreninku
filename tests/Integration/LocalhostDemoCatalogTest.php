<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/localhost_demo_catalog.php';

final class LocalhostDemoCatalogTest extends TestCase
{
    public function testProgramHasItsOwnProductAndDoesNotConsumeTheOnlyImportedGoods(): void
    {
        $pdo = $this->database();
        $before = $pdo->query('SELECT * FROM shop_products WHERE id=1')->fetch();
        $program = \localhostDemoCatalogEnsureProduct($pdo, 7, 'program');
        self::assertNotSame(1, $program['id']);
        self::assertSame($before, $pdo->query('SELECT * FROM shop_products WHERE id=1')->fetch());
        self::assertSame('program', $pdo->query('SELECT offer_type FROM shop_products WHERE id=' . $program['id'])->fetchColumn());
        self::assertNull($pdo->query('SELECT stock_quantity_decimal FROM shop_variants WHERE id=' . $program['variant_id'])->fetchColumn());
        self::assertSame($program, \localhostDemoCatalogEnsureProduct($pdo, 7, 'program'));
        self::assertSame(2, (int)$pdo->query('SELECT COUNT(*) FROM shop_products')->fetchColumn());
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM shop_catalog_admin_events')->fetchColumn());
    }

    public function testOldDemoWithNoGoodsGetsStableSeparateGoodsWithoutChangingHistoricalProgram(): void
    {
        $pdo = $this->database();
        $pdo->exec("UPDATE shop_products SET offer_type='program' WHERE id=1");
        $before = $pdo->query('SELECT * FROM shop_products WHERE id=1')->fetch();
        $goods = \localhostDemoCatalogEnsureProduct($pdo, 7, 'goods');
        self::assertNotSame(1, $goods['id']);
        self::assertSame($before, $pdo->query('SELECT * FROM shop_products WHERE id=1')->fetch());
        self::assertSame('goods', $pdo->query('SELECT offer_type FROM shop_products WHERE id=' . $goods['id'])->fetchColumn());
        self::assertSame('12.000000', $pdo->query('SELECT stock_quantity_decimal FROM shop_variants WHERE id=' . $goods['variant_id'])->fetchColumn());
        self::assertSame($goods, \localhostDemoCatalogEnsureProduct($pdo, 7, 'goods'));
        self::assertSame(2, (int)$pdo->query('SELECT COUNT(*) FROM shop_products')->fetchColumn());
    }

    public function testDemoSkuCollisionFailsWithoutReusingAnotherProduct(): void
    {
        $pdo = $this->database();
        $pdo->exec("UPDATE shop_variants SET sku='KP-LOCAL-DEMO-PROGRAM' WHERE id=1");
        try {
            \localhostDemoCatalogEnsureProduct($pdo, 7, 'program');
            self::fail('An imported product must never be reused as the demo program.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('another product', $exception->getMessage());
        }
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM shop_products')->fetchColumn());
        self::assertSame('goods', $pdo->query('SELECT offer_type FROM shop_products WHERE id=1')->fetchColumn());
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $pdo->exec(<<<'SQL'
            CREATE TABLE shop_products (
                id INTEGER PRIMARY KEY AUTOINCREMENT, source_candidate_id INTEGER, source_run_id INTEGER,
                origin TEXT DEFAULT 'import', created_by_trainer_id INTEGER, external_product_key TEXT UNIQUE,
                name TEXT, short_description TEXT, offer_type TEXT, visibility TEXT, item_type TEXT,
                catalog_status TEXT DEFAULT 'draft', updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE shop_variants (
                id INTEGER PRIMARY KEY AUTOINCREMENT, product_id INTEGER, source_candidate_id INTEGER,
                origin TEXT DEFAULT 'import', created_by_trainer_id INTEGER, sku TEXT UNIQUE, ean TEXT,
                attributes_json TEXT, price_mode TEXT, amount_minor INTEGER, compare_at_amount_minor INTEGER,
                currency TEXT, includes_vat INTEGER, vat_rate_basis_points INTEGER, stock_quantity_decimal TEXT,
                unit_code TEXT, visible INTEGER, catalog_status TEXT, updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE shop_catalog_admin_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT, product_id INTEGER, variant_id INTEGER,
                actor_type TEXT, actor_id INTEGER, action TEXT, before_json TEXT, after_json TEXT, reason TEXT
            );
            INSERT INTO shop_products(id,external_product_key,name,offer_type) VALUES(1,'shoptet:only-goods','Jediné zboží','goods');
            INSERT INTO shop_variants(id,product_id,sku,stock_quantity_decimal) VALUES(1,1,'IMPORT-ONLY','5.000000');
            SQL);
        return $pdo;
    }
}
