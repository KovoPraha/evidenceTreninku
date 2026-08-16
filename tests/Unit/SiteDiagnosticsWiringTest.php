<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/funkce.php';

final class SiteDiagnosticsWiringTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $originalSession = [];

    protected function setUp(): void
    {
        $this->originalSession = $_SESSION ?? [];
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->originalSession;
    }

    public function testPageIsHardCodedAdminOnlyForEveryOtherIdentity(): void
    {
        $source = $this->source();
        self::assertStringContainsString("roleAtLeast('admin')", $source);
        self::assertStringNotContainsString('canAccess(', $source);

        foreach ([
            [],
            ['verejny_uzivatel_id' => 10],
            ['trener_id' => 10, 'role' => 'trener'],
            ['trener_id' => 10, 'role' => 'hlavni'],
        ] as $session) {
            $_SESSION = $session;
            self::assertFalse(isset($_SESSION['trener_id']) && \roleAtLeast('admin'));
        }

        $_SESSION = ['trener_id' => 10, 'role' => 'admin'];
        self::assertTrue(isset($_SESSION['trener_id']) && \roleAtLeast('admin'));
    }

    public function testPageIsReadOnlyNoStoreAndShowsRequiredRequestFields(): void
    {
        $source = $this->source();
        foreach ([
            'Cache-Control: no-store',
            'REMOTE_ADDR',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CF_CONNECTING_IP',
            'HTTP_FORWARDED',
            'auth_rate_limit_request_ip()',
            'auth_rate_limit_ip_is_private',
        ] as $needle) {
            self::assertStringContainsString($needle, $source);
        }

        foreach (['<form', '$_POST', 'error_log(', 'INSERT ', 'UPDATE ', 'DELETE '] as $needle) {
            self::assertStringNotContainsString($needle, $source);
        }
    }

    public function testExampleConfigurationKeepsProxyTrustEmptyByDefault(): void
    {
        $config = file_get_contents(dirname(__DIR__, 2) . '/config.example.php');
        self::assertIsString($config);
        self::assertStringContainsString("define('AUTH_TRUSTED_PROXIES', []);", $config);
        self::assertStringContainsString("define('AUTH_RATE_LIMIT_ACCOUNT_MAX_ATTEMPTS', 5);", $config);
        self::assertStringContainsString("define('AUTH_RATE_LIMIT_IP_MAX_ATTEMPTS', 40);", $config);
    }

    private function source(): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/diagnostika_site_admin.php');
        self::assertIsString($source);
        return $source;
    }
}
