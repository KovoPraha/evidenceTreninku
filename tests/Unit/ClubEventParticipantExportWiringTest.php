<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ClubEventParticipantExportWiringTest extends TestCase
{
    public function testEndpointIsAdminPostCsrfProtectedAndNonCacheable(): void
    {
        $root = dirname(__DIR__, 2);
        $endpoint = (string)file_get_contents($root . '/club_event_participants_export.php');
        $admin = (string)file_get_contents($root . '/eshop_events_admin.php');

        self::assertStringContainsString("roleAtLeast('admin')", $endpoint);
        self::assertStringContainsString("REQUEST_METHOD'] !== 'POST'", $endpoint);
        self::assertStringContainsString('csrf_verify', $endpoint);
        self::assertStringContainsString("Cache-Control: no-store", $endpoint);
        self::assertStringContainsString('X-Content-Type-Options: nosniff', $endpoint);
        self::assertStringContainsString('action="club_event_participants_export.php"', $admin);
        self::assertStringContainsString('csrf_field()', $admin);
    }

    public function testExportContractDoesNotContainPasswordsOrFullConsentText(): void
    {
        $service = (string)file_get_contents(
            dirname(__DIR__, 2) . '/includes/club_event_export.php'
        );

        self::assertStringContainsString('m2.event-participants.v1', $service);
        self::assertStringNotContainsString('heslo', $service);
        self::assertStringNotContainsString('consent_text_snapshot', $service);
        self::assertStringContainsString('clubEventExportCsvCell', $service);
        self::assertStringContainsString('export_participants', $service);
    }
}
