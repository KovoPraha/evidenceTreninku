<?php

declare(strict_types=1);

/** Archives immutable input roots outside the application; never logs names or file contents. */
function productionFileBackup(array $roots, string $destination): array
{
    $parent = realpath(dirname($destination));
    if ($parent === false || file_exists($destination) || is_link($destination)) {
        throw new RuntimeException('Invalid new backup destination.');
    }
    $files = [];
    $bytes = 0;
    foreach ($roots as $label => $directory) {
        $root = realpath($directory);
        if (!preg_match('/^[a-z]+$/D', (string)$label) || $root === false || !is_dir($root) || is_link($directory)
            || str_starts_with(str_replace('\\', '/', $parent) . '/', str_replace('\\', '/', $root) . '/')) {
            throw new RuntimeException('Invalid backup root.');
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isLink() || !$file->isFile()) {
                throw new RuntimeException('Backup refuses links or special files.');
            }
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            $key = $label . '/' . $relative;
            $size = $file->getSize();
            $files[$key] = ['path' => $file->getPathname(), 'bytes' => $size, 'sha256' => hash_file('sha256', $file->getPathname())];
            $bytes += $size;
        }
    }
    if (disk_free_space($parent) < $bytes * 2 + 100 * 1024 * 1024) {
        throw new RuntimeException('Insufficient backup space.');
    }
    ksort($files);
    $handle = fopen($destination, 'x+b');
    if ($handle === false) {
        throw new RuntimeException('Cannot exclusively create private backup.');
    }
    fclose($handle);
    chmod($destination, 0600);
    $zip = new ZipArchive();
    if ($zip->open($destination, ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Cannot create private backup.');
    }
    chmod($destination, 0600);
    $manifest = [];
    foreach ($files as $key => $file) {
        if (!$zip->addFile($file['path'], $key)) {
            throw new RuntimeException('Cannot archive a file.');
        }
        $manifest[$key] = ['bytes' => $file['bytes'], 'sha256' => $file['sha256']];
    }
    $zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    if (!$zip->close()) {
        throw new RuntimeException('Cannot finalize private backup.');
    }
    chmod($destination, 0600);
    return ['file' => basename($destination), 'files' => count($files), 'source_bytes' => $bytes,
        'archive_bytes' => filesize($destination), 'sha256' => hash_file('sha256', $destination)];
}

function productionFileBackupMain(): void
{
    if (PHP_SAPI !== 'cli' || getenv('APP_HOST') !== 'kis.kovopraha.cz' || getenv('KIS_FILE_BACKUP_CONFIRM') !== 'ZALOHOVAT') {
        http_response_code(404);
        exit(2);
    }
    umask(0077);
    $root = realpath((string)getenv('APP_ROOT'));
    if ($root === false || !is_file($root . '/config.php')) {
        exit(2);
    }
    ob_start();
    try {
        require $root . '/config.php';
        ob_end_clean();
        if (!defined('JE_LOKALNE') || JE_LOKALNE !== false || !defined('APP_BASE_URL') || APP_BASE_URL !== 'https://kis.kovopraha.cz'
            || !defined('APP_PRIVATE_STORAGE_ROOT')) {
            throw new RuntimeException('Target mismatch.');
        }
        $result = productionFileBackup(['app' => $root, 'private' => APP_PRIVATE_STORAGE_ROOT], (string)getenv('KIS_FILE_BACKUP_PATH'));
        echo json_encode(['ok' => true] + $result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), "\n";
    } catch (Throwable $error) {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        fwrite(STDERR, "Production file backup failed; no application files changed.\n");
        exit(1);
    }
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    productionFileBackupMain();
}
