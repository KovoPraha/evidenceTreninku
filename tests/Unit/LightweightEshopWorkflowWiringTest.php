<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class LightweightEshopWorkflowWiringTest extends TestCase
{
    public function testCatalogUsesSeparateCreationAndWithdrawalWithProtectedSharedController(): void
    {
        $root=dirname(__DIR__,2);
        $source = (string)file_get_contents($root . '/includes/shop_admin_page.php');
        $templates=(string)file_get_contents($root.'/includes/shop_admin_templates.php');
        self::assertStringContainsString("\$type==='goods'?'product':'service'", $source);
        self::assertStringContainsString("\$input['visibility']='visible'", $source);
        self::assertStringContainsString("\$action==='publish'", $source);
        self::assertStringContainsString('shopCatalogPublicationActivate(', $source);
        self::assertStringContainsString("\$action==='unpublish'", $source);
        self::assertStringContainsString('shopCatalogPublicationDeactivate(', $source);
        self::assertStringContainsString("\$screen==='new'",$templates);
        self::assertStringContainsString("\$screen==='withdraw'",$templates);
        self::assertStringContainsString('eshop_produkt_novy_admin.php',$source);
        self::assertStringContainsString('eshop_produkt_stahnout_admin.php',$source);
        self::assertStringNotContainsString('name="attributes_json"', $templates);
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
        self::assertStringContainsString('<title>Objednávky</title>', $orders);
        self::assertStringContainsString('<summary class="btn btn-outline-secondary btn-sm">Pokročilé</summary>', $payments);
        self::assertStringContainsString('Automatické párování Fio', $payments);
        self::assertStringContainsString('Nastavení bankovního účtu', $payments);
    }

    public function testGuestCheckoutConfirmationIsSubmittedAndVerifiedByTheServer(): void
    {
        $root = dirname(__DIR__, 2);
        $page = (string)file_get_contents($root . '/booking/rychly_nakup.php');
        $service = (string)file_get_contents($root . '/includes/shop_guest_checkout.php');
        self::assertStringContainsString('name="confirmed" value="1" required', $page);
        self::assertStringContainsString("(\$customer['confirmed'] ?? '') !== '1'", $service);
        self::assertStringContainsString('Před objednáním potvrďte', $service);
    }

    public function testProgramProductUsesHumanPaymentPeriodInsteadOfInternalSku(): void
    {
        $page = (string) file_get_contents(dirname(__DIR__, 2) . '/booking/produkt.php');
        self::assertStringContainsString('function shopProductProgramPeriodLabel', $page);
        self::assertStringContainsString("\$offer ? shopProductProgramPeriodLabel((string)\$offer['purchase_option']) : shopProductVariantLabel(\$variant)", $page);
    }
}
