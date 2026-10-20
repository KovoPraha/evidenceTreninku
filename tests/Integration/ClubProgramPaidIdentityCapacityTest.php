<?php
declare(strict_types=1);

namespace Tests\Integration;

use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/club_program.php';

final class ClubProgramPaidIdentityCapacityTest extends TestCase
{
    public function testPaymentKeepsFinalPlaceAfterDeadlineUntilIdentityBecomesEnrollment(): void
    {
        $pdo = $this->database();
        $this->order($pdo, 1, 101, 1001, 99);
        self::assertSame(0, $this->state($pdo)['available_count']);

        $pdo->exec("UPDATE shop_orders SET status='processing',payment_status='paid',payment_expires_at='2000-01-01'");
        $paid = $this->state($pdo);
        self::assertSame(0, $paid['active_enrollment_count']);
        self::assertSame(1, $paid['held_order_count']);
        self::assertSame(0, $paid['available_count'], 'A paid family must not lose its place while identity review is pending.');

        $pdo->beginTransaction();
        $pdo->exec("UPDATE account_person_claim_requests SET status='approved' WHERE id=99");
        $pdo->exec('UPDATE shop_order_items SET beneficiary_sportovec_id=50 WHERE id=1');
        $pdo->exec("INSERT INTO club_program_enrollments VALUES(1,1,50,'active',1)");
        $pdo->commit();
        $active = $this->state($pdo);
        self::assertSame(1, $active['active_enrollment_count']);
        self::assertSame(0, $active['held_order_count']);
        self::assertSame(1, $active['occupied_count']);
    }

    public function testPaidReviewSharesAnnualAndSemesterCapacityAndDeduplicatesRequest(): void
    {
        $pdo = $this->database();
        $this->order($pdo, 1, 101, 1001, 99);
        $this->order($pdo, 2, 102, 1002, 99);
        $pdo->exec("UPDATE shop_orders SET status='processing',payment_status='paid',payment_expires_at=NULL");
        self::assertSame(1, $this->state($pdo)['held_order_count']);
        self::assertSame(1, $this->state($pdo, 2)['held_order_count']);
        self::assertSame(1, $this->state($pdo, 3)['held_order_count'], 'The annual offer overlaps the second semester.');

        $pdo->exec("UPDATE shop_orders SET status='cancelled',payment_status='refund_required' WHERE id=1");
        self::assertSame(1, $this->state($pdo, 2)['held_order_count']);
        self::assertSame(0, $this->state($pdo, 3)['held_order_count'], 'The first semester must not reserve the non-overlapping second semester.');
    }

    public function testTerminalOrdersAndRequestsReleasePendingAndPaidHolds(): void
    {
        foreach (['rejected', 'cancelled'] as $requestStatus) {
            foreach (['pending', 'paid'] as $paymentStatus) {
                $pdo = $this->database();
                $this->order($pdo, 1, 101, 1001, 99);
                if ($paymentStatus === 'paid') $pdo->exec("UPDATE shop_orders SET status='processing',payment_status='paid'");
                $pdo->prepare('UPDATE account_person_claim_requests SET status=? WHERE id=99')->execute([$requestStatus]);
                self::assertSame(0, $this->state($pdo)['held_order_count'], "$requestStatus / $paymentStatus");
            }
        }
        foreach (['cancelled', 'refund_required', 'refunded'] as $paymentStatus) {
            $pdo = $this->database();
            $this->order($pdo, 1, 101, 1001, 99);
            $pdo->prepare("UPDATE shop_orders SET status='cancelled',payment_status=?")->execute([$paymentStatus]);
            self::assertSame(1, $this->state($pdo)['available_count']);
        }
    }

    public function testUnpaidExpiryStillReleasesAndUnrelatedPaidItemDoesNotReserve(): void
    {
        $pdo = $this->database();
        $this->order($pdo, 1, 101, 1001, 99);
        $pdo->exec("UPDATE shop_orders SET payment_expires_at='2000-01-01'");
        self::assertSame(1, $this->state($pdo)['available_count']);
        $pdo->exec("UPDATE shop_orders SET status='processing',payment_status='paid'");
        $pdo->exec('UPDATE shop_order_items SET athlete_registration_request_id=NULL');
        self::assertSame(1, $this->state($pdo)['available_count']);
    }

    public function testLatePaymentCannotTakeAnotherFamilysReservedOrActivePlace(): void
    {
        foreach (['held', 'active'] as $otherFamily) {
            $pdo = $this->database();
            $this->order($pdo, 1, 101, 1001, 99);
            $pdo->exec("UPDATE shop_orders SET payment_expires_at='2000-01-01'");
            if ($otherFamily === 'held') {
                $pdo->exec("INSERT INTO account_person_claim_requests(id,status,account_id) VALUES(100,'pending',8)");
                $this->order($pdo, 2, 102, 1002, 100);
            } else {
                $pdo->exec("INSERT INTO club_program_enrollments VALUES(1,2,50,'active',900)");
            }
            $pdo->beginTransaction();
            try {
                $pdo->exec("UPDATE shop_orders SET status='processing',payment_status='paid' WHERE id=1");
                \clubProgramActivatePaidOrderInTransaction($pdo, 1, 7);
                self::fail('Late payment must not oversell the shared roster.');
            } catch (\ClubProgramException $exception) {
                self::assertStringContainsString('Opožděnou platbu musí správce prověřit', $exception->getMessage());
                $pdo->rollBack();
            }
            self::assertSame('pending', $pdo->query('SELECT payment_status FROM shop_orders WHERE id=1')->fetchColumn());
            self::assertSame(1, $this->state($pdo)['occupied_count']);
        }
    }

    public function testPaidIdentityValidationAcceptsItsOwnHoldAndRemainsIdempotent(): void
    {
        $pdo = $this->database();
        $this->order($pdo, 1, 101, 1001, 99);
        $pdo->exec("UPDATE shop_orders SET status='processing',payment_status='paid',payment_expires_at='2000-01-01'");
        $pdo->beginTransaction();
        $expected = ['program_items' => 1, 'created' => 0, 'pending_identity' => 1];
        self::assertSame($expected, \clubProgramActivatePaidOrderInTransaction($pdo, 1, 7));
        self::assertSame($expected, \clubProgramActivatePaidOrderInTransaction($pdo, 1, 7));
        $pdo->commit();
        self::assertSame(1, $this->state($pdo)['held_order_count']);
    }

    public function testRejectedRequestCannotReceivePaymentAndReacquireItsSeat(): void
    {
        $pdo = $this->database();
        $this->order($pdo, 1, 101, 1001, 99);
        $pdo->exec("UPDATE shop_orders SET status='processing',payment_status='paid'");
        $pdo->exec("UPDATE account_person_claim_requests SET status='rejected' WHERE id=99");
        $pdo->beginTransaction();
        try {
            \clubProgramActivatePaidOrderInTransaction($pdo, 1, 7);
            self::fail('A terminal request must not silently retain a paid order.');
        } catch (\ClubProgramException $exception) {
            self::assertStringContainsString('Žádost rychlé přihlášky již není platná', $exception->getMessage());
        } finally {
            $pdo->rollBack();
        }
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $pdo->exec('CREATE TABLE club_program_offers(id INTEGER PRIMARY KEY,team_id INTEGER,product_id INTEGER,variant_id INTEGER,starts_on TEXT,ends_on TEXT);
            CREATE TABLE club_program_enrollments(id INTEGER PRIMARY KEY,offer_id INTEGER,sportovec_id INTEGER,status TEXT,source_order_item_id INTEGER);
            CREATE TABLE shop_orders(id INTEGER PRIMARY KEY,status TEXT,payment_status TEXT,payment_expires_at TEXT);
            CREATE TABLE shop_order_items(id INTEGER PRIMARY KEY,order_id INTEGER,product_id INTEGER,variant_id INTEGER,quantity INTEGER,beneficiary_sportovec_id INTEGER,athlete_registration_request_id INTEGER);
            CREATE TABLE account_person_claim_requests(id INTEGER PRIMARY KEY,status TEXT)');
        $pdo->exec("INSERT INTO club_program_offers VALUES(1,10,101,1001,'2026-09-01','2027-06-30'),(2,10,102,1002,'2026-09-01','2027-01-31'),(3,10,103,1003,'2027-02-01','2027-06-30');
            INSERT INTO account_person_claim_requests VALUES(99,'pending')");
        $pdo->exec("ALTER TABLE club_program_offers ADD COLUMN program_id INTEGER DEFAULT 1;
            ALTER TABLE club_program_offers ADD COLUMN capacity INTEGER DEFAULT 1;
            ALTER TABLE club_program_offers ADD COLUMN status TEXT DEFAULT 'active';
            ALTER TABLE account_person_claim_requests ADD COLUMN account_id INTEGER DEFAULT 7;
            ALTER TABLE shop_orders ADD COLUMN account_id INTEGER DEFAULT 7;
            CREATE TABLE club_teams(id INTEGER PRIMARY KEY);
            INSERT INTO club_teams VALUES(10);
            CREATE TABLE club_programs(id INTEGER PRIMARY KEY,name TEXT,status TEXT);
            INSERT INTO club_programs VALUES(1,'LOCALHOST NEPLATIT','active')");
        return $pdo;
    }

    private function order(PDO $pdo, int $id, int $productId, int $variantId, int $requestId): void
    {
        $pdo->prepare("INSERT INTO shop_orders(id,status,payment_status,payment_expires_at) VALUES(?,'placed','pending','2099-12-31 23:59:59')")->execute([$id]);
        $pdo->prepare('INSERT INTO shop_order_items VALUES(?,?,?,?,1,NULL,?)')->execute([$id, $id, $productId, $variantId, $requestId]);
    }

    private function state(PDO $pdo, int $offerId = 1): array
    {
        $offer = $pdo->query('SELECT * FROM club_program_offers WHERE id=' . $offerId)->fetch();
        return \clubProgramOfferCapacityState($pdo, $offer + ['capacity' => 1], new DateTimeImmutable('2026-10-20 12:00:00'));
    }
}
