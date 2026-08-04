<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PublicCalendarWiringTest extends TestCase
{
    public function testPublicPagesLinkTheIcsFeedAndEndpointSetsSafeHeaders(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (['booking/treninky.php', 'booking/krouzky.php', 'booking/velodrom.php'] as $file) {
            self::assertStringContainsString('href="verejny_kalendar.php"', (string)file_get_contents($root . '/' . $file));
        }
        $endpoint = (string)file_get_contents($root . '/booking/verejny_kalendar.php');
        self::assertStringContainsString("header('Content-Type: text/calendar; charset=UTF-8')", $endpoint);
        self::assertStringContainsString("header('X-Content-Type-Options: nosniff')", $endpoint);
        self::assertStringNotContainsString('app_session_start', $endpoint);
    }
}
