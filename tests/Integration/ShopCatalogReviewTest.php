<?php
declare(strict_types=1);

namespace Tests\Integration;

use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/shoptet_product_input.php';
require_once dirname(__DIR__, 2) . '/includes/shop_catalog_stage.php';
require_once dirname(__DIR__, 2) . '/includes/shop_catalog_review.php';

final class ShopCatalogReviewTest extends TestCase
{
    public function testManualReviewCreatesAuditAndMakesRunReadyWithoutPublishing(): void
    {
        $pdo = $this->databaseWithCatalog();
        $pending = $pdo->query(
            "SELECT id, run_id FROM shop_catalog_product_candidates WHERE review_status='pending'"
        )->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($pending);

        $result = \shopCatalogReviewProduct(
            $pdo,
            (int)$pending['run_id'],
            (int)$pending['id'],
            7,
            'approve',
            'goods',
            'Jde o fyzické tričko, nikoliv registraci do kroužku.'
        );

        self::assertSame('approved', $result['status']);
        self::assertSame('goods', $result['effective_offer_type']);
        self::assertSame('ready_for_promotion', $result['run_status']);
        self::assertSame('ready_for_promotion', $pdo->query(
            'SELECT status FROM shop_catalog_import_runs'
        )->fetchColumn());
        self::assertSame(1, (int)$pdo->query(
            'SELECT COUNT(*) FROM shop_catalog_review_events'
        )->fetchColumn());
        self::assertSame(0, (int)$pdo->query(
            "SELECT COUNT(*) FROM sqlite_master WHERE type='table' "
            . "AND name IN ('shop_products','shop_orders','payments','club_event_registrations')"
        )->fetchColumn());

        $detail = \shopCatalogReviewDetail($pdo, (int)$pending['run_id'], 'approved', 'goods', 'AMBIGUOUS');
        self::assertCount(1, $detail['products']);
        self::assertCount(1, $detail['events']);
        self::assertSame('Admin Test', $detail['events'][0]['actor_name']);
    }

    public function testTypeOverrideWithoutReasonRollsBack(): void
    {
        $pdo = $this->databaseWithCatalog();
        $product = $pdo->query(
            "SELECT id, run_id FROM shop_catalog_product_candidates WHERE offer_type='rental' LIMIT 1"
        )->fetch(PDO::FETCH_ASSOC);

        $this->expectException(InvalidArgumentException::class);
        try {
            \shopCatalogReviewProduct(
                $pdo,
                (int)$product['run_id'],
                (int)$product['id'],
                7,
                'approve',
                'goods',
                ''
            );
        } finally {
            self::assertSame(0, (int)$pdo->query(
                'SELECT COUNT(*) FROM shop_catalog_review_events'
            )->fetchColumn());
            self::assertSame('auto_classified', $pdo->query(
                'SELECT review_status FROM shop_catalog_product_candidates WHERE id=' . (int)$product['id']
            )->fetchColumn());
        }
    }

    private function databaseWithCatalog(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('CREATE TABLE treneri (id INTEGER PRIMARY KEY, jmeno TEXT NOT NULL)');
        $pdo->exec("INSERT INTO treneri (id, jmeno) VALUES (7, 'Admin Test')");
        foreach ([
            '20260802170000_shop_catalog_staging.php',
            '20260802190000_shop_catalog_review.php',
        ] as $filename) {
            $migration = require dirname(__DIR__, 2) . '/migrations/' . $filename;
            $migration['up']($pdo);
        }
        $input = dirname(__DIR__) . '/fixtures/shoptet/products-offer-types.csv';
        \shopCatalogStage(
            $pdo,
            \ShopCatalogContract::build(\ShoptetProductInput::read($input))
        );
        return $pdo;
    }
}
