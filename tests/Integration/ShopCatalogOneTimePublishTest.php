<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/includes/shop_catalog_one_time_publish.php';
require_once dirname(__DIR__,2).'/includes/shop_purchase_mode.php';

final class ShopCatalogOneTimePublishTest extends TestCase
{
    public function testVisibleImportedProductsArePublishedAsConventionalGoodsAndHiddenStayInactive():void
    {
        $pdo=$this->database();$sha=str_repeat('a',64);
        $preview=\shopCatalogOneTimePublishPreview($pdo,$sha,2,1,1,1);
        self::assertTrue($preview['ok']);self::assertSame(0,$preview['database_writes']);self::assertSame(1,$preview['normalization']['offer_types_to_goods']);self::assertSame(1,$preview['normalization']['free_prices_to_fixed_zero']);
        $result=\shopCatalogOneTimePublishApply($pdo,$sha,2,1,1,1,\SHOP_CATALOG_ONE_TIME_PUBLISH_CONFIRM);
        self::assertTrue($result['applied']);self::assertSame(1,$result['products_changed']);self::assertSame(1,$result['verification']['public_storefront_products']);
        self::assertSame('goods',(string)$pdo->query("SELECT offer_type FROM shop_products WHERE external_product_key='shoptet:pair:1'")->fetchColumn());
        $visibleProductId=(int)$pdo->query("SELECT id FROM shop_products WHERE external_product_key='shoptet:pair:1'")->fetchColumn();self::assertFalse(\shopProductRequiresAthlete($pdo,$visibleProductId));
        self::assertSame('active',(string)$pdo->query("SELECT catalog_status FROM shop_products WHERE external_product_key='shoptet:pair:1'")->fetchColumn());
        self::assertSame('inactive',(string)$pdo->query("SELECT catalog_status FROM shop_products WHERE external_product_key='shoptet:pair:2'")->fetchColumn());
        self::assertSame('fixed',(string)$pdo->query("SELECT price_mode FROM shop_variants WHERE sku='VISIBLE-1'")->fetchColumn());
        self::assertSame(0,(int)$pdo->query("SELECT amount_minor FROM shop_variants WHERE sku='VISIBLE-1'")->fetchColumn());
        self::assertSame(1,(int)$pdo->query("SELECT COUNT(*) FROM shop_product_publications WHERE status='active'")->fetchColumn());

        $same=\shopCatalogOneTimePublishApply($pdo,$sha,2,1,1,1,\SHOP_CATALOG_ONE_TIME_PUBLISH_CONFIRM);
        self::assertSame(0,$same['products_changed']);self::assertSame(1,$same['products_unchanged']);self::assertSame(0,$same['offer_types_normalized']);self::assertSame(0,$same['prices_normalized']);
    }

    private function database():PDO
    {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT,role TEXT,aktivni INTEGER)');$pdo->exec("INSERT INTO treneri VALUES(7,'Admin','admin',1)");
        foreach(['20260802170000_shop_catalog_staging.php','20260802190000_shop_catalog_review.php','20260802210000_shop_canonical_catalog.php','20260816200000_shop_manual_catalog_origin.php','20260803090000_shop_product_publication.php']as$file){$migration=require dirname(__DIR__,2).'/migrations/'.$file;$migration['up']($pdo);}
        $sha=str_repeat('a',64);$pdo->prepare("INSERT INTO shop_catalog_import_runs(source_sha256,source_filename,contract_version,status,product_count,variant_count,warning_count,manual_review_count) VALUES(?,'products.xml','test','promoted',2,2,0,0)")->execute([$sha]);$runId=(int)$pdo->lastInsertId();
        $insertProduct=$pdo->prepare("INSERT INTO shop_products(source_run_id,origin,external_product_key,name,short_description,description_html_untrusted,offer_type,visibility,item_type,catalog_status) VALUES(?,'import',?,?,?,?,?,?,?,?)");
        $insertProduct->execute([$runId,'shoptet:pair:1','Kroužek – viditelná nabídka','','<p>Úplný popis nabídky.</p>','club_event','visible','service','draft']);$visibleId=(int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO shop_product_categories(product_id,category_path,is_default,sort_order) VALUES(?,'Cyklo kroužky',1,0)")->execute([$visibleId]);
        $insertProduct->execute([$runId,'shoptet:pair:2','Skrytá nabídka','Skrytá.','','goods','hidden','product','inactive']);$hiddenId=(int)$pdo->lastInsertId();
        $insertVariant=$pdo->prepare("INSERT INTO shop_variants(product_id,origin,sku,attributes_json,price_mode,amount_minor,currency,visible,catalog_status) VALUES(?,'import',?,'{}',?,?,?,?,?)");
        $insertVariant->execute([$visibleId,'VISIBLE-1','free',0,'CZK',1,'draft']);$insertVariant->execute([$hiddenId,'HIDDEN-1','fixed',100,'CZK',1,'inactive']);
        $pdo->prepare("INSERT INTO shop_product_images(product_id,image_url,sort_order) VALUES(?,'uploads/shop-products/0123456789abcdef0123456789abcdef.jpg',0)")->execute([$visibleId]);
        return$pdo;
    }
}
