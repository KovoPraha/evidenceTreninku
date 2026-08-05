<?php
declare(strict_types=1);

namespace Tests\Integration;

use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/family_annual_paid_overview.php';

final class FamilyAnnualPaidOverviewTest extends TestCase
{
    public function testItReturnsOnlyPaidRowsForCurrentlyAuthorizedPeopleAndKeepsSourcesSeparate(): void
    {
        $pdo = $this->database();

        $overview = familyAnnualPaidOverview($pdo, 1, 2026);

        self::assertSame(['CH-PAID-CZK', 'CH-PAID-EUR'], array_column($overview['member_charges'], 'public_code'));
        self::assertSame(['ORD-PAID'], array_column($overview['shop_items'], 'public_code'));
        self::assertSame(['CZK' => 250000, 'EUR' => 1200], $overview['totals']['member_charges']);
        self::assertSame(['CZK' => 159900], $overview['totals']['shop_items']);
        self::assertSame('2 500,00 CZK + 12,00 EUR', familyAnnualPaidOverviewTotalsLabel($overview['totals']['member_charges']));

        self::assertNotContains('CH-PENDING', array_column($overview['member_charges'], 'public_code'));
        self::assertNotContains('CH-OLD', array_column($overview['member_charges'], 'public_code'));
        self::assertNotContains('CH-FOREIGN', array_column($overview['member_charges'], 'public_code'));
        self::assertNotContains('ORD-REFUND', array_column($overview['shop_items'], 'public_code'));
        self::assertNotContains('ORD-FOREIGN', array_column($overview['shop_items'], 'public_code'));
    }

    public function testRevokedRelationImmediatelyRemovesFinancialRows(): void
    {
        $pdo = $this->database();
        $pdo->exec("UPDATE account_person_roles SET status='revoked' WHERE account_id=1");

        $overview = familyAnnualPaidOverview($pdo, 1, 2026);

        self::assertSame([], $overview['member_charges']);
        self::assertSame([], $overview['shop_items']);
        self::assertSame([], $overview['totals']['member_charges']);
        self::assertSame([], $overview['totals']['shop_items']);
    }

    public function testYearInputDefaultsToCurrentYearAndRejectsFutureOrMalformedValues(): void
    {
        $today = new DateTimeImmutable('2026-08-05 12:00:00');
        self::assertSame(2026, familyAnnualPaidOverviewYear('', $today));
        self::assertSame(2025, familyAnnualPaidOverviewYear('2025', $today));

        foreach (['2027', '26', '2026x', ['2026']] as $invalid) {
            try {
                familyAnnualPaidOverviewYear($invalid, $today);
                self::fail('Invalid year was accepted.');
            } catch (\InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testPageWiringStatesInformationalBoundaryAndDoesNotAcceptPersonId(): void
    {
        $source = (string)file_get_contents(dirname(__DIR__, 2) . '/booking/sportovni_prehled.php');
        self::assertStringContainsString('familyAnnualPaidOverview($pdo, $accountId', $source);
        self::assertStringContainsString('Roční přehled uhrazených klubových služeb', $source);
        self::assertStringContainsString('Není účetním ani daňovým dokladem', $source);
        self::assertStringContainsString('nesčítají se do jednoho součtu', $source);
        self::assertStringNotContainsString("\$_GET['sportovec_id']", $source);
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,aktivni INTEGER,email_overeno INTEGER)');
        $pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,narozeni TEXT,stav_clenstvi TEXT)');
        $pdo->exec('CREATE TABLE account_person_roles(id INTEGER PRIMARY KEY,account_id INTEGER,sportovec_id INTEGER,relation_role TEXT,status TEXT,valid_from TEXT,valid_to TEXT)');
        $pdo->exec('CREATE TABLE club_member_charges(id INTEGER PRIMARY KEY,sportovec_id INTEGER,public_code TEXT,title_snapshot TEXT,amount_minor INTEGER,currency TEXT,status TEXT)');
        $pdo->exec('CREATE TABLE shop_orders(id INTEGER PRIMARY KEY,public_code TEXT,account_id INTEGER,status TEXT,payment_status TEXT)');
        $pdo->exec('CREATE TABLE shop_order_items(id INTEGER PRIMARY KEY,order_id INTEGER,beneficiary_sportovec_id INTEGER,product_name_snapshot TEXT,quantity INTEGER,line_amount_minor INTEGER,currency TEXT)');
        $pdo->exec('CREATE TABLE payments(id INTEGER PRIMARY KEY,payable_type TEXT,payable_id INTEGER,status TEXT,paid_at TEXT)');

        $pdo->exec("INSERT INTO verejni_uzivatele VALUES(1,1,1),(2,1,1)");
        $pdo->exec("INSERT INTO sportovci VALUES(10,'Anna','Rodinná','2012-04-01','active'),(11,'Cizí','Profil','2011-02-02','active')");
        $pdo->exec("INSERT INTO account_person_roles VALUES(1,1,10,'guardian','approved','2020-01-01 00:00:00',NULL),(2,2,11,'guardian','approved','2020-01-01 00:00:00',NULL)");

        $pdo->exec("INSERT INTO club_member_charges VALUES"
            . "(1,10,'CH-PAID-CZK','Roční příspěvek',250000,'CZK','paid'),"
            . "(2,10,'CH-PAID-EUR','Zahraniční startovné',1200,'EUR','paid'),"
            . "(3,10,'CH-PENDING','Neuhrazený příspěvek',50000,'CZK','pending'),"
            . "(4,10,'CH-OLD','Starší příspěvek',40000,'CZK','paid'),"
            . "(5,11,'CH-FOREIGN','Cizí příspěvek',90000,'CZK','paid')");
        $pdo->exec("INSERT INTO shop_orders VALUES"
            . "(1,'ORD-PAID',2,'completed','paid'),"
            . "(2,'ORD-REFUND',1,'cancelled','refunded'),"
            . "(3,'ORD-FOREIGN',2,'completed','paid')");
        $pdo->exec("INSERT INTO shop_order_items VALUES"
            . "(1,1,10,'Klubový dres',1,159900,'CZK'),"
            . "(2,2,10,'Vrácená položka',1,9900,'CZK'),"
            . "(3,3,11,'Cizí položka',1,19900,'CZK')");
        $pdo->exec("INSERT INTO payments VALUES"
            . "(1,'member_charge',1,'paid','2026-03-02 10:00:00'),"
            . "(2,'member_charge',2,'paid','2026-02-01 09:00:00'),"
            . "(3,'member_charge',3,'pending',NULL),"
            . "(4,'member_charge',4,'paid','2025-12-20 10:00:00'),"
            . "(5,'member_charge',5,'paid','2026-04-02 10:00:00'),"
            . "(6,'shop_order',1,'paid','2026-05-10 12:00:00'),"
            . "(7,'shop_order',2,'refunded','2026-05-11 12:00:00'),"
            . "(8,'shop_order',3,'paid','2026-06-10 12:00:00')");

        return $pdo;
    }
}
