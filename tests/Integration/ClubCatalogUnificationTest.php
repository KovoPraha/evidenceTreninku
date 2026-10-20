<?php
declare(strict_types=1);
namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/includes/club_catalog_unification.php';
require_once dirname(__DIR__,2).'/includes/shop_catalog_one_time_sync.php';

final class ClubCatalogUnificationTest extends TestCase
{
    public function testArchiveAliasesKeepCanonicalRostersAndHistoricalGoodsIntact():void
    {
        $pdo=$this->database();
        self::assertCount(47,\clubCatalogUnificationPlan($pdo));
        $preview=\clubCatalogUnify($pdo,7,false);
        self::assertFalse($preview['applied']);self::assertSame(47,$preview['targets']);
        self::assertNull(\clubCatalogLegacyOfferTarget($pdo,143,671));
        self::assertTrue(\shopCatalogOneTimeManagedPumptrackExists($pdo));
        $orderBefore=$pdo->query('SELECT * FROM shop_order_items')->fetchAll(PDO::FETCH_ASSOC);
        $stockBefore=$pdo->query('SELECT id,stock_quantity_decimal FROM shop_variants WHERE origin=\'import\' ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $result=\clubCatalogUnify($pdo,7,true);
        self::assertSame(47,$result['created']);self::assertSame(0,$result['already_linked']);
        self::assertSame(47,(int)$pdo->query("SELECT COUNT(*) FROM shop_products WHERE origin='import' AND catalog_status='inactive'")->fetchColumn());
        self::assertSame(47,(int)$pdo->query("SELECT COUNT(*) FROM shop_variants WHERE origin='import' AND catalog_status='inactive'")->fetchColumn());
        self::assertSame(47,(int)$pdo->query("SELECT COUNT(*) FROM shop_product_publications WHERE status='inactive'")->fetchColumn());
        self::assertSame(47,(int)$pdo->query("SELECT COUNT(*) FROM shop_product_publication_events WHERE action='deactivate'")->fetchColumn());
        self::assertSame(0,(int)$pdo->query("SELECT COUNT(*) FROM club_program_offers WHERE code LIKE 'SHOPTET-2627-%'")->fetchColumn());
        self::assertSame(47,(int)$pdo->query("SELECT COUNT(*) FROM club_program_offers WHERE status='active'")->fetchColumn());
        self::assertSame($stockBefore,$pdo->query('SELECT id,stock_quantity_decimal FROM shop_variants WHERE origin=\'import\' ORDER BY id')->fetchAll(PDO::FETCH_ASSOC));
        self::assertSame($orderBefore,$pdo->query('SELECT * FROM shop_order_items')->fetchAll(PDO::FETCH_ASSOC));
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM shop_order_items oi JOIN club_program_offers o ON o.product_id=oi.product_id AND o.variant_id=oi.variant_id')->fetchColumn());
        self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM shop_cart_items WHERE variant_id=671')->fetchColumn());
        $cartVariant=$pdo->query("SELECT p.catalog_status AS product_status,p.offer_type,v.catalog_status,pub.status AS publication_status,v.visible,v.price_mode,v.amount_minor,v.currency,v.id FROM shop_cart_items ci JOIN shop_variants v ON v.id=ci.variant_id JOIN shop_products p ON p.id=v.product_id JOIN shop_product_publications pub ON pub.product_id=p.id WHERE ci.variant_id=671")->fetch(PDO::FETCH_ASSOC);
        self::assertFalse(\shopCheckoutVariantIsSaleable($cartVariant,$pdo));
        $alias=\clubCatalogLegacyOfferTarget($pdo,143,671);
        self::assertNotNull($alias);self::assertGreaterThan(10000,$alias['product_id']);
        self::assertGreaterThan(20000,$alias['variant_id']);
        self::assertSame($alias,\clubCatalogLegacyOfferTarget($pdo,143));
        self::assertNull(\clubCatalogLegacyOfferTarget($pdo,143,999999));
        self::assertNull(\clubCatalogLegacyOfferTarget($pdo,999999));
        foreach(\clubCatalogUnificationTargets()as$group=>$targets){
            $code='KROUZKY-2627-'.\clubCatalogImportSlug($group);
            $q=$pdo->prepare('SELECT COUNT(DISTINCT o.team_id) FROM club_program_offers o JOIN club_programs p ON p.id=o.program_id WHERE p.code=?');
            $q->execute([$code]);self::assertSame(1,(int)$q->fetchColumn(),$group);
            foreach($targets as[$oldProduct,$oldVariant,$sku,$option]){
                $actual=\clubCatalogLegacyOfferTarget($pdo,$oldProduct,$oldVariant);
                self::assertNotNull($actual,$group.'/'.$option);
                $target=$pdo->prepare('SELECT o.purchase_option,p.code FROM club_program_offers o JOIN club_programs p ON p.id=o.program_id WHERE o.product_id=? AND o.variant_id=?');
                $target->execute([$actual['product_id'],$actual['variant_id']]);
                self::assertSame(['purchase_option'=>$option,'code'=>$code],$target->fetch(PDO::FETCH_ASSOC));
            }
        }
        self::assertTrue(\shopCatalogOneTimeManagedPumptrackExists($pdo));
        $repeat=\clubCatalogUnify($pdo,7,true);
        self::assertSame(0,$repeat['created']);self::assertSame(47,$repeat['already_linked']);
        self::assertSame(47,(int)$pdo->query("SELECT COUNT(*) FROM shop_product_publication_events WHERE action='deactivate'")->fetchColumn());
    }

    public function testDriftFailsBeforeAnyMutation():void
    {
        $pdo=$this->database();
        $pdo->exec("UPDATE shop_variants SET amount_minor=123456 WHERE id=671");
        $this->expectException(\ClubCatalogUnificationException::class);
        try{\clubCatalogUnify($pdo,7,true);}
        finally{
            self::assertSame(0,(int)$pdo->query("SELECT COUNT(*) FROM shop_products WHERE origin='import' AND catalog_status='inactive'")->fetchColumn());
            self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM shop_product_publication_events')->fetchColumn());
        }
    }

    private function database():PDO
    {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        foreach([
            'CREATE TABLE club_programs(id INTEGER PRIMARY KEY,code TEXT UNIQUE,name TEXT,status TEXT)',
            'CREATE TABLE club_seasons(id INTEGER PRIMARY KEY,starts_on TEXT,ends_on TEXT)',
            'CREATE TABLE club_teams(id INTEGER PRIMARY KEY,season_id INTEGER)',
            'CREATE TABLE shop_products(id INTEGER PRIMARY KEY,name TEXT,origin TEXT,offer_type TEXT,catalog_status TEXT,updated_at TEXT)',
            'CREATE TABLE shop_variants(id INTEGER PRIMARY KEY,product_id INTEGER,sku TEXT,amount_minor INTEGER,currency TEXT,origin TEXT,catalog_status TEXT,stock_quantity_decimal TEXT,unit_code TEXT,visible INTEGER,price_mode TEXT DEFAULT \'fixed\',updated_at TEXT)',
            'CREATE TABLE shop_product_publications(product_id INTEGER PRIMARY KEY,status TEXT,public_name TEXT DEFAULT \'Program\',public_summary TEXT DEFAULT \'\',decision_note TEXT,activated_by_trainer_id INTEGER,deactivated_at TEXT,updated_at TEXT)',
            'CREATE TABLE shop_product_publication_events(id INTEGER PRIMARY KEY,product_id INTEGER,actor_trainer_id INTEGER,action TEXT,from_status TEXT,to_status TEXT,public_name TEXT,public_summary TEXT,note TEXT)',
            'CREATE TABLE club_program_offers(id INTEGER PRIMARY KEY AUTOINCREMENT,program_id INTEGER,season_id INTEGER,team_id INTEGER,product_id INTEGER,variant_id INTEGER UNIQUE,code TEXT UNIQUE,name TEXT,starts_on TEXT,ends_on TEXT,sales_open_at TEXT,sales_close_at TEXT,capacity INTEGER,birth_year_from INTEGER,birth_year_to INTEGER,purchase_option TEXT,is_featured INTEGER,status TEXT,created_by_trainer_id INTEGER,updated_at TEXT)',
            'CREATE TABLE shop_cart_items(id INTEGER PRIMARY KEY,variant_id INTEGER,quantity INTEGER)',
            'CREATE TABLE shop_order_items(id INTEGER PRIMARY KEY,product_id INTEGER,variant_id INTEGER,product_name_snapshot TEXT,unit_amount_minor INTEGER,quantity INTEGER,program_terms_snapshot_json TEXT)',
        ]as$sql)$pdo->exec($sql);
        $pdo->exec("INSERT INTO club_seasons VALUES(1,'2026-09-01','2027-08-31')");
        $programId=0;$manualId=10000;
        foreach(\clubCatalogUnificationTargets()as$group=>$targets){
            $programId++;$code='KROUZKY-2627-'.\clubCatalogImportSlug($group);
            $pdo->prepare("INSERT INTO club_programs VALUES(?,?,?,'active')")->execute([$programId,$code,$group]);
            $pdo->prepare('INSERT INTO club_teams VALUES(?,1)')->execute([$programId]);
            foreach($targets as[$productId,$variantId,$sku,$option]){
                $manualId++;$manualVariant=$manualId+10000;
                $price=$group==='Pumptrack'?($option==='first_half'?650000:1150000):410000;
                $oldPrice=$group==='Pumptrack'?($option==='first_half'?440000:850000):$price;
                $end=$option==='first_half'?'2027-01-31':'2027-06-30';
                $pdo->prepare("INSERT INTO shop_products(id,name,origin,offer_type,catalog_status) VALUES(?,?,'manual','program','active')")->execute([$manualId,$group]);
                $pdo->prepare("INSERT INTO shop_variants(id,product_id,sku,amount_minor,currency,origin,catalog_status,stock_quantity_decimal,unit_code,visible) VALUES(?,?,?,?,?,'manual','active',NULL,'person',1)")
                    ->execute([$manualVariant,$manualId,'KP-TEST-'.$manualVariant,$price,'CZK']);
                $pdo->prepare('INSERT INTO shop_product_publications(product_id,status) VALUES(?,\'active\')')->execute([$manualId]);
                $pdo->prepare("INSERT INTO club_program_offers(program_id,season_id,team_id,product_id,variant_id,code,name,starts_on,ends_on,sales_open_at,sales_close_at,capacity,birth_year_from,birth_year_to,purchase_option,is_featured,status,created_by_trainer_id) VALUES(?,1,?,?,?,?,?,'2026-09-01',?,'2026-09-01 00:00:00',?,30,NULL,NULL,?,?,'active',7)")
                    ->execute([$programId,$programId,$manualId,$manualVariant,'KP-TEST-'.$manualVariant,$group.' · '.$option,$end,$end.' 23:59:59',$option,$option==='full_year'?1:0]);
                $pdo->prepare("INSERT INTO shop_products(id,name,origin,offer_type,catalog_status) VALUES(?,?,'import','goods','active')")->execute([$productId,$group.' '.$option]);
                $pdo->prepare("INSERT INTO shop_variants(id,product_id,sku,amount_minor,currency,origin,catalog_status,stock_quantity_decimal,unit_code,visible) VALUES(?,?,?,?,?,'import','active','30.000000','piece',1)")
                    ->execute([$variantId,$productId,$sku,$oldPrice,'CZK']);
                $pdo->prepare('INSERT INTO shop_product_publications(product_id,status) VALUES(?,\'active\')')->execute([$productId]);
            }
        }
        $pdo->exec("INSERT INTO shop_cart_items VALUES(1,671,1)");
        $pdo->exec("INSERT INTO shop_order_items VALUES(1,143,671,'Původní zboží',410000,1,NULL)");
        return$pdo;
    }
}
