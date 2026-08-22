<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class FamilyWeeklyDeliveryWiringTest extends TestCase
{
    public function testAccountOptInAndOneStepOptOutAreCsrfProtected(): void
    {
        $page = $this->source('booking/sportovni_prehled.php');
        self::assertStringContainsString('family_weekly_delivery_save', $page);
        self::assertStringContainsString('familyWeeklyDeliverySavePreference', $page);
        self::assertStringContainsString('csrf_verify', $page);
        self::assertStringContainsString('Vypnout týdenní souhrn', $page);
    }

    public function testAdminUsesLocalPreviewAndCliHasExplicitProductionTransport(): void
    {
        $admin = $this->source('family_weekly_summaries_admin.php');
        $cli = $this->source('bin/family-weekly-summaries.php');
        $header = $this->source('hlavicka.php');
        self::assertStringContainsString("roleAtLeast('admin')", $admin);
        self::assertStringContainsString('csrf_verify', $admin);
        self::assertStringContainsString("defined('JE_LOKALNE')", $admin);
        self::assertStringContainsString('familyWeeklyDeliveryLocalOutboxSender', $admin);
        self::assertStringContainsString('--send-local', $cli);
        self::assertStringContainsString("in_array('--send', \$arguments, true)", $cli);
        self::assertStringContainsString('familyWeeklyDeliveryMailSender', $cli);
        self::assertStringNotContainsString('mail(', $cli);
        self::assertStringContainsString('family_weekly_summaries_admin.php', $header);
    }

    public function testMailSenderValidatesHeadersBeforeUsingProductionMail(): void
    {
        $delivery = $this->source('includes/family_weekly_delivery.php');
        self::assertStringContainsString('function familyWeeklyDeliveryMailSender', $delivery);
        self::assertStringContainsString("preg_match('/[\\r\\n]/'", $delivery);
        self::assertStringContainsString('base64_encode($subject)', $delivery);
    }

    public function testMessageExplainsUnsubscribeAndContainsNoIdentityToken(): void
    {
        $summary = $this->source('includes/family_weekly_summary.php');
        self::assertStringContainsString("appUrl('booking/sportovni_prehled.php')", $summary);
        self::assertStringContainsString('jedním krokem vypnout', $summary);
        self::assertStringNotContainsString('sportovec_id', $summary);
        self::assertStringNotContainsString('token=', $summary);
    }

    private function source(string $relative): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $relative);
        self::assertIsString($source);
        return $source;
    }
}
