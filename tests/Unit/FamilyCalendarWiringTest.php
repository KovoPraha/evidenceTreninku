<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class FamilyCalendarWiringTest extends TestCase
{
    public function testAccountPageUsesCsrfProtectedIssueAndRevokeActions(): void
    {
        $source = $this->source('booking/sportovni_prehled.php');
        self::assertStringContainsString("csrf_verify((string)(\$_POST['csrf_token']", $source);
        self::assertStringContainsString('family_calendar_issue', $source);
        self::assertStringContainsString('family_calendar_revoke', $source);
        self::assertStringContainsString('family_calendar_token_once', $source);
        self::assertStringContainsString('familyCalendarAgenda', $source);
        self::assertStringContainsString('Co nás čeká v příštích 30 dnech', $source);
        self::assertStringContainsString('familyPageItemCount(count($familyAgenda))', $source);
        self::assertStringContainsString('familyWeeklySummaryPreview', $source);
        self::assertStringContainsString('Produkční e-mailový transport zatím není aktivní', $source);
        self::assertStringNotContainsString('familyWeeklySummarySend', $source);
        self::assertStringContainsString('familyWeeklySummaryStartDate', $source);
        self::assertStringContainsString('Další týden →', $source);
        self::assertStringNotContainsString("\$_GET['sportovec_id']", $source);
        self::assertStringContainsString("unset(\$_SESSION['family_calendar_message'], \$_SESSION['family_calendar_token_once'])", $source);
    }

    public function testPrivateEndpointIsNonCacheableAndDoesNotAcceptPersonSelection(): void
    {
        $source = $this->source('booking/rodinny_kalendar.php');
        self::assertStringContainsString("Cache-Control: private, no-store", $source);
        self::assertStringContainsString('Referrer-Policy: no-referrer', $source);
        self::assertStringContainsString('X-Robots-Tag: noindex', $source);
        self::assertStringNotContainsString("\$_GET['sportovec_id']", $source);
        self::assertStringContainsString('familyCalendarFeedResolveAccount', $source);
    }

    public function testServiceStoresHashAndChecksLiveRelations(): void
    {
        $source = $this->source('includes/family_calendar_feed.php');
        self::assertStringContainsString("hash('sha256', \$token)", $source);
        self::assertStringContainsString('familyPortalAuthorizedPeople($pdo, $accountId)', $source);
        self::assertStringContainsString("appUrl('booking/rodinny_kalendar.php')", $source);
        self::assertStringNotContainsString('token_plain', $source);
    }

    private function source(string $relative): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $relative);
        self::assertIsString($source);
        return $source;
    }
}
