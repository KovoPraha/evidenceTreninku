<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/member_charge_read.php';
require_once dirname(__DIR__, 2) . '/includes/family_portal.php';
require_once dirname(__DIR__, 2) . '/includes/child_access.php';

final class MemberChargeReadTest extends TestCase
{
    public function testSportovecReadReturnsOnlyExactPersonAndLatestPayment(): void
    {
        $pdo = $this->database();

        $rows = \memberChargeRowsForSportovec($pdo, 10);

        self::assertCount(1, $rows);
        self::assertSame('CH-ANNA', $rows[0]['public_code']);
        self::assertSame('paid', $rows[0]['payment_status']);
        self::assertSame('2026-08-03 10:00:00', $rows[0]['paid_at']);
    }

    public function testFamilyOverviewCannotLeakForeignCharge(): void
    {
        $overview = \familyPortalOverview($this->database(), 1);

        self::assertCount(1, $overview);
        self::assertSame(10, (int)$overview[0]['person']['sportovec_id']);
        self::assertSame(['CH-ANNA'], array_column($overview[0]['member_charges'], 'public_code'));
    }

    public function testChildOverviewDerivesChargeOwnerFromRevocableAccount(): void
    {
        $overview = \childAccessOverview($this->database(), 20);

        self::assertSame(10, (int)$overview['person']['sportovec_id']);
        self::assertSame(['CH-ANNA'], array_column($overview['member_charges'], 'public_code'));
    }

    public function testAdminSearchAndStatusFiltersAreCombined(): void
    {
        $pdo = $this->database();

        self::assertSame(['CH-ANNA'], array_column(\memberChargeAdminRows($pdo, 'Anna', 'paid'), 'public_code'));
        self::assertSame(['CH-BARA'], array_column(\memberChargeAdminRows($pdo, '', 'pending'), 'public_code'));
        self::assertSame([], \memberChargeAdminRows($pdo, 'Anna', 'pending'));
    }

    public function testMissingChargeTablesYieldEmptyReadModel(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        self::assertSame([], \memberChargeRowsForSportovec($pdo, 10));
        self::assertSame([], \memberChargeAdminRows($pdo));
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY, aktivni INTEGER, email_overeno INTEGER)');
        $pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY, jmeno TEXT, prijmeni TEXT, narozeni TEXT, stav_clenstvi TEXT)');
        $pdo->exec('CREATE TABLE account_person_roles(id INTEGER PRIMARY KEY, account_id INTEGER, sportovec_id INTEGER, relation_role TEXT, status TEXT, valid_from TEXT, valid_to TEXT)');
        $pdo->exec('CREATE TABLE child_access_accounts(id INTEGER PRIMARY KEY, sportovec_id INTEGER, login_name TEXT, session_version INTEGER, active INTEGER)');
        $pdo->exec('CREATE TABLE club_member_charges(id INTEGER PRIMARY KEY, public_code TEXT, sportovec_id INTEGER, charge_type TEXT, title_snapshot TEXT, period_from TEXT, period_to TEXT, amount_minor INTEGER, currency TEXT, due_on TEXT, status TEXT, source_system TEXT, created_at TEXT)');
        $pdo->exec('CREATE TABLE payments(id INTEGER PRIMARY KEY, payable_type TEXT, payable_id INTEGER, status TEXT, method TEXT, variable_symbol TEXT, paid_at TEXT)');
        $pdo->exec('INSERT INTO verejni_uzivatele VALUES(1,1,1),(2,1,1)');
        $pdo->exec("INSERT INTO sportovci VALUES(10,'Anna','První','2012-01-01','aktivni'),(11,'Bára','Druhá','2014-01-01','aktivni')");
        $pdo->exec("INSERT INTO account_person_roles VALUES(1,1,10,'guardian','approved','2020-01-01',NULL),(2,2,11,'guardian','approved','2020-01-01',NULL)");
        $pdo->exec("INSERT INTO child_access_accounts VALUES(20,10,'anna',1,1),(21,11,'bara',1,1)");
        $pdo->exec("INSERT INTO club_member_charges VALUES(100,'CH-ANNA',10,'membership','Členský příspěvek','2026-01-01','2026-12-31',250000,'CZK','2026-08-15','paid','kis','2026-08-01 10:00:00'),(101,'CH-BARA',11,'membership','Příspěvek U13','2026-01-01','2026-12-31',180000,'CZK','2026-08-20','pending','kis','2026-08-01 11:00:00')");
        $pdo->exec("INSERT INTO payments VALUES(1,'member_charge',100,'pending','bank_transfer','100001',NULL),(2,'member_charge',100,'paid','bank_transfer','100001','2026-08-03 10:00:00')");
        return $pdo;
    }
}
