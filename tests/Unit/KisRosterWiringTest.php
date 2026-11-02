<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class KisRosterWiringTest extends TestCase
{
    public function testRosterAdminIsProtectedAndLinked(): void
    {
        $root = dirname(__DIR__, 2);
        $admin = (string)file_get_contents($root.'/kis_rosters_admin.php');
        self::assertStringContainsString("staffRequireActivePosition('registrar')", $admin);
        self::assertStringContainsString('http_response_code(410)', $admin);
        self::assertStringContainsString('kis_roster_hierarchy_admin.php', $admin);
        self::assertStringNotContainsString('create_series', $admin);
        self::assertStringNotContainsString('preview_season_id', $admin);
        $new = (string)file_get_contents($root.'/kis_roster_hierarchy_admin.php');
        self::assertStringContainsString('csrf_verify', $new);
        self::assertStringContainsString('memberRosterMutate', $new);
    }

    private function previewFunction(string$service): string
    {
        $start = strpos($service, 'function kisRosterPreviewRollover');
        $end = strpos($service, 'function kisRosterEvent', $start);
        self::assertNotFalse($start);
        self::assertNotFalse($end);
        return substr($service, (int)$start, (int)$end - (int)$start);
    }
}
