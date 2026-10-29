<?php

declare(strict_types=1);

namespace Tests\Integration;

use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

use function Tests\Support\programCapacityFixture;
use function Tests\Support\programCapacityHold;
use function Tests\Support\programCapacityOffer;

require_once dirname(__DIR__) . '/Support/ProgramCapacityFixture.php';

final class ProgramCapacityCharacterizationTest extends TestCase
{
    private PDO $pdo;
    private ?PDO $admin = null;
    private ?string $database = null;

    protected function setUp(): void
    {
        if (getenv('EVIDENCE_CAPACITY_MARIADB') === '1') {
            $port = getenv('EVIDENCE_MARIADB_SMOKE_PORT') ?: '3306';
            if (!ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) {
                throw new \RuntimeException('Invalid isolated localhost port.');
            }
            $dsn = 'mysql:host=127.0.0.1;port=' . $port . ';charset=utf8mb4';
            $this->admin = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $this->database = 'w6_capacity_' . bin2hex(random_bytes(8));
            $this->admin->exec('CREATE DATABASE `' . $this->database . '`');
            $this->pdo = new PDO($dsn . ';dbname=' . $this->database, 'root', '', [PDO::ATTR_EMULATE_PREPARES => false]);
        } else {
            $this->pdo = new PDO('sqlite::memory:');
        }
        programCapacityFixture($this->pdo);
    }

    protected function tearDown(): void
    {
        if ($this->admin !== null && $this->database !== null) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->admin->exec('DROP DATABASE `' . $this->database . '`');
        }
    }

    private function state(int $offerId = 1, ?int $capacity = 10, int $exclude = 0): array
    {
        return \clubProgramOfferCapacityState(
            $this->pdo,
            programCapacityOffer($this->pdo, $offerId, $capacity),
            new DateTimeImmutable('2026-10-29 12:00:00 Europe/Prague'),
            false,
            $exclude
        );
    }

    private function expected(int $active, int $held, ?int $capacity = 10): array
    {
        return ['active_enrollment_count' => $active, 'held_order_count' => $held,
            'occupied_count' => $active + $held, 'available_count' => $capacity === null ? null : max(0, $capacity - $active - $held)];
    }

    public function testEmptyAndOverfullCapacityKeepExactTypes(): void
    {
        foreach ([null, 0, 1, 10] as $capacity) {
            self::assertSame($this->expected(0, 0, $capacity), $this->state(1, $capacity));
        }
        programCapacityHold($this->pdo, 1, 1, null, null, 3);
        self::assertSame($this->expected(0, 3, 1), $this->state(1, 1));
    }

    public function testIdentityNamespacesAndMaximumQuantityDoNotCollapseIntoRowCount(): void
    {
        $this->pdo->exec("INSERT INTO club_program_enrollments VALUES
            (1,1,50,'active',900),(2,2,50,'active',901),(3,2,51,'cancelled',902)");
        programCapacityHold($this->pdo, 1, 1, 50, null, 5);
        programCapacityHold($this->pdo, 2, 2, 51, null, 2);
        programCapacityHold($this->pdo, 3, 1, 51, 99, 4);
        programCapacityHold($this->pdo, 4, 1, null, 51, 3);
        programCapacityHold($this->pdo, 5, 2, null, 51, 2);
        programCapacityHold($this->pdo, 6, 1, null, null, 2);
        programCapacityHold($this->pdo, 7, 1, null, null, 1);
        self::assertSame($this->expected(1, 10), $this->state());
        self::assertSame($this->expected(1, 6), $this->state(1, 10, 51));
        self::assertSame($this->expected(1, 10), $this->state(1, 10, 50));
    }

    public function testZeroAndLegacyNegativeQuantityCannotSubtractOccupancy(): void
    {
        programCapacityHold($this->pdo, 1, 1, null, null, 0);
        programCapacityHold($this->pdo, 2, 1, null, null, -2);
        self::assertSame($this->expected(0, 0), $this->state());
    }

    public function testExpiryIncludesExactSecondAndConvertsUtcToPragueAcrossDst(): void
    {
        programCapacityHold($this->pdo, 1, 1, null, null);
        foreach (['2026-10-29T11:00:00+00:00', '2026-03-29T01:00:00+00:00', '2026-10-25T01:30:00+00:00'] as $instant) {
            $now = new DateTimeImmutable($instant);
            foreach ([-1 => 0, 0 => 1, 1 => 1] as $seconds => $held) {
                $expiry = $now->modify(($seconds >= 0 ? '+' : '') . $seconds . ' seconds')
                    ->setTimezone(new \DateTimeZone('Europe/Prague'))->format('Y-m-d H:i:s');
                $this->pdo->prepare('UPDATE shop_orders SET payment_expires_at=?')->execute([$expiry]);
                self::assertSame($this->expected(0, $held), \clubProgramOfferCapacityState($this->pdo, programCapacityOffer($this->pdo), $now), $instant . '/' . $seconds);
            }
        }
        $this->pdo->exec('UPDATE shop_orders SET payment_expires_at=NULL');
        self::assertSame($this->expected(0, 0), $this->state());
    }

    public function testOverlapIncludesTouchingDateButNotFollowingDay(): void
    {
        $this->pdo->exec("INSERT INTO club_program_enrollments VALUES(1,3,50,'active',900)");
        self::assertSame($this->expected(0, 0), $this->state(2));
        $this->pdo->exec("UPDATE club_program_offers SET starts_on='2027-01-31' WHERE id=3");
        self::assertSame($this->expected(1, 0), $this->state(2));
        self::assertSame($this->expected(1, 0), $this->state());
    }

    public function testInvalidOrMissingDatesUseOfferLocalLegacyCounting(): void
    {
        $this->pdo->exec("INSERT INTO club_program_enrollments VALUES
            (1,1,50,'active',900),(2,1,50,'active',901),(3,2,51,'active',902)");
        programCapacityHold($this->pdo, 1, 1, 50, null);
        foreach (['', 'not-a-date', '2026-02-30'] as $date) {
            $offer = programCapacityOffer($this->pdo);
            $offer['starts_on'] = $date;
            self::assertSame($this->expected(2, 1), \clubProgramOfferCapacityState(
                $this->pdo,
                $offer,
                new DateTimeImmutable('2026-10-29 12:00:00 Europe/Prague')
            ));
        }
    }

    public function testMultiplePhysicalTeamsUseMostOccupiedRatherThanSum(): void
    {
        $this->pdo->exec('INSERT INTO club_program_physical_teams VALUES(1,20),(1,10)');
        $this->pdo->exec("INSERT INTO club_program_enrollments VALUES
            (1,2,50,'active',900),(2,4,51,'active',901),(3,4,52,'active',902)");
        foreach ([null, 0, 1, 10] as $capacity) {
            $expected = $this->expected(2, 0, $capacity);
            $expected['physical_team_states'] = [10 => $this->expected(1, 0, $capacity), 20 => $this->expected(2, 0, $capacity)];
            self::assertSame($expected, $this->state(1, $capacity));
        }
    }

    public function testLinkedTeamFindsOtherProgramsEvenWhenTheirPrimaryTeamDiffers(): void
    {
        $this->pdo->exec('INSERT INTO club_program_physical_teams VALUES(4,10)');
        $this->pdo->exec("INSERT INTO club_program_enrollments VALUES(1,4,51,'active',901)");
        programCapacityHold($this->pdo, 1, 4, 52, null);
        self::assertSame($this->expected(1, 1), $this->state());
    }

    public function testActivatedSourceItemAndRejectedClaimCannotHoldPlace(): void
    {
        programCapacityHold($this->pdo, 1, 1, null, 50, 3);
        programCapacityHold($this->pdo, 2, 1, null, 51, 2);
        programCapacityHold($this->pdo, 3, 1, null, 52, 4);
        $this->pdo->exec("INSERT INTO club_program_enrollments VALUES(1,1,99,'active',1)");
        $this->pdo->exec("INSERT INTO account_person_claim_requests VALUES(51,'rejected'),(52,'cancelled')");
        self::assertSame($this->expected(1, 0), $this->state());
    }

    public function testExplicitOfferLinkCannotFallBackToMatchingProductAndVariant(): void
    {
        programCapacityHold($this->pdo, 1, 1, null, null);
        $this->pdo->exec('UPDATE shop_order_items SET club_program_offer_id=2');
        self::assertSame($this->expected(0, 0), $this->state());
        $this->pdo->exec('ALTER TABLE shop_order_items DROP COLUMN club_program_offer_id');
        self::assertSame($this->expected(0, 1), $this->state());
    }

    public function testLegacyMissingIdentityAndHoldColumnsRemainSupported(): void
    {
        programCapacityHold($this->pdo, 1, 1, null, null, 2);
        $this->pdo->exec('ALTER TABLE shop_order_items DROP COLUMN beneficiary_sportovec_id');
        $this->pdo->exec('ALTER TABLE shop_order_items DROP COLUMN athlete_registration_request_id');
        $this->pdo->exec('ALTER TABLE club_program_enrollments DROP COLUMN sportovec_id');
        $this->pdo->exec("INSERT INTO club_program_enrollments VALUES(1,1,'active',900),(2,2,'active',901)");
        self::assertSame($this->expected(1, 2), $this->state());
        $this->pdo->exec('ALTER TABLE shop_orders DROP COLUMN payment_expires_at');
        self::assertSame($this->expected(1, 0), $this->state());
    }

    public function testNextReadAndRollbackDoNotReuseOccupancy(): void
    {
        self::assertSame($this->expected(0, 0), $this->state());
        $this->pdo->beginTransaction();
        programCapacityHold($this->pdo, 1, 1, 51, null);
        self::assertSame($this->expected(0, 1), $this->state());
        $this->pdo->rollBack();
        self::assertSame($this->expected(0, 0), $this->state());
        programCapacityHold($this->pdo, 1, 1, 51, null);
        self::assertSame($this->expected(0, 1), $this->state());
    }

    public function testPaidReviewStatesHoldAfterExpiryButResolvedIdentityDoesNot(): void
    {
        programCapacityHold($this->pdo, 1, 1, null, 51);
        foreach (['processing', 'ready', 'completed'] as $status) {
            $this->pdo->prepare("UPDATE shop_orders SET status=?,payment_status='paid',payment_expires_at='2000-01-01 00:00:00'")->execute([$status]);
            self::assertSame($this->expected(0, 1), $this->state());
        }
        $this->pdo->exec('UPDATE shop_order_items SET beneficiary_sportovec_id=51');
        self::assertSame($this->expected(0, 0), $this->state());
    }
}
