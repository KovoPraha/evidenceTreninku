<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/shop_catalog_promotion.php';

final class ShopCatalogPromotionPreflightTest extends TestCase
{
    public function testReportCombinesCanonicalAndWithinRunCollisions(): void
    {
        $report = \shopCatalogPromotionCollisionReport(
            [
                ['external_product_key' => 'shoptet:pair:1', 'skus' => ['SKU-A', 'SKU-B']],
                ['external_product_key' => 'shoptet:pair:1', 'skus' => ['SKU-C']],
                ['external_product_key' => 'shoptet:pair:3', 'skus' => ['SKU-B', 'SKU-D', 'SKU-D']],
            ],
            ['shoptet:pair:3'],
            ['SKU-A']
        );

        self::assertSame(['SKU-A', 'SKU-B', 'SKU-C', 'SKU-D'], $report['colliding_skus']);
        self::assertSame(['shoptet:pair:1', 'shoptet:pair:3'], $report['colliding_external_keys']);
        self::assertSame([], $report['non_import_skus']);
        self::assertSame([], $report['non_import_external_keys']);
    }

    public function testReportMarksEverySkuFromNonImportStagingProduct(): void
    {
        $report = \shopCatalogPromotionCollisionReport(
            [
                ['external_product_key' => 'manual:123', 'skus' => ['KP-ONE', 'KP-TWO']],
                ['external_product_key' => 'shoptet:pair:2', 'skus' => ['IMPORT-2']],
            ],
            [],
            []
        );

        self::assertSame([], $report['colliding_skus']);
        self::assertSame([], $report['colliding_external_keys']);
        self::assertSame(['KP-ONE', 'KP-TWO'], $report['non_import_skus']);
        self::assertSame(['manual:123'], $report['non_import_external_keys']);
    }

    public function testNonImportProductWithoutVariantIsStillRejectedByReport(): void
    {
        $report = \shopCatalogPromotionCollisionReport(
            [['external_product_key' => 'manual:without-variant', 'skus' => []]],
            [],
            []
        );

        self::assertSame(['manual:without-variant'], $report['non_import_external_keys']);
        self::assertSame([], $report['non_import_skus']);
    }

    public function testSkuListIsStableAndExplicit(): void
    {
        self::assertSame('`A-1`, `B-2`', \shopCatalogPromotionSkuList(['A-1', 'B-2']));
    }
}
