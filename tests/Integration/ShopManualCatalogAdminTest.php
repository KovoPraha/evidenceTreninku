<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2) . '/includes/shop_manual_catalog.php';
require_once dirname(__DIR__,2) . '/includes/shop_catalog_management.php';

final class ShopManualCatalogAdminTest extends TestCase
{
    public function testMigrationCreateEditInventoryAndArchiveAreTransactionalAndAudited(): void
    {
        $pdo=$this->database();
        $migration=require dirname(__DIR__,2).'/migrations/20260817110000_shop_catalog_admin.php';
        $migration['up']($pdo);$migration['up']($pdo);self::assertTrue($migration['verify']($pdo));
        $created=\shopManualCatalogCreate($pdo,7,[
            'name'=>'Rajčátka','short_description'=>'Kroužek pro děti.','offer_type'=>'program',
            'visibility'=>'visible','item_type'=>'service',
        ],$this->variant('KP-RAJCATA-2026',150000,null), 'Nový sezonní kroužek.',true);
        self::assertMatchesRegularExpression('/^manual:[a-f0-9]{32}$/',$created['external_product_key']);
        $product=$pdo->query('SELECT * FROM shop_products WHERE id='.(int)$created['product_id'])->fetch(PDO::FETCH_ASSOC);
        $variant=$pdo->query('SELECT * FROM shop_variants WHERE id='.(int)$created['variant_id'])->fetch(PDO::FETCH_ASSOC);
        self::assertSame('manual',$product['origin']);self::assertSame('draft',$product['catalog_status']);
        self::assertSame('manual',$variant['origin']);self::assertSame('draft',$variant['catalog_status']);
        self::assertSame(1,(int)$pdo->query("SELECT COUNT(*) FROM shop_catalog_admin_events WHERE action='create_product' AND actor_type='trainer' AND actor_id=7")->fetchColumn());

        $pdo->exec("UPDATE shop_products SET catalog_status='active' WHERE id=".(int)$created['product_id']);
        $pdo->exec("UPDATE shop_variants SET catalog_status='active' WHERE id=".(int)$created['variant_id']);
        $added=\shopManualCatalogAddVariant($pdo,7,(int)$created['product_id'],$this->variant('KP-RAJCATA-2027',160000,null),'Nová sezona.',true);
        self::assertSame(2,(int)$pdo->query('SELECT COUNT(*) FROM shop_variants WHERE product_id='.(int)$created['product_id'])->fetchColumn());
        self::assertSame('active',$pdo->query('SELECT catalog_status FROM shop_variants WHERE id='.(int)$added['id'])->fetchColumn());
        self::assertSame('add_variant',$pdo->query('SELECT action FROM shop_catalog_admin_events WHERE variant_id='.(int)$added['id'])->fetchColumn());

        $pdo->exec("INSERT INTO shop_carts(id) VALUES(1)");$pdo->exec("INSERT INTO verejni_uzivatele(id) VALUES(10)");
        $pdo->exec("INSERT INTO shop_orders(id,account_id,source_cart_id) VALUES(1,10,1)");
        $pdo->exec("INSERT INTO shop_order_items(id,order_id,product_id,variant_id,product_name_snapshot,sku_snapshot,unit_amount_minor,line_amount_minor) VALUES(1,1,501,601,'Historické zboží','IMPORT-1',5000,5000)");
        \shopManualCatalogUpdateVariant($pdo,7,601,$this->variant('IMPORT-1',7500,'5'),'Změna ceny bez přímého zásahu do skladu.',true);
        \shopCatalogAdjustStock($pdo,7,601,'7','Samostatná inventura.',true);
        self::assertSame(5000,(int)$pdo->query('SELECT unit_amount_minor FROM shop_order_items WHERE id=1')->fetchColumn());
        self::assertSame(7500,(int)$pdo->query('SELECT amount_minor FROM shop_variants WHERE id=601')->fetchColumn());
        $movement=$pdo->query("SELECT * FROM shop_inventory_movements WHERE movement_type='manual_adjustment'")->fetch(PDO::FETCH_ASSOC);
        self::assertSame('trainer',$movement['actor_type']);self::assertSame(7,(int)$movement['actor_id']);
        self::assertSame('2.000000',$movement['quantity_delta_decimal']);self::assertSame('7.000000',$movement['stock_after_decimal']);
        self::assertSame('Samostatná inventura.',$movement['reason']);

        \shopManualCatalogArchive($pdo,7,(int)$created['product_id'],'Konec testovací nabídky.',true);
        self::assertSame('inactive',$pdo->query('SELECT catalog_status FROM shop_products WHERE id='.(int)$created['product_id'])->fetchColumn());
        self::assertSame(0,(int)$pdo->query("SELECT COUNT(*) FROM shop_variants WHERE product_id=".(int)$created['product_id']." AND catalog_status<>'inactive'")->fetchColumn());
        self::assertSame('archive_product',$pdo->query('SELECT action FROM shop_catalog_admin_events WHERE product_id='.(int)$created['product_id'].' ORDER BY id DESC LIMIT 1')->fetchColumn());
        self::assertFalse($pdo->inTransaction());
    }

    public function testValidationFailsClosedWithoutPartialRowsOrSkuMutation(): void
    {
        $pdo=$this->database();$migration=require dirname(__DIR__,2).'/migrations/20260817110000_shop_catalog_admin.php';$migration['up']($pdo);
        try{\shopManualCatalogCreate($pdo,7,['name'=>'Bez potvrzení','offer_type'=>'goods','item_type'=>'product','visibility'=>'visible'],$this->variant('KP-NO',1000,null),'Chybí potvrzení.',false);self::fail('Confirmation is mandatory.');}
        catch(\InvalidArgumentException){}
        self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM shop_products')->fetchColumn());
        try{\shopManualCatalogCreate($pdo,7,['name'=>'Kolize','offer_type'=>'goods','item_type'=>'product','visibility'=>'visible'],$this->variant('IMPORT-1',1000,null),'Kolizní test.',true);self::fail('Manual SKU prefix is mandatory.');}
        catch(\InvalidArgumentException){}
        try{\shopManualCatalogUpdateVariant($pdo,7,601,$this->variant('KP-CHANGED',6000,'5'),'Importní SKU se nemění.',true);self::fail('Imported SKU must be immutable.');}
        catch(\ShopManualCatalogException){}
        self::assertSame('IMPORT-1',$pdo->query('SELECT sku FROM shop_variants WHERE id=601')->fetchColumn());
        self::assertSame(5000,(int)$pdo->query('SELECT amount_minor FROM shop_variants WHERE id=601')->fetchColumn());
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM shop_catalog_admin_events')->fetchColumn());
        self::assertFalse($pdo->inTransaction());
    }

    /** @return array<string,mixed> */
    private function variant(string $sku,int $amount,?string $stock): array
    {
        return ['sku'=>$sku,'ean'=>'','attributes_json'=>'{}','amount_minor'=>$amount,
            'compare_at_amount_minor'=>null,'includes_vat'=>1,'vat_rate_basis_points'=>2100,
            'stock_quantity_decimal'=>$stock??'','unit_code'=>'ks','visible'=>1];
    }

    private function database(): PDO
    {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT)');$pdo->exec("INSERT INTO treneri VALUES(7,'Admin')");
        $pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY)');
        $pdo->exec('CREATE TABLE shop_carts(id INTEGER PRIMARY KEY)');
        $pdo->exec('CREATE TABLE shop_products(id INTEGER PRIMARY KEY AUTOINCREMENT,source_candidate_id INTEGER NULL,source_run_id INTEGER NULL,origin TEXT NOT NULL DEFAULT \'import\',created_by_trainer_id INTEGER NULL,external_product_key TEXT NOT NULL UNIQUE,name TEXT NOT NULL,short_description TEXT NULL,offer_type TEXT NOT NULL,visibility TEXT NULL,item_type TEXT NULL,catalog_status TEXT NOT NULL DEFAULT \'draft\',created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec('CREATE TABLE shop_variants(id INTEGER PRIMARY KEY AUTOINCREMENT,product_id INTEGER NOT NULL,source_candidate_id INTEGER NULL,origin TEXT NOT NULL DEFAULT \'import\',created_by_trainer_id INTEGER NULL,sku TEXT NOT NULL UNIQUE,ean TEXT NULL,attributes_json TEXT NOT NULL,price_mode TEXT NOT NULL,amount_minor INTEGER NULL,compare_at_amount_minor INTEGER NULL,currency TEXT NULL,includes_vat INTEGER NULL,vat_rate_basis_points INTEGER NULL,stock_quantity_decimal TEXT NULL,unit_code TEXT NULL,visible INTEGER NULL,catalog_status TEXT NOT NULL DEFAULT \'draft\',created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(product_id) REFERENCES shop_products(id))');
        $pdo->exec("INSERT INTO shop_products(id,source_candidate_id,source_run_id,external_product_key,name,offer_type,visibility,item_type,catalog_status) VALUES(501,101,1,'shoptet:501','Importované zboží','goods','visible','product','active')");
        $pdo->exec("INSERT INTO shop_variants(id,product_id,source_candidate_id,sku,attributes_json,price_mode,amount_minor,currency,includes_vat,vat_rate_basis_points,stock_quantity_decimal,unit_code,visible,catalog_status) VALUES(601,501,201,'IMPORT-1','{}','fixed',5000,'CZK',1,2100,'5.000000','ks',1,'active')");
        $pdo->exec('CREATE TABLE shop_product_publications(product_id INTEGER PRIMARY KEY,status TEXT,public_name TEXT,public_summary TEXT,decision_note TEXT,activated_by_trainer_id INTEGER,activated_at TEXT,deactivated_at TEXT,updated_at TEXT,FOREIGN KEY(product_id) REFERENCES shop_products(id))');
        $pdo->exec('CREATE TABLE shop_product_publication_events(id INTEGER PRIMARY KEY AUTOINCREMENT,product_id INTEGER,actor_trainer_id INTEGER,action TEXT,from_status TEXT,to_status TEXT,public_name TEXT,public_summary TEXT,note TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec('CREATE TABLE shop_orders(id INTEGER PRIMARY KEY,account_id INTEGER,source_cart_id INTEGER)');
        $pdo->exec('CREATE TABLE shop_order_items(id INTEGER PRIMARY KEY,order_id INTEGER,product_id INTEGER,variant_id INTEGER,product_name_snapshot TEXT,sku_snapshot TEXT,unit_amount_minor INTEGER,line_amount_minor INTEGER)');
        $pdo->exec('CREATE TABLE shop_inventory_movements(id INTEGER PRIMARY KEY AUTOINCREMENT,variant_id INTEGER NOT NULL,order_id INTEGER NOT NULL,order_item_id INTEGER NOT NULL,movement_type TEXT NOT NULL,quantity_delta_decimal TEXT NOT NULL,stock_after_decimal TEXT NOT NULL,created_at TEXT DEFAULT CURRENT_TIMESTAMP,UNIQUE(order_item_id,movement_type),FOREIGN KEY(variant_id) REFERENCES shop_variants(id),FOREIGN KEY(order_id) REFERENCES shop_orders(id),FOREIGN KEY(order_item_id) REFERENCES shop_order_items(id))');
        return $pdo;
    }
}
