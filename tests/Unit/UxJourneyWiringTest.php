<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class UxJourneyWiringTest extends TestCase
{
    public function testPublicCalendarsUseSharedGridAndOfferListFallback(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (['treninky.php', 'klubovy_kalendar.php'] as $file) {
            $source = (string)file_get_contents($root . '/booking/' . $file);
            self::assertStringContainsString("includes/calendar_ui.php", $source, $file);
            self::assertStringContainsString('calendarUiRender(', $source, $file);
        }
        $events = (string)file_get_contents($root . '/booking/klubovy_kalendar.php');
        self::assertStringContainsString('K přihlášení', $events);
        self::assertStringContainsString('#akce-', $events);
        $lessons = (string)file_get_contents($root . '/booking/kalendar.php');
        self::assertStringContainsString('Přihlásit se a rezervovat', $lessons);
        self::assertStringNotContainsString('<a href="prihlaseni.php" class="btn btn-outline-primary btn-sm">Přihlásit</a>', $lessons);
        $velodrome = (string)file_get_contents($root . '/booking/velodrom.php');
        self::assertStringContainsString("uiFormatDate((string)\$slot['datum'])", $velodrome);
        self::assertStringContainsString("'velodrom.php#slot-'", $velodrome);
    }

    public function testShopAndDashboardsExposePrioritiesInsteadOfDeadEnds(): void
    {
        $root = dirname(__DIR__, 2);
        $shop = (string)file_get_contents($root . '/booking/eshop.php');
        self::assertStringContainsString('Co hledáte?', $shop);
        self::assertStringContainsString('$perPage=12', $shop);
        self::assertStringContainsString('Nakoupit můžete i bez účtu.', $shop);
        $product = (string)file_get_contents($root . '/booking/produkt.php');
        self::assertStringContainsString('app-product-media-sticky', $product);
        self::assertStringContainsString('shopProductInterestVariantLabel', $product);
        $family = (string)file_get_contents($root . '/booking/sportovni_prehled.php');
        self::assertStringContainsString('Čeká na odpověď', $family);
        self::assertStringContainsString('app-anchor-nav', $family);
        $staff = (string)file_get_contents($root . '/pracovni_pozice.php');
        self::assertStringContainsString('Dnes a vyžaduje pozornost', $staff);
        self::assertStringContainsString('Proběhlé tréninky bez evidence', $staff);
    }
}
