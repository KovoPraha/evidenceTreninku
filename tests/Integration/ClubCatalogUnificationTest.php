<?php
declare(strict_types=1);
namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/includes/club_catalog_unification.php';

final class ClubCatalogUnificationTest extends TestCase
{
    public function testPreviewApplyAndRepeatPreserveTheSharedRosterAndHistory():void
    {
        $pdo=$this->database();
        self::assertCount(47,\clubCatalogUnificationPlan($pdo));
        $preview=\clubCatalogUnify($pdo,7,false);
        self::assertFalse($preview['applied']);
        self::assertSame(47,$preview['targets']);
        self::assertSame(0,(int)$pdo->query("SELECT COUNT(*) FROM club_program_offers WHERE code LIKE 'SHOPTET-2627-%'")->fetchColumn());

        $result=\clubCatalogUnify($pdo,7,true);
        self::assertSame(47,$result['created']);
        self::assertSame(0,$result['already_linked']);
        self::assertSame(47,(int)$pdo->query("SELECT COUNT(*) FROM club_program_offers WHERE code LIKE 'SHOPTET-2627-%' AND status='active'")->fetchColumn());
        self::assertSame(47,(int)$pdo->query("SELECT COUNT(*) FROM club_program_offers WHERE code LIKE 'KP-TEST-%' AND status='closed'")->fetchColumn());
        self::assertSame(47,(int)$pdo->query("SELECT COUNT(*) FROM shop_variants WHERE sku LIKE 'KP-TEST-%' AND catalog_status='inactive'")->fetchColumn());
        self::assertSame(47,(int)$pdo->query("SELECT COUNT(*) FROM club_program_events WHERE action='close_offer'")->fetchColumn());
        self::assertSame(47,(int)$pdo->query("SELECT COUNT(*) FROM shop_catalog_admin_events WHERE action='retire_duplicate_program_variant'")->fetchColumn());
        foreach(\clubCatalogUnificationTargets()as$group=>$targets){
            $code='KROUZKY-2627-'.\clubCatalogImportSlug($group);
            $q=$pdo->prepare('SELECT COUNT(DISTINCT o.team_id) FROM club_program_offers o JOIN club_programs p ON p.id=o.program_id WHERE p.code=?');
            $q->execute([$code]);self::assertSame(1,(int)$q->fetchColumn(),$group);
        }
        $repeat=\clubCatalogUnify($pdo,7,true);
        self::assertSame(0,$repeat['created']);
        self::assertSame(47,$repeat['already_linked']);
        self::assertSame(47,(int)$pdo->query("SELECT COUNT(*) FROM club_program_events WHERE action='close_offer'")->fetchColumn());
    }

    public function testDriftFailsBeforeAnyMutation():void
    {
        $pdo=$this->database();
        $pdo->exec("UPDATE shop_variants SET amount_minor=123456 WHERE id=671");
        $this->expectException(\ClubCatalogUnificationException::class);
        try{\clubCatalogUnify($pdo,7,true);}
        finally{
            self::assertSame(0,(int)$pdo->query("SELECT COUNT(*) FROM club_program_offers WHERE code LIKE 'SHOPTET-2627-%'")->fetchColumn());
            self::assertSame(0,(int)$pdo->query("SELECT COUNT(*) FROM club_program_offers WHERE status='closed'")->fetchColumn());
        }
    }

    private function database():PDO
    {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        foreach([
            'CREATE TABLE club_programs(id INTEGER PRIMARY KEY,code TEXT UNIQUE,name TEXT,status TEXT)',
            'CREATE TABLE club_seasons(id INTEGER PRIMARY KEY,starts_on TEXT,ends_on TEXT)',
            'CREATE TABLE club_teams(id INTEGER PRIMARY KEY,season_id INTEGER)',
            'CREATE TABLE shop_products(id INTEGER PRIMARY KEY,name TEXT,origin TEXT,offer_type TEXT,catalog_status TEXT)',
            'CREATE TABLE shop_variants(id INTEGER PRIMARY KEY,product_id INTEGER,sku TEXT,amount_minor INTEGER,currency TEXT,origin TEXT,catalog_status TEXT,stock_quantity_decimal TEXT,unit_code TEXT,visible INTEGER,updated_at TEXT)',
            'CREATE TABLE shop_product_publications(product_id INTEGER PRIMARY KEY,status TEXT)',
            'CREATE TABLE club_program_offers(id INTEGER PRIMARY KEY AUTOINCREMENT,program_id INTEGER,season_id INTEGER,team_id INTEGER,product_id INTEGER,variant_id INTEGER UNIQUE,code TEXT UNIQUE,name TEXT,starts_on TEXT,ends_on TEXT,sales_open_at TEXT,sales_close_at TEXT,capacity INTEGER,birth_year_from INTEGER,birth_year_to INTEGER,purchase_option TEXT,is_featured INTEGER,status TEXT,created_by_trainer_id INTEGER,updated_at TEXT)',
            'CREATE TABLE club_program_events(id INTEGER PRIMARY KEY,program_id INTEGER,offer_id INTEGER,actor_type TEXT,actor_id INTEGER,action TEXT,before_json TEXT,after_json TEXT)',
            'CREATE TABLE shop_catalog_admin_events(id INTEGER PRIMARY KEY,product_id INTEGER,variant_id INTEGER,actor_type TEXT,actor_id INTEGER,action TEXT,before_json TEXT,after_json TEXT,reason TEXT)',
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
                $end=$option==='first_half'?'2027-01-31':'2027-06-30';
                $pdo->prepare("INSERT INTO shop_products VALUES(?,?, 'manual','program','active')")->execute([$manualId,$group]);
                $pdo->prepare("INSERT INTO shop_variants(id,product_id,sku,amount_minor,currency,origin,catalog_status,stock_quantity_decimal,unit_code,visible) VALUES(?,?,?,?,?,'manual','active',NULL,'person',1)")
                    ->execute([$manualVariant,$manualId,'KP-TEST-'.$manualVariant,$price,'CZK']);
                $pdo->prepare('INSERT INTO shop_product_publications VALUES(?,\'active\')')->execute([$manualId]);
                $pdo->prepare("INSERT INTO club_program_offers(program_id,season_id,team_id,product_id,variant_id,code,name,starts_on,ends_on,sales_open_at,sales_close_at,capacity,birth_year_from,birth_year_to,purchase_option,is_featured,status,created_by_trainer_id) VALUES(?,1,?,?,?,?,?,'2026-09-01',?,'2026-09-01 00:00:00',?,30,NULL,NULL,?,?,'active',7)")
                    ->execute([$programId,$programId,$manualId,$manualVariant,'KP-TEST-'.$manualVariant,$group.' · '.$option,$end,$end.' 23:59:59',$option,$option==='full_year'?1:0]);
                $pdo->prepare("INSERT INTO shop_products VALUES(?,?,'import','program','active')")->execute([$productId,$group.' '.$option]);
                $pdo->prepare("INSERT INTO shop_variants(id,product_id,sku,amount_minor,currency,origin,catalog_status,stock_quantity_decimal,unit_code,visible) VALUES(?,?,?,?,?,'import','active',NULL,'person',1)")
                    ->execute([$variantId,$productId,$sku,$price,'CZK']);
                $pdo->prepare("INSERT INTO shop_product_publications VALUES(?,'active')")->execute([$productId]);
            }
        }
        return$pdo;
    }
}
