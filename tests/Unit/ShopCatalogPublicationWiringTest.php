<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ShopCatalogPublicationWiringTest extends TestCase
{
    public function testPublicationAdminIsProtectedExplicitAndHasNoStorefrontWrites(): void
    {
        $root = dirname(__DIR__, 2);
        $source = (string)file_get_contents($root . '/eshop_catalog_publication_admin.php');

        self::assertStringContainsString("roleAtLeast('admin')", $source);
        self::assertStringContainsString('csrf_verify', $source);
        self::assertStringContainsString('shopCatalogPublicationActivate', $source);
        self::assertStringContainsString('shopCatalogPublicationDeactivate', $source);
        self::assertStringContainsString("name=\"confirmed\"", $source);
        self::assertStringContainsString('Aktivace zde produkt zveřejní v klubovém e-shopu.', $source);
        self::assertStringNotContainsString('Storefront ani checkout neexistují', $source);
        self::assertStringNotContainsString('INSERT INTO shop_orders', $source);
        self::assertStringNotContainsString('kis_', strtolower($source));
    }

    public function testAdminNavigationLinksToPublicationDecisions(): void
    {
        // index.php's card-wall was retired 2026-08-08 in favor of the hlavicka.php
        // navbar as the single admin navigation source (see docs/navrh-informacni-architektury.md).
        $root = dirname(__DIR__, 2);
        foreach (['hlavicka.php', 'eshop_admin.php'] as $filename) {
            self::assertStringContainsString(
                'eshop_catalog_publication_admin.php',
                (string)file_get_contents($root . '/' . $filename),
                $filename
            );
        }
    }
}
