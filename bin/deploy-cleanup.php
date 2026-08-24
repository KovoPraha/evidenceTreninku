<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$confirm = (string)getenv('DEPLOY_CLEANUP_CONFIRM');
$configuredRoot = trim((string)getenv('APP_ROOT'));
if ($confirm !== 'ODSTRANIT-OSIŘELÉ-SOUBORY' || $configuredRoot === '') {
    fwrite(STDERR, "Deploy cleanup nebyl bezpečně potvrzen.\n");
    exit(2);
}

$appRoot = realpath($configuredRoot);
$manifest = __DIR__ . '/deploy-delete-manifest.txt';
if ($appRoot === false || !is_dir($appRoot) || !is_file($manifest)) {
    fwrite(STDERR, "Deploy cleanup nemá platný kořen aplikace nebo manifest.\n");
    exit(2);
}

$appRoot = rtrim(str_replace('\\', '/', $appRoot), '/');
$lines = file($manifest, FILE_IGNORE_NEW_LINES);
if ($lines === false) {
    fwrite(STDERR, "Manifest nelze načíst.\n");
    exit(2);
}

$removed = [];
$missing = [];
foreach ($lines as $line) {
    $relative = trim($line);
    if ($relative === '' || str_starts_with($relative, '#')) {
        continue;
    }
    $normalized = str_replace('\\', '/', $relative);
    if (str_starts_with($normalized, '/')
        || preg_match('/^[A-Za-z]:/', $normalized) === 1
        || preg_match('~(?:^|/)\.\.(?:/|$)~', $normalized) === 1
        || str_contains($normalized, "\0")
        || strtolower(pathinfo($normalized, PATHINFO_EXTENSION)) !== 'php'
    ) {
        fwrite(STDERR, "Neplatná cesta v deploy manifestu: {$relative}\n");
        exit(2);
    }

    $target = $appRoot . '/' . $normalized;
    $parent = realpath(dirname($target));
    if ($parent === false) {
        $missing[] = $normalized;
        continue;
    }
    $parent = rtrim(str_replace('\\', '/', $parent), '/');
    if ($parent !== $appRoot && !str_starts_with($parent . '/', $appRoot . '/')) {
        fwrite(STDERR, "Cesta z manifestu opouští aplikaci: {$relative}\n");
        exit(2);
    }
    $safeTarget = $parent . '/' . basename($normalized);
    if (!is_file($safeTarget) && !is_link($safeTarget)) {
        $missing[] = $normalized;
        continue;
    }
    if (!unlink($safeTarget)) {
        fwrite(STDERR, "Soubor nelze odstranit: {$relative}\n");
        exit(1);
    }
    $removed[] = $normalized;
}

echo json_encode([
    'ok' => true,
    'removed' => $removed,
    'missing' => $missing,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
