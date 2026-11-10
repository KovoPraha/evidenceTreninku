<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/BootstrapAssetComparison.php';

final class BootstrapAssetComparisonTest extends TestCase
{
    public function testOnlyTheExactDuplicateWithOneLocalCopyCanBeRemoved(): void
    {
        $local = '<link href="/assets/vendor/bootstrap/bootstrap.min.css?v=123" rel="stylesheet">';
        $cdn = '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"' . "\n"
            . ' integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">' . "\n";
        $html = $cdn . $local . '<main>Original values: 420,00 Kč</main>';
        self::assertSame($local . '<main>Original values: 420,00 Kč</main>', \bootstrapAssetComparisonHtml($html));
        $contentVersion = str_repeat('a', 64);
        self::assertSame(
            str_replace('v=123', 'v=' . $contentVersion, $local) . '<main>Original values: 420,00 Kč</main>',
            \bootstrapAssetComparisonHtml(str_replace('v=123', 'v=' . $contentVersion, $html))
        );
        $malformed = str_replace('v=123', 'v=' . substr($contentVersion, 1), $html);
        self::assertSame($malformed, \bootstrapAssetComparisonHtml($malformed));
        foreach ([$cdn, $cdn . $local . $local, str_replace('5.3.3', '5.3.4', $html),
            str_replace('sha384-QWT', 'sha384-BAD', $html), str_replace('href="/assets/', 'href="https://other.invalid/assets/', $html)] as $negative) {
            self::assertSame($negative, \bootstrapAssetComparisonHtml($negative));
        }
    }
}
