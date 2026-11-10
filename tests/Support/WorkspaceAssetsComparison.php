<?php

declare(strict_types=1);

/** Only the known unversioned workspace duplicate; keep the canonical versioned tag. */
function workspaceAssetsComparisonHtml(string $html): string
{
    $canonical = '~<link href="/(?!/)[^"]*assets/vendor/bootstrap/bootstrap\.min\.css\?v=(?:[0-9]+|[a-f0-9]{64})" rel="stylesheet">~';
    if (preg_match_all($canonical, $html) !== 1) {
        return $html;
    }
    $duplicate = '~^    <link href="/(?!/)[^"\n]*assets/vendor/bootstrap/bootstrap\.min\.css" rel="stylesheet">\n~m';
    if (preg_match_all($duplicate, $html) !== 1) {
        return $html;
    }
    return (string) preg_replace($duplicate, '', $html, 1);
}
