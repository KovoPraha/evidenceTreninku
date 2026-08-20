<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/includes/shop_catalog_management.php';

final class ShopCatalogManagementTest extends TestCase
{
    public function testBulkActivationUsesReadinessForEveryProductAndRollsBackAll():void
    {
        $pdo=$this->database();
        try{\shopCatalogBulkActivate($pdo,7,[1,2],[1=>'Veřejný první',2=>'Veřejný druhý'],[1=>'Bezpečný popis prvního.',2=>'Bezpečný popis druhého.'],'Hromadná aktivace pro test.',true);self::fail('Invalid second product must roll back the first activation.');}
        catch(\ShopCatalogPublicationException$exception){self::assertStringContainsString('cenu',$exception->getMessage());}
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM shop_product_publications')->fetchColumn());
        self::assertSame(0,(int)$pdo->query("SELECT COUNT(*) FROM shop_products WHERE catalog_status='active'")->fetchColumn());self::assertFalse($pdo->inTransaction());
        $pdo->exec('UPDATE shop_variants SET amount_minor=2000 WHERE id=22');
        self::assertSame(2,\shopCatalogBulkActivate($pdo,7,[1,2],[1=>'Veřejný první',2=>'Veřejný druhý'],[1=>'Bezpečný popis prvního.',2=>'Bezpečný popis druhého.'],'Druhá hromadná aktivace.',true));
        self::assertSame(2,(int)$pdo->query("SELECT COUNT(*) FROM shop_products WHERE catalog_status='active'")->fetchColumn());
    }

    public function testStockCorrectionAlwaysCreatesDedicatedMovementAndAudit():void
    {
        $pdo=$this->database();$result=\shopCatalogAdjustStock($pdo,7,11,'7,5','Inventura skladu.',true);self::assertTrue($result['changed']);
        $movement=$pdo->query('SELECT * FROM shop_inventory_movements')->fetch(PDO::FETCH_ASSOC);self::assertSame('manual_adjustment',$movement['movement_type']);self::assertSame('2.500000',$movement['quantity_delta_decimal']);self::assertSame('7.500000',$movement['stock_after_decimal']);self::assertSame('Inventura skladu.',$movement['reason']);
        self::assertSame('adjust_stock',$pdo->query('SELECT action FROM shop_catalog_admin_events')->fetchColumn());
    }

    public function testOverviewFiltersOrderingAndBulkCategoryAreConsistent():void
    {
        $pdo=$this->database();$rows=\shopCatalogManagementProducts($pdo);$overview=\shopCatalogManagementOverview($rows);self::assertSame(2,$overview['draft']);self::assertSame(1,$overview['missing_image']);self::assertSame(1,$overview['missing_category']);self::assertSame(1,$overview['missing_price']);
        $pdo->exec("INSERT INTO shop_product_publications(product_id,status,public_name,public_summary,decision_note) VALUES(1,'draft','Bunda veřejně','Teplá bunda.','Koncept')");$rows=\shopCatalogManagementProducts($pdo);self::assertSame([1],array_column(\shopCatalogManagementFilter($rows,['q'=>'teplá','origin'=>'manual','sort'=>'name']),'id'));
        self::assertSame(2,\shopCatalogBulkAssignCategory($pdo,7,[1,2],'Oblečení','Sjednocení kategorie.',true));self::assertSame(2,(int)$pdo->query("SELECT COUNT(*) FROM shop_product_categories WHERE category_path='Oblečení' AND is_default=1")->fetchColumn());
        self::assertSame(2,\shopCatalogBulkSetOrder($pdo,7,[1,2],[1=>20,2=>10],'Nastavení pořadí.',true));$ordered=\shopCatalogManagementFilter(\shopCatalogManagementProducts($pdo),['sort'=>'order']);self::assertSame([2,1],array_map('intval',array_column($ordered,'id')));
    }

    private function database():PDO
    {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT)');$pdo->exec("INSERT INTO treneri VALUES(7,'Admin')");
        $pdo->exec("CREATE TABLE shop_products(id INTEGER PRIMARY KEY,source_candidate_id INTEGER NULL,source_run_id INTEGER NULL,origin TEXT,created_by_trainer_id INTEGER NULL,external_product_key TEXT,name TEXT,short_description TEXT,offer_type TEXT,visibility TEXT,item_type TEXT,catalog_status TEXT,sort_order INTEGER DEFAULT 0,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)");
        $pdo->exec("INSERT INTO shop_products VALUES(1,NULL,NULL,'manual',7,'manual:1','První bunda','Interní','goods','visible','product','draft',0,CURRENT_TIMESTAMP),(2,2,1,'import',NULL,'shoptet:2','Druhý produkt','Interní','goods','visible','product','draft',0,CURRENT_TIMESTAMP)");
        $pdo->exec('CREATE TABLE shop_variants(id INTEGER PRIMARY KEY,product_id INTEGER,sku TEXT,attributes_json TEXT,price_mode TEXT,amount_minor INTEGER NULL,currency TEXT,stock_quantity_decimal TEXT NULL,visible INTEGER,catalog_status TEXT,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');$pdo->exec("INSERT INTO shop_variants VALUES(11,1,'KP-FIRST','{}','fixed',1000,'CZK','5.000000',1,'draft',CURRENT_TIMESTAMP),(22,2,'IMPORT-2','{}','fixed',NULL,'CZK',NULL,1,'draft',CURRENT_TIMESTAMP)");
        $pdo->exec('CREATE TABLE shop_product_publications(product_id INTEGER PRIMARY KEY,status TEXT,public_name TEXT,public_summary TEXT,decision_note TEXT,activated_by_trainer_id INTEGER NULL,activated_at TEXT NULL,deactivated_at TEXT NULL,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec('CREATE TABLE shop_product_publication_events(id INTEGER PRIMARY KEY AUTOINCREMENT,product_id INTEGER,actor_trainer_id INTEGER,action TEXT,from_status TEXT,to_status TEXT,public_name TEXT,public_summary TEXT,note TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec('CREATE TABLE shop_product_images(id INTEGER PRIMARY KEY,product_id INTEGER,image_url TEXT,sort_order INTEGER)');$pdo->exec("INSERT INTO shop_product_images VALUES(1,1,'/x.jpg',0)");
        $pdo->exec('CREATE TABLE shop_product_categories(id INTEGER PRIMARY KEY AUTOINCREMENT,product_id INTEGER,category_path TEXT,is_default INTEGER,sort_order INTEGER,UNIQUE(product_id,category_path))');$pdo->exec("INSERT INTO shop_product_categories(product_id,category_path,is_default,sort_order) VALUES(1,'Oblečení',1,0)");
        $pdo->exec('CREATE TABLE shop_category_meta(category_path TEXT PRIMARY KEY,display_name TEXT,parent_path TEXT NULL,sort_order INTEGER,visible_in_menu INTEGER,description TEXT NULL,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');$pdo->exec("INSERT INTO shop_category_meta VALUES('Oblečení','Oblečení',NULL,0,1,NULL,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
        $pdo->exec('CREATE TABLE shop_member_category_rules(id INTEGER PRIMARY KEY,category_path TEXT)');
        $pdo->exec('CREATE TABLE shop_catalog_admin_events(id INTEGER PRIMARY KEY AUTOINCREMENT,product_id INTEGER,variant_id INTEGER NULL,actor_type TEXT,actor_id INTEGER,action TEXT,before_json TEXT NULL,after_json TEXT,reason TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec('CREATE TABLE shop_inventory_movements(id INTEGER PRIMARY KEY AUTOINCREMENT,variant_id INTEGER,order_id INTEGER NULL,order_item_id INTEGER NULL,movement_type TEXT,actor_type TEXT NULL,actor_id INTEGER NULL,reason TEXT NULL,quantity_delta_decimal TEXT,stock_after_decimal TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP)');return$pdo;
    }
}
