<?php
declare(strict_types=1);

namespace Kis\PrivateStorageProbe;

require_once __DIR__ . '/private_storage_readiness.php';

/** Explicit administrator POST only. No database, user paths, or directory creation. */
function run(): array
{
    $result = ['preflight_passed'=>false, 'created'=>false, 'permissions_set'=>false,
        'unix_permissions_supported'=>DIRECTORY_SEPARATOR === '/', 'unix_permissions_verified'=>false,
        'write_verified'=>false, 'read_verified'=>false, 'cleanup_verified'=>false, 'ok'=>false];
    $handle = null;
    $path = null;
    $identity = null;
    // Suppress hosting filesystem warnings, which can expose absolute paths.
    set_error_handler(static fn(): bool => true);
    try {
        $ready = \privateStorageReadiness();
        foreach (['configured','outside_webroot','directory_exists','writable',
            'open_basedir_allows_configured_root','root_realpath_available'] as $check) {
            if (!$ready[$check]) throw new \RuntimeException('preflight');
        }
        $root = realpath(\privateStorageRoot());
        if (!is_string($root)) throw new \RuntimeException('root');
        $result['preflight_passed'] = true;
        $path = $root . DIRECTORY_SEPARATOR . '.uat-storage-probe-' . bin2hex(random_bytes(16));
        // The restrictive umask also covers the short interval before chmod.
        $oldMask = umask(0077);
        try { $handle = fopen($path, 'x+b'); } finally { umask($oldMask); }
        if (!is_resource($handle)) throw new \RuntimeException('create');
        $result['created'] = true;
        $identity = fstat($handle);
        if (!is_array($identity)) throw new \RuntimeException('stat');
        $result['permissions_set'] = chmod($path, 0600);
        if (!$result['permissions_set']) throw new \RuntimeException('permissions');
        $permissions = fstat($handle);
        $result['unix_permissions_verified'] = $result['unix_permissions_supported']
            && is_array($permissions) && ($permissions['mode'] & 0777) === 0600;
        if ($result['unix_permissions_supported'] && !$result['unix_permissions_verified']) throw new \RuntimeException('mode');
        $bytes = random_bytes(64);
        if (fwrite($handle, $bytes) !== strlen($bytes) || !fflush($handle)) throw new \RuntimeException('write');
        $result['write_verified'] = true;
        if (!rewind($handle)) throw new \RuntimeException('rewind');
        $read = stream_get_contents($handle);
        $result['read_verified'] = is_string($read) && hash_equals($bytes, $read);
    } catch (\Throwable) {
        // Return only booleans; neither exceptions nor configured paths escape.
    } finally {
        try {
            if (is_resource($handle)) {
                try { fclose($handle); } catch (\Throwable) {}
            }
            if ($result['created'] && is_string($path)) {
                clearstatcache(true, $path);
                $current = lstat($path);
                // Never remove a replacement, symlink, or another process's file.
                if (is_array($identity) && is_array($current)
                    && ($current['mode'] & 0170000) === 0100000
                    && $identity['dev'] === $current['dev'] && $identity['ino'] === $current['ino']) {
                    if (unlink($path)) {
                        clearstatcache(true, $path);
                        $result['cleanup_verified'] = lstat($path) === false;
                    }
                }
            }
        } catch (\Throwable) {
            $result['cleanup_verified'] = false;
        } finally {
            restore_error_handler();
        }
    }
    $result['ok'] = $result['preflight_passed'] && $result['created'] && $result['permissions_set']
        && (!$result['unix_permissions_supported'] || $result['unix_permissions_verified'])
        && $result['write_verified'] && $result['read_verified'] && $result['cleanup_verified'];
    return $result;
}
