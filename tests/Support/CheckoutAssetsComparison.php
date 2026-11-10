<?php

declare(strict_types=1);

/** Comparison-only: remove exactly the reviewed two-line tag, retain all form data and scripts. */
function checkoutAssetsComparisonHtml(string $html): string
{
    $local = '~<link href="/(?!/)[^"]*assets/vendor/bootstrap/bootstrap\.min\.css\?v=[a-f0-9]{64}" rel="stylesheet">~';
    $tag = '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"'
        . ' integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"'
        . "\ncrossorigin=\"anonymous\">";
    if (preg_match_all($local, $html) !== 1 || substr_count($html, $tag) !== 1) {
        return $html;
    }
    return str_replace($tag, '', $html);
}
