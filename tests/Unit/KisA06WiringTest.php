<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class KisA06WiringTest extends TestCase
{
    public function testA06IsLocalhostAdminOnlyAndLinkedFromAcceptanceHub(): void
    {
        $root = dirname(__DIR__,2);
        $page = (string)file_get_contents($root . '/kis_rollover_a06_admin.php');
        $hub = (string)file_get_contents($root . '/includes/localhost_acceptance_hub.php');
        self::assertStringContainsString('localhostAcceptanceRequestIsAllowed', $page);
        self::assertStringContainsString("roleAtLeast('admin')", $page);
        self::assertStringContainsString('csrf_verify', $page);
        self::assertStringContainsString('confirm_a06', $page);
        self::assertStringContainsString('kis_rollover_a06_admin.php', $hub);
    }
}
