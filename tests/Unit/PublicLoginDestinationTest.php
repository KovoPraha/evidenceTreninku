<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/public_login_destination.php';

final class PublicLoginDestinationTest extends TestCase
{
    public function testDefaultDestinationDependsOnAccountRole(): void
    {
        self::assertSame('sportovni_prehled.php', \publicLoginDestination(null, false));
        self::assertSame('../pracovni_pozice.php', \publicLoginDestination(null, true));
    }

    public function testValidCustomerReturnLinkTakesPriorityForEitherRole(): void
    {
        $requested = 'krouzky.php?akce=2#moje-prihlasky';
        self::assertSame($requested, \publicLoginDestination($requested, false));
        self::assertSame($requested, \publicLoginDestination($requested, true));
    }

    public function testInvalidReturnLinkFallsBackWithoutOpenRedirect(): void
    {
        foreach (['https://example.com', '../login.php', '//example.com', "eshop.php\r\nLocation: https://example.com"] as $requested) {
            self::assertSame('sportovni_prehled.php', \publicLoginDestination($requested, false));
            self::assertSame('../pracovni_pozice.php', \publicLoginDestination($requested, true));
        }
    }
}
