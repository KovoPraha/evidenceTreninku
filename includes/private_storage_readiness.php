<?php
declare(strict_types=1);

require_once __DIR__ . '/private_storage.php';

/** No database, paths in output, directory creation, or write probe. */
function privateStorageReadiness(): array
{
    $value = getenv('APP_PRIVATE_STORAGE_ROOT');
    if (!is_string($value) || trim($value) === '') $value = defined('APP_PRIVATE_STORAGE_ROOT') ? (string)APP_PRIVATE_STORAGE_ROOT : '';
    $result = ['configured'=>trim($value) !== '' || (defined('JE_LOKALNE') && JE_LOKALNE === true),
        'outside_webroot'=>false, 'directory_exists'=>false, 'writable'=>false];
    try {
        $root = privateStorageRoot();
        // stat calls can warn when the web SAPI has narrower open_basedir.
        // These expected permission failures must not reveal server paths.
        $resolved = @realpath($root);
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
    } catch (Throwable) {
        // No exception detail: a rejected configuration can contain paths.
    }
    return $result;
}
