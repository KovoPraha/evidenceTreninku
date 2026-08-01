<?php
declare(strict_types=1);

defined('APP_SESSION_NAME') || define('APP_SESSION_NAME', 'EVIDENCESESSID');
defined('APP_SESSION_IDLE_TIMEOUT') || define('APP_SESSION_IDLE_TIMEOUT', 7200);
defined('APP_SESSION_ABSOLUTE_TIMEOUT') || define('APP_SESSION_ABSOLUTE_TIMEOUT', 43200);
defined('APP_SESSION_ROTATION_INTERVAL') || define('APP_SESSION_ROTATION_INTERVAL', 900);

const APP_SESSION_AUTHENTICATED_AT = '__app_authenticated_at';
const APP_SESSION_LAST_ACTIVITY_AT = '__app_last_activity_at';
const APP_SESSION_ROTATED_AT = '__app_rotated_at';

/** @param array<string, mixed>|null $server */
function app_session_request_is_local(?array $server = null): bool
{
    $server ??= $_SERVER;
    $host = strtolower(trim((string)($server['HTTP_HOST'] ?? $server['SERVER_NAME'] ?? '')));

    if (str_starts_with($host, '[')) {
        $closingBracket = strpos($host, ']');
        $host = $closingBracket === false ? $host : substr($host, 1, $closingBracket - 1);
    } else {
        $host = (string)preg_replace('/:\d+$/', '', $host);
    }

    return $host === ''
        || in_array($host, ['localhost', '127.0.0.1', '::1'], true)
        || str_ends_with($host, '.local')
        || str_ends_with($host, '.test');
}

/** @param array<string, mixed>|null $server */
function app_session_request_is_https(?array $server = null): bool
{
    $server ??= $_SERVER;
    $https = strtolower((string)($server['HTTPS'] ?? ''));

    return ($https !== '' && $https !== 'off' && $https !== '0')
        || strtolower((string)($server['REQUEST_SCHEME'] ?? '')) === 'https'
        || (int)($server['SERVER_PORT'] ?? 0) === 443;
}

/**
 * @param array<string, mixed>|null $server
 * @param array<string, int|string|bool> $overrides
 * @return array{name:string,idle_timeout:int,absolute_timeout:int,rotation_interval:int,cookie_path:string,cookie_secure:bool,cookie_httponly:bool,cookie_samesite:string}
 */
function app_session_policy(?array $server = null, array $overrides = []): array
{
    $server ??= $_SERVER;
    $policy = [
        'name' => (string)APP_SESSION_NAME,
        'idle_timeout' => (int)APP_SESSION_IDLE_TIMEOUT,
        'absolute_timeout' => (int)APP_SESSION_ABSOLUTE_TIMEOUT,
        'rotation_interval' => (int)APP_SESSION_ROTATION_INTERVAL,
        'cookie_path' => '/',
        'cookie_secure' => !app_session_request_is_local($server) || app_session_request_is_https($server),
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ];

    foreach ($overrides as $key => $value) {
        if (array_key_exists($key, $policy)) {
            $policy[$key] = $value;
        }
    }

    return $policy;
}

/**
 * @param array<string, mixed>|null $server
 * @param array<string, int|string|bool> $overrides
 * @return array{lifetime:int,path:string,secure:bool,httponly:bool,samesite:string}
 */
function app_session_cookie_options(?array $server = null, array $overrides = []): array
{
    $policy = app_session_policy($server, $overrides);

    return [
        'lifetime' => 0,
        'path' => (string)$policy['cookie_path'],
        'secure' => (bool)$policy['cookie_secure'],
        'httponly' => (bool)$policy['cookie_httponly'],
        'samesite' => (string)$policy['cookie_samesite'],
    ];
}

/**
 * @param array<string, mixed> $cookieParams
 * @return array{expires:int,path:string,domain:string,secure:bool,httponly:bool,samesite:string}
 */
function app_session_expired_cookie_options(array $cookieParams, int $now): array
{
    return [
        'expires' => $now - 42000,
        'path' => (string)($cookieParams['path'] ?? '/'),
        'domain' => (string)($cookieParams['domain'] ?? ''),
        'secure' => (bool)($cookieParams['secure'] ?? false),
        'httponly' => (bool)($cookieParams['httponly'] ?? true),
        'samesite' => (string)($cookieParams['samesite'] ?? 'Lax'),
    ];
}

/**
 * @param array<string, mixed> $session
 * @param array<string, int|string|bool>|null $policy
 * @return array{expired:?string,rotate:bool,initialize:bool}
 */
function app_session_lifecycle_decision(array $session, int $now, ?array $policy = null): array
{
    $policy ??= app_session_policy();
    $authenticatedAt = isset($session[APP_SESSION_AUTHENTICATED_AT])
        ? (int)$session[APP_SESSION_AUTHENTICATED_AT]
        : null;
    $lastActivityAt = isset($session[APP_SESSION_LAST_ACTIVITY_AT])
        ? (int)$session[APP_SESSION_LAST_ACTIVITY_AT]
        : null;
    $rotatedAt = isset($session[APP_SESSION_ROTATED_AT])
        ? (int)$session[APP_SESSION_ROTATED_AT]
        : null;

    if ($authenticatedAt === null || $lastActivityAt === null || $rotatedAt === null) {
        return ['expired' => null, 'rotate' => false, 'initialize' => true];
    }

    if ($now - $authenticatedAt >= (int)$policy['absolute_timeout']) {
        return ['expired' => 'absolute', 'rotate' => false, 'initialize' => false];
    }

    if ($now - $lastActivityAt >= (int)$policy['idle_timeout']) {
        return ['expired' => 'idle', 'rotate' => false, 'initialize' => false];
    }

    return [
        'expired' => null,
        'rotate' => $now - $rotatedAt >= (int)$policy['rotation_interval'],
        'initialize' => false,
    ];
}

function app_session_has_authenticated_identity(): bool
{
    return isset($_SESSION['trener_id']) || isset($_SESSION['verejny_uzivatel_id']);
}

function app_session_rotate_csrf_token(): string
{
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;

    return $token;
}

function app_session_send_security_headers(): void
{
    if (!headers_sent()) {
        header('Referrer-Policy: strict-origin-when-cross-origin', true);
    }
}

/**
 * @param array<string, mixed>|null $server
 * @param array<string, int|string|bool> $overrides
 */
function app_session_start(?array $server = null, ?int $now = null, array $overrides = []): void
{
    $server ??= $_SERVER;
    $now ??= time();
    $policy = app_session_policy($server, $overrides);

    app_session_send_security_headers();

    if (session_status() !== PHP_SESSION_ACTIVE) {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name((string)$policy['name']);
        session_set_cookie_params(app_session_cookie_options($server, $overrides));

        if (!session_start()) {
            throw new RuntimeException('Session could not be started.');
        }
    }

    if (!app_session_has_authenticated_identity()) {
        return;
    }

    $decision = app_session_lifecycle_decision($_SESSION, $now, $policy);
    if ($decision['expired'] !== null) {
        app_session_destroy($now);
        app_session_start($server, $now, $overrides);
        return;
    }

    if ($decision['initialize']) {
        $_SESSION[APP_SESSION_AUTHENTICATED_AT] = $now;
        $_SESSION[APP_SESSION_LAST_ACTIVITY_AT] = $now;
        $_SESSION[APP_SESSION_ROTATED_AT] = $now;
        return;
    }

    if ($decision['rotate']) {
        if (!session_regenerate_id(true)) {
            throw new RuntimeException('Session identifier could not be rotated.');
        }
        $_SESSION[APP_SESSION_ROTATED_AT] = $now;
    }

    $_SESSION[APP_SESSION_LAST_ACTIVITY_AT] = $now;
}

function app_session_mark_authenticated(?int $now = null): void
{
    $now ??= time();
    if (session_status() !== PHP_SESSION_ACTIVE) {
        app_session_start(null, $now);
    }

    if (!session_regenerate_id(true)) {
        throw new RuntimeException('Session identifier could not be rotated after authentication.');
    }

    app_session_rotate_csrf_token();
    $_SESSION[APP_SESSION_AUTHENTICATED_AT] = $now;
    $_SESSION[APP_SESSION_LAST_ACTIVITY_AT] = $now;
    $_SESSION[APP_SESSION_ROTATED_AT] = $now;
}

function app_session_destroy(?int $now = null): void
{
    $now ??= time();
    if (session_status() !== PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        return;
    }

    $_SESSION = [];
    if ((bool)ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', app_session_expired_cookie_options($params, $now));
        unset($_COOKIE[session_name()]);
    }

    session_destroy();
    session_id('');
}

function app_session_logout_public_identity(?int $now = null): void
{
    $now ??= time();
    unset($_SESSION['verejny_uzivatel_id'], $_SESSION['verejny_uzivatel_jmeno']);

    if (!isset($_SESSION['trener_id'])) {
        app_session_destroy($now);
        return;
    }

    if (!session_regenerate_id(true)) {
        app_session_destroy($now);
        return;
    }

    app_session_rotate_csrf_token();
    $_SESSION[APP_SESSION_AUTHENTICATED_AT] ??= $now;
    $_SESSION[APP_SESSION_LAST_ACTIVITY_AT] = $now;
    $_SESSION[APP_SESSION_ROTATED_AT] = $now;
}
