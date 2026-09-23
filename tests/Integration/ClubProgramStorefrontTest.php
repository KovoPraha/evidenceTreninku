<?php
declare(strict_types=1);

namespace Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/includes/club_program_storefront.php';

final class ClubProgramStorefrontTest extends TestCase
{
    public function testMostClubsCanSellTwoSemestersAndDiscountedYearIntoOneRoster():void
    {
        $pdo=$this->database();
        $pdo->exec("UPDATE club_program_offers SET purchase_option='full_year',is_featured=1 WHERE id=1");

        $first=\clubProgramCreatePaymentOption($pdo,7,1,$this->option(
            'first_half','MORCATA-H1-2627','MORCATA-2627-H1','Morčata — 1. pololetí',800000,'2026-09-01','2027-01-31'
        ),'Pololetní platba pro stejnou skupinu.',true);
        $second=\clubProgramCreatePaymentOption($pdo,7,1,$this->option(
            'second_half','MORCATA-H2-2627','MORCATA-2627-H2','Morčata — 2. pololetí',800000,'2027-02-01','2027-06-30'
        ),'Druhé pololetí je možné koupit už od září.',true);

        self::assertSame(3,(int)$pdo->query('SELECT COUNT(*) FROM club_program_offers')->fetchColumn());
        self::assertSame([10],array_map('intval',$pdo->query('SELECT DISTINCT team_id FROM club_program_offers')->fetchAll(PDO::FETCH_COLUMN)));
        self::assertSame([501],array_map('intval',$pdo->query('SELECT DISTINCT product_id FROM club_program_offers')->fetchAll(PDO::FETCH_COLUMN)));
        self::assertSame(800000,(int)$pdo->query('SELECT amount_minor FROM shop_variants WHERE id='.(int)$first['variant_id'])->fetchColumn());
        self::assertSame(800000,(int)$pdo->query('SELECT amount_minor FROM shop_variants WHERE id='.(int)$second['variant_id'])->fetchColumn());
        self::assertSame(1500000,(int)$pdo->query("SELECT amount_minor FROM shop_variants WHERE sku='MORCATA-YEAR-2627'")->fetchColumn());
        self::assertSame('2026-09-01 00:00:00',$pdo->query("SELECT sales_open_at FROM club_program_offers WHERE purchase_option='second_half'")->fetchColumn());

        \clubProgramStorefrontSavePresentation($pdo,7,5,[
            'public_name'=>'Morčata','public_summary'=>'Jedna skupina, tři způsoby úhrady.',
            'location_name'=>'Velodrom Třebešín','age_label'=>'4–6 let','listing_status'=>'published',
            'sort_order'=>10,'interest_enabled'=>1,
        ],'Zveřejnění kroužku.',true);
        \clubProgramStorefrontAddSchedule($pdo,7,5,[
            'season_id'=>1,'weekday'=>1,'starts_at'=>'16:00','ends_at'=>'17:00','location_name'=>'Velodrom Třebešín','sort_order'=>0,
        ],'Pravidelný termín.',true);

        $catalog=\clubProgramStorefrontCatalog($pdo,new DateTimeImmutable('2026-09-23 12:00:00',new DateTimeZone('Europe/Prague')));
        self::assertCount(1,$catalog);self::assertSame('Morčata',$catalog[0]['public_name']);self::assertCount(3,$catalog[0]['offers']);
        self::assertSame(['full_year','first_half','second_half'],array_column($catalog[0]['offers'],'purchase_option'));
        self::assertSame([true,true,true],array_column($catalog[0]['offers'],'saleable'));
        self::assertSame(10,(int)$catalog[0]['offers'][2]['team_id']);
        self::assertSame('Pondělí',\clubProgramStorefrontWeekdayLabel((int)$catalog[0]['schedule'][0]['weekday']));
    }

    public function testMigrationIsIdempotent():void
    {
        $pdo=$this->database(false);$migration=require dirname(__DIR__,2).'/migrations/20260923200000_club_program_storefront.php';
        $migration['up']($pdo);$migration['up']($pdo);self::assertTrue($migration['verify']($pdo));
        self::assertSame('custom',$pdo->query('SELECT purchase_option FROM club_program_offers WHERE id=1')->fetchColumn());
    }

    /** @return array<string,mixed> */
    private function option(string$option,string$sku,string$code,string$name,int$amount,string$starts,string$ends):array
    {
        return[
            'purchase_option'=>$option,'is_featured'=>0,'sku'=>$sku,'code'=>$code,'name'=>$name,
            'amount_minor'=>$amount,'compare_at_amount_minor'=>null,'includes_vat'=>1,'vat_rate_basis_points'=>0,
            'starts_on'=>$starts,'ends_on'=>$ends,'sales_open_at'=>'2026-09-01T00:00','sales_close_at'=>'2027-02-28T23:59',
            'capacity'=>15,'birth_year_from'=>2020,'birth_year_to'=>2022,'status'=>'active',
        ];
    }

    private function database(bool$withMigration=true):PDO
    {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$pdo->exec('PRAGMA foreign_keys=ON');
        $schema=[
            'CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT)',
            "INSERT INTO treneri VALUES(7,'Admin')",
            "CREATE TABLE shop_products(id INTEGER PRIMARY KEY,origin TEXT,external_product_key TEXT,name TEXT,offer_type TEXT,catalog_status TEXT,created_by_trainer_id INTEGER NULL)",
            "INSERT INTO shop_products VALUES(501,'manual','manual:501','Morčata','program','active',7)",
            "CREATE TABLE shop_variants(id INTEGER PRIMARY KEY AUTOINCREMENT,product_id INTEGER,source_candidate_id INTEGER NULL,origin TEXT,created_by_trainer_id INTEGER NULL,sku TEXT UNIQUE,ean TEXT NULL,attributes_json TEXT,price_mode TEXT,amount_minor INTEGER,compare_at_amount_minor INTEGER NULL,currency TEXT,includes_vat INTEGER NULL,vat_rate_basis_points INTEGER NULL,stock_quantity_decimal TEXT NULL,unit_code TEXT NULL,visible INTEGER,catalog_status TEXT,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)",
            "INSERT INTO shop_variants(product_id,origin,created_by_trainer_id,sku,attributes_json,price_mode,amount_minor,currency,includes_vat,vat_rate_basis_points,unit_code,visible,catalog_status) VALUES(501,'manual',7,'MORCATA-YEAR-2627','{}','fixed',1500000,'CZK',1,0,'person',1,'active')",
            'CREATE TABLE shop_product_publications(product_id INTEGER PRIMARY KEY,status TEXT,public_name TEXT,public_summary TEXT)',
            "INSERT INTO shop_product_publications VALUES(501,'active','Morčata','Cyklistický kroužek')",
            'CREATE TABLE shop_catalog_admin_events(id INTEGER PRIMARY KEY AUTOINCREMENT,product_id INTEGER,variant_id INTEGER NULL,actor_type TEXT,actor_id INTEGER,action TEXT,before_json TEXT NULL,after_json TEXT NULL,reason TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP)',
            "CREATE TABLE club_seasons(id INTEGER PRIMARY KEY,code TEXT,name TEXT,starts_on TEXT,ends_on TEXT,status TEXT,created_by_trainer_id INTEGER NULL)",
            "INSERT INTO club_seasons VALUES(1,'SCHOOL-2627','Školní rok 2026/27','2026-09-01','2027-08-31','active',7)",
            "CREATE TABLE club_teams(id INTEGER PRIMARY KEY,season_id INTEGER,code TEXT,name TEXT,status TEXT)",
            "INSERT INTO club_teams VALUES(10,1,'MORCATA','Morčata 2026/27','active')",
            "CREATE TABLE club_programs(id INTEGER PRIMARY KEY,code TEXT,name TEXT,description TEXT,status TEXT,created_by_trainer_id INTEGER NULL)",
            "INSERT INTO club_programs VALUES(5,'MORCATA','Morčata','Kroužek pro děti.','active',7)",
            "CREATE TABLE club_program_offers(id INTEGER PRIMARY KEY AUTOINCREMENT,program_id INTEGER,season_id INTEGER,team_id INTEGER,product_id INTEGER,variant_id INTEGER UNIQUE,code TEXT UNIQUE,name TEXT,starts_on TEXT,ends_on TEXT,sales_open_at TEXT NULL,sales_close_at TEXT NULL,capacity INTEGER NULL,birth_year_from INTEGER NULL,birth_year_to INTEGER NULL,status TEXT,created_by_trainer_id INTEGER NULL,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)",
            "INSERT INTO club_program_offers(program_id,season_id,team_id,product_id,variant_id,code,name,starts_on,ends_on,sales_open_at,sales_close_at,capacity,birth_year_from,birth_year_to,status,created_by_trainer_id) VALUES(5,1,10,501,1,'MORCATA-2627-YEAR','Morčata — celý rok','2026-09-01','2027-06-30','2026-09-01 00:00:00','2026-10-31 23:59:00',15,2020,2022,'active',7)",
            'CREATE TABLE club_program_enrollments(id INTEGER PRIMARY KEY AUTOINCREMENT,offer_id INTEGER,sportovec_id INTEGER,status TEXT)',
            'CREATE TABLE club_program_events(id INTEGER PRIMARY KEY AUTOINCREMENT,program_id INTEGER,offer_id INTEGER NULL,actor_type TEXT,actor_id INTEGER,action TEXT,before_json TEXT NULL,after_json TEXT NULL,created_at TEXT DEFAULT CURRENT_TIMESTAMP)',
            "CREATE TABLE club_event_term_versions(id INTEGER PRIMARY KEY AUTOINCREMENT,scope_type TEXT,scope_key TEXT,consent_purpose TEXT,terms_version TEXT,consent_text_plain TEXT,status TEXT)",
            "INSERT INTO club_event_term_versions(scope_type,scope_key,consent_purpose,terms_version,consent_text_plain,status) VALUES('club_program','program:5','program_cancellation','v1','Storno podmínky.','active'),('club_program','program:5','program_consent','v1','Souhlas s účastí.','active')",
        ];foreach($schema as$sql)$pdo->exec($sql);
        if($withMigration){$migration=require dirname(__DIR__,2).'/migrations/20260923200000_club_program_storefront.php';$migration['up']($pdo);self::assertTrue($migration['verify']($pdo));}
        return$pdo;
    }
}
