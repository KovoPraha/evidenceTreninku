<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SumUpWiringTest extends TestCase
{
    public function testCheckoutAndCallbackRemainBehindFailClosedFlag(): void
    {
        $config = $this->source('config.example.php');
        $order = $this->source('booking/objednavka.php');
        $callback = $this->source('booking/sumup_webhook.php');
        self::assertStringContainsString("define('SUMUP_ENABLED', is_string(\$sumupEnabled) && \$sumupEnabled === '1')", $config);
        self::assertStringContainsString('sumupIsEnabled()', $order);
        self::assertStringContainsString('Zaplatit online přes SumUp', $order);
        self::assertStringContainsString("REQUEST_METHOD'] ?? 'GET') !== 'POST'", $callback);
        self::assertStringContainsString("header('Cache-Control: no-store", $callback);
        self::assertStringContainsString('sumupHandleWebhook', $callback);
        self::assertStringNotContainsString('echo (string)SUMUP_API_KEY', $callback, 'Endpoint nesmí vypsat tajný klíč do odpovědi.');
    }

    private function source(string $relative): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $relative);
        self::assertIsString($source);
        return $source;
    }
}
