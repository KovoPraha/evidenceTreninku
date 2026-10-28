<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/shop_public_navigation.php';
require_once dirname(__DIR__) . '/Support/StorefrontReadFixture.php';

final class StorefrontReadPerformanceTest extends TestCase
{
    public function testDetailReadsOnlyItsOwnVariantsEvenWithLargeUnrelatedCatalog(): void
    {
        $small = \Tests\Support\storefrontReadFixture(1, false);
        $large = \Tests\Support\storefrontReadFixture(1000, false);
        self::assertSame(\shopStorefrontProductDetail($small, 1), \shopStorefrontProductDetail($large, 1));
        self::assertSame(1, $large->productRows);
        self::assertCount(count($small->queries), $large->queries);
        self::assertLessThanOrEqual(12, count($large->queries));
    }

    public function testCatalogSchemaQueriesDoNotGrowWithVariantCount(): void
    {
        $small = \Tests\Support\storefrontReadFixture(1, false);
        $large = \Tests\Support\storefrontReadFixture(1000, false);
        self::assertCount(1, \shopStorefrontCatalog($small));
        self::assertCount(1000, \shopStorefrontCatalog($large));
        self::assertCount(count($small->queries), $large->queries);
    }

    public function testNavigationQueryBudgetAndNoDetailPresentationData(): void
    {
        foreach ([false, true] as $imported) {
            $small = \Tests\Support\storefrontReadFixture(1, $imported);
            $large = \Tests\Support\storefrontReadFixture(1000, $imported);
            $smallMenu = \shopPublicNavigationItems($small);
            $largeMenu = \shopPublicNavigationItems($large);
            self::assertSame(array_column($smallMenu, 'category_path'), array_column($largeMenu, 'category_path'));
            self::assertSame([1000, 1000], array_column($largeMenu, 'product_count'));
            self::assertCount(count($small->queries), $large->queries);
            self::assertLessThanOrEqual(20, count($large->queries));
            foreach ($large->queries as $sql) {
                self::assertStringNotContainsString('shop_product_images', $sql);
                self::assertStringNotContainsString('attributes_json', $sql);
                self::assertStringNotContainsString('shop_attribute_definitions', $sql);
            }
        }
    }
}
