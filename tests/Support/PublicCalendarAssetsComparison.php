<?php

declare(strict_types=1);

/** Only the exact reviewed duplicate; all booking data, scripts and other assets remain. */
function publicCalendarAssetsComparisonHtml(string $html): string
{
    $local = '~<link href="/(?!/)[^"]*assets/vendor/bootstrap/bootstrap\.min\.css\?v=(?:[0-9]+|[a-f0-9]{64})" rel="stylesheet">~';
    if (preg_match_all($local, $html) !== 1) {
        return $html;
    }
    $cdn = '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"'
        . ' integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">';
    if (substr_count($html, $cdn) !== 1) {
        return $html;
    }
    // PHP's previous closing tag consumes its newline, so this source-owned
    // four-space line can occur after inline SEO markup in the HTTP response.
    foreach (["\r\n", "\n"] as $newline) {
        $sourceLine = '    ' . $cdn . $newline;
        if (str_contains($html, $sourceLine)) {
            return str_replace($sourceLine, '', $html);
        }
    }
    return str_replace($cdn, '', $html);
}
