<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/CatalogParityComparison.php';

final class CatalogParityComparisonTest extends TestCase
{
    public function testOnlyExplicitRequestAndReleaseValuesAreNormalized(): void
    {
        $html = '<script nonce="abc">const amount=420;</script><link href="/assets/app-ui.css?v=123">'
            . '<input name="csrf_token" value="' . str_repeat('a', 64) . '"><div data-app-csp-handler="' . "\n    abc\">";
        $new = str_replace(['abc', 'v=123', str_repeat('a', 64), "\n    def"], ['def', 'v=' . str_repeat('b', 64), str_repeat('c', 64), 'def'], $html);
        self::assertSame(
            \catalogParityHtml($html, ['content-security-policy' => "script-src 'nonce-abc'"]),
            \catalogParityHtml($new, ['content-security-policy' => "script-src 'nonce-def'"])
        );
    }

    public function testChangesOfPriceNameAvailabilityImageOrderOrScriptMustFailParity(): void
    {
        $html = '<main><div><img class="app-product-image" src="/one.jpg"><h3>Dres</h3>420 Kč Skladem</div>'
            . '<div><img class="app-product-image" src="/two.jpg"><h3>Čepice</h3>100 Kč</div></main><script>const count=2;</script>';
        $expected = \catalogParitySections(\catalogParityHtml($html, []));
        foreach ([['420', '421'], ['Dres', 'Bunda'], ['Skladem', 'Vyprodáno'], ['one.jpg', 'other.jpg'],
            ['const count=2', 'const count=3'], ['Dres', 'Čepice']] as [$from, $to]) {
            self::assertNotSame($expected, \catalogParitySections(\catalogParityHtml(str_replace($from, $to, $html), [])));
        }
        $reversed = '<main><div><img class="app-product-image" src="/two.jpg"><h3>Čepice</h3>100 Kč</div>'
            . '<div><img class="app-product-image" src="/one.jpg"><h3>Dres</h3>420 Kč Skladem</div></main><script>const count=2;</script>';
        self::assertNotSame($expected, \catalogParitySections(\catalogParityHtml($reversed, [])));
        self::assertCount(2, $expected['cards']);
    }
}
