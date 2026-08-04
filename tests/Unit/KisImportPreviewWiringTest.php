<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class KisImportPreviewWiringTest extends TestCase
{
    public function testSyncCenterExposesReadOnlyIntegrityReport(): void
    {
        $source = (string)file_get_contents(dirname(__DIR__, 2) . '/kis_sync_center.php');
        foreach (['kisImportStoredPreviewReport', 'preview_report=json', 'Integrita náhledu', 'Tento náhled nic nezapisuje'] as $needle) {
            self::assertStringContainsString($needle, $source);
        }
        self::assertStringContainsString('Cache-Control: no-store', $source);
    }

    public function testDemoSeedIsCliLocalhostOnlyExplicitAndIdempotent(): void
    {
        $source = (string)file_get_contents(dirname(__DIR__, 2) . '/bin/seed-kis-m23-preview.php');
        foreach (['PHP_SAPI', "['localhost', '127.0.0.1']", 'JE_LOKALNE', 'confirm-seed', "'status' => 'existing'", 'kisImportStoredPreviewReport'] as $needle) {
            self::assertStringContainsString($needle, $source);
        }
    }
}
