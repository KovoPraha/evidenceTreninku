<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class LightweightEshopWorkflowWiringTest extends TestCase
{
    public function testCatalogCreatesGoodsWithSafeDefaultsAndPublishesOnTheSamePage(): void
    {
        $source = (string)file_get_contents(dirname(__DIR__, 2) . '/eshop_produkt_admin.php');
        self::assertStringContainsString('function productAdminGeneratedSku()', $source);
        self::assertStringContainsString("\$_POST['offer_type']='goods'", $source);
        self::assertStringContainsString("\$_POST['visibility']='visible'", $source);
        self::assertStringContainsString("\$action==='publish'", $source);
        self::assertStringContainsString('shopCatalogPublicationActivate(', $source);
        self::assertStringContainsString("\$action==='unpublish'", $source);
        self::assertStringContainsString('shopCatalogPublicationDeactivate(', $source);
        self::assertStringContainsString('Zveřejnit v e-shopu', $source);
        self::assertStringContainsString('Zobrazit technické údaje', $source);
        self::assertStringNotContainsString('href="eshop_catalog_publication_admin.php"', $source);
        self::assertStringNotContainsString('name="attributes_json"', $source);
    }

    public function testClubProgramIsOneShortFormWithApprovedTermsFilledByTheSystem(): void
    {
        $source = (string)file_get_contents(dirname(__DIR__, 2) . '/club_program_wizard_admin.php');
        self::assertSame(1, substr_count($source, '<form method="post"'));
        self::assertStringContainsString("\$input['category_path'] = 'Kroužky'", $source);
        self::assertStringContainsString("\$input[\$purpose . '_source'] = 'existing'", $source);
        self::assertStringContainsString("\$input['team_mode'] = 'new'", $source);
        self::assertStringContainsString('Vypsat a zveřejnit kroužek', $source);
        self::assertStringNotContainsString('Sazba DPH', $source);
        self::assertStringNotContainsString('Auditní důvod', $source);
        self::assertStringNotContainsString('JSON', $source);
    }

    public function testOrderAndPaymentQueuesKeepExceptionsCollapsed(): void
    {
        $root = dirname(__DIR__, 2);
        $orders = (string)file_get_contents($root . '/eshop_orders_admin.php');
        $payments = (string)file_get_contents($root . '/eshop_payments_admin.php');
        self::assertStringContainsString('<summary class="btn btn-outline-secondary btn-sm">Pokročilé</summary>', $orders);
        self::assertStringContainsString('Ruční expirace nezaplacených', $orders);
        self::assertStringContainsString('<summary class="btn btn-outline-secondary btn-sm">Pokročilé</summary>', $payments);
        self::assertStringContainsString('Automatické párování Fio', $payments);
        self::assertStringContainsString('Nastavení bankovního účtu', $payments);
    }
}
