<?php

declare(strict_types=1);

namespace Tests\Integration;

use ClubProgramReadContext;
use DateTimeImmutable;
use DateTimeZone;
use LogicException;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/ProgramStorefrontFixture.php';
require_once dirname(__DIR__, 2) . '/includes/shop_public_navigation.php';

final class ProgramStorefrontReadCompatibilityTest extends TestCase
{
    public function testReadMatchesCanonicalSharedCapacityAndTermsWithPaidIdentityHold(): void
    {
        $pdo = \Tests\Support\programStorefrontFixture(3);
        $pdo->exec('UPDATE club_program_offers SET capacity=3,team_id=1');
        $pdo->exec('INSERT INTO club_program_physical_teams VALUES(1,1),(1,2),(2,1),(3,1)');
        $pdo->exec("INSERT INTO club_program_enrollments VALUES(1,2,42,NULL,'active'),(2,3,42,NULL,'active')");
        $pdo->exec("INSERT INTO shop_orders VALUES(1,'placed','pending','2026-10-28 13:00:00'),(2,'completed','paid','2020-01-01 00:00:00')");
        $pdo->exec('INSERT INTO shop_order_items VALUES(1,1,2,2,2,42,NULL,1),(2,1,3,3,3,43,NULL,1),(3,2,1,1,1,NULL,5,1)');
        $pdo->exec("INSERT INTO account_person_claim_requests VALUES(5,'pending')");
        $now = new DateTimeImmutable('2026-10-28 12:00:00', new DateTimeZone('Europe/Prague'));
        $context = new ClubProgramReadContext($pdo, $now);
        foreach ([1, 2, 3, 999] as $id) {
            self::assertSame(\clubProgramVariantSaleState($pdo, $id, $now), $context->saleState($id));
        }
        self::assertFalse($context->saleState(1)['saleable']);
        self::assertSame(1, $context->offer(1)['active_enrollment_count']);
        self::assertSame(2, $context->offer(1)['held_order_count']);
        $pdo->exec("UPDATE account_person_claim_requests SET status='rejected'");
        $fresh = new ClubProgramReadContext($pdo, $now);
        self::assertTrue($fresh->saleState(1)['saleable']);
        self::assertSame(\clubProgramVariantSaleState($pdo, 1, $now), $fresh->saleState(1));
    }

    public function testNewProjectionSeesRevokedTermsCapacityAndPublication(): void
    {
        $pdo = \Tests\Support\programStorefrontFixture(1);
        self::assertCount(2, \shopPublicNavigationItems($pdo));
        $pdo->exec("UPDATE club_event_term_versions SET status='withdrawn'");
        self::assertSame([], \shopPublicNavigationItems($pdo));
        $pdo->exec("UPDATE club_event_term_versions SET status='active'");
        self::assertCount(1, \shopPublicSaleableCatalog($pdo));
        $pdo->exec("INSERT INTO club_event_term_versions VALUES(99,'club_program_offer','offer:1','program_consent','VZOR — pracovní text','active')");
        self::assertSame([], \shopPublicSaleableCatalog($pdo));
        $pdo->exec("UPDATE club_event_term_versions SET status='withdrawn' WHERE id=99");
        $pdo->exec('UPDATE club_program_offers SET capacity=0');
        self::assertSame([], \shopPublicNavigationItems($pdo));
        $pdo->exec('UPDATE club_program_offers SET capacity=NULL');
        self::assertCount(2, \shopPublicNavigationItems($pdo));
        $pdo->exec("UPDATE club_programs SET status='archived'");
        self::assertNull(\shopStorefrontProductDetail($pdo, 1));
    }

    public function testLockingDecisionIgnoresAnEarlierReadContext(): void
    {
        $pdo = \Tests\Support\programStorefrontFixture(1);
        $context = new ClubProgramReadContext($pdo);
        self::assertTrue($context->saleState(1)['saleable']);
        $pdo->exec('UPDATE club_program_offers SET capacity=0');
        self::assertFalse(\clubProgramVariantSaleState($pdo, 1, null, true, $context)['saleable']);
        $pdo->exec('UPDATE club_program_offers SET capacity=10');
        $pdo->exec("UPDATE club_event_term_versions SET status='withdrawn'");
        self::assertFalse(\clubProgramVariantSaleState($pdo, 1, null, true, $context)['saleable']);
    }

    public function testSalesAndHoldTimeBoundariesAreReevaluated(): void
    {
        $pdo = \Tests\Support\programStorefrontFixture(1);
        $pdo->exec("UPDATE club_program_offers SET capacity=1,sales_open_at='2026-10-28 10:00:00',sales_close_at='2026-10-28 12:00:00'");
        $pdo->exec("INSERT INTO shop_orders VALUES(1,'placed','pending','2026-10-28 11:00:00')");
        $pdo->exec('INSERT INTO shop_order_items VALUES(1,1,1,1,1,42,NULL,1)');
        foreach (['09:59:59' => false, '10:00:00' => false, '11:00:00' => false, '11:00:01' => true, '12:00:00' => true, '12:00:01' => false] as $time => $expected) {
            $now = new DateTimeImmutable('2026-10-28 ' . $time, new DateTimeZone('Europe/Prague'));
            self::assertSame($expected, (new ClubProgramReadContext($pdo, $now))->saleState(1)['saleable']);
            self::assertCount($expected ? 1 : 0, \shopStorefrontProducts($pdo, $now));
        }
    }

    public function testContextCannotCrossConnectionsEvenAfterItsOfferWasCached(): void
    {
        $first = \Tests\Support\programStorefrontFixture(1);
        $second = \Tests\Support\programStorefrontFixture(1);
        $context = new ClubProgramReadContext($first);
        $context->saleState(1);
        $this->expectException(LogicException::class);
        \clubProgramVariantSaleState($second, 1, null, false, $context);
    }

    public function testNewProjectionDoesNotRetainSchemaAcrossMigrationOrMissingOffer(): void
    {
        $pdo = \Tests\Support\programStorefrontFixture(1);
        $pdo->exec('DROP TABLE club_event_term_versions');
        self::assertTrue((new ClubProgramReadContext($pdo))->saleState(1)['saleable']);
        $pdo->exec('CREATE TABLE club_event_term_versions(id INTEGER PRIMARY KEY,scope_type TEXT,scope_key TEXT,consent_purpose TEXT,consent_text_plain TEXT,status TEXT)');
        self::assertFalse((new ClubProgramReadContext($pdo))->saleState(1)['saleable']);
        $pdo->exec("UPDATE club_program_offers SET status='closed'");
        self::assertSame([], \shopPublicNavigationItems($pdo));
    }
}
