<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/app_url.php';

final class AppUrlTest extends TestCase
{
    private string|false $previous;
    private mixed $previousHost;

    protected function setUp(): void
    {
        $this->previous = getenv('APP_BASE_URL');
        $this->previousHost = $_SERVER['HTTP_HOST'] ?? null;
    }

    protected function tearDown(): void
    {
        $this->previous === false
            ? putenv('APP_BASE_URL')
            : putenv('APP_BASE_URL=' . $this->previous);
        if ($this->previousHost === null) {
            unset($_SERVER['HTTP_HOST']);
        } else {
            $_SERVER['HTTP_HOST'] = $this->previousHost;
        }
    }

    public function testCanonicalUrlIgnoresHostHeader(): void
    {
        putenv('APP_BASE_URL=https://data.kovopraha.cz/evidence/');
        $_SERVER['HTTP_HOST'] = 'attacker.example';

        self::assertSame(
            'https://data.kovopraha.cz/evidence/booking/overeni.php',
            \appUrl('/booking/overeni.php')
        );
    }

    public function testRejectsConfiguredUrlWithInjectedQuery(): void
    {
        putenv('APP_BASE_URL=https://data.kovopraha.cz/evidence?next=https://attacker.example');
        $this->expectException(\RuntimeException::class);
        \appCanonicalBaseUrl();
    }
}
