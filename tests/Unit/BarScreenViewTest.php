<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/bar/menu_view.php';

final class BarScreenViewTest extends TestCase
{
    private function menu(string $layout): array
    {
        return ['offer' => ['title' => 'LOCALHOST <menu>', 'layout' => $layout], 'products' => [
            ['id' => 1, 'name' => 'LOCALHOST </script> Tea', 'description' => 'Safe <b>text</b>',
                'category' => 'Drinks', 'color_index' => 2, 'price_minor' => 3500, 'sold_out' => 0, 'image_id' => 7],
        ]];
    }

    public function testTextUsesOriginalColumnsPricesAndEscaping(): void
    {
        $html = \barMenuHtml($this->menu('text'));
        self::assertStringContainsString('class="sloupce"', $html);
        self::assertStringContainsString('kategorie k2', $html);
        self::assertStringContainsString('35 Kč', $html);
        self::assertStringContainsString('&lt;/script&gt;', $html);
        self::assertStringContainsString('bar-screen.js', $html);
        self::assertStringNotContainsString('class="items"', $html);
    }

    public function testPhotoPayloadCannotBreakScriptAndUsesProtectedPublicImages(): void
    {
        $html = \barMenuHtml($this->menu('photos'));
        self::assertStringContainsString('class="plocha"', $html);
        self::assertStringContainsString('bar-photos.js', $html);
        self::assertStringContainsString('menu-image.php?id=7', $html);
        self::assertStringNotContainsString('LOCALHOST </script>', $html);
        self::assertStringContainsString('\\u003C', $html);
        self::assertSame('35,25 Kč', \barDisplayPrice(3525));
    }

    public function testExplicitViewDoesNotMutateOfferOrPrintOutput(): void
    {
        $menu = $this->menu('photos');
        self::assertStringContainsString('class="sloupce"', \barMenuHtml($menu, false, 'text'));
        self::assertStringContainsString('class="items"', \barMenuHtml($menu, true));
        self::assertSame('photos', $menu['offer']['layout']);
    }
}
