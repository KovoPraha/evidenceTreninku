<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class LocalhostAcceptanceHubWiringTest extends TestCase
{
    public function testPageFailsClosedBeforeBootstrapAndDoesNotExposeDemoPasswords(): void
    {
        $root = dirname(__DIR__, 2);
        $page = (string)file_get_contents($root . '/testovaci_scenare.php');
        $helper = (string)file_get_contents($root . '/includes/localhost_acceptance_hub.php');

        $guardPosition = strpos($page, 'localhostAcceptanceRequestIsAllowed');
        $bootstrapPosition = strpos($page, "includes/init.php");
        self::assertNotFalse($guardPosition);
        self::assertNotFalse($bootstrapPosition);
        self::assertLessThan($bootstrapPosition, $guardPosition);
        self::assertStringContainsString('http_response_code(404)', $page);
        self::assertStringContainsString("roleAtLeast('admin')", $page);
        self::assertStringContainsString('csrf_verify', $page);
        self::assertStringContainsString('reset_local_demo', $page);
        self::assertStringContainsString('localhostAcceptanceRunSeedReset', $page);
        self::assertStringContainsString('localhostAcceptanceScenarios', $page);
        self::assertStringContainsString("['HTTP_HOST', 'SERVER_ADDR', 'REMOTE_ADDR']", $helper);
        self::assertStringContainsString("[\$cliBinary, \$root . '/bin/seed-local-demo.php']", $helper);
        self::assertStringContainsString('PHP_BINDIR', $helper);
        self::assertStringContainsString("['bypass_shell' => true]", $helper);
        self::assertStringContainsString("\$environment['APP_HOST'] = 'localhost'", $helper);
        self::assertStringContainsString('stream_get_contents($pipes[1])', $helper);
        self::assertStringNotContainsString('Localhost123!', $page . $helper);
        self::assertStringNotContainsString('LocalhostAdmin123!', $page . $helper);
    }
}
