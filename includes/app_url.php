<?php
declare(strict_types=1);

/**
 * Canonical application URL. Request headers are deliberately never used:
 * Host is attacker-controlled and must not enter e-mail or approval links.
 */
function appCanonicalBaseUrl(): string
{
    $configured = getenv('APP_BASE_URL');
    if (!is_string($configured) || trim($configured) === '') {
        $configured = defined('APP_BASE_URL') ? (string)APP_BASE_URL : '';
    }
    if (trim($configured) === '' && defined('VELOCOTA_EVIDENCE_BASE_URL')) {
        $configured = (string)VELOCOTA_EVIDENCE_BASE_URL;
    }
    if (trim($configured) === '' && defined('JE_LOKALNE') && JE_LOKALNE) {
        $configured = 'http://localhost/evidencePavel';
    }

    $configured = rtrim(trim($configured), '/');
    $parts = $configured !== '' ? parse_url($configured) : false;
    if (!is_array($parts)
        || !in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true)
        || trim((string)($parts['host'] ?? '')) === ''
        || isset($parts['user'])
        || isset($parts['pass'])
        || isset($parts['query'])
        || isset($parts['fragment'])
    ) {
        throw new RuntimeException('APP_BASE_URL neni nastavena na platnou duveryhodnou adresu aplikace.');
    }

    return $configured;
}

function appUrl(string $path = ''): string
{
    $path = ltrim($path, '/');
    return appCanonicalBaseUrl() . ($path === '' ? '' : '/' . $path);
}
