<?php

declare(strict_types=1);

/** Comparison-only allowance for removing the one known duplicate stylesheet. */
function bootstrapAssetComparisonHtml(string $html): string
{
    $local = '~<link href="/(?!/)[^"]*assets/vendor/bootstrap/bootstrap\.min\.css\?v=(?:[0-9]+|[a-f0-9]{64})" rel="stylesheet">~';
    if (preg_match_all($local, $html) !== 1) {
        return $html;
    }
    $cdn = '~^[ \t]*<link href="https://cdn\.jsdelivr\.net/npm/bootstrap@5\.3\.3/dist/css/bootstrap\.min\.css" rel="stylesheet"\s+'
        . 'integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW\+ALEwIH"\s+'
        . 'crossorigin="anonymous">[ \t]*(?:\r?\n)?~m';
    if (preg_match_all($cdn, $html) !== 1) {
        return $html;
    }
    return (string) preg_replace($cdn, '', $html, 1);
}
