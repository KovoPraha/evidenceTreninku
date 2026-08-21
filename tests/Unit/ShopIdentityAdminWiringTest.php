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

        self::assertStringContainsString("staffActivePositionIs('registrar')", $source);
        self::assertStringContainsString('csrf_verify', $source);
        self::assertStringContainsString('accountPersonRoleApprove', $source);
        self::assertStringContainsString('accountPersonRoleRevoke', $source);
        self::assertStringContainsString('nikdy nevytvářejí automaticky', $source);
        self::assertStringNotContainsString('kis_push', strtolower($source));
        self::assertStringNotContainsString('UPDATE kis_', $source);
    }

    public function testAdminNavigationLinksToIdentityDecisions(): void
    {
        // Pracovni registry je jediny zdroj staff navigace; hlavicka z nej
        // vykresluje pouze prave aktivni pozici.
        $root = dirname(__DIR__, 2);
        foreach (['includes/staff_workspaces.php', 'hlavicka.php'] as $filename) {
            self::assertStringContainsString(
                $filename === 'hlavicka.php' ? "foreach (\$staff_active['groups']" : 'eshop_identity_admin.php',
                (string)file_get_contents($root . '/' . $filename),
                $filename
            );
        }
    }
}
