<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

/** CLI-only, no database/bootstrap, no attachment migration. */
function productionPrivateStorageSafePath(string $path): string
{
    $path = str_replace('\\', '/', $path);
    if (!preg_match('~^(?:/|[A-Za-z]:/)[^\x00-\x1f]*$~D', $path)
        || preg_match('~(?:^|/)\.{1,2}(?:/|$)~', $path)) {
        throw new RuntimeException('invalid_path');
    }
    $path = rtrim($path, '/');
    for ($part = $path; $part !== '' && dirname($part) !== $part; $part = dirname($part)) {
        if (is_link($part)) throw new RuntimeException('symlink_path');
        if (file_exists($part) && !is_dir($part)) throw new RuntimeException('non_directory_path');
        if (is_dir($part)) {
            $canonical = str_replace('\\', '/', (string)realpath($part));
            $same = DIRECTORY_SEPARATOR === '\\' ? strcasecmp($canonical, $part) === 0 : $canonical === $part;
            if (!$same) throw new RuntimeException('symlinked_ancestor');
            // Do not inspect ancestors outside the hosting open_basedir.
            break;
        }
    }
    return $path;
}

function productionPrivateStorageDirectory(string $path): void
{
    productionPrivateStorageSafePath($path);
    if (!is_dir($path)) {
        $parent = dirname($path);
        if (!is_dir($parent)) productionPrivateStorageDirectory($parent);
        if (!mkdir($path, 0700) || !chmod($path, 0700)) throw new RuntimeException('directory_create_failed');
    }
    productionPrivateStorageSafePath($path);
}

/** Append under the caller's exclusive lock; the original backup is already verified. */
function productionPrivateStorageAppend($handle, string $original, string $append): void
{
    if (!rewind($handle) || !hash_equals($original, (string)stream_get_contents($handle))) throw new RuntimeException('config_changed_concurrently');
    try {
        if (fseek($handle, 0, SEEK_END) !== 0
            || fwrite($handle, $append) !== strlen($append)
            || !fflush($handle)
            || !rewind($handle)
            || !hash_equals($original . $append, (string)stream_get_contents($handle))) {
            throw new RuntimeException('config_append_verification_failed');
        }
    } catch (Throwable $failure) {
        // A throwing global warning handler must not bypass restoration. Try
        // truncation, flush, and verification independently, even if one fails.
        set_error_handler(static fn(): bool => true);
        $truncated = $flushed = $verified = false;
        try {
            try { $truncated = ftruncate($handle, strlen($original)); } catch (Throwable) {}
            try { $flushed = fflush($handle); } catch (Throwable) {}
            try { $verified = rewind($handle) && hash_equals($original, (string)stream_get_contents($handle)); } catch (Throwable) {}
        } finally { restore_error_handler(); }
        throw new RuntimeException($truncated && $flushed && $verified ? 'config_append_failed_restored' : 'config_append_failed_restore_required', 0, $failure);
    }
}

/** @return array<string,bool> */
function productionPrivateStorageProvision(string $appRoot, string $configured, string $configBytes, $configHandle): array
{
    $appRoot = productionPrivateStorageSafePath($appRoot);
    $dataRoot = productionPrivateStorageSafePath(dirname($appRoot) . '/data');
    $configured = trim($configured);
    $target = productionPrivateStorageSafePath($configured !== '' ? $configured : $dataRoot . '/kis-private');
    // Only the hosting's private data subtree is authorized. Never infer or
    // relocate a previously configured directory outside this boundary.
    if (!str_starts_with($target . '/', $dataRoot . '/') || $target === $dataRoot
        || $target === $appRoot || str_starts_with($target . '/', $appRoot . '/')) {
        throw new RuntimeException('storage_not_allowlisted');
    }
    $documentRoot = trim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
    if ($documentRoot !== '') {
        $webRoot = realpath($documentRoot);
        if ($webRoot === false) throw new RuntimeException('unknown_document_root');
        $webRoot = str_replace('\\', '/', $webRoot);
        if ($target === $webRoot || str_starts_with($target . '/', rtrim($webRoot, '/') . '/')) throw new RuntimeException('storage_inside_document_root');
    }
    // Fail before any directory or config change on malformed PHP.
    token_get_all($configBytes, TOKEN_PARSE);
    $tokens = token_get_all($configBytes);
    $inPhp = false;
    foreach ($tokens as $token) {
        if ($configured === '' && is_array($token) && in_array($token[0], [T_RETURN, T_HALT_COMPILER, T_EXIT], true)) throw new RuntimeException('config_append_not_reachable');
        if (is_array($token) && $token[0] === T_OPEN_TAG) $inPhp = true;
        if (is_array($token) && $token[0] === T_CLOSE_TAG) $inPhp = false;
    }
    $append = ($inPhp ? "\n" : "\n<?php\n")
        . "// Private storage provisioned by the protected KIS release workflow.\n"
        . "if (!defined('APP_PRIVATE_STORAGE_ROOT')) { define('APP_PRIVATE_STORAGE_ROOT', " . var_export($target, true) . "); }\n";
    token_get_all($configBytes . $append, TOKEN_PARSE);
    // An explicitly defined empty value cannot be repaired by appending a guard.
    if ($configured === '' && defined('APP_PRIVATE_STORAGE_ROOT')) throw new RuntimeException('empty_existing_storage_constant');
    $created = !is_dir($target);
    productionPrivateStorageDirectory($target);
    clearstatcache(true, $target);
    if (DIRECTORY_SEPARATOR === '/' && (fileperms($target) & 0077) !== 0) throw new RuntimeException('storage_permissions_not_private');
    $probe = $target . '/.provision-probe-' . bin2hex(random_bytes(16));
    $bytes = random_bytes(64);
    $probeHandle = fopen($probe, 'x+b');
    if ($probeHandle === false) throw new RuntimeException('probe_create_failed');
    try {
        if (!chmod($probe, 0600) || fwrite($probeHandle, $bytes) !== strlen($bytes) || !fflush($probeHandle)) throw new RuntimeException('probe_write_failed');
        rewind($probeHandle);
        if (!hash_equals($bytes, (string)stream_get_contents($probeHandle))) throw new RuntimeException('probe_read_failed');
    } finally {
        fclose($probeHandle);
        if (!unlink($probe)) throw new RuntimeException('probe_cleanup_failed');
    }
    $changed = false;
    if ($configured === '') {
        $backupRoot = $dataRoot . '/.kis-backups/private-storage';
        productionPrivateStorageDirectory($backupRoot);
        if (DIRECTORY_SEPARATOR === '/' && (fileperms($backupRoot) & 0077) !== 0) throw new RuntimeException('backup_permissions_not_private');
        $backup = $backupRoot . '/config-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(12)) . '.php';
        $backupHandle = fopen($backup, 'x+b');
        if ($backupHandle === false) throw new RuntimeException('backup_create_failed');
        try {
            if (!chmod($backup, 0600) || fwrite($backupHandle, $configBytes) !== strlen($configBytes) || !fflush($backupHandle)) throw new RuntimeException('backup_write_failed');
            rewind($backupHandle);
            if (!hash_equals($configBytes, (string)stream_get_contents($backupHandle))) throw new RuntimeException('backup_verify_failed');
        } finally { fclose($backupHandle); }
        productionPrivateStorageAppend($configHandle, $configBytes, $append);
        $changed = true;
    }
    return ['ok'=>true, 'storage_created'=>$created, 'existing_configuration_preserved'=>!$changed,
        'config_appended'=>$changed, 'private_config_backup_verified'=>$changed,
        'cli_write_read_delete_probe'=>true, 'web_runtime_verified'=>false,
        'attachments_moved'=>false, 'database_accessed'=>false];
}

function provisionProductionPrivateStorageMain(): int
{
    if (PHP_SAPI !== 'cli') return 2;
    ini_set('display_errors', '0');
    set_error_handler(static function (): never { throw new RuntimeException('private_storage_operation_failed'); });
    umask(0077);
    $handle = null;
    try {
        if (getenv('APP_HOST') !== 'kis.kovopraha.cz'
            || getenv('KIS_PRIVATE_STORAGE_CONFIRM') !== 'PRIPRAVIT-PRIVATNI-ULOZISTE') throw new RuntimeException('invalid_target');
        $input = (string)getenv('APP_ROOT');
        if ($input === '') throw new RuntimeException('missing_root');
        $root = realpath($input);
        if ($root === false) throw new RuntimeException('missing_root');
        productionPrivateStorageSafePath($root);
        $config = $root . '/config.php';
        if (!is_file($config) || is_link($config)) throw new RuntimeException('invalid_config');
        $original = file_get_contents($config);
        if (!is_string($original)) throw new RuntimeException('config_read_failed');
        $_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = 'kis.kovopraha.cz';
        ob_start();
        try { require $config; } finally { ob_end_clean(); }
        if (!defined('JE_LOKALNE') || JE_LOKALNE !== false || !defined('APP_BASE_URL') || APP_BASE_URL !== 'https://kis.kovopraha.cz') throw new RuntimeException('config_identity_mismatch');
        // Windows enforces flock against require's second file descriptor.
        // Load first, then lock and verify that the loaded bytes stayed intact.
        $handle = fopen($config, 'r+b');
        if ($handle === false || !flock($handle, LOCK_EX | LOCK_NB)) throw new RuntimeException('config_lock_failed');
        if (!hash_equals($original, (string)stream_get_contents($handle))) throw new RuntimeException('config_changed_concurrently');
        $configured = getenv('APP_PRIVATE_STORAGE_ROOT');
        if (!is_string($configured) || trim($configured) === '') $configured = defined('APP_PRIVATE_STORAGE_ROOT') ? (string)APP_PRIVATE_STORAGE_ROOT : '';
        echo json_encode(productionPrivateStorageProvision($root, $configured, $original, $handle), JSON_THROW_ON_ERROR) . "\n";
        return 0;
    } catch (Throwable) {
        // Never expose configuration contents, filesystem paths, or secrets.
        echo "{\"ok\":false,\"error\":\"private_storage_provision_failed\"}\n";
        return 2;
    } finally {
        if (is_resource($handle)) { flock($handle, LOCK_UN); fclose($handle); }
        restore_error_handler();
    }
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) exit(provisionProductionPrivateStorageMain());
