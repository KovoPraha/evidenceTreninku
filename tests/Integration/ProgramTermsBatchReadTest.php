<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/ProgramStorefrontFixture.php';
require_once dirname(__DIR__, 2) . '/includes/shop_catalog_cards.php';

final class ProgramTermsBatchReadTest extends TestCase
{
    public function testCatalogBatchesPublishedTermsInsteadOfFourLookupsPerProgram(): void
    {
        $pdo = \Tests\Support\programStorefrontFixture(50);
        self::assertCount(50, \shopPublicCatalogCards($pdo));
        $queries = array_filter($pdo->queries, static fn (string $sql): bool => str_contains($sql, 'FROM club_event_term_versions'));
        self::assertLessThanOrEqual(2, count($queries), 'Offer and program terms belong to two bounded batches.');
        self::assertLessThanOrEqual(225, count($pdo->queries));
    }

    public function testLatestActiveOfferOverrideAndProgramFallbackMatchCanonicalRows(): void
    {
        $pdo = \Tests\Support\programStorefrontFixture(2);
        $pdo->exec("INSERT INTO club_event_term_versions VALUES "
            . "(100,'club_program_offer','offer:1','program_consent','Old offer consent','active'),"
            . "(101,'club_program_offer','offer:1','program_consent','Latest offer consent','active'),"
            . "(102,'club_program_offer','offer:1','program_consent','Archived consent','archived'),"
            . "(103,'club_program','program:1','program_cancellation','Latest cancellation','active')");
        $context = new \ClubProgramReadContext($pdo);
        $context->warmTermsForVariants([1, 2, 1]);
        foreach ([1, 2] as $id) {
            self::assertSame(\clubProgramTermsEffective($pdo, $id, $id), \clubProgramTermsEffective($pdo, $id, $id, false, $context));
        }
        $terms = \clubProgramTermsEffective($pdo, 1, 1, false, $context);
        self::assertSame(101, $terms['program_consent']['id']);
        self::assertSame(103, $terms['program_cancellation']['id']);
        $pdo->queries = [];
        $context->warmTermsForVariants([1, 2]);
        self::assertSame([], $pdo->queries, 'A repeated warmup must retain this operation snapshot, including missing offer terms.');
    }

    public function testDraftOrEmptyActiveOverrideStillBlocksInsteadOfFallingBack(): void
    {
        $pdo = \Tests\Support\programStorefrontFixture(1);
        foreach (['VZOR — pracovní text', ''] as $text) {
            $pdo->exec('DELETE FROM club_event_term_versions WHERE id=100');
            $pdo->prepare("INSERT INTO club_event_term_versions VALUES(100,'club_program_offer','offer:1','program_consent',?,'active')")->execute([$text]);
            $context = new \ClubProgramReadContext($pdo);
            $context->warmTermsForVariants([1]);
            self::assertSame(\clubProgramVariantSaleState($pdo, 1), $context->saleState(1));
            self::assertFalse($context->saleState(1)['saleable']);
            self::assertSame([], \shopPublicCatalogCards($pdo));
        }
    }

    public function testNewReadAndLockingReadSeePublicationAfterCachedMiss(): void
    {
        $pdo = \Tests\Support\programStorefrontFixture(1);
        $context = new \ClubProgramReadContext($pdo);
        $context->warmTermsForVariants([1]);
        self::assertFalse($context->term('offer', 1, 'program_consent'));
        $pdo->exec("INSERT INTO club_event_term_versions VALUES(100,'club_program_offer','offer:1','program_consent','VZOR — changed','active')");
        self::assertTrue($context->saleState(1)['saleable']);
        self::assertFalse(\clubProgramVariantSaleState($pdo, 1, null, true, $context)['saleable']);
        self::assertSame([], \shopPublicCatalogCards($pdo));
        $pdo->exec("UPDATE club_event_term_versions SET status='withdrawn' WHERE id=100");
        self::assertCount(1, \shopPublicCatalogCards($pdo));
        $pdo->exec("UPDATE club_event_term_versions SET status='withdrawn'");
        self::assertSame([], \shopPublicCatalogCards($pdo));
        self::assertFalse(\clubProgramVariantSaleState($pdo, 1, null, true, $context)['saleable']);
    }

    public function testBatchBoundaryAndGoodsOnlyProjectionRemainBounded(): void
    {
        $pdo = \Tests\Support\programStorefrontFixture(81);
        self::assertCount(81, \shopPublicCatalogCards($pdo));
        $queries = array_filter($pdo->queries, static fn (string $sql): bool => str_contains($sql, 'FROM club_event_term_versions'));
        self::assertCount(4, $queries);
        foreach ($queries as $sql) {
            self::assertLessThanOrEqual(800, substr_count($sql, '?'));
        }
        $goods = \Tests\Support\storefrontReadFixture(10, false);
        self::assertCount(10, \shopPublicCatalogCards($goods));
        self::assertSame([], array_values(array_filter($goods->queries, static fn (string $sql): bool => str_contains($sql, 'club_event_term_versions'))));
    }

    public function testMissingRegistryRetainsLegacyBehaviorWithoutCreatingSchema(): void
    {
        $pdo = \Tests\Support\programStorefrontFixture(1);
        $pdo->exec('DROP TABLE club_event_term_versions');
        self::assertCount(1, \shopPublicCatalogCards($pdo));
        self::assertFalse(\clubProgramRuntimeTableExists($pdo, 'club_event_term_versions'));
        $pdo->exec('CREATE TABLE club_event_term_versions(id INTEGER PRIMARY KEY,scope_type TEXT,scope_key TEXT,consent_purpose TEXT,consent_text_plain TEXT,status TEXT)');
        self::assertSame([], \shopPublicCatalogCards($pdo));
    }

    public function testSqliteCaseSensitiveScopesAndConnectionGuardMatchCanonicalReads(): void
    {
        $pdo = \Tests\Support\programStorefrontFixture(1);
        $pdo->exec("UPDATE club_event_term_versions SET scope_key='PROGRAM:1'");
        $context = new \ClubProgramReadContext($pdo);
        $context->warmTermsForVariants([1]);
        self::assertSame(\clubProgramTermsEffective($pdo, 1, 1), \clubProgramTermsEffective($pdo, 1, 1, false, $context));
        self::assertFalse($context->saleState(1)['saleable']);
        $other = \Tests\Support\programStorefrontFixture(1);
        $this->expectException(\LogicException::class);
        \clubProgramTermsEffective($other, 1, 1, false, $context);
    }
}
