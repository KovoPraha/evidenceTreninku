<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/shop_storefront.php';

final class ShopStorefrontTest extends TestCase
{
    public function testCatalogGroupsVariantsAndReturnsOnlyReviewedPublicContent(): void
    {
        $pdo = $this->database();
        $catalog = \shopStorefrontCatalog($pdo);

        self::assertCount(1, $catalog);
        self::assertSame(501, $catalog[0]['product_id']);
        self::assertSame('Dres KOVO', $catalog[0]['public_name']);
        self::assertSame('Schválený stručný popis.', $catalog[0]['public_summary']);
        self::assertCount(2, $catalog[0]['variants']);
        $stockBySku = [];
        foreach ($catalog[0]['variants'] as $variant) {
            $stockBySku[$variant['sku']] = $variant['in_stock'];
        }
        self::assertTrue($stockBySku['DRES-M']);
        self::assertFalse($stockBySku['DRES-L']);
        self::assertSame(['https://cdn.example.test/dres.jpg'], $catalog[0]['images']);
        self::assertSame(['Oblečení > Dresy','Klubové zboží'],$catalog[0]['categories']);
        self::assertArrayNotHasKey('description_html_untrusted', $catalog[0]);
        self::assertStringNotContainsString('script', json_encode($catalog, JSON_THROW_ON_ERROR));
    }

    public function testDetailDoesNotRevealDraftInactiveOrUnsupportedProducts(): void
    {
        $pdo = $this->database();

        self::assertSame(501, \shopStorefrontProductDetail($pdo, 501)['product_id']);
        self::assertNull(\shopStorefrontProductDetail($pdo, 502));
        self::assertNull(\shopStorefrontProductDetail($pdo, 503));
        self::assertNull(\shopStorefrontProductDetail($pdo, 504));
        self::assertNull(\shopStorefrontProductDetail($pdo, 0));
    }

    public function testImageAllowListRejectsUnsafeSchemesCredentialsAndControls(): void
    {
        self::assertSame('https://cdn.example.test/a.jpg?x=1', \shopStorefrontSafeImageUrl('https://cdn.example.test/a.jpg?x=1'));
        self::assertSame(
            \appUrl('uploads/shop-products/0123456789abcdef0123456789abcdef.jpg'),
            \shopStorefrontSafeImageUrl('uploads/shop-products/0123456789abcdef0123456789abcdef.jpg')
        );
        self::assertNull(\shopStorefrontSafeImageUrl('http://cdn.example.test/a.jpg'));
        self::assertNull(\shopStorefrontSafeImageUrl('javascript:alert(1)'));
        self::assertNull(\shopStorefrontSafeImageUrl('https://user:pass@cdn.example.test/a.jpg'));
        self::assertNull(\shopStorefrontSafeImageUrl("https://cdn.example.test/a.jpg\nX-Test: bad"));
        self::assertNull(\shopStorefrontSafeImageUrl('uploads/shop-products/../../config.php'));
        self::assertNull(\shopStorefrontSafeImageUrl('uploads/shop-products/not-random.jpg'));
        self::assertNull(\shopStorefrontSafeImageUrl('/uploads/shop-products/0123456789abcdef0123456789abcdef.jpg'));
        self::assertTrue(\shopStorefrontIsLocalImageUrl(\appUrl('uploads/shop-products/0123456789abcdef0123456789abcdef.jpg')));
        self::assertFalse(\shopStorefrontIsLocalImageUrl('https://attacker.example/uploads/shop-products/0123456789abcdef0123456789abcdef.jpg'));
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('CREATE TABLE shop_products(id INTEGER PRIMARY KEY,offer_type TEXT,catalog_status TEXT,description_html_untrusted TEXT)');
        $pdo->exec("INSERT INTO shop_products VALUES(501,'goods','active','<script>alert(1)</script>'),(502,'goods','draft','draft'),(503,'goods','active','inactive publication'),(504,'bookable_service','active','service')");
        $pdo->exec('CREATE TABLE shop_product_publications(product_id INTEGER PRIMARY KEY,status TEXT,public_name TEXT,public_summary TEXT)');
        $pdo->exec("INSERT INTO shop_product_publications VALUES(501,'active','Dres KOVO','Schválený stručný popis.'),(502,'active','Koncept','Nezobrazit.'),(503,'inactive','Skryté','Nezobrazit.'),(504,'active','Služba','Nezobrazit jako zboží.')");
        $pdo->exec('CREATE TABLE shop_variants(id INTEGER PRIMARY KEY,product_id INTEGER,sku TEXT,attributes_json TEXT,price_mode TEXT,amount_minor INTEGER,currency TEXT,stock_quantity_decimal TEXT,visible INTEGER,catalog_status TEXT)');
        $pdo->exec("INSERT INTO shop_variants VALUES(601,501,'DRES-M','{\"Velikost\":\"M\"}','fixed',120000,'CZK','2.000000',1,'active'),(602,501,'DRES-L','{\"Velikost\":\"L\"}','fixed',125000,'CZK','0.000000',1,'active'),(603,501,'DRES-XL','{}','fixed',130000,'CZK','1.000000',0,'inactive'),(604,502,'DRAFT','{}','fixed',100,'CZK',NULL,1,'active'),(605,503,'HIDDEN','{}','fixed',100,'CZK',NULL,1,'active'),(606,504,'SERVICE','{}','fixed',100,'CZK',NULL,1,'active')");
        $pdo->exec('CREATE TABLE shop_product_images(id INTEGER PRIMARY KEY,product_id INTEGER,image_url TEXT,sort_order INTEGER)');
        $pdo->exec("INSERT INTO shop_product_images VALUES(1,501,'https://cdn.example.test/dres.jpg',0),(2,501,'http://cdn.example.test/insecure.jpg',1),(3,501,'javascript:alert(1)',2),(4,502,'https://cdn.example.test/draft.jpg',0)");
        $pdo->exec('CREATE TABLE shop_product_categories(id INTEGER PRIMARY KEY,product_id INTEGER,category_path TEXT,is_default INTEGER,sort_order INTEGER)');
        $pdo->exec("INSERT INTO shop_product_categories VALUES(1,501,'Oblečení > Dresy',1,0),(2,501,'Klubové zboží',0,1),(3,502,'Koncepty',1,0)");
        return $pdo;
    }
}
