<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ShopIdentityAdminWiringTest extends TestCase
{
    public function testIdentityPageIsAdminOnlyAuditedAndDoesNotWriteToKis(): void
    {
        $root = dirname(__DIR__, 2);
        $source = (string)file_get_contents($root . '/eshop_identity_admin.php');

        self::assertStringContainsString("roleAtLeast('admin')", $source);
        self::assertStringContainsString('csrf_verify', $source);
        self::assertStringContainsString('accountPersonRoleApprove', $source);
        self::assertStringContainsString('accountPersonRoleRevoke', $source);
        self::assertStringContainsString('nikdy nevytvářejí automaticky', $source);
        self::assertStringNotContainsString('kis_push', strtolower($source));
        self::assertStringNotContainsString('UPDATE kis_', $source);
    }

    public function testAdminNavigationLinksToIdentityDecisions(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (['hlavicka.php', 'index.php', 'eshop_admin.php', 'admin_dashboard.php'] as $filename) {
            self::assertStringContainsString(
                'eshop_identity_admin.php',
                (string)file_get_contents($root . '/' . $filename),
                $filename
            );
        }
    }
}
