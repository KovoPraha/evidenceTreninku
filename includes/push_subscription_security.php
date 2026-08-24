<?php
declare(strict_types=1);

/**
 * Browser push sluzby maji verejne zname hosty. Vlastni URL by zmenila
 * odesilani notifikace na blind SSRF z produkcni site.
 */
function pushSubscriptionValidateEndpoint(mixed $value): string
{
    if (!is_string($value)) {
        throw new InvalidArgumentException('Neplatná adresa push služby.');
    }
    $endpoint = trim($value);
    if ($endpoint === '' || strlen($endpoint) > 2048 || filter_var($endpoint, FILTER_VALIDATE_URL) === false) {
        throw new InvalidArgumentException('Neplatná adresa push služby.');
    }
    $parts = parse_url($endpoint);
    if (!is_array($parts)
        || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
        || trim((string)($parts['host'] ?? '')) === ''
        || isset($parts['user'])
        || isset($parts['pass'])
        || isset($parts['port'])
        || isset($parts['fragment'])
    ) {
        throw new InvalidArgumentException('Push služba musí používat bezpečnou HTTPS adresu.');
    }
    $host = strtolower(rtrim((string)$parts['host'], '.'));
    $allowed = $host === 'fcm.googleapis.com'
        || $host === 'web.push.apple.com'
        || $host === 'push.services.mozilla.com'
        || str_ends_with($host, '.push.services.mozilla.com')
        || $host === 'notify.windows.com'
        || str_ends_with($host, '.notify.windows.com');
    if (!$allowed) {
        throw new InvalidArgumentException('Tato push služba není podporována.');
    }
    return $endpoint;
}

function pushSubscriptionValidateKey(mixed $value, string $label, int $maxLength): string
{
    if (!is_string($value)) {
        throw new InvalidArgumentException($label . ' chybí.');
    }
    $key = trim($value);
    if ($key === '' || strlen($key) > $maxLength || preg_match('/^[A-Za-z0-9_-]+$/D', $key) !== 1) {
        throw new InvalidArgumentException($label . ' není platný.');
    }
    return $key;
}
