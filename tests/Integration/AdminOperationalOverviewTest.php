<?php
declare(strict_types=1);

namespace Tests\Integration;

use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/admin_operational_overview.php';

final class AdminOperationalOverviewTest extends TestCase
{
    public function testItAggregatesOnlyActionableCanonicalStates(): void
    {
        $overview = adminOperationalOverview($this->database(), new DateTimeImmutable('2026-08-05 12:00:00'));

        self::assertSame([], $overview['unavailable']);
        self::assertSame(13, $overview['signal_count']);
        self::assertSame([
            'overdue_member_charges', 'refunds_required', 'expired_shop_orders', 'fio_proposals',
        ], array_column($overview['sections']['payments']['items'], 'key'));
        self::assertSame(['full_events', 'full_program_offers'], array_column($overview['sections']['capacity']['items'], 'key'));
        self::assertSame([
            'waitlisted_registrations', 'pending_person_claims', 'payment_pending_registrations',
        ], array_column($overview['sections']['registrations']['items'], 'key'));
        self::assertSame([
            'failed_charge_reminders', 'failed_event_notifications', 'fio_review', 'kis_conflicts',
        ], array_column($overview['sections']['exceptions']['items'], 'key'));

        $refund = $this->item($overview, 'payments', 'refunds_required');
        self::assertSame(1, $refund['count']);
        self::assertSame('eshop_orders_admin.php', $refund['href']);
        $kis = $this->item($overview, 'exceptions', 'kis_conflicts');
        self::assertSame(1, $kis['count'], 'Only conflicts from the latest KIS run are actionable.');
    }

    public function testResolvedAndNonActionableStatesProduceNoSignals(): void
    {
        $pdo = $this->database();
        $pdo->exec("UPDATE club_member_charges SET status='paid'");
        $pdo->exec("UPDATE shop_orders SET status='completed',payment_status='paid'");
        $pdo->exec("UPDATE payments SET status='paid'");
        $pdo->exec("UPDATE fio_account_movements SET match_status='ignored_non_credit'");
        $pdo->exec("UPDATE club_events SET status='closed'");
        $pdo->exec("UPDATE club_program_offers SET capacity=NULL");
        $pdo->exec("UPDATE club_event_registrations SET status='cancelled'");
        $pdo->exec("UPDATE account_person_claim_requests SET status='approved'");
        $pdo->exec("UPDATE member_charge_reminders SET status='sent'");
        $pdo->exec("UPDATE club_event_notifications SET status='sent'");
        $pdo->exec("UPDATE kis_import_matches SET match_status='matched'");

        $overview = adminOperationalOverview($pdo, new DateTimeImmutable('2026-08-05 12:00:00'));

        self::assertSame(0, $overview['signal_count']);
        foreach ($overview['sections'] as $section) self::assertSame([], $section['items']);
    }

    public function testMissingSourceIsReportedInsteadOfPretendingZero(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $overview = adminOperationalOverview($pdo, new DateTimeImmutable('2026-08-05 12:00:00'));

        self::assertSame(0, $overview['signal_count']);
        self::assertContains('objednávky a platby', $overview['unavailable']);
        self::assertContains('klubové akce a přihlášky', $overview['unavailable']);
        self::assertContains('KIS importní kontrola', $overview['unavailable']);
    }

    public function testPageIsAdminOnlyReadOnlyAndLinkedFromNavigation(): void
    {
        $root = dirname(__DIR__, 2);
        $page = (string)file_get_contents($root . '/provozni_prehled_admin.php');
        $header = (string)file_get_contents($root . '/hlavicka.php');
        self::assertStringContainsString("roleAtLeast('admin')", $page);
        self::assertStringContainsString("Cache-Control: no-store, private", $page);
        self::assertStringContainsString('Přehled je pouze ke čtení', $page);
        self::assertStringNotContainsString("REQUEST_METHOD", $page);
        self::assertStringContainsString('provozni_prehled_admin.php', $header);
    }

    /** @return array<string,mixed> */
    private function item(array $overview, string $section, string $key): array
    {
        foreach ($overview['sections'][$section]['items'] as $item) if ($item['key'] === $key) return $item;
        self::fail('Missing operational item: ' . $section . '/' . $key);
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE club_member_charges(id INTEGER PRIMARY KEY,status TEXT,due_on TEXT)');
        $pdo->exec('CREATE TABLE shop_orders(id INTEGER PRIMARY KEY,status TEXT,payment_status TEXT,payment_expires_at TEXT)');
        $pdo->exec('CREATE TABLE payments(id INTEGER PRIMARY KEY,payable_type TEXT,payable_id INTEGER,status TEXT,due_at TEXT)');
        $pdo->exec('CREATE TABLE fio_account_movements(id INTEGER PRIMARY KEY,match_status TEXT)');
        $pdo->exec('CREATE TABLE club_events(id INTEGER PRIMARY KEY,status TEXT,capacity INTEGER)');
        $pdo->exec('CREATE TABLE club_event_sessions(id INTEGER PRIMARY KEY,event_id INTEGER,status TEXT,capacity_override INTEGER)');
        $pdo->exec('CREATE TABLE club_event_registrations(id INTEGER PRIMARY KEY,event_id INTEGER,status TEXT)');
        $pdo->exec('CREATE TABLE club_program_offers(id INTEGER PRIMARY KEY,status TEXT,capacity INTEGER)');
        $pdo->exec('CREATE TABLE club_program_enrollments(id INTEGER PRIMARY KEY,offer_id INTEGER,status TEXT)');
        $pdo->exec('CREATE TABLE account_person_claim_requests(id INTEGER PRIMARY KEY,status TEXT)');
        $pdo->exec('CREATE TABLE member_charge_reminders(id INTEGER PRIMARY KEY,status TEXT)');
        $pdo->exec('CREATE TABLE club_event_notifications(id INTEGER PRIMARY KEY,status TEXT)');
        $pdo->exec('CREATE TABLE kis_import_runs(id INTEGER PRIMARY KEY)');
        $pdo->exec('CREATE TABLE kis_import_matches(id INTEGER PRIMARY KEY,run_id INTEGER,match_status TEXT)');

        $pdo->exec("INSERT INTO club_member_charges VALUES(1,'pending','2026-08-01'),(2,'pending','2026-08-10'),(3,'paid','2026-07-01')");
        $pdo->exec("INSERT INTO shop_orders VALUES(1,'cancelled','refund_required',NULL),(2,'placed','pending','2026-08-04 10:00:00'),(3,'completed','paid',NULL)");
        $pdo->exec("INSERT INTO payments VALUES(1,'shop_order',1,'refund_required','2026-07-01 00:00:00'),(2,'shop_order',2,'pending','2026-08-04 10:00:00'),(3,'shop_order',3,'paid','2026-07-01 00:00:00')");
        $pdo->exec("INSERT INTO fio_account_movements VALUES(1,'proposed_exact'),(2,'review_amount'),(3,'ignored_non_credit')");
        $pdo->exec("INSERT INTO club_events VALUES(1,'open',2),(2,'closed',1)");
        $pdo->exec("INSERT INTO club_event_sessions VALUES(1,1,'scheduled',NULL)");
        $pdo->exec("INSERT INTO club_event_registrations VALUES(1,1,'confirmed'),(2,1,'payment_pending'),(3,1,'waitlisted')");
        $pdo->exec("INSERT INTO club_program_offers VALUES(1,'active',1),(2,'active',5)");
        $pdo->exec("INSERT INTO club_program_enrollments VALUES(1,1,'active')");
        $pdo->exec("INSERT INTO account_person_claim_requests VALUES(1,'pending')");
        $pdo->exec("INSERT INTO member_charge_reminders VALUES(1,'failed')");
        $pdo->exec("INSERT INTO club_event_notifications VALUES(1,'failed')");
        $pdo->exec("INSERT INTO kis_import_runs VALUES(1),(2)");
        $pdo->exec("INSERT INTO kis_import_matches VALUES(1,1,'conflict'),(2,2,'ambiguous'),(3,2,'matched')");
        return $pdo;
    }
}
