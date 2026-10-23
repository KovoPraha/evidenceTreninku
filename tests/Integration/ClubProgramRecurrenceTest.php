<?php
declare(strict_types=1);
namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/includes/club_program_recurrence.php';

final class ClubProgramRecurrenceTest extends TestCase
{
    public function testRepeatKeepsProductAndTeamButCreatesImmutableDatedOffer():void
    {
        $pdo=$this->database();
        $program=\clubProgramCreate($pdo,7,'ALIGATORI','Aligátoři');
        $offer=\clubProgramCreateOffer($pdo,7,(int)$program['id'],1,10,501,601,'ALIGATORI-2026','Aligátoři 2026/27','2026-09-01','2027-06-30','2026-04-01 00:00:00','2027-03-31 23:59:59',12,'active');
        $pdo->prepare("INSERT INTO club_event_term_versions(scope_type,scope_key,consent_purpose,status,consent_text_plain) VALUES ('club_program',?,'program_cancellation','active','Schválené storno podmínky.'),('club_program',?,'program_consent','active','Schválený souhlas.')")
            ->execute(['program:'.$program['id'],'program:'.$program['id']]);
        $pdo->exec("INSERT INTO shop_orders(id) VALUES(1)");
        $pdo->exec("INSERT INTO shop_order_items(order_id,product_id,variant_id) VALUES(1,501,601)");
        $migration=require dirname(__DIR__,2).'/migrations/20261021100000_club_program_recurring_offers.php';
        $migration['up']($pdo);$migration['up']($pdo);self::assertTrue($migration['verify']($pdo));
        self::assertSame((int)$offer['id'],(int)$pdo->query('SELECT club_program_offer_id FROM shop_order_items WHERE id=1')->fetchColumn());
        $edited=$pdo->query('SELECT * FROM club_program_offers WHERE id='.(int)$offer['id'])->fetch(PDO::FETCH_ASSOC);
        $edited['ends_on']='2027-07-31';
        try{\clubProgramUpdateOffer($pdo,7,(int)$offer['id'],$edited,'Posun po objednávce.',true);self::fail('Purchased period must stay immutable.');}
        catch(\ClubProgramException $exception){self::assertStringContainsString('Zakoupené období',$exception->getMessage());}
        \clubProgramRecurrenceConfigure($pdo,7,(int)$offer['id'],true,1,'Opakovat kroužek v dalším roce.');
        $now=new \DateTimeImmutable('2026-10-20',new \DateTimeZone('Europe/Prague'));
        $preview=\clubProgramRecurrenceRefresh($pdo,$now,false);
        self::assertSame(1,$preview['would_create']);self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM club_program_offers')->fetchColumn());
        $run=\clubProgramRecurrenceRefresh($pdo,$now,true);
        self::assertSame([], $run['errors']);self::assertSame(1,$run['created']);
        self::assertSame(0,\clubProgramRecurrenceRefresh($pdo,$now,true)['created']);
        $next=$pdo->query('SELECT * FROM club_program_offers WHERE recurrence_source_offer_id='.(int)$offer['id'])->fetch(PDO::FETCH_ASSOC);
        self::assertSame('draft',$next['status']);self::assertSame('2027-09-01',$next['starts_on']);self::assertSame('2028-06-30',$next['ends_on']);
        self::assertSame('Aligátoři 2027/28',$next['name']);self::assertSame(10,(int)$next['team_id']);self::assertSame(501,(int)$next['product_id']);self::assertNotSame(601,(int)$next['variant_id']);
        self::assertSame((int)$offer['id'],(int)$pdo->query('SELECT club_program_offer_id FROM shop_order_items WHERE id=1')->fetchColumn());
        self::assertSame('2027/28',\clubProgramSchoolYearLabel($next['starts_on']));
    }

    private function database():PDO
    {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec("CREATE TABLE treneri(id INTEGER PRIMARY KEY);INSERT INTO treneri VALUES(7)");
        $pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY);CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY)');
        $pdo->exec("CREATE TABLE shop_products(id INTEGER PRIMARY KEY,name TEXT,offer_type TEXT,catalog_status TEXT);INSERT INTO shop_products VALUES(501,'Aligátoři','program','active')");
        $pdo->exec("CREATE TABLE shop_variants(id INTEGER PRIMARY KEY AUTOINCREMENT,product_id INTEGER,source_candidate_id INTEGER,origin TEXT,created_by_trainer_id INTEGER,sku TEXT UNIQUE,ean TEXT,attributes_json TEXT,price_mode TEXT,amount_minor INTEGER,compare_at_amount_minor INTEGER,currency TEXT,includes_vat INTEGER,vat_rate_basis_points INTEGER,stock_quantity_decimal TEXT,unit_code TEXT,visible INTEGER,catalog_status TEXT);INSERT INTO shop_variants(id,product_id,sku,attributes_json,price_mode,amount_minor,currency,visible,catalog_status) VALUES(601,501,'KP-ALIGATORI-2026','{}','fixed',250000,'CZK',1,'active')");
        $pdo->exec("CREATE TABLE shop_product_publications(product_id INTEGER PRIMARY KEY,status TEXT);INSERT INTO shop_product_publications VALUES(501,'active')");
        $pdo->exec('CREATE TABLE shop_order_items(id INTEGER PRIMARY KEY AUTOINCREMENT,order_id INTEGER,product_id INTEGER,variant_id INTEGER);CREATE TABLE shop_orders(id INTEGER PRIMARY KEY)');
        $pdo->exec("CREATE TABLE club_seasons(id INTEGER PRIMARY KEY,name TEXT,starts_on TEXT,ends_on TEXT);INSERT INTO club_seasons VALUES(1,'Legacy 2026/27','2026-09-01','2027-08-31')");
        $pdo->exec("CREATE TABLE club_teams(id INTEGER PRIMARY KEY,season_id INTEGER,name TEXT);INSERT INTO club_teams VALUES(10,1,'Aligátoři')");
        $pdo->exec('CREATE TABLE club_roster_members(id INTEGER PRIMARY KEY,team_id INTEGER,sportovec_id INTEGER);CREATE TABLE club_roster_events(id INTEGER PRIMARY KEY)');
        foreach(['20260804140000_club_programs.php','20260817090000_club_program_events.php','20260817130000_club_program_offer_age.php','20260923200000_club_program_storefront.php']as$file){$migration=require dirname(__DIR__,2).'/migrations/'.$file;$migration['up']($pdo);}
        $pdo->exec('CREATE TABLE club_event_term_versions(id INTEGER PRIMARY KEY AUTOINCREMENT,scope_type TEXT,scope_key TEXT,consent_purpose TEXT,status TEXT,consent_text_plain TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        return $pdo;
    }
}
