<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/ProgramStorefrontFixture.php';
require_once dirname(__DIR__, 2) . '/includes/shop_public_navigation.php';

final class ProgramStorefrontReadPerformanceTest extends TestCase
{
    public function testProgramNavigationDoesNotRepeatSchemaOrSaleStatePerVariant(): void
    {
        $pdo = \Tests\Support\programStorefrontFixture(50);
        $menu = \shopPublicNavigationItems($pdo);
        self::assertSame([50, 50], array_column($menu, 'product_count'));
        $metadata = array_filter($pdo->queries, static fn (string $sql): bool =>
            str_contains($sql, 'PRAGMA') || str_contains($sql, 'sqlite_master'));
        self::assertLessThanOrEqual(25, count($metadata), 'Schema reads belong to one projection, not every variant.');
        self::assertLessThanOrEqual(450, count($pdo->queries), 'Offer capacity and terms must not be reloaded by the same projection filter.');
    }
}
