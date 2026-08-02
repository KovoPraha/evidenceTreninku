<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/shoptet_product_input.php';
require_once dirname(__DIR__, 2) . '/includes/shop_catalog_stage.php';
require_once dirname(__DIR__, 2) . '/includes/shop_catalog_review.php';
require_once dirname(__DIR__, 2) . '/includes/shop_catalog_promotion.php';

final class ShopCatalogPromotionTest extends TestCase
{
    public function testReviewedRunPromotesExactlyOnceIntoDraftCatalog(): void
    {
        [$pdo, $catalog, $runId] = $this->databaseWithStagedCatalog();
        $this->resolvePending($pdo, $runId);

        $first = \shopCatalogPromote($pdo, $runId, 7, true);
        $second = \shopCatalogPromote($pdo, $runId, 7, true);

        self::assertTrue($first['created']);
        self::assertFalse($second['created']);
        self::assertSame($first['promotion_id'], $second['promotion_id']);
        self::assertSame(10, $first['products']);
        self::assertSame(10, $first['variants']);
        self::assertSame('draft', $first['catalog_status']);
        self::assertSame(10, (int)$pdo->query('SELECT COUNT(*) FROM shop_products')->fetchColumn());
        self::assertSame(10, (int)$pdo->query('SELECT COUNT(*) FROM shop_variants')->fetchColumn());
        self::assertSame(1, (int)$pdo->query(
            "SELECT COUNT(*) FROM shop_products WHERE offer_type='bookable_rental'"
        )->fetchColumn());
        self::assertSame(0, (int)$pdo->query(
            "SELECT COUNT(*) FROM shop_products WHERE catalog_status<>'draft'"
        )->fetchColumn());
        self::assertSame('promoted', $pdo->query(
            'SELECT status FROM shop_catalog_import_runs WHERE id=' . $runId
        )->fetchColumn());
        self::assertSame($catalog['summary']['products'], $first['products']);
        self::assertSame(0, (int)$pdo->query(
            "SELECT COUNT(*) FROM sqlite_master WHERE type='table' "
            . "AND name IN ('shop_orders','payments','club_event_registrations')"
        )->fetchColumn());

        $summary = \shopCanonicalCatalogSummary($pdo);
        self::assertSame(10, $summary['products']);
        self::assertSame(10, $summary['draft_products']);
        self::assertSame($runId, (int)$summary['last_promotion']['run_id']);
    }

    public function testCollisionRollsBackWholeSecondPromotion(): void
    {
        [$pdo, $catalog, $firstRunId] = $this->databaseWithStagedCatalog();
        $this->resolvePending($pdo, $firstRunId);
        \shopCatalogPromote($pdo, $firstRunId, 7, true);

        $catalog['source']['sha256'] = str_repeat('b', 64);
        $secondRun = \shopCatalogStage($pdo, $catalog);
        $this->resolvePending($pdo, $secondRun['run_id']);

        try {
            \shopCatalogPromote($pdo, $secondRun['run_id'], 7, true);
            self::fail('Duplicate canonical keys must block the complete promotion.');
        } catch (\ShopCatalogPromotionException $exception) {
            self::assertStringContainsString('bez částečného zápisu', $exception->getMessage());
        }

        self::assertSame(10, (int)$pdo->query('SELECT COUNT(*) FROM shop_products')->fetchColumn());
        self::assertSame(10, (int)$pdo->query('SELECT COUNT(*) FROM shop_variants')->fetchColumn());
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM shop_catalog_promotions')->fetchColumn());
        self::assertSame('ready_for_promotion', $pdo->query(
            'SELECT status FROM shop_catalog_import_runs WHERE id=' . (int)$secondRun['run_id']
        )->fetchColumn());
    }

    public function testExplicitConfirmationIsRequiredBeforeAnyWrite(): void
    {
        [$pdo, , $runId] = $this->databaseWithStagedCatalog();
        $this->resolvePending($pdo, $runId);

        $this->expectException(\InvalidArgumentException::class);
        try {
            \shopCatalogPromote($pdo, $runId, 7, false);
        } finally {
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM shop_products')->fetchColumn());
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM shop_catalog_promotions')->fetchColumn());
        }
    }

    /** @return array{PDO,array<string,mixed>,int} */
    private function databaseWithStagedCatalog(): array
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        foreach ([
            '20260802170000_shop_catalog_staging.php',
            '20260802190000_shop_catalog_review.php',
            '20260802210000_shop_canonical_catalog.php',
        ] as $filename) {
            $migration = require dirname(__DIR__, 2) . '/migrations/' . $filename;
            $migration['up']($pdo);
            $migration['up']($pdo);
            self::assertTrue($migration['verify']($pdo));
        }
        $input = dirname(__DIR__) . '/fixtures/shoptet/products-offer-types.csv';
        $catalog = \ShopCatalogContract::build(\ShoptetProductInput::read($input));
        $run = \shopCatalogStage($pdo, $catalog);
        return [$pdo, $catalog, $run['run_id']];
    }

    private function resolvePending(PDO $pdo, int $runId): void
    {
        $pending = $pdo->query(
            "SELECT id FROM shop_catalog_product_candidates WHERE run_id=$runId AND review_status='pending'"
        )->fetchColumn();
        self::assertNotFalse($pending);
        \shopCatalogReviewProduct(
            $pdo,
            $runId,
            (int)$pending,
            7,
            'approve',
            'goods',
            'Fyzické zboží.'
        );
    }
}
