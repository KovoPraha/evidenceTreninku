<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AdminFormAccessibilityWiringTest extends TestCase
{
    public function testReservationAndVenueScreensExposePrimaryHeadingsAndLabels(): void
    {
        $root = dirname(__DIR__, 2) . '/';
        $venue = (string)file_get_contents($root . 'sprava_sportovist.php');
        $reservation = (string)file_get_contents($root . 'rezervovat_sportoviste.php');
        $calendar = (string)file_get_contents($root . 'kalendar_sportovist.php');
        $lessons = (string)file_get_contents($root . 'individualni_lekce_sprava.php');

        self::assertStringContainsString('<h1 class="h4', $venue);
        self::assertStringContainsString('for="sportoviste-kod"', $venue);
        self::assertStringContainsString('aria-label="Smazat sportoviště', $venue);
        self::assertStringContainsString('<h1 class="h4', $reservation);
        self::assertStringContainsString('for="rezervace-datum"', $reservation);
        self::assertStringContainsString('<h1 class="h5', $calendar);
        self::assertStringContainsString('<h1 class="h4', $lessons);
    }
}
