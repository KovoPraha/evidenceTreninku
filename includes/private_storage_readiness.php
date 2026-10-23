<?php
declare(strict_types=1);

require_once __DIR__ . '/private_storage.php';

/** Normalize an absolute local path for comparison only; never create it. */
function privateStorageReadinessComparablePath(string $path): ?string
{
    $path = str_replace('\\', '/', trim($path));
    if (!preg_match('~^(?:/|[A-Za-z]:/)~', $path) || str_contains($path, "\0")) return null;
    $parts = [];
    foreach (explode('/', $path) as $part) {
        if ($part === '' || $part === '.') continue;
        if ($part === '..') { if ($parts !== []) array_pop($parts); continue; }
        $parts[] = $part;
    }
    $normalized = (str_starts_with($path, '/') ? '/' : '') . implode('/', $parts);
    return DIRECTORY_SEPARATOR === '\\' ? strtolower($normalized) : $normalized;
}

/** Rule comparison is separate from actual stat/access results below. */
function privateStorageReadinessBasedirAllows(string $root, string $restriction): bool
{
    if (trim($restriction) === '') return true;
    $candidate = privateStorageReadinessComparablePath($root);
    if ($candidate === null) return false;
    foreach (explode(PATH_SEPARATOR, $restriction) as $allowed) {
        if (trim($allowed) === '') continue;
        if (privateStorageReadinessComparablePath($allowed) === null) {
            $cwd = getcwd();
            if (!is_string($cwd)) continue;
            $allowed = $cwd . '/' . $allowed;
        }
        $resolved = @realpath($allowed);
        $allowed = privateStorageReadinessComparablePath(is_string($resolved) ? $resolved : $allowed);
        if ($allowed !== null && ($candidate === $allowed || str_starts_with($candidate . '/', rtrim($allowed, '/') . '/'))) return true;
    }
    return false;
}

/** No database, paths in output, directory creation, or write probe. */
function privateStorageReadiness(): array
{
    $environment = trim((string)getenv('APP_PRIVATE_STORAGE_ROOT'));
    $constant = defined('APP_PRIVATE_STORAGE_ROOT') ? trim((string)APP_PRIVATE_STORAGE_ROOT) : '';
    $value = $environment !== '' ? $environment : $constant;
    $basedir = trim((string)ini_get('open_basedir'));
    $result = ['configured'=>trim($value) !== '' || (defined('JE_LOKALNE') && JE_LOKALNE === true),
        'outside_webroot'=>false, 'directory_exists'=>false, 'writable'=>false,
        'configured_from_environment'=>$environment !== '',
        'configured_from_constant'=>$environment === '' && $constant !== '',
        'environment_constant_mismatch'=>$environment !== '' && $constant !== '' && $environment !== $constant,
        'open_basedir_enabled'=>$basedir !== '', 'open_basedir_allows_configured_root'=>false,
        'root_realpath_available'=>false, 'directory_stat_available'=>false,
        'effective_uid_available'=>false, 'directory_owner_uid_available'=>false,
        'directory_owner_matches_effective_uid'=>false];
    try {
        $root = privateStorageRoot();
        // stat calls can warn when the web SAPI has narrower open_basedir.
        // These expected permission failures must not reveal server paths.
        $resolved = @realpath($root);
        $result['root_realpath_available'] = is_string($resolved);
        $result['open_basedir_allows_configured_root'] = privateStorageReadinessBasedirAllows(is_string($resolved) ? $resolved : $root, $basedir);
        $effectiveUid = function_exists('posix_geteuid') ? posix_geteuid() : null;
        $result['effective_uid_available'] = is_int($effectiveUid);
        $normalize = static fn(string $path): string => strtolower(rtrim(str_replace('\\', '/', $path), '/'));
        $candidate = $normalize(is_string($resolved) ? $resolved : $root);
        foreach ([dirname(__DIR__), trim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''))] as $parent) {
            if ($parent === '') continue;
            $resolvedParent = @realpath($parent);
            $parent = $normalize(is_string($resolvedParent) ? $resolvedParent : $parent);
            if ($candidate === $parent || str_starts_with($candidate . '/', $parent . '/')) return $result;
        }
        $result['outside_webroot'] = true;
        $result['directory_exists'] = @is_dir($root);
        $result['writable'] = $result['directory_exists'] && @is_writable($root);
        $stat = @stat($root);
        $result['directory_stat_available'] = is_array($stat);
        $result['directory_owner_uid_available'] = is_array($stat) && isset($stat['uid']) && is_int($stat['uid']);
        $result['directory_owner_matches_effective_uid'] = $result['effective_uid_available'] && $result['directory_owner_uid_available'] && $stat['uid'] === $effectiveUid;
    } catch (Throwable) {
        // No exception detail: a rejected configuration can contain paths.
    }
    return $result;
}
