<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PasswordResetWiringTest extends TestCase
{
    public function testResetRoutesUseCsrfRateLimitAndFragmentToken(): void
    {
        $root = dirname(__DIR__, 2);
        $request = (string)file_get_contents($root . '/booking/zapomenute_heslo.php');
        $consume = (string)file_get_contents($root . '/booking/nove_heslo.php');

        self::assertStringContainsString('csrf_verify', $request);
        self::assertStringContainsString('auth_rate_limit_reserve_attempt', $request);
        self::assertStringContainsString("nove_heslo.php#token=", $request);
        self::assertStringContainsString('Pokud účet existuje', $request);
        self::assertStringContainsString('csrf_verify', $consume);
        self::assertStringContainsString('window.location.hash.slice(1)', $consume);
        self::assertStringContainsString('history.replaceState', $consume);
        self::assertStringNotContainsString("nove_heslo.php?token=", $request);
    }
}
