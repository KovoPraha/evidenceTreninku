<?php

declare(strict_types=1);

namespace Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use LogicException;
use PHPUnit\Framework\TestCase;
use Tests\Support\StorefrontCountingPdo;

require_once dirname(__DIR__) . '/Support/ProgramStorefrontFixture.php';
require_once dirname(__DIR__, 2) . '/includes/club_program_admin_read.php';

final class ProgramCapacityReadTest extends TestCase
{
    public function testStrictAdministrativeRowsAtCapacityBatchBoundaries(): void
    {
        foreach ([0, 2, 12, 79, 80, 81, 160, 161, 401] as $size) {
            $pdo = self::fixture($size);
            self::assertSame(\clubProgramAdminOffers($pdo), \clubProgramAdminOffersRead($pdo));
            $pdo->queries = [];
            \clubProgramAdminOffersRead($pdo);
            $capacity = array_filter($pdo->queries, static fn (string $sql): bool => str_contains($sql, ' AS _read_target,'));
            self::assertCount(2 * (int) ceil($size / 80), $capacity);
            foreach ($capacity as $sql) {
                self::assertLessThanOrEqual(80, substr_count($sql, ' AS _read_target,'));
                self::assertLessThanOrEqual(480, substr_count($sql, '?'));
            }
        }
    }

    public function testEveryCapacityPredicateMatchesCanonicalSqlAcrossLegacySchemas(): void
    {
        foreach ([null, 'club_program_physical_teams', 'sportovec_id', 'variant_id', 'payment_expires_at',
            'beneficiary_sportovec_id', 'athlete_registration_request_id', 'club_program_offer_id', 'source_order_item_id'] as $missing) {
            $pdo = self::fixture(2);
            if ($missing === 'club_program_physical_teams') {
                $pdo->exec('DROP TABLE club_program_physical_teams');
            } elseif ($missing !== null) {
                $table = match ($missing) {
                    'sportovec_id', 'source_order_item_id' => 'club_program_enrollments',
                    'payment_expires_at' => 'shop_orders',
                    default => 'shop_order_items',
                };
                $pdo->exec('ALTER TABLE ' . $table . ' DROP COLUMN ' . $missing);
            }
            $offers = $pdo->query('SELECT * FROM club_program_offers ORDER BY id')->fetchAll();
            $now = new DateTimeImmutable('2026-11-14 12:00:00', new DateTimeZone('Europe/Prague'));
            $context = new \ClubProgramReadContext($pdo, $now);
            $context->warmAdminOffers($offers);
            $pdo->queries = [];
            $expected = \clubProgramOfferCapacityState($pdo, $offers[0], $now, false, 0, $context);
            $canonical = self::capacitySql($pdo->queries);
            $pdo->queries = [];
            $actual = (new \ClubProgramCapacityRead($pdo, $context, $now))->states([$offers[0]]);
            self::assertSame($expected, $actual[1], (string) $missing);
            self::assertSame($canonical, self::capacitySql($pdo->queries), (string) $missing);
            self::assertSame(\clubProgramAdminOffers($pdo), \clubProgramAdminOffersRead($pdo), (string) $missing);
        }
    }

    public function testOverlapEdgesIdentityQuantitiesAndExactExpiryInstant(): void
    {
        $pdo = self::fixture(12);
        $pdo->exec("UPDATE club_program_offers SET team_id=1,capacity=12,starts_on='2026-11-14',ends_on='2026-11-20';
            UPDATE club_program_offers SET starts_on='2026-11-20',ends_on='2026-11-30' WHERE id=2;
            UPDATE club_program_offers SET starts_on='2026-11-21',ends_on='2026-11-30' WHERE id=3;
            UPDATE club_program_offers SET starts_on='2026-11-01',ends_on='2026-11-14' WHERE id=4;
            UPDATE club_program_offers SET starts_on='2026-11-01',ends_on='2026-11-13' WHERE id=5;
            UPDATE club_program_offers SET capacity=NULL WHERE id=6;
            UPDATE club_program_offers SET capacity=0 WHERE id=7;
            INSERT INTO club_program_physical_teams VALUES(1,1),(1,2),(8,2);
            INSERT INTO club_program_enrollments VALUES
            (1,1,42,NULL,'active'),(2,2,42,NULL,'active'),(3,3,43,NULL,'active'),
            (4,4,44,NULL,'active'),(5,5,45,NULL,'active'),(6,1,46,NULL,'cancelled');
            INSERT INTO shop_orders VALUES
            (1,'placed','pending','2026-11-14 12:00:00'),(2,'placed','pending','2026-11-14 11:59:59'),
            (3,'placed','pending',NULL),(4,'completed','paid',NULL),(5,'processing','paid',NULL),
            (6,'ready','paid',NULL),(7,'cancelled','pending','2099-01-01 00:00:00');
            INSERT INTO account_person_claim_requests VALUES(1,'pending'),(2,'rejected'),(3,'cancelled');
            INSERT INTO shop_order_items VALUES
            (1,1,1,1,1,42,NULL,1),(2,1,2,2,2,50,NULL,2),(3,1,4,4,4,50,NULL,3),
            (4,2,1,1,1,51,NULL,1),(5,3,1,1,1,52,NULL,1),(6,4,1,1,1,NULL,1,2),
            (7,5,1,1,1,NULL,2,3),(8,6,1,1,1,NULL,3,4),(9,7,1,1,1,53,NULL,1),
            (10,1,1,1,1,NULL,NULL,2),(11,1,1,1,1,NULL,NULL,3),
            (12,1,1,1,1,54,NULL,4),(13,1,1,2,1,55,NULL,5);
            INSERT INTO club_program_enrollments VALUES(7,1,54,12,'active')");
        $offers = $pdo->query('SELECT * FROM club_program_offers ORDER BY id')->fetchAll();
        $now = new DateTimeImmutable('2026-11-14 11:00:00', new DateTimeZone('UTC'));
        $context = new \ClubProgramReadContext($pdo, $now);
        $context->warmAdminOffers($offers);
        $actual = (new \ClubProgramCapacityRead($pdo, $context, $now))->states($offers);
        foreach ($offers as $offer) {
            self::assertSame(\clubProgramOfferCapacityState($pdo, $offer, $now), $actual[(int) $offer['id']]);
        }
        self::assertSame(3, $actual[1]['active_enrollment_count']);
        self::assertSame(10, $actual[1]['held_order_count']);
        self::assertNull($actual[6]['available_count']);
        self::assertSame(0, $actual[7]['available_count']);
    }

    public function testStreamedTargetsAboveNineAndMultipleBatchesKeepIdentityDeduplication(): void
    {
        $pdo = self::fixture(81);
        $pdo->exec("INSERT INTO shop_orders VALUES(1,'placed','pending','2099-01-01 00:00:00')");
        $item = $pdo->prepare('INSERT INTO shop_order_items VALUES(?,1,?,?,?,?,NULL,?)');
        for ($i = 1; $i <= 2500; $i++) {
            $offer = $i % 81 + 1;
            $item->execute([$i, $offer, $offer, $offer, $i % 3 + 100, $i % 4 + 1]);
        }
        $pdo->exec('INSERT INTO club_program_physical_teams VALUES(1,1),(1,2),(2,1),(2,2)');
        self::assertSame(\clubProgramAdminOffers($pdo), \clubProgramAdminOffersRead($pdo));
        $pdo->exec("UPDATE shop_orders SET status='cancelled'");
        self::assertSame(\clubProgramAdminOffers($pdo), \clubProgramAdminOffersRead($pdo));
        self::assertSame(0, \clubProgramAdminOffersRead($pdo)[0]['held_order_count']);
    }

    public function testInvalidDatesAndMissingClaimStatusKeepOfferLocalFallback(): void
    {
        $pdo = self::fixture(3);
        $pdo->exec("UPDATE club_program_offers SET starts_on='invalid' WHERE id=1;
            UPDATE club_program_offers SET team_id=0 WHERE id=2;
            ALTER TABLE account_person_claim_requests DROP COLUMN status;
            INSERT INTO club_program_enrollments VALUES(1,1,42,NULL,'active'),(2,1,42,NULL,'active')");
        $offers = $pdo->query('SELECT * FROM club_program_offers')->fetchAll();
        $context = new \ClubProgramReadContext($pdo);
        $context->warmAdminOffers($offers);
        $actual = (new \ClubProgramCapacityRead($pdo, $context))->states($offers);
        foreach ($offers as $offer) {
            self::assertSame(\clubProgramOfferCapacityState($pdo, $offer), $actual[(int) $offer['id']]);
        }
        self::assertSame(2, $actual[1]['active_enrollment_count']);
    }

    public function testProjectionDoesNotReuseContextAcrossConnections(): void
    {
        $pdo = self::fixture(1);
        $this->expectException(LogicException::class);
        new \ClubProgramCapacityRead(self::fixture(1), new \ClubProgramReadContext($pdo));
    }

    public function testEqualOccupiedCountsKeepFirstPhysicalTeamState(): void
    {
        $pdo = self::fixture(3);
        $pdo->exec("INSERT INTO club_program_physical_teams VALUES(1,1),(1,2);
            UPDATE club_program_offers SET team_id=1 WHERE id=3;
            INSERT INTO club_program_enrollments VALUES(1,1,42,NULL,'active'),(2,2,43,NULL,'active');
            INSERT INTO shop_orders VALUES(1,'placed','pending','2099-01-01 00:00:00');
            INSERT INTO shop_order_items VALUES(1,1,3,3,3,44,NULL,1)");
        $expected = \clubProgramAdminOffers($pdo);
        $actual = \clubProgramAdminOffersRead($pdo);
        self::assertSame($expected, $actual);
        $row = array_column($actual, null, 'id')[1];
        self::assertSame(2, $row['occupied_count']);
        self::assertSame(1, $row['active_enrollment_count']);
        self::assertSame(1, $row['held_order_count']);
        self::assertSame([1, 2], array_keys($row['physical_team_states']));
    }

    /** @param list<string> $queries
     * @return list<string>
     */
    private static function capacitySql(array $queries): array
    {
        $result = [];
        foreach ($queries as $sql) {
            if (!str_contains($sql, 'FROM club_program_enrollments') && !str_contains($sql, 'FROM shop_order_items')) {
                continue;
            }
            $sql = preg_replace('/^SELECT 0 AS _read_target,/', 'SELECT ', $sql);
            $sql = preg_replace('/ ORDER BY _read_target$/', '', $sql);
            $result[] = trim(preg_replace('/\s+/', ' ', $sql));
        }
        return $result;
    }

    private static function fixture(int $count): StorefrontCountingPdo
    {
        $pdo = \Tests\Support\programStorefrontFixture($count);
        $pdo->exec('ALTER TABLE club_programs ADD COLUMN code TEXT');
        $pdo->exec('ALTER TABLE shop_products ADD COLUMN name TEXT');
        return $pdo;
    }
}
