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

function anonymousResponseProvesDenial(string $route, int $status, string $headers): bool
{
    if (in_array($status, [401, 403], true)) {
        return true;
    }
    if ($status === 404 && (isset(anonymousHiddenRoutes()[$route]) || (anonymousCliRoutes()[$route] ?? null) === 404)) {
        return true;
    }
    $target = anonymousLocalRedirect($route, $headers);
    return in_array($status, [302, 303], true) && $target !== null
        && in_array(parse_url($target, PHP_URL_PATH), ['login.php', 'booking/prihlaseni.php', 'booking/sportovec_prihlaseni.php'], true);
}
