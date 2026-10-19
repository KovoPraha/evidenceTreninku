<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/includes/shoptet_product_input.php';
require_once dirname(__DIR__,2).'/includes/shop_catalog_one_time_sync.php';

final class ShopCatalogOneTimeSyncTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp():void
    {
        $this->temporaryDirectory=sys_get_temp_dir().DIRECTORY_SEPARATOR.'shop-once-'.bin2hex(random_bytes(8));
        mkdir($this->temporaryDirectory.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'images',0700,true);
        mkdir($this->temporaryDirectory.DIRECTORY_SEPARATOR.'app',0700,true);
    }

    protected function tearDown():void{$this->removeDirectory($this->temporaryDirectory);}

    public function testInitialRunAndFinalRunUpdateSameDraftCatalogWithLocalImages():void
    {
        $pdo=$this->database();$catalog=$this->catalog();$manifest=$this->manifest($catalog);
        $preview=\shopCatalogOneTimePreview($pdo,$catalog,$manifest);
        self::assertTrue($preview['ok']);self::assertSame(0,$preview['database_writes']);

        $first=\shopCatalogOneTimeApply($pdo,$catalog,$manifest,$this->temporaryDirectory.'/app',\SHOP_CATALOG_ONE_TIME_CONFIRM);
        self::assertTrue($first['applied']);self::assertSame(2,$first['products_created']);self::assertSame(3,$first['variants_created']);
        self::assertSame(2,(int)$pdo->query('SELECT COUNT(*) FROM shop_products')->fetchColumn());
        self::assertSame(3,(int)$pdo->query('SELECT COUNT(*) FROM shop_variants')->fetchColumn());
        self::assertSame(3,(int)$pdo->query('SELECT COUNT(*) FROM shop_inventory_movements')->fetchColumn());
        $image=(string)$pdo->query('SELECT image_url FROM shop_product_images')->fetchColumn();
        self::assertMatchesRegularExpression('~^uploads/shop-products/[a-f0-9]{32}\.jpg$~',$image);
        self::assertFileExists($this->temporaryDirectory.'/app/'.$image);

        $same=\shopCatalogOneTimeApply($pdo,$catalog,$manifest,$this->temporaryDirectory.'/app',\SHOP_CATALOG_ONE_TIME_CONFIRM);
        self::assertTrue($same['idempotent']);self::assertFalse($same['applied']);

        $catalog['source']['sha256']=str_repeat('b',64);
        $catalog['products'][1]['variants'][0]['price']['amount_minor']=175000;
        $catalog['products'][1]['variants'][0]['stock']['quantity_decimal']='4';
        $manifest=$this->manifest($catalog);
        $second=\shopCatalogOneTimeApply($pdo,$catalog,$manifest,$this->temporaryDirectory.'/app',\SHOP_CATALOG_ONE_TIME_CONFIRM);
        self::assertSame(0,$second['products_created']);self::assertSame(2,$second['products_updated']);self::assertSame(3,$second['variants_updated']);
        self::assertSame(175000,(int)$pdo->query("SELECT amount_minor FROM shop_variants WHERE sku='DRES-M'")->fetchColumn());
        self::assertSame('4',(string)$pdo->query("SELECT stock_quantity_decimal FROM shop_variants WHERE sku='DRES-M'")->fetchColumn());
        self::assertSame(4,(int)$pdo->query('SELECT COUNT(*) FROM shop_inventory_movements')->fetchColumn());
        self::assertSame(2,(int)$pdo->query('SELECT COUNT(*) FROM shop_catalog_promotions')->fetchColumn());
    }

    public function testManualSkuCollisionAndActiveImportFailClosedWithoutWrites():void
    {
        $pdo=$this->database();$catalog=$this->catalog();$manifest=$this->manifest($catalog);
        $pdo->exec("INSERT INTO shop_products(source_candidate_id,source_run_id,origin,created_by_trainer_id,external_product_key,name,offer_type,catalog_status) VALUES(NULL,NULL,'manual',7,'manual:test','Ruční','goods','draft')");
        $product=(int)$pdo->lastInsertId();$pdo->prepare("INSERT INTO shop_variants(product_id,source_candidate_id,origin,created_by_trainer_id,sku,attributes_json,price_mode,amount_minor,currency,catalog_status) VALUES(?,NULL,'manual',7,'DRES-M','{}','fixed',1,'CZK','draft')")->execute([$product]);
        $preview=\shopCatalogOneTimePreview($pdo,$catalog,$manifest);self::assertFalse($preview['ok']);self::assertContains('sku_collisions',$preview['blockers']);
        try{\shopCatalogOneTimeApply($pdo,$catalog,$manifest,$this->temporaryDirectory.'/app',\SHOP_CATALOG_ONE_TIME_CONFIRM);self::fail('Kolize musí import zablokovat.');}catch(\ShopCatalogOneTimeSyncException){}
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM shop_catalog_import_runs')->fetchColumn());
    }

    public function testUnrelatedImportedCatalogIsNotBlockedOrRetired():void
    {
        $pdo=$this->database();$catalog=$this->catalog();$manifest=$this->manifest($catalog);
        $pdo->exec("INSERT INTO shop_products(source_candidate_id,source_run_id,origin,created_by_trainer_id,external_product_key,name,offer_type,catalog_status) VALUES(NULL,NULL,'import',NULL,'club:program:example','Kroužek','club_event','active')");
        $product=(int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO shop_variants(product_id,source_candidate_id,origin,created_by_trainer_id,sku,attributes_json,price_mode,amount_minor,currency,visible,catalog_status) VALUES(?,NULL,'import',NULL,'CLUB-EXAMPLE','{}','fixed',100,'CZK',1,'active')")->execute([$product]);

        self::assertTrue(\shopCatalogOneTimePreview($pdo,$catalog,$manifest)['ok']);
        $keys=array_map(static fn(array$row):string=>(string)$row['external_product_key'],$catalog['products']);
        \shopCatalogOneTimeRetireMissingProducts($pdo,$keys);
        self::assertSame('active',(string)$pdo->query("SELECT catalog_status FROM shop_products WHERE external_product_key='club:program:example'")->fetchColumn());
        self::assertSame('active',(string)$pdo->query("SELECT catalog_status FROM shop_variants WHERE sku='CLUB-EXAMPLE'")->fetchColumn());
    }

    public function testExplicitlyAllowlistedActiveImportIsUpdatedWithoutActivatingAnythingElse():void
    {
        $pdo=$this->database();$catalog=$this->catalog();$manifest=$this->manifest($catalog);
        \shopCatalogOneTimeApply($pdo,$catalog,$manifest,$this->temporaryDirectory.'/app',\SHOP_CATALOG_ONE_TIME_CONFIRM);
        $key=(string)$catalog['products'][0]['external_product_key'];
        $productId=(int)$pdo->query("SELECT id FROM shop_products WHERE external_product_key=".$pdo->quote($key))->fetchColumn();
        $pdo->prepare("UPDATE shop_products SET catalog_status='active' WHERE id=?")->execute([$productId]);
        $pdo->prepare("UPDATE shop_variants SET catalog_status='active' WHERE product_id=?")->execute([$productId]);
        $activeVariantsBefore=(int)$pdo->query("SELECT COUNT(*) FROM shop_variants WHERE catalog_status='active'")->fetchColumn();

        self::assertFalse(\shopCatalogOneTimePreview($pdo,$catalog,$manifest)['ok']);
        $allowedPreview=\shopCatalogOneTimePreview($pdo,$catalog,$manifest,[$key]);
        self::assertTrue($allowedPreview['ok']);self::assertSame(1,$allowedPreview['active_imported_products']);

        $catalog['source']['sha256']=str_repeat('c',64);$catalog['products'][0]['variants'][0]['price']['amount_minor']=654321;$manifest=$this->manifest($catalog);
        $result=\shopCatalogOneTimeApply($pdo,$catalog,$manifest,$this->temporaryDirectory.'/app',\SHOP_CATALOG_ONE_TIME_CONFIRM,[$key]);
        self::assertTrue($result['applied']);self::assertSame(1,$result['verification']['active_products']);
        self::assertLessThanOrEqual($activeVariantsBefore,$result['verification']['active_variants']);
        self::assertSame(1,(int)$pdo->query("SELECT COUNT(*) FROM shop_products WHERE catalog_status='active'")->fetchColumn());
        self::assertSame(654321,(int)$pdo->query("SELECT amount_minor FROM shop_variants WHERE product_id=".$productId." ORDER BY id LIMIT 1")->fetchColumn());
    }

    public function testSignedShoptetStockIsPreservedAndAuditedWithoutWeakeningManualRules():void
    {
        $pdo=$this->database();$catalog=$this->catalog();$catalog['source']['sha256']=str_repeat('d',64);$catalog['products'][0]['variants'][0]['stock']['quantity_decimal']='-5';$catalog['products'][0]['variants'][0]['stock']['allow_negative']=true;$manifest=$this->manifest($catalog);
        $sku=(string)$catalog['products'][0]['variants'][0]['sku'];
        \shopCatalogOneTimeApply($pdo,$catalog,$manifest,$this->temporaryDirectory.'/app',\SHOP_CATALOG_ONE_TIME_CONFIRM);
        $statement=$pdo->prepare('SELECT stock_quantity_decimal FROM shop_variants WHERE sku=?');$statement->execute([$sku]);
        self::assertSame('-5',(string)$statement->fetchColumn());
        $statement=$pdo->prepare('SELECT quantity_delta_decimal,stock_after_decimal FROM shop_inventory_movements m JOIN shop_variants v ON v.id=m.variant_id WHERE v.sku=? ORDER BY m.id DESC LIMIT 1');$statement->execute([$sku]);$movement=$statement->fetch(PDO::FETCH_ASSOC);
        self::assertSame('-5.000000',$movement['quantity_delta_decimal']);self::assertSame('-5.000000',$movement['stock_after_decimal']);
        $this->expectException(\InvalidArgumentException::class);\shopManualCatalogStock('-5');
    }

    /** @return array<string,mixed> */
    private function catalog():array{return \ShopCatalogContract::build(\ShoptetProductInput::read(dirname(__DIR__).'/fixtures/shoptet/products-export.xml'));}

    /** @return array<string,mixed> */
    private function manifest(array$catalog):array
    {
        $bytes=base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAEf/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABBQJ//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPwF//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPwF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQAGPwJ//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPyF//9oADAMBAAIAAwAAABD/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/EB//xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/EB//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/EB//2Q==',true);
        self::assertIsString($bytes);$sha=hash('sha256',$bytes);$name=substr($sha,0,32).'.jpg';file_put_contents($this->temporaryDirectory.'/assets/images/'.$name,$bytes);
        return['version'=>\SHOP_CATALOG_ASSET_MANIFEST_VERSION,'source_sha256'=>$catalog['source']['sha256'],'images'=>['https://example.invalid/dres.jpg'=>['file'=>'images/'.$name,'sha256'=>$sha]],'_directory'=>$this->temporaryDirectory.'/assets'];
    }

    private function database():PDO
    {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT,role TEXT,aktivni INTEGER)');$pdo->exec("INSERT INTO treneri VALUES(7,'Admin','admin',1)");
        foreach(['20260802170000_shop_catalog_staging.php','20260802190000_shop_catalog_review.php','20260802210000_shop_canonical_catalog.php','20260816200000_shop_manual_catalog_origin.php']as$file){$migration=require dirname(__DIR__,2).'/migrations/'.$file;$migration['up']($pdo);}
        $pdo->exec('CREATE TABLE shop_inventory_movements(id INTEGER PRIMARY KEY AUTOINCREMENT,variant_id INTEGER NOT NULL,order_id INTEGER,order_item_id INTEGER,movement_type TEXT NOT NULL,actor_type TEXT,actor_id INTEGER,reason TEXT,quantity_delta_decimal TEXT NOT NULL,stock_after_decimal TEXT NOT NULL,created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        return$pdo;
    }

    private function removeDirectory(string$directory):void{if(!is_dir($directory))return;foreach(new \FilesystemIterator($directory,\FilesystemIterator::SKIP_DOTS)as$item){$item->isDir()?$this->removeDirectory($item->getPathname()):unlink($item->getPathname());}rmdir($directory);}
}
