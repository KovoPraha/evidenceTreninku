<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/PublicCalendarAssetsComparison.php';

final class PublicCalendarAssetsComparisonTest extends TestCase
{
    public function testExactInlineOrStandaloneDuplicateOnly(): void
    {
        $tag = '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"'
            . ' integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">';
        $local = '<link href="/assets/vendor/bootstrap/bootstrap.min.css?v=' . str_repeat('a', 64) . '" rel="stylesheet">';
        $data = '<main>10:00, 420 Kč, 3 volno</main><script>const count=3;</script>';
        foreach ([$tag . $local . $data, '    ' . $tag . "\n" . $local . $data] as $html) {
            self::assertSame($local . $data, \publicCalendarAssetsComparisonHtml($html));
        }
        foreach ([$tag . $tag . $local, $tag, str_replace('sha384-QWT', 'sha384-BAD', $tag) . $local,
            str_replace('5.3.3', '5.3.4', $tag) . $local, $tag . $local . $local,
            $tag . str_replace(str_repeat('a', 64), str_repeat('a', 63), $local)] as $negative) {
            self::assertSame($negative, \publicCalendarAssetsComparisonHtml($negative));
        }
        foreach ([['420', '421'], ['3 volno', '2 volno'], ['10:00', '11:00'], ['const count=3', 'const count=4']] as [$from, $to]) {
            self::assertNotSame($local . $data, \publicCalendarAssetsComparisonHtml($tag . $local . str_replace($from, $to, $data)));
        }
    }
}
