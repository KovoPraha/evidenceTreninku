<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ShopAdminWiringTest extends TestCase
{
    public function testAdminPageIsRoleAndCsrfProtectedAndDoesNotPublishCatalog(): void
    {
        $root = dirname(__DIR__, 2);
        $source = file_get_contents($root . '/eshop_admin.php');
        self::assertIsString($source);

        self::assertStringContainsString("roleAtLeast('admin')", $source);
        self::assertStringContainsString('csrf_verify', $source);
        self::assertStringContainsString('shopCatalogReviewProduct', $source);
        self::assertStringContainsString('shopCatalogPromote', $source);
        self::assertStringContainsString('confirm_promotion', $source);
        self::assertStringNotContainsString('description_html_untrusted', $source);
        self::assertStringNotContainsString('INSERT INTO shop_products', $source);
        self::assertStringNotContainsString('INSERT INTO shop_orders', $source);
        self::assertStringContainsString('ve stavu draft', $source);
        self::assertStringContainsString('shopBankSettingsResolve', $source);
        self::assertStringContainsString('Objednávky nyní nelze dokončit.', $source);
    }

    public function testAdminNavigationLinksToShopReview(): void
    {
        $root = dirname(__DIR__, 2);
        self::assertStringContainsString('eshop_admin.php', (string)file_get_contents($root . '/hlavicka.php'));
        self::assertStringContainsString('eshop_admin.php', (string)file_get_contents($root . '/index.php'));
        self::assertStringContainsString('eshop_admin.php', (string)file_get_contents($root . '/admin_dashboard.php'));
    }

    public function testProductAdminUsesProtectedDomainWritersAndPrg(): void
    {
        $root = dirname(__DIR__, 2);
        $source = (string)file_get_contents($root . '/eshop_produkt_admin.php');
        self::assertStringContainsString("roleAtLeast('admin')", $source);
        self::assertStringContainsString('csrf_verify', $source);
        self::assertStringContainsString('shopManualCatalogCreate', $source);
        self::assertStringContainsString('shopManualCatalogUpdateProduct', $source);
        self::assertStringContainsString('shopManualCatalogUpdateVariant', $source);
        self::assertStringContainsString('shopManualCatalogArchive', $source);
        self::assertStringContainsString("header('Location: eshop_produkt_admin.php", $source);
        self::assertStringNotContainsString('INSERT INTO shop_products', $source);
        self::assertStringContainsString('eshop_produkt_admin.php', (string)file_get_contents($root . '/eshop_admin.php'));
    }
}
