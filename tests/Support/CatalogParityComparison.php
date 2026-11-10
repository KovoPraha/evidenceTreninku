<?php

declare(strict_types=1);

/** Preserve all product data, order, inline scripts and literal HTML; mask only explicit request/release values. */
function catalogParityHtml(string $html, array $headers, array $origins = []): string
{
    $html = str_replace("\r\n", "\n", $html);
    foreach ($origins as $origin) {
        $html = str_replace($origin, 'OWN_ORIGIN', $html);
    }
    if (preg_match("/nonce-([^']+)/", $headers['content-security-policy'] ?? '', $nonce)) {
        $html = str_replace($nonce[1], 'OWN_NONCE', $html);
    }
    preg_match_all('/name="csrf_token"\s+value="([a-f0-9]{64})"/i', $html, $csrf);
    foreach (array_unique($csrf[1]) as $token) {
        // The exact token from the hidden field can also occur in the push initialization JSON.
        $html = str_replace($token, 'OWN_CSRF', $html);
    }
    foreach (['assets/vendor/bootstrap/bootstrap.min.css', 'assets/vendor/bootstrap/bootstrap.bundle.min.js',
        'assets/vendor/bootstrap-icons/font/bootstrap-icons.css', 'assets/app-ui.css', 'assets/app-ui.js'] as $path) {
        $html = (string) preg_replace('~(' . preg_quote('/' . $path, '~') . '\?v=)(?:[0-9]+|[a-f0-9]{64})(["\'])~', '$1OWN_VERSION$2', $html);
    }
    return str_replace('data-app-csp-handler="' . "\n    OWN_NONCE\"", 'data-app-csp-handler="OWN_NONCE"', $html);
}

/** Public fingerprints only: never save raw HTML, session cookies, nonce or CSRF values. */
function catalogParitySections(string $normalized): array
{
    $doc = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8">' . $normalized, LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $xpath = new DOMXPath($doc);
    $cards = [];
    foreach ($xpath->query('//img[contains(concat(" ",normalize-space(@class)," ")," app-product-image ")]/parent::*') as $card) {
        $cards[] = ['html_sha256' => hash('sha256', (string) $doc->saveHTML($card)),
            'text_sha256' => hash('sha256', (string) preg_replace('/\s+/u', ' ', trim($card->textContent))),
            'image_path' => (string) $xpath->evaluate('string(.//img/@src)', $card)];
    }
    return ['whole_html_sha256' => hash('sha256', $normalized), 'cards' => $cards,
        'main_text_sha256' => hash('sha256', (string) preg_replace('/\s+/u', ' ', trim((string) $xpath->evaluate('string(//main)'))))];
}
