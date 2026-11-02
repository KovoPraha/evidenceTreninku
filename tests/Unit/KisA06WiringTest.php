<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class KisA06WiringTest extends TestCase
{
    public function testA06IsLocalhostAdminOnlyAndLinkedFromAcceptanceHub(): void
    {
        $root = dirname(__DIR__, 2);
        $page = (string)file_get_contents($root . '/kis_rollover_a06_admin.php');
        $hub = (string)file_get_contents($root . '/includes/localhost_acceptance_hub.php');
        self::assertStringContainsString("staffRequireActivePosition('registrar')", $page);
        self::assertStringContainsString('http_response_code(410)', $page);
        self::assertStringNotContainsString('confirm_a06', $page);
        self::assertStringContainsString('season_preparation_admin.php', $hub);
    }
}
