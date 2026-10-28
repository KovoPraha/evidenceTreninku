<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class LoggedInUxWiringTest extends TestCase
{
    public function testEmptyAccountShowsNextStepAndRegistrationRespectsTermsGate(): void
    {
        $root = dirname(__DIR__, 2);
        $overview = (string)file_get_contents($root . '/booking/sportovni_prehled.php');
        $people = (string)file_get_contents($root . '/booking/moje_osoby.php');

        self::assertStringContainsString('Začněte propojením sportovce', $overview);
        self::assertStringContainsString('moje_osoby.php#propojeni', $overview);
        self::assertStringContainsString('if ($trainingRsvps !== [])', $overview);
        self::assertStringContainsString('athleteRegistrationTermsReady(athleteRegistrationCurrentTerms($pdo))', $people);
        self::assertStringContainsString('id="propojeni"', $people);
        self::assertStringContainsString('Registrace nového sportovce je dočasně pozastavena', $people);
    }

    public function testCartActionReturnsToVisibleBasketAndNavigationFitsBreakpoint(): void
    {
        $root = dirname(__DIR__, 2);
        $product = (string)file_get_contents($root . '/booking/produkt.php');
        $shop = (string)file_get_contents($root . '/booking/eshop.php');
        $shell = (string)file_get_contents($root . '/includes/ui_shell.php');

        self::assertStringContainsString("header('Location: eshop.php#kosik'", $product);
        self::assertStringContainsString("header('Location: eshop.php#kosik'", $shop);
        self::assertMatchesRegularExpression('/\$cartLineCount\s*>\s*0\s*\?\s*\'order-first order-lg-last\'/', $shop);
        self::assertMatchesRegularExpression('/\$cartLineCount\s*===\s*1\s*\?\s*\'je\'\s*:\s*\'jsou\'/', $shop);
        self::assertStringContainsString('navbar-expand-xxl', $shell);
    }
}
