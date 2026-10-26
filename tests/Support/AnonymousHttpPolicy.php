<?php

declare(strict_types=1);

function anonymousLocalRedirect(string $route, string $headers): ?string
{
    if (!preg_match('~^Location:\s*([^\r\n]+)~mi', $headers, $match)) {
        return null;
    }
    $location = trim($match[1]);
    if (str_contains($location, ':') || str_contains($location, '\\') || str_starts_with($location, '//')) {
        return null;
    }
    $path = parse_url($location, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        return null;
    }
    $parts = [];
    foreach (explode('/', str_starts_with($path, '/') ? $path : dirname($route) . '/' . $path) as $part) {
        if ($part === '..') {
            array_pop($parts);
        } elseif ($part !== '' && $part !== '.') {
            $parts[] = $part;
        }
    }
    $query = parse_url($location, PHP_URL_QUERY);
    return implode('/', $parts) . (is_string($query) ? '?' . $query : '');
}

function anonymousResponseProvesDenial(string $route, int $status, string $headers, string $body): bool
{
    if (in_array($status, [401, 403], true)) {
        return true;
    }
    if ($status === 404 && (isset(anonymousHiddenRoutes()[$route]) || (anonymousCliRoutes()[$route] ?? null) === 404)) {
        return true;
    }
    $target = anonymousLocalRedirect($route, $headers);
    return in_array($status, [302, 303], true) && $body === '' && $target !== null
        && in_array(parse_url($target, PHP_URL_PATH), ['login.php', 'booking/prihlaseni.php', 'booking/sportovec_prihlaseni.php'], true);
}

/** @return array{status: int, denied: bool, redirectBody: bool} */
function anonymousProbeResponse(string $baseUrl, string $route, string $method): array
{
    for ($redirect = 0; $redirect < 4; $redirect++) {
        $curl = curl_init($baseUrl . '/' . $route);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 15, CURLOPT_PROXY => '',
            CURLOPT_CUSTOMREQUEST => $method,
        ]);
        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $headers = substr((string) $response, 0, $headerSize);
        $body = substr((string) $response, $headerSize);
        curl_close($curl);
        $denied = anonymousResponseProvesDenial((string) parse_url($route, PHP_URL_PATH), $status, $headers, $body);
        $isRedirect = in_array($status, [301, 302, 303, 307, 308], true);
        $redirectBody = $isRedirect && $body !== '';
        // Inspect every hop before following an alias. A safe final login must
        // never hide bytes already exposed by an earlier redirect without exit.
        if ($denied || !$isRedirect || $redirectBody) {
            break;
        }
        $target = anonymousLocalRedirect($route, $headers);
        if ($target === null) {
            break;
        }
        $route = $target;
        $method = in_array($status, [307, 308], true) ? $method : 'GET';
    }
    return ['status' => $status, 'denied' => $denied, 'redirectBody' => $redirectBody];
}
