<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class KisImportSandboxPromotionWiringTest extends TestCase
{
    public function testSyncCenterGuardsSandboxMutationAndExplainsIsolation(): void
    {
        $page = (string)file_get_contents(dirname(__DIR__, 2) . '/kis_sync_center.php');
        foreach (["roleAtLeast('admin')", 'JE_LOKALNE', "require_once __DIR__ . '/csrf_helper.php'", 'csrf_verify', 'confirm_action', 'preview_fingerprint', 'kisImportSandboxPromote', 'kisImportSandboxRollback', 'Tabulky sportovců'] as $needle) {
            self::assertStringContainsString($needle, $page);
        }
    }

    public function testServiceHasFingerprintTransactionAuditAndNoCanonicalWrites(): void
    {
        $service = (string)file_get_contents(dirname(__DIR__, 2) . '/includes/kis_import_sandbox_promotion.php');
        foreach (['hash_equals', 'beginTransaction', 'rollBack', 'SAVEPOINT', 'kis_import_sandbox_events', 'kisImportBuildPreviewReport'] as $needle) {
            self::assertStringContainsString($needle, $service);
        }
        self::assertStringNotContainsString('UPDATE sportovci', $service);
        self::assertStringNotContainsString('INSERT INTO sportovci', $service);
        self::assertStringNotContainsString('club_roster_members', $service);
    }
}
