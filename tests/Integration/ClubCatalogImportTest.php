<?php
declare(strict_types=1);
namespace Tests\Integration;

use FilesystemIterator;
use PDO;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

require_once dirname(__DIR__,2).'/includes/club_catalog_import.php';

final class ClubCatalogImportTest extends TestCase
{
    private string$root;
    protected function setUp():void
    {
        $this->root=sys_get_temp_dir().DIRECTORY_SEPARATOR.'club-catalog-import-'.bin2hex(random_bytes(5));mkdir($this->root.'/assets/clubs',0750,true);
        foreach(['source-group.jpg','source-trail.jpg','source-youngest.jpg','source-descent.jpg','source-coach.jpg']as$file)copy(dirname(__DIR__,2).'/assets/clubs/'.$file,$this->root.'/assets/clubs/'.$file);
    }
    protected function tearDown():void
    {
        if(!is_dir($this->root))return;$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
        foreach($iterator as$item)$item->isDir()?rmdir($item->getPathname()):unlink($item->getPathname());rmdir($this->root);
    }

    public function testImportCreatesCanonicalKisProductsAndIsIdempotent():void
    {
        $pdo=$this->database();$first=\clubCatalogImport($pdo,7,$this->root);$second=\clubCatalogImport($pdo,7,$this->root);
        self::assertSame(26,$first['created']);self::assertSame(0,$second['created']);self::assertSame(26,$second['updated']);
        self::assertSame(26,$second['programs']);self::assertSame(26,$second['products']);self::assertSame(49,$second['variants']);
        self::assertSame(26,(int)$pdo->query("SELECT COUNT(*) FROM club_program_presentations WHERE listing_status='published'")->fetchColumn());
        self::assertSame(26,(int)$pdo->query('SELECT COUNT(*) FROM club_program_images')->fetchColumn());
        self::assertSame(26,(int)$pdo->query("SELECT COUNT(DISTINCT team_id) FROM club_program_offers o JOIN club_programs p ON p.id=o.program_id WHERE p.code LIKE 'KROUZKY-2627-%'")->fetchColumn());
        self::assertSame(0,(int)$pdo->query("SELECT COUNT(*) FROM club_program_offers o JOIN shop_variants v ON v.id=o.variant_id JOIN club_programs p ON p.id=o.program_id WHERE p.code LIKE 'KROUZKY-2627-%' AND v.stock_quantity_decimal IS NOT NULL")->fetchColumn());
        $shared=$pdo->query("SELECT COUNT(DISTINCT team_id) teams,COUNT(*) offers FROM club_program_offers o JOIN club_programs p ON p.id=o.program_id WHERE p.code='KROUZKY-2627-ALIGATORI'")->fetch(PDO::FETCH_ASSOC);
        self::assertSame(1,(int)$shared['teams']);self::assertSame(2,(int)$shared['offers']);
    }

    public function testFallbackNeverLinksTheOldShopOrDisplaysMigrationCopy():void
    {
        $fallback=\legacyClubCatalogFallback([],new \DateTimeImmutable('2026-09-24',new \DateTimeZone('Europe/Prague')));
        self::assertNotEmpty($fallback);
        foreach($fallback as$program){self::assertSame('',$program['public_summary']);foreach($program['offers']as$offer){self::assertFalse($offer['saleable']);self::assertNull($offer['source_url']);}}
        $page=(string)file_get_contents(dirname(__DIR__,2).'/booking/cyklisticke_krouzky.php');
        self::assertStringNotContainsString('Koupit ve stávajícím e-shopu',$page);
        self::assertStringNotContainsString('shop.kovopraha.cz',$page);
    }

    private function database():PDO
    {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$pdo->exec('PRAGMA foreign_keys=OFF');
        $schema=[
            "CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT,role TEXT,aktivni INTEGER)","INSERT INTO treneri VALUES(7,'Admin','admin',1)",
            "CREATE TABLE shop_products(id INTEGER PRIMARY KEY AUTOINCREMENT,source_candidate_id INTEGER NULL,source_run_id INTEGER NULL,origin TEXT NOT NULL,created_by_trainer_id INTEGER NULL,external_product_key TEXT NOT NULL UNIQUE,name TEXT NOT NULL,short_description TEXT NULL,description_html_untrusted TEXT NULL,offer_type TEXT NOT NULL,visibility TEXT NULL,item_type TEXT NULL,catalog_status TEXT NOT NULL DEFAULT 'draft',created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)",
            "CREATE TABLE shop_variants(id INTEGER PRIMARY KEY AUTOINCREMENT,product_id INTEGER NOT NULL,source_candidate_id INTEGER NULL,origin TEXT NOT NULL,created_by_trainer_id INTEGER NULL,sku TEXT NOT NULL UNIQUE,ean TEXT NULL,attributes_json TEXT NOT NULL,price_mode TEXT NOT NULL,amount_minor INTEGER NULL,compare_at_amount_minor INTEGER NULL,currency TEXT NULL,includes_vat INTEGER NULL,vat_rate_basis_points INTEGER NULL,stock_quantity_decimal TEXT NULL,unit_code TEXT NULL,availability_in_stock TEXT NULL,availability_out_of_stock TEXT NULL,free_shipping INTEGER NULL,free_billing INTEGER NULL,visible INTEGER NULL,catalog_status TEXT NOT NULL DEFAULT 'draft',created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)",
            'CREATE TABLE shop_product_categories(id INTEGER PRIMARY KEY AUTOINCREMENT,product_id INTEGER,category_path TEXT,is_default INTEGER,sort_order INTEGER,UNIQUE(product_id,category_path))',
            'CREATE TABLE shop_product_images(id INTEGER PRIMARY KEY AUTOINCREMENT,product_id INTEGER,image_url TEXT,sort_order INTEGER,UNIQUE(product_id,image_url))',
            'CREATE TABLE shop_catalog_admin_events(id INTEGER PRIMARY KEY AUTOINCREMENT,product_id INTEGER,variant_id INTEGER,actor_type TEXT,actor_id INTEGER,action TEXT,before_json TEXT,after_json TEXT,reason TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP)',
            'CREATE TABLE shop_product_publications(product_id INTEGER PRIMARY KEY,status TEXT,public_name TEXT,public_summary TEXT,decision_note TEXT,activated_by_trainer_id INTEGER,activated_at TEXT,deactivated_at TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)',
            'CREATE TABLE shop_product_publication_events(id INTEGER PRIMARY KEY AUTOINCREMENT,product_id INTEGER,actor_trainer_id INTEGER,action TEXT,from_status TEXT,to_status TEXT,public_name TEXT,public_summary TEXT,note TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP)',
            "CREATE TABLE club_seasons(id INTEGER PRIMARY KEY AUTOINCREMENT,code TEXT UNIQUE,name TEXT,season_type TEXT,starts_on TEXT,ends_on TEXT,status TEXT DEFAULT 'active',created_by_trainer_id INTEGER,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)",
            "CREATE TABLE club_teams(id INTEGER PRIMARY KEY AUTOINCREMENT,season_id INTEGER,series_id INTEGER NULL,code TEXT,name TEXT,discipline TEXT,age_label TEXT,status TEXT DEFAULT 'active',created_by_trainer_id INTEGER,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP,UNIQUE(season_id,code))",
            'CREATE TABLE club_team_series(id INTEGER PRIMARY KEY,season_type TEXT,status TEXT)','CREATE TABLE club_roster_events(id INTEGER PRIMARY KEY AUTOINCREMENT,team_id INTEGER,roster_member_id INTEGER,actor_trainer_id INTEGER,action TEXT,before_json TEXT,after_json TEXT,note TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP)',
            "CREATE TABLE club_programs(id INTEGER PRIMARY KEY AUTOINCREMENT,code TEXT UNIQUE,name TEXT,description TEXT,status TEXT DEFAULT 'active',created_by_trainer_id INTEGER,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)",
            "CREATE TABLE club_program_offers(id INTEGER PRIMARY KEY AUTOINCREMENT,program_id INTEGER,season_id INTEGER,team_id INTEGER,product_id INTEGER,variant_id INTEGER UNIQUE,code TEXT UNIQUE,name TEXT,starts_on TEXT,ends_on TEXT,sales_open_at TEXT,sales_close_at TEXT,capacity INTEGER,birth_year_from INTEGER,birth_year_to INTEGER,status TEXT,created_by_trainer_id INTEGER,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)",
            'CREATE TABLE club_program_enrollments(id INTEGER PRIMARY KEY AUTOINCREMENT,offer_id INTEGER,sportovec_id INTEGER,status TEXT)',
            'CREATE TABLE club_program_events(id INTEGER PRIMARY KEY AUTOINCREMENT,program_id INTEGER,offer_id INTEGER,actor_type TEXT,actor_id INTEGER,action TEXT,before_json TEXT,after_json TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP)',
            "CREATE TABLE club_event_term_versions(id INTEGER PRIMARY KEY AUTOINCREMENT,scope_type TEXT,scope_key TEXT,consent_purpose TEXT,event_id INTEGER,terms_version TEXT,consent_text_plain TEXT,cancellation_policy_plain TEXT,cancellation_deadline_at TEXT,actor_trainer_id INTEGER,actor_type TEXT,actor_id INTEGER,status TEXT DEFAULT 'active',archived_at TEXT,archived_by_trainer_id INTEGER,created_at TEXT DEFAULT CURRENT_TIMESTAMP,UNIQUE(scope_type,scope_key,consent_purpose,terms_version))",
        ];foreach($schema as$sql)$pdo->exec($sql);
        $migration=require dirname(__DIR__,2).'/migrations/20260923200000_club_program_storefront.php';$migration['up']($pdo);
        $pdo->exec("INSERT INTO club_programs(id,code,name,description,status,created_by_trainer_id) VALUES(900,'TEMPLATE','Schválený vzor','Vzor','active',7)");
        $pdo->exec("INSERT INTO club_event_term_versions(scope_type,scope_key,consent_purpose,terms_version,consent_text_plain,actor_type,actor_id,status) VALUES('club_program','program:900','program_cancellation','v1','Schválené storno podmínky.','trainer',7,'active'),('club_program','program:900','program_consent','v1','Schválený souhlas s účastí.','trainer',7,'active')");
        return$pdo;
    }
}
