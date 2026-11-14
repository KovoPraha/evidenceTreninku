<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\StorefrontCountingPdo;

require_once dirname(__DIR__) . '/Support/ProgramStorefrontFixture.php';
require_once dirname(__DIR__, 2) . '/includes/shop_catalog_cards.php';
require_once dirname(__DIR__, 2) . '/includes/club_program_admin_read.php';

final class ProgramAdminReadTest extends TestCase
{
    public function testCompleteRowsTypesAndOrderMatchAtBatchBoundaries(): void
    {
        foreach ([0, 2, 12, 80, 81, 401] as $count) {
            $pdo = self::fixture($count);
            self::assertSame(\clubProgramAdminOffers($pdo), \clubProgramAdminOffersRead($pdo));
            $pdo->queries = [];
            self::assertCount($count, \clubProgramAdminOffersRead($pdo));
            if ($count === 0) {
                self::assertCount(1, $pdo->queries);
            } elseif ($count >= 80) {
                self::assertLessThan($count * 3, count($pdo->queries));
                $terms = array_filter($pdo->queries, static fn (string $sql): bool => str_contains($sql, 'FROM club_event_term_versions'));
                self::assertCount((int) ceil($count / 80) * 2, $terms);
                foreach ($terms as $sql) {
                    self::assertLessThanOrEqual(800, substr_count($sql, '?'));
                }
            }
        }
    }

    public function testInactiveExpiredDuplicateVariantsAndEveryTermStateRemainVisible(): void
    {
        $pdo = self::fixture(8);
        $pdo->exec("UPDATE club_program_offers SET status='closed' WHERE id=2;
            UPDATE club_program_offers SET status='draft',variant_id=1 WHERE id=3;
            UPDATE club_programs SET status='closed' WHERE id=4;
            UPDATE club_program_offers SET ends_on='2001-01-01' WHERE id=5;
            UPDATE club_program_offers SET capacity=0 WHERE id=6;
            UPDATE club_program_offers SET capacity=NULL WHERE id=7;
            UPDATE club_program_offers SET sales_open_at='2099-01-01 00:00:00' WHERE id=8");
        $pdo->exec("INSERT INTO club_event_term_versions VALUES
            (10000,'club_program_offer','offer:1','program_consent','Old override','active'),
            (10001,'club_program_offer','offer:1','program_consent','Latest override','active'),
            (10002,'club_program_offer','offer:1','program_consent','Archived override','archived'),
            (10003,'club_program_offer','offer:2','program_consent','VZOR — pracovní text','active'),
            (10004,'club_program_offer','offer:3','program_cancellation','','active'),
            (10005,'club_program','PROGRAM:4','program_consent','Different SQLite case','active')");
        $expected = \clubProgramAdminOffers($pdo);
        $actual = \clubProgramAdminOffersRead($pdo);
        self::assertSame($expected, $actual);
        self::assertCount(8, $actual);
        $byId = array_column($actual, null, 'id');
        self::assertSame(10001, $byId[1]['effective_terms']['program_consent']['id']);
        self::assertFalse($byId[2]['terms_complete']);
        self::assertFalse($byId[3]['terms_complete']);
        self::assertSame('Nabídka není aktivní.', $byId[2]['sale_reason']);
    }

    public function testSharedCapacityPeopleDeduplicationHoldsAndPendingIdentityMatch(): void
    {
        $pdo = self::fixture(3);
        $pdo->exec('UPDATE club_program_offers SET team_id=1,capacity=12');
        $pdo->exec('INSERT INTO club_program_physical_teams VALUES(1,1),(1,2),(2,1),(2,2)');
        $pdo->exec("INSERT INTO club_program_enrollments VALUES
            (1,1,42,NULL,'active'),(2,2,42,NULL,'active'),(3,3,43,NULL,'cancelled')");
        $pdo->exec("INSERT INTO shop_orders VALUES
            (1,'placed','pending','2099-01-01 00:00:00'),
            (2,'placed','pending','2001-01-01 00:00:00'),
            (3,'completed','paid',NULL),(4,'processing','paid',NULL),
            (5,'cancelled','pending','2099-01-01 00:00:00')");
        $pdo->exec("INSERT INTO account_person_claim_requests VALUES(5,'pending'),(6,'rejected'),(7,'cancelled')");
        $pdo->exec('INSERT INTO shop_order_items VALUES
            (1,1,1,1,1,42,NULL,1),(2,1,2,2,2,44,NULL,1),
            (3,2,1,1,1,45,NULL,1),(4,3,1,1,1,NULL,5,2),
            (5,4,2,2,2,NULL,6,1),(6,5,1,1,1,46,NULL,1),
            (7,4,1,1,1,NULL,7,1)');
        $expected = \clubProgramAdminOffers($pdo);
        self::assertSame($expected, \clubProgramAdminOffersRead($pdo));
        self::assertArrayHasKey('physical_team_states', array_column($expected, null, 'id')[1]);
    }

    public function testMissingRegistryPhysicalLinksAndLegacyColumnsKeepFallbacks(): void
    {
        foreach (['club_event_term_versions', 'club_program_physical_teams'] as $table) {
            $pdo = self::fixture(2);
            $pdo->exec('DROP TABLE ' . $table);
            self::assertSame(\clubProgramAdminOffers($pdo), \clubProgramAdminOffersRead($pdo));
        }
        foreach (['beneficiary_sportovec_id', 'athlete_registration_request_id', 'club_program_offer_id'] as $column) {
            $pdo = self::fixture(2);
            $pdo->exec('ALTER TABLE shop_order_items DROP COLUMN ' . $column);
            self::assertSame(\clubProgramAdminOffers($pdo), \clubProgramAdminOffersRead($pdo));
        }
        $pdo = self::fixture(2);
        $pdo->exec('ALTER TABLE club_program_enrollments DROP COLUMN sportovec_id');
        self::assertSame(\clubProgramAdminOffers($pdo), \clubProgramAdminOffersRead($pdo));
    }

    public function testEveryNewProjectionSeesMutationsAndLockingReadIgnoresCachedTerms(): void
    {
        $pdo = self::fixture(1);
        $rows = \clubProgramAdminOffersRead($pdo);
        self::assertTrue($rows[0]['terms_complete']);
        $context = new \ClubProgramReadContext($pdo);
        $context->warmAdminOffers($rows);
        $pdo->exec("UPDATE club_event_term_versions SET status='withdrawn';
            INSERT INTO club_program_enrollments VALUES(1,1,42,NULL,'active')");
        $fresh = \clubProgramAdminOffersRead($pdo);
        self::assertSame(\clubProgramAdminOffers($pdo), $fresh);
        self::assertFalse($fresh[0]['terms_complete']);
        self::assertSame(1, $fresh[0]['occupied_count']);
        self::assertTrue(\clubProgramTermsComplete(\clubProgramTermsEffective($pdo, 1, 1, false, $context)));
        self::assertFalse(\clubProgramTermsComplete(\clubProgramTermsEffective($pdo, 1, 1, true, $context)));
    }

    private static function fixture(int $count): StorefrontCountingPdo
    {
        $pdo = \Tests\Support\programStorefrontFixture($count);
        $pdo->exec('ALTER TABLE club_programs ADD COLUMN code TEXT');
        $pdo->exec('ALTER TABLE shop_products ADD COLUMN name TEXT');
        $pdo->exec("UPDATE shop_products SET name='LOCALHOST NEPLATIT'");
        return $pdo;
    }
}
