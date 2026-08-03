<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/localhost_acceptance_hub.php';

final class LocalhostAcceptanceHubTest extends TestCase
{
    public function testLoopbackRequestMustMatchAtEveryBoundary(): void
    {
        $request = [
            'HTTP_HOST' => 'localhost:8080',
            'SERVER_ADDR' => '127.0.0.1',
            'REMOTE_ADDR' => '::1',
        ];

        self::assertTrue(\localhostAcceptanceRequestIsAllowed($request, 'localhost'));
        self::assertTrue(\localhostAcceptanceRequestIsAllowed($request, ''));
        self::assertFalse(\localhostAcceptanceRequestIsAllowed($request, 'data.kovopraha.cz'));
        self::assertFalse(\localhostAcceptanceRequestIsAllowed(array_replace($request, ['REMOTE_ADDR' => '192.0.2.10']), 'localhost'));
        self::assertFalse(\localhostAcceptanceRequestIsAllowed(array_diff_key($request, ['SERVER_ADDR' => true]), 'localhost'));
    }

    public function testHostNormalizationIsExactAndRejectsHeaderTricks(): void
    {
        self::assertTrue(\localhostAcceptanceIsLoopbackHost('[::1]:8080'));
        self::assertTrue(\localhostAcceptanceIsLoopbackHost('127.0.0.1:80'));
        self::assertFalse(\localhostAcceptanceIsLoopbackHost('localhost.example.test'));
        self::assertFalse(\localhostAcceptanceIsLoopbackHost('localhost, data.kovopraha.cz'));
        self::assertFalse(\localhostAcceptanceIsLoopbackHost('localhost/path'));
        self::assertFalse(\localhostAcceptanceIsLoopbackHost('user@localhost'));
    }

    public function testCatalogueContainsA01ThroughA10AndTruthfulPartialStates(): void
    {
        $scenarios = \localhostAcceptanceScenarios(dirname(__DIR__, 2));

        self::assertSame(['A01', 'A02', 'A03', 'A04', 'A05', 'A06', 'A07', 'A08', 'A09', 'A10'], array_column($scenarios, 'id'));
        self::assertSame('partial', $scenarios[1]['status']);
        self::assertSame('partial', $scenarios[4]['status']);
        self::assertSame('partial', $scenarios[8]['status']);
        self::assertSame('partial', $scenarios[9]['status']);
        foreach ($scenarios as $scenario) {
            self::assertNotSame('', $scenario['expected']);
            self::assertNotSame([], $scenario['steps']);
            self::assertNotSame([], $scenario['links']);
        }
    }

    public function testMissingRouteMakesScenarioUnavailable(): void
    {
        $temporaryRoot = sys_get_temp_dir() . '/acceptance-hub-' . bin2hex(random_bytes(6));
        mkdir($temporaryRoot);
        try {
            $scenarios = \localhostAcceptanceScenarios($temporaryRoot);
            self::assertSame(array_fill(0, 10, 'unavailable'), array_column($scenarios, 'status'));
        } finally {
            rmdir($temporaryRoot);
        }
    }

    public function testResetIsUnavailableWithoutSeedOrLocalConfiguration(): void
    {
        $temporaryRoot = sys_get_temp_dir() . '/acceptance-reset-' . bin2hex(random_bytes(6));
        mkdir($temporaryRoot);
        try {
            $availability = \localhostAcceptanceSeedResetAvailability($temporaryRoot);
            self::assertFalse($availability['available']);
            self::assertNotSame('', $availability['reason']);
            $result = \localhostAcceptanceRunSeedReset($temporaryRoot, 5);
            self::assertFalse($result['ok']);
        } finally {
            rmdir($temporaryRoot);
        }
    }

    public function testResetUsesLocalEnvironmentAndNeverReturnsChildOutput(): void
    {
        if (!function_exists('proc_open') || !is_file(PHP_BINARY)) {
            self::markTestSkipped('proc_open or PHP CLI is unavailable.');
        }
        $temporaryRoot = sys_get_temp_dir() . '/acceptance-reset-run-' . bin2hex(random_bytes(6));
        mkdir($temporaryRoot);
        mkdir($temporaryRoot . '/bin');
        file_put_contents($temporaryRoot . '/config.php', "<?php\n");
        file_put_contents(
            $temporaryRoot . '/bin/seed-local-demo.php',
            "<?php\nfwrite(STDOUT, 'SENSITIVE-STDOUT');\nfwrite(STDERR, 'SENSITIVE-STDERR');\nexit(getenv('APP_HOST') === 'localhost' ? 0 : 9);\n"
        );
        try {
            $result = \localhostAcceptanceRunSeedReset($temporaryRoot, 5);
            self::assertTrue($result['ok']);
            self::assertStringNotContainsString('SENSITIVE', $result['reason']);
        } finally {
            unlink($temporaryRoot . '/bin/seed-local-demo.php');
            unlink($temporaryRoot . '/config.php');
            rmdir($temporaryRoot . '/bin');
            rmdir($temporaryRoot);
        }
    }
}
