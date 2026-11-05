<?php

declare(strict_types=1);

$preview = ($_GET['preview'] ?? '') === '1';
if ($preview) {
    require_once __DIR__ . '/includes/init.php';
    staffRequireActivePosition('tv_operator');
} else {
    require_once __DIR__ . '/db.php';
}
require_once __DIR__ . '/includes/tv_program.php';
if (!isset($pdo) || !$pdo instanceof PDO) {
    throw new RuntimeException('Databázové připojení není dostupné.');
}
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('X-Content-Type-Options: nosniff');
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$asset = $id === false ? null : tvAsset($pdo, (int)$id, $preview);
$path = $asset ? privateStorageResolve((string)$asset['storage_key']) : null;
if ($path === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Obrázek není dostupný.');
}
if ($preview && session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
header('Content-Type: image/jpeg');
header('Content-Length: ' . (string)filesize($path));
// Do not send binary bytes through the HTML CSP output filter.
while (ob_get_level() > 0) {
    ob_end_clean();
}
readfile($path);
