<?php
declare(strict_types=1);
namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/includes/club_approved_catalog_settings.php';
require_once dirname(__DIR__,2).'/includes/shop_catalog_one_time_sync.php';

final class ClubApprovedCatalogSettingsTest extends TestCase
{
    public function testPreviewApplyAndRepeatSetEveryOfferCapacityAndBothApprovedPrices():void
    {
        $pdo=$this->database();
        $preview=\clubApprovedCatalogSettings($pdo,7,false);
        self::assertFalse($preview['applied']);self::assertSame(74,$preview['capacity_offers']);
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM club_program_events')->fetchColumn());
        $applied=\clubApprovedCatalogSettings($pdo,7,true);
        self::assertSame(74,$applied['capacity_changed']);self::assertSame(2,$applied['price_changed']);
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM club_program_offers WHERE capacity<>30 OR capacity IS NULL')->fetchColumn());
        self::assertSame(650000,(int)$pdo->query('SELECT amount_minor FROM shop_variants WHERE id=941')->fetchColumn());
        self::assertSame(1150000,(int)$pdo->query('SELECT amount_minor FROM shop_variants WHERE id=893')->fetchColumn());
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM shop_variants WHERE id IN (893,941) AND compare_at_amount_minor IS NOT NULL')->fetchColumn());
        self::assertSame(74,(int)$pdo->query("SELECT COUNT(*) FROM club_program_events WHERE action='update_offer'")->fetchColumn());
        self::assertSame(2,(int)$pdo->query("SELECT COUNT(*) FROM shop_catalog_admin_events WHERE action='update_variant'")->fetchColumn());
        $repeat=\clubApprovedCatalogSettings($pdo,7,true);
        self::assertSame(0,$repeat['capacity_changed']);self::assertSame(0,$repeat['price_changed']);
    }

    public function testOccupiedOverThirtyFailsBeforeAnyWrite():void
    {
        $pdo=$this->database();
        $insert=$pdo->prepare("INSERT INTO club_program_enrollments(offer_id,sportovec_id,status) VALUES(1,?,'active')");
        for($athlete=1;$athlete<=31;$athlete++)$insert->execute([$athlete]);
        $this->expectException(\ClubApprovedCatalogSettingsException::class);
        try{\clubApprovedCatalogSettings($pdo,7,true);}finally{
            self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM club_program_offers WHERE capacity IS NOT NULL')->fetchColumn());
            self::assertSame(440000,(int)$pdo->query('SELECT amount_minor FROM shop_variants WHERE id=941')->fetchColumn());
        }
    }

    public function testUnexpectedPumptrackIdentityFailsClosed():void
    {
        $pdo=$this->database();$pdo->exec("UPDATE shop_variants SET sku='WRONG' WHERE id=941");
        $this->expectException(\ClubApprovedCatalogSettingsException::class);
        try{\clubApprovedCatalogSettings($pdo,7,true);}finally{
            self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM club_program_events')->fetchColumn());
        }
    }

    public function testLaterShoptetSynchronizationCannotResetManagedPumptrack():void
    {
        $pdo=$this->database();
        self::assertTrue(\shopCatalogOneTimeManagedPumptrackExists($pdo));
        $this->expectException(\ShopCatalogOneTimeSyncException::class);
        \shopCatalogOneTimeSynchronizeRun($pdo,1,7,[]);
    }

    private function database():PDO
    {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        foreach([
            'CREATE TABLE club_seasons(id INTEGER PRIMARY KEY,code TEXT,starts_on TEXT,ends_on TEXT)',
            'CREATE TABLE club_programs(id INTEGER PRIMARY KEY,code TEXT,name TEXT)',
            'CREATE TABLE club_program_offers(id INTEGER PRIMARY KEY,program_id INTEGER,season_id INTEGER,team_id INTEGER,product_id INTEGER,variant_id INTEGER,capacity INTEGER,status TEXT,starts_on TEXT,ends_on TEXT,updated_at TEXT)',
            'CREATE TABLE club_program_enrollments(id INTEGER PRIMARY KEY,offer_id INTEGER,sportovec_id INTEGER,status TEXT)',
            'CREATE TABLE shop_products(id INTEGER PRIMARY KEY,name TEXT,origin TEXT,offer_type TEXT,catalog_status TEXT)',
            'CREATE TABLE shop_variants(id INTEGER PRIMARY KEY,product_id INTEGER,sku TEXT,origin TEXT,price_mode TEXT,amount_minor INTEGER,compare_at_amount_minor INTEGER,currency TEXT,unit_code TEXT,catalog_status TEXT,updated_at TEXT)',
            'CREATE TABLE club_program_events(id INTEGER PRIMARY KEY,program_id INTEGER,offer_id INTEGER,actor_type TEXT,actor_id INTEGER,action TEXT,before_json TEXT,after_json TEXT)',
            'CREATE TABLE shop_catalog_admin_events(id INTEGER PRIMARY KEY,product_id INTEGER,variant_id INTEGER,actor_type TEXT,actor_id INTEGER,action TEXT,before_json TEXT,after_json TEXT,reason TEXT)',
        ]as$sql)$pdo->exec($sql);
        $pdo->exec("INSERT INTO club_seasons VALUES(1,'SCHOOL-2026-27','2026-09-01','2027-08-31')");
        $pdo->exec("INSERT INTO club_programs VALUES(1,'KROUZKY-2627-ALIGATORI','Aligátoři'),(2,'KROUZKY-2627-PUMPTRACK','Pumptrack')");
        $pdo->exec("INSERT INTO shop_products VALUES(290,'Pumptrack první pololetí','import','program','active'),(279,'Pumptrack celý rok','import','program','active'),(500,'Kanonický Pumptrack','manual','program','active')");
        $pdo->exec("INSERT INTO shop_variants VALUES(941,290,'997','import','fixed',440000,600000,'CZK','person','active',NULL),(893,279,'1000','import','fixed',850000,NULL,'CZK','person','active',NULL),(501,500,'KP-PUMP-H1','manual','fixed',650000,NULL,'CZK','person','active',NULL),(502,500,'KP-PUMP-FULL','manual','fixed',1150000,NULL,'CZK','person','active',NULL)");
        for($id=1;$id<=74;$id++){
            $pump=$id>=73;$program=$pump?2:1;$product=$pump?500:1000+$id;$variant=$pump?($id===73?501:502):2000+$id;
            $pdo->prepare("INSERT INTO club_program_offers(id,program_id,season_id,team_id,product_id,variant_id,capacity,status,starts_on,ends_on) VALUES(?,?,1,?,?,?,?, 'active','2026-09-01','2027-06-30')")
                ->execute([$id,$program,$id,$product,$variant,null]);
        }
        $pdo->exec("ALTER TABLE club_program_offers ADD COLUMN purchase_option TEXT");
        $pdo->exec("UPDATE club_program_offers SET purchase_option='first_half' WHERE id=73");
        $pdo->exec("UPDATE club_program_offers SET purchase_option='full_year' WHERE id=74");
        return $pdo;
    }
}
