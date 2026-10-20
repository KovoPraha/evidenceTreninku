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
        $source = (string)file_get_contents($root . '/includes/shop_admin_page.php');
        self::assertStringContainsString("roleAtLeast('admin')", $source);
        self::assertStringContainsString('csrf_verify', $source);
        self::assertStringContainsString('shopManualCatalogCreate', $source);
        self::assertStringContainsString('shopManualCatalogUpdateProduct', $source);
        self::assertStringContainsString('shopManualCatalogUpdateVariant', $source);
        self::assertStringContainsString('shopManualCatalogAddVariants', $source);
        self::assertStringContainsString('shopCatalogPublicationDeactivate', $source);
        self::assertStringContainsString("header('Location: '.\$redirect,true,303)", $source);
        foreach(['eshop_produkt_admin.php','eshop_produkt_novy_admin.php','eshop_produkt_stahnout_admin.php','eshop_varianta_admin.php','eshop_ceny_admin.php','eshop_sklad_admin.php'] as $route) {
            self::assertStringContainsString("require __DIR__.'/includes/shop_admin_page.php'",(string)file_get_contents($root.'/'.$route));
        }
        self::assertStringNotContainsString('INSERT INTO shop_products', $source);
        self::assertStringContainsString('eshop_produkt_admin.php', (string)file_get_contents($root . '/eshop_admin.php'));
    }

    public function testCategoryAdminUsesProtectedDomainWritersAuditAndPrg(): void
    {
        $root=dirname(__DIR__,2);$source=(string)file_get_contents($root.'/eshop_categories_admin.php');
        self::assertStringContainsString("roleAtLeast('admin')",$source);
        self::assertStringContainsString('csrf_verify',$source);
        self::assertStringContainsString('shopCategoryAdminSave',$source);
        self::assertStringContainsString('shopCategoryAdminDelete',$source);
        self::assertStringContainsString('shopCategoryAdminAssignProduct',$source);
        self::assertStringContainsString('COUNT(pc.id) category_count',$source);
        self::assertStringContainsString('bez kategorie – neúplné',$source);
        self::assertStringContainsString("header('Location: eshop_categories_admin.php",$source);
        self::assertStringNotContainsString('INSERT INTO shop_category_meta',$source);
        self::assertStringContainsString('eshop_categories_admin.php',(string)file_get_contents($root.'/hlavicka.php'));
        self::assertStringContainsString('eshop_categories_admin.php',(string)file_get_contents($root.'/eshop_admin.php'));
    }

    public function testAttributeAdminAndVariantEditorUseProtectedDictionaryWriters(): void
    {
        $root=dirname(__DIR__,2);$admin=(string)file_get_contents($root.'/eshop_attributes_admin.php');
        self::assertStringContainsString("roleAtLeast('admin')",$admin);
        self::assertStringContainsString('csrf_verify',$admin);
        self::assertStringContainsString('shopAttributeAdminSave',$admin);
        self::assertStringContainsString('shopAttributeDiscoveredKeys',$admin);
        self::assertStringNotContainsString('INSERT INTO shop_attribute_definitions',$admin);
        $product=(string)file_get_contents($root.'/includes/shop_admin_view.php');
        self::assertStringContainsString('attribute_keys[]',$product);
        self::assertStringContainsString('data-shop-add-attribute',$product);
        self::assertStringContainsString("definition.value_type === 'choice'",(string)file_get_contents($root.'/assets/shop-admin.js'));
        self::assertStringContainsString('shopAttributeFormJson',(string)file_get_contents($root.'/includes/shop_admin_input.php'));
        self::assertStringContainsString('eshop_attributes_admin.php',(string)file_get_contents($root.'/hlavicka.php'));
        self::assertStringContainsString('eshop_attributes_admin.php',(string)file_get_contents($root.'/eshop_admin.php'));
    }

    public function testCatalogManagementAndProgramOfferEditorsUseSharedAuditedWriters():void
    {
        $root=dirname(__DIR__,2);$catalog=(string)file_get_contents($root.'/eshop_catalog_admin.php');self::assertStringContainsString("roleAtLeast('admin')",$catalog);self::assertStringContainsString('csrf_verify',$catalog);self::assertStringContainsString('shopCatalogBulkActivate',$catalog);self::assertStringContainsString('shopCatalogAdjustStock',$catalog);self::assertStringNotContainsString('UPDATE shop_variants SET stock_quantity_decimal',$catalog);
        $offers=(string)file_get_contents($root.'/club_program_offers_admin.php');self::assertStringContainsString('clubProgramUpdateOffer',$offers);self::assertStringContainsString('clubProgramCloseOffer',$offers);self::assertStringContainsString('sale_reason',$offers);self::assertStringContainsString('eshop_catalog_admin.php',(string)file_get_contents($root.'/hlavicka.php'));self::assertStringContainsString('club_program_offers_admin.php',(string)file_get_contents($root.'/hlavicka.php'));
    }
}
