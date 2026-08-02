<?php
declare(strict_types=1);

namespace Tests\Integration;

use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/shoptet_product_input.php';
require_once dirname(__DIR__, 2) . '/includes/shop_catalog_stage.php';

final class ShopCatalogStageTest extends TestCase
{
    public function testMigrationAndStagingAreIdempotentAndKeepCatalogProvisional(): void
    {
        $pdo = $this->database();
        $migration = require dirname(__DIR__, 2)
            . '/migrations/20260802170000_shop_catalog_staging.php';
        $reviewMigration = require dirname(__DIR__, 2)
            . '/migrations/20260802190000_shop_catalog_review.php';

        $migration['up']($pdo);
        $migration['up']($pdo);
        self::assertTrue($migration['verify']($pdo));
        $reviewMigration['up']($pdo);
        $reviewMigration['up']($pdo);
        self::assertTrue($reviewMigration['verify']($pdo));

        $input = dirname(__DIR__) . '/fixtures/shoptet/products-offer-types.csv';
        $catalog = \ShopCatalogContract::build(\ShoptetProductInput::read($input));
        $first = \shopCatalogStage($pdo, $catalog);
        $second = \shopCatalogStage($pdo, $catalog);

        self::assertTrue($first['created']);
        self::assertFalse($second['created']);
        self::assertSame($first['run_id'], $second['run_id']);
        self::assertSame(10, $first['products']);
        self::assertSame(10, $first['variants']);
        self::assertSame(1, $first['manual_review_products']);
        self::assertSame(1, (int)$pdo->query(
            'SELECT COUNT(*) FROM shop_catalog_import_runs'
        )->fetchColumn());
        self::assertSame(10, (int)$pdo->query(
            'SELECT COUNT(*) FROM shop_catalog_product_candidates'
        )->fetchColumn());
        self::assertSame(10, (int)$pdo->query(
            'SELECT COUNT(*) FROM shop_catalog_variant_candidates'
        )->fetchColumn());

        $track = $pdo->query(
            "SELECT offer_type, needs_manual_review FROM shop_catalog_product_candidates "
            . "WHERE external_product_key = 'shoptet:sku:RENTAL-TRACK'"
        )->fetch(PDO::FETCH_ASSOC);
        self::assertSame('bookable_rental', $track['offer_type']);
        self::assertSame(0, (int)$track['needs_manual_review']);
        self::assertSame(0, (int)$pdo->query(
            "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' "
            . "AND name IN ('shop_products', 'shop_orders', 'payments')"
        )->fetchColumn());
    }

    public function testUnreadyCandidateIsRejectedBeforeAnyWrite(): void
    {
        $pdo = $this->database();
        $migration = require dirname(__DIR__, 2)
            . '/migrations/20260802170000_shop_catalog_staging.php';
        $migration['up']($pdo);
        $reviewMigration = require dirname(__DIR__, 2)
            . '/migrations/20260802190000_shop_catalog_review.php';
        $reviewMigration['up']($pdo);

        $this->expectException(InvalidArgumentException::class);
        try {
            \shopCatalogStage($pdo, [
                'contract_version' => \ShopCatalogContract::VERSION,
                'provisional' => true,
                'summary' => ['contract_ready' => false],
            ]);
        } finally {
            self::assertSame(0, (int)$pdo->query(
                'SELECT COUNT(*) FROM shop_catalog_import_runs'
            )->fetchColumn());
        }
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        return $pdo;
    }
}
