<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDOException;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/shop_public_navigation.php';
require_once dirname(__DIR__) . '/Support/StorefrontReadFixture.php';

final class StorefrontReadCompatibilityTest extends TestCase
{
    public function testSchemaMetadataIsRefreshedAfterChangesAndBetweenConnections(): void
    {
        $pdo = \Tests\Support\storefrontReadFixture(1, false);
        $other = \Tests\Support\storefrontReadFixture(1, false);
        $pdo->exec('UPDATE shop_variants SET stock_quantity_decimal=NULL');
        self::assertSame([], \shopStorefrontProducts($pdo));
        $pdo->exec('DROP TABLE shop_stock_balances');
        self::assertCount(1, \shopStorefrontProducts($pdo));
        $other->exec('UPDATE shop_variants SET stock_quantity_decimal=NULL');
        self::assertSame([], \shopStorefrontProducts($other));
        $pdo->exec('CREATE TABLE shop_stock_balances(variant_id INTEGER PRIMARY KEY)');
        self::assertSame([], \shopStorefrontProducts($pdo));
        $pdo->exec("UPDATE shop_variants SET stock_quantity_decimal='0.000000'");
        self::assertSame(0, (int) \shopStorefrontProducts($pdo)[0]['sort_order']);
        $pdo->exec('ALTER TABLE shop_products ADD COLUMN sort_order INTEGER DEFAULT 7');
        self::assertSame(7, (int) \shopStorefrontProducts($pdo)[0]['sort_order']);
        self::assertFalse(\shopStorefrontProductDetail($pdo, 1)['variants'][0]['in_stock']);
    }

    public function testBrokenInventorySchemaIsNotTreatedAsMissingInventory(): void
    {
        $pdo = \Tests\Support\storefrontReadFixture(1, false);
        $pdo->exec('DROP TABLE shop_stock_balances');
        $pdo->exec('CREATE TABLE shop_stock_balances(broken_id INTEGER)');
        $this->expectException(PDOException::class);
        \shopStorefrontProducts($pdo);
    }

    public function testDetailPreservesCatalogPresentationAndDoesNotReadOtherVariants(): void
    {
        $pdo = \Tests\Support\storefrontReadFixture(3, false);
        $pdo->exec("UPDATE shop_variants SET attributes_json='{\"Velikost\":\"M\"}' WHERE id=1");
        $pdo->exec("INSERT INTO shop_variants SELECT 10,product_id,'EXTRA','{}',price_mode,amount_minor,currency,
            stock_quantity_decimal,visible,catalog_status FROM shop_variants WHERE id=1");
        $pdo->exec("INSERT INTO shop_product_images VALUES(1,1,'https://example.invalid/image.jpg',0)");
        $catalog = \shopStorefrontCatalog($pdo);
        $pdo->productRows = 0;
        self::assertSame($catalog[0], \shopStorefrontProductDetail($pdo, 1));
        self::assertSame(2, $pdo->productRows);
        self::assertNull(\shopStorefrontProductDetail($pdo, 999));
        self::assertNull(\shopStorefrontProductDetail($pdo, -1));
        $pdo->exec("UPDATE shop_product_publications SET status='draft' WHERE product_id=1");
        self::assertNull(\shopStorefrontProductDetail($pdo, 1));
    }

    public function testNavigationRetainsImportedExceptionsAndRejectsUnlinkedManualCourses(): void
    {
        $pdo = \Tests\Support\storefrontReadFixture(5, false);
        $pdo->exec("UPDATE shop_product_publications SET public_name='Cyklistický kroužek' WHERE product_id IN (2,3)");
        $pdo->exec("UPDATE shop_products SET origin='import',external_product_key='shoptet:pair:3' WHERE id=3");
        $pdo->exec("UPDATE shop_product_categories SET category_path='Kurzy' WHERE product_id IN (2,3)");
        $pdo->exec("UPDATE shop_product_categories SET category_path='Skryté' WHERE product_id=5");
        $pdo->exec("INSERT INTO shop_category_meta VALUES('Skryté','Skryté',NULL,0,0,'')");
        // An inactive link still classifies the entire product as a program offer.
        $pdo->exec("INSERT INTO club_programs VALUES(1,'active','Program')");
        $pdo->exec("INSERT INTO club_program_offers VALUES(1,4,4,1,'inactive')");
        self::assertSame([3,1,5], array_column(\shopPublicSaleableCatalog($pdo), 'product_id'));
        $menu = \shopPublicNavigationItems($pdo);
        self::assertSame(['Kurzy','Test','Test > Goods'], array_column($menu, 'category_path'));
        self::assertSame([1,1,1], array_column($menu, 'product_count'));
        self::assertSame(\shopStorefrontCategoryMenu($pdo, \shopPublicSaleableCatalog($pdo)), $menu);
    }

    public function testAthleteCategoryClassificationUsesTheSameReadData(): void
    {
        $pdo = \Tests\Support\storefrontReadFixture(1, false);
        $pdo->exec("UPDATE shop_product_categories SET category_path='Cyklistické kroužky'");
        self::assertTrue(\shopProductRequiresAthlete($pdo, 1));
        self::assertSame([], \shopPublicNavigationItems($pdo));
        $pdo->exec("UPDATE shop_product_categories SET category_path='Test > Goods'");
        self::assertFalse(\shopProductRequiresAthlete($pdo, 1));
        self::assertCount(2, \shopPublicNavigationItems($pdo));
    }

    public function testProgramsKeepCurrentCapacityTermsAndSalesWindowChecks(): void
    {
        $pdo = \Tests\Support\storefrontReadFixture(1, false);
        $pdo->exec("UPDATE shop_products SET offer_type='program'");
        $pdo->exec("INSERT INTO club_programs VALUES(1,'active','Program')");
        $pdo->exec('ALTER TABLE club_program_offers ADD COLUMN ends_on TEXT');
        $pdo->exec('ALTER TABLE club_program_offers ADD COLUMN sales_open_at TEXT');
        $pdo->exec('ALTER TABLE club_program_offers ADD COLUMN sales_close_at TEXT');
        $pdo->exec('ALTER TABLE club_program_offers ADD COLUMN capacity INTEGER');
        $pdo->exec("INSERT INTO club_program_offers VALUES(1,1,1,1,'active','2099-12-31',NULL,NULL,1)");
        $pdo->exec('CREATE TABLE club_program_enrollments(id INTEGER PRIMARY KEY,offer_id INTEGER,status TEXT)');
        $pdo->exec('CREATE TABLE club_event_term_versions(id INTEGER PRIMARY KEY,scope_type TEXT,scope_key TEXT,
            consent_purpose TEXT,terms_version TEXT,consent_text_plain TEXT,status TEXT)');
        // Missing published terms hide the offer, even though capacity is free.
        self::assertSame([], \shopPublicNavigationItems($pdo));
        $pdo->exec("INSERT INTO club_event_term_versions VALUES
            (1,'club_program','program:1','program_cancellation','v1','Storno','active'),
            (2,'club_program','program:1','program_consent','v1','Souhlas','active')");
        self::assertCount(2, \shopPublicNavigationItems($pdo));
        $pdo->exec("INSERT INTO club_program_enrollments VALUES(1,1,'active')");
        self::assertSame([], \shopPublicNavigationItems($pdo));
        $pdo->exec("UPDATE club_program_enrollments SET status='cancelled'");
        self::assertCount(2, \shopPublicNavigationItems($pdo));
        $pdo->exec("UPDATE club_program_offers SET sales_open_at='2099-01-01 00:00:00'");
        self::assertSame([], \shopPublicNavigationItems($pdo));
        $pdo->exec("UPDATE club_program_offers SET sales_open_at='2026-10-28 10:00:00',sales_close_at='2026-10-28 11:00:00'");
        $zone = new \DateTimeZone('Europe/Prague');
        foreach (['09:59:59' => 0, '10:00:00' => 1, '11:00:00' => 1, '11:00:01' => 0] as $time => $expected) {
            self::assertCount($expected, \shopStorefrontProducts($pdo, new \DateTimeImmutable('2026-10-28 ' . $time, $zone)));
        }
    }
}
