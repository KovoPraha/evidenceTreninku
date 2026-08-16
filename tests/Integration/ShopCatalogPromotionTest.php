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
        self::assertSame(0, (int)$pdo->query(
            "SELECT COUNT(*) FROM shop_products "
            . "WHERE origin<>'import' OR source_candidate_id IS NULL OR source_run_id IS NULL"
        )->fetchColumn());
        self::assertSame(0, (int)$pdo->query(
            "SELECT COUNT(*) FROM shop_variants WHERE origin<>'import' OR source_candidate_id IS NULL"
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
            self::assertStringContainsString('před prvním zápisem', $exception->getMessage());
            self::assertStringContainsString('Kolidující SKU:', $exception->getMessage());
            self::assertStringContainsString('Přejmenujte v administraci ručně založená SKU', $exception->getMessage());
            self::assertStringContainsString('importní soubor není rozbitý', $exception->getMessage());
        }

        self::assertSame(10, (int)$pdo->query('SELECT COUNT(*) FROM shop_products')->fetchColumn());
        self::assertSame(10, (int)$pdo->query('SELECT COUNT(*) FROM shop_variants')->fetchColumn());
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM shop_catalog_promotions')->fetchColumn());
        self::assertSame('ready_for_promotion', $pdo->query(
            'SELECT status FROM shop_catalog_import_runs WHERE id=' . (int)$secondRun['run_id']
        )->fetchColumn());
    }

    public function testReservedManualSkuDoesNotCollideWithSyntheticImport(): void
    {
        [$pdo, , $runId] = $this->databaseWithStagedCatalog();
        $this->resolvePending($pdo, $runId);
        $this->insertManualProduct($pdo, 'KP-R2-SYNTHETIC-UNIQUE', true);

        $result = \shopCatalogPromote($pdo, $runId, 7, true);

        self::assertTrue($result['created']);
        self::assertSame(10, $result['products']);
        self::assertSame(11, (int)$pdo->query('SELECT COUNT(*) FROM shop_products')->fetchColumn());
        self::assertSame(11, (int)$pdo->query('SELECT COUNT(*) FROM shop_variants')->fetchColumn());
    }

    public function testManualSkuCollisionStopsBeforeFirstPromotionInsertWithExactGuidance(): void
    {
        [$pdo, , $runId] = $this->databaseWithStagedCatalog();
        $this->resolvePending($pdo, $runId);
        $collisionSku = (string)$pdo->query(
            'SELECT sku FROM shop_catalog_variant_candidates WHERE run_id=' . $runId . ' ORDER BY sku LIMIT 1'
        )->fetchColumn();
        self::assertNotSame('', $collisionSku);
        $this->insertManualProduct($pdo, $collisionSku, false);
        $before = $this->canonicalWriteCounts($pdo);

        try {
            \shopCatalogPromote($pdo, $runId, 7, true);
            self::fail('Manual SKU collision must stop the import preflight.');
        } catch (\ShopCatalogPromotionException $exception) {
            self::assertStringContainsString('před prvním zápisem', $exception->getMessage());
            self::assertStringContainsString('`' . $collisionSku . '`', $exception->getMessage());
            self::assertStringContainsString('Přejmenujte v administraci ručně založená SKU', $exception->getMessage());
        }

        self::assertSame($before, $this->canonicalWriteCounts($pdo));
        self::assertSame(0, $before['promotions']);
    }

    public function testPromotionRejectsManualNamespaceInStagingBeforeAnyInsert(): void
    {
        [$pdo, , $runId] = $this->databaseWithStagedCatalog();
        $this->resolvePending($pdo, $runId);
        $candidateId = (int)$pdo->query(
            'SELECT id FROM shop_catalog_product_candidates WHERE run_id=' . $runId . ' ORDER BY id LIMIT 1'
        )->fetchColumn();
        $manualKey = \shopCatalogManualExternalProductKey();
        $pdo->prepare('UPDATE shop_catalog_product_candidates SET external_product_key=? WHERE id=?')
            ->execute([$manualKey, $candidateId]);

        try {
            \shopCatalogPromote($pdo, $runId, 7, true);
            self::fail('Promotion must never process a manual product namespace.');
        } catch (\ShopCatalogPromotionException $exception) {
            self::assertStringContainsString('namespace shoptet:', $exception->getMessage());
            self::assertStringContainsString('Ruční produkty patří přímo do katalogu', $exception->getMessage());
        }
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM shop_catalog_promotions')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM shop_products')->fetchColumn());
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
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT NOT NULL)');
        $pdo->exec("INSERT INTO treneri VALUES(7,'Admin')");
        foreach ([
            '20260802170000_shop_catalog_staging.php',
            '20260802190000_shop_catalog_review.php',
            '20260802210000_shop_canonical_catalog.php',
            '20260816200000_shop_manual_catalog_origin.php',
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

    private function insertManualProduct(PDO $pdo, string $sku, bool $enforceReservedPrefix): void
    {
        $externalKey = \shopCatalogManualExternalProductKey();
        \shopCatalogAssertManualExternalProductKey($externalKey);
        if ($enforceReservedPrefix) {
            \shopCatalogAssertManualSku($sku);
        }
        \shopCatalogAssertProductOrigin(\ShopCatalogOrigin::MANUAL, null, null, 7);
        \shopCatalogAssertVariantOrigin(
            \ShopCatalogOrigin::MANUAL,
            null,
            7,
            \ShopCatalogOrigin::MANUAL
        );
        $statement = $pdo->prepare(
            'INSERT INTO shop_products '
            . '(source_candidate_id,source_run_id,origin,created_by_trainer_id,external_product_key,name,'
            . "offer_type,catalog_status) VALUES(NULL,NULL,'manual',7,?,'Ruční R2 produkt','goods','draft')"
        );
        $statement->execute([$externalKey]);
        $productId = (int)$pdo->lastInsertId();
        $statement = $pdo->prepare(
            'INSERT INTO shop_variants '
            . '(product_id,source_candidate_id,origin,created_by_trainer_id,sku,attributes_json,price_mode,'
            . "amount_minor,currency,catalog_status) VALUES(?,NULL,'manual',7,?,'{}','fixed',10000,'CZK','draft')"
        );
        $statement->execute([$productId, $sku]);
    }

    /** @return array{promotions:int,products:int,variants:int} */
    private function canonicalWriteCounts(PDO $pdo): array
    {
        return [
            'promotions' => (int)$pdo->query('SELECT COUNT(*) FROM shop_catalog_promotions')->fetchColumn(),
            'products' => (int)$pdo->query('SELECT COUNT(*) FROM shop_products')->fetchColumn(),
            'variants' => (int)$pdo->query('SELECT COUNT(*) FROM shop_variants')->fetchColumn(),
        ];
    }
}
