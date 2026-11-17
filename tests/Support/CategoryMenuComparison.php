<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/** Verify CSP before translating only the explicit synthetic origin. */
function categoryMenuComparisonCsp(string $html, string $csp, string $nonce, string $origin): string
{
    $originalCsp = $csp;
    preg_match_all('/<[^>]+>/s', $html, $tags);
    foreach ($tags[0] as $tag) {
        if (!str_contains($tag, 'data-app-csp-handler="' . $nonce . '"')) {
            continue;
        }
        preg_match_all('/\son[a-z]+\s*=\s*(["\'])(.*?)\1/is', $tag, $matches);
        foreach ($matches[2] as $handler) {
            $decoded = html_entity_decode($handler, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (!str_contains($decoded, $origin)) {
                continue;
            }
            $rawHash = base64_encode(hash('sha256', $decoded, true));
            $normalized = str_replace($origin, 'http://synthetic.local', $decoded);
            $normalizedHash = base64_encode(hash('sha256', $normalized, true));
            // Multiple images may share the same handler/hash.
            if (!str_contains($originalCsp, "'sha256-" . $rawHash . "'")) {
                throw new RuntimeException('Expected actual CSP image handler hash is missing.');
            }
            $csp = str_replace("'sha256-" . $rawHash . "'", "'sha256-" . $normalizedHash . "'", $csp);
        }
    }
    return $csp;
}
