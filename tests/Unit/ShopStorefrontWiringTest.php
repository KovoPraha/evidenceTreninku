<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ShopStorefrontWiringTest extends TestCase
{
    public function testProductDetailIsAuthenticatedCsrfProtectedAndUsesReviewedFields(): void
    {
        $root = dirname(__DIR__, 2);
        $source = (string)file_get_contents($root . '/booking/produkt.php');

        self::assertStringContainsString("verejny_uzivatel_id", $source);
        self::assertStringContainsString('csrf_verify', $source);
        self::assertStringContainsString('shopStorefrontProductDetail', $source);
        self::assertStringContainsString('shopCartSetQuantity', $source);
        self::assertStringContainsString('referrerpolicy="no-referrer"', $source);
        self::assertStringNotContainsString('description_html_untrusted', $source);
        self::assertStringNotContainsString('short_description', $source);
    }

    public function testStorefrontLinksToProductDetail(): void
    {
        $source = (string)file_get_contents(dirname(__DIR__, 2) . '/booking/eshop.php');
        self::assertStringContainsString('produkt.php?id=', $source);
        self::assertStringContainsString('shopStorefrontCatalog', $source);
    }
}
