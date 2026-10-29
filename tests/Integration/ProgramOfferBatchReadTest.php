<?php

declare(strict_types=1);

namespace Tests\Integration;

use ClubProgramReadContext;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/ProgramStorefrontFixture.php';
require_once dirname(__DIR__, 2) . '/includes/shop_catalog_cards.php';

final class ProgramOfferBatchReadTest extends TestCase
{
    public function testEmptySmallAndExactFullBatch(): void
    {
        foreach ([0, 1, 10, 400] as $count) {
            $pdo = \Tests\Support\programStorefrontFixture($count);
            $context = new ClubProgramReadContext($pdo);
            $context->warmOffersForVariants($count === 0 ? [] : range(1, $count));
            $queries = array_filter($pdo->queries, static fn (string $q): bool => str_contains($q, 'SELECT o.*,p.name AS program_name'));
            self::assertCount($count === 0 ? 0 : 1, $queries);
            if ($count > 0) {
                self::assertSame(\clubProgramOfferForVariant($pdo, $count), $context->offer($count));
            }
        }
    }

    public function testCatalogBatchesOffersAndPhysicalLinks(): void
    {
        $pdo = \Tests\Support\programStorefrontFixture(50);
        self::assertCount(50, \shopPublicCatalogCards($pdo));
        self::assertLessThanOrEqual(125, count($pdo->queries));
        self::assertCount(1, array_filter($pdo->queries, static fn (string $q): bool => str_contains($q, 'SELECT o.*,p.name AS program_name')));
        self::assertCount(1, array_filter($pdo->queries, static fn (string $q): bool => str_contains($q, 'FROM club_program_physical_teams WHERE')));
    }

    public function testBatchMatchesCanonicalSharedCapacityAndFallbackTeams(): void
    {
        $pdo = \Tests\Support\programStorefrontFixture(3);
        $pdo->exec('UPDATE club_program_offers SET team_id=1,capacity=4');
        $pdo->exec('INSERT INTO club_program_physical_teams VALUES(1,2),(1,1),(2,1)');
        $pdo->exec("INSERT INTO club_program_enrollments VALUES(1,2,42,NULL,'active'),(2,3,42,NULL,'active')");
        $pdo->exec("INSERT INTO shop_orders VALUES(1,'placed','pending','2099-01-01 00:00:00'),(2,'completed','paid',NULL)");
        $pdo->exec('INSERT INTO shop_order_items VALUES(1,1,2,2,2,42,NULL,1),(2,1,3,3,3,43,NULL,1),(3,2,1,1,1,NULL,5,1)');
        $pdo->exec("INSERT INTO account_person_claim_requests VALUES(5,'pending')");
        $context = new ClubProgramReadContext($pdo);
        $context->warmOffersForVariants([1, 2, 3, 999, 1]);
        foreach ([1, 2, 3, 999] as $id) {
            self::assertSame(\clubProgramOfferForVariant($pdo, $id), $context->offer($id));
        }
        self::assertSame([1, 2], array_keys($context->offer(1)['physical_team_states']));
        self::assertSame(1, $context->offer(1)['active_enrollment_count']);
        self::assertSame(2, $context->offer(1)['held_order_count']);
        $pdo->queries = [];
        $context->warmOffersForVariants([1, 2, 3, 999]);
        self::assertSame([], $pdo->queries);
    }

    public function testInactiveAndBrokenLinksAndDuplicateLegacyOffersUseCanonicalSemantics(): void
    {
        $pdo = \Tests\Support\programStorefrontFixture(5);
        $pdo->exec("UPDATE club_program_offers SET status='closed' WHERE id=2");
        $pdo->exec("UPDATE club_programs SET status='archived' WHERE id=3");
        $pdo->exec('UPDATE club_program_offers SET team_id=999 WHERE id=4');
        $pdo->exec('UPDATE club_program_offers SET season_id=999 WHERE id=5');
        $pdo->exec("INSERT INTO club_program_offers VALUES(99,1,1,1,'active',1,1,'2026-01-01','2099-12-31',NULL,NULL,2)");
        $context = new ClubProgramReadContext($pdo);
        $context->warmOffersForVariants([1, 2, 3, 4, 5, 999]);
        foreach ([1, 2, 3, 4, 5, 999] as $id) {
            self::assertSame(\clubProgramOfferForVariant($pdo, $id), $context->offer($id));
        }
    }

    public function testLegacySchemaAndPrimaryFallbackRemainCompatible(): void
    {
        $pdo = \Tests\Support\programStorefrontFixture(2);
        $pdo->exec('DROP TABLE club_program_physical_teams');
        $pdo->exec('DROP TABLE club_seasons');
        $pdo->exec('ALTER TABLE club_programs DROP COLUMN description');
        $context = new ClubProgramReadContext($pdo);
        $context->warmOffersForVariants([1, 2]);
        foreach ([1, 2] as $id) {
            self::assertSame(\clubProgramOfferForVariant($pdo, $id), $context->offer($id));
        }
        self::assertNull($context->offer(1)['program_description']);
        self::assertNull($context->offer(1)['team_name']);
        self::assertSame([1], \clubProgramPhysicalTeamIds($pdo, ['team_id' => 1, 'program_id' => 1], $context));
    }

    public function testNextOperationAndLockingReadSeeChangedLinksAndCapacity(): void
    {
        $pdo = \Tests\Support\programStorefrontFixture(2);
        $context = new ClubProgramReadContext($pdo);
        $context->warmOffersForVariants([1]);
        $pdo->exec('UPDATE club_program_offers SET capacity=1 WHERE id=1');
        $pdo->exec('INSERT INTO club_program_physical_teams VALUES(1,2)');
        $pdo->exec("INSERT INTO club_program_enrollments VALUES(1,2,42,NULL,'active')");
        self::assertSame(10, $context->offer(1)['available_count']);
        self::assertFalse(\clubProgramVariantSaleState($pdo, 1, null, true, $context)['saleable']);
        $fresh = new ClubProgramReadContext($pdo);
        $fresh->warmOffersForVariants([1]);
        self::assertSame(\clubProgramOfferForVariant($pdo, 1), $fresh->offer(1));
        self::assertSame(0, $fresh->offer(1)['available_count']);
        self::assertCount(1, \shopPublicCatalogCards($pdo));
    }

    public function testBatchBoundaryAndAlreadyCachedOffers(): void
    {
        $pdo = \Tests\Support\programStorefrontFixture(401);
        $context = new ClubProgramReadContext($pdo);
        $context->warmOffersForVariants(range(1, 401));
        $offerQueries = array_filter($pdo->queries, static fn (string $q): bool => str_contains($q, 'SELECT o.*,p.name AS program_name'));
        self::assertCount(2, $offerQueries);
        foreach ($offerQueries as $query) {
            self::assertLessThanOrEqual(400, substr_count($query, '?'));
        }
        self::assertSame(\clubProgramOfferForVariant($pdo, 401), $context->offer(401));
        $pdo->exec('UPDATE club_program_offers SET capacity=0 WHERE id=1');
        $context->warmOffersForVariants([1, 401]);
        self::assertSame(10, $context->offer(1)['available_count']);
    }
}
