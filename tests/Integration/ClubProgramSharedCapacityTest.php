<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/includes/club_program.php';

final class ClubProgramSharedCapacityTest extends TestCase
{
    public function testAnnualAndSemesterOffersShareRosterCapacityWithoutDoubleCountingAthlete():void
    {
        $pdo=new PDO('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE club_program_offers(id INTEGER PRIMARY KEY,team_id INTEGER,product_id INTEGER,variant_id INTEGER,starts_on TEXT,ends_on TEXT);CREATE TABLE club_program_enrollments(id INTEGER PRIMARY KEY,offer_id INTEGER,sportovec_id INTEGER,status TEXT,source_order_item_id INTEGER);CREATE TABLE shop_orders(id INTEGER PRIMARY KEY,status TEXT,payment_status TEXT,payment_expires_at TEXT);CREATE TABLE shop_order_items(id INTEGER PRIMARY KEY,order_id INTEGER,product_id INTEGER,variant_id INTEGER,quantity INTEGER,beneficiary_sportovec_id INTEGER,athlete_registration_request_id INTEGER)');
        $pdo->exec("INSERT INTO club_program_offers VALUES(1,10,100,1001,'2026-09-01','2027-06-30'),(2,10,101,1002,'2026-09-01','2027-01-31'),(3,10,102,1003,'2027-02-01','2027-06-30');INSERT INTO club_program_enrollments VALUES(1,1,50,'active',900);INSERT INTO shop_orders VALUES(20,'placed','pending','2099-12-31 23:59:59'),(21,'placed','pending','2099-12-31 23:59:59'),(22,'placed','pending','2099-12-31 23:59:59');INSERT INTO shop_order_items VALUES(200,20,101,1002,1,51,NULL),(201,21,101,1002,1,50,NULL),(202,22,102,1003,1,NULL,99)");
        $first=\clubProgramOfferCapacityState($pdo,['id'=>2,'team_id'=>10,'product_id'=>101,'variant_id'=>1002,'starts_on'=>'2026-09-01','ends_on'=>'2027-01-31','capacity'=>2]);
        self::assertSame(1,$first['active_enrollment_count']);self::assertSame(1,$first['held_order_count']);self::assertSame(0,$first['available_count']);
        $second=\clubProgramOfferCapacityState($pdo,['id'=>3,'team_id'=>10,'product_id'=>102,'variant_id'=>1003,'starts_on'=>'2027-02-01','ends_on'=>'2027-06-30','capacity'=>3]);
        self::assertSame(1,$second['active_enrollment_count']);self::assertSame(1,$second['held_order_count']);self::assertSame(1,$second['available_count']);
    }
}
