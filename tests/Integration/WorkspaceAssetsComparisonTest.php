<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/WorkspaceAssetsComparison.php';

final class WorkspaceAssetsComparisonTest extends TestCase
{
    public function testOnlyTheKnownDuplicateMayBeRemoved(): void
    {
        $canonical = '<link href="/club/assets/vendor/bootstrap/bootstrap.min.css?v=123" rel="stylesheet">';
        $duplicate = '    <link href="/club/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">' . "\n";
        $after = '<h1>Workspace</h1>' . "\n" . $canonical;
        $before = $duplicate . $after;
        self::assertSame($after, workspaceAssetsComparisonHtml($before));
        self::assertSame($after, workspaceAssetsComparisonHtml($after));
        self::assertSame($duplicate, workspaceAssetsComparisonHtml($duplicate));
        self::assertSame($duplicate . $before, workspaceAssetsComparisonHtml($duplicate . $before));
        self::assertNotSame($after, workspaceAssetsComparisonHtml(str_replace('Workspace', 'Changed', $before)));
        $unexpected = str_replace('rel="stylesheet">', 'rel="stylesheet" media="print">', $before);
        self::assertSame($unexpected, workspaceAssetsComparisonHtml($unexpected));
        $contentVersion = str_repeat('a', 64);
        $hashedBefore = str_replace('?v=123', '?v=' . $contentVersion, $before);
        self::assertSame(str_replace('?v=123', '?v=' . $contentVersion, $after), workspaceAssetsComparisonHtml($hashedBefore));
        $invalidVersion = str_replace($contentVersion, str_repeat('a', 63), $hashedBefore);
        self::assertSame($invalidVersion, workspaceAssetsComparisonHtml($invalidVersion));
    }
}
