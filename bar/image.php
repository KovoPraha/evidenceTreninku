<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bar/bootstrap.php';
$pdo = barBootstrap();
require_once dirname(__DIR__) . '/includes/bar/web.php';
require_once dirname(__DIR__) . '/includes/bar/transfer_assets.php';
barRequireWebActor($pdo, 'catalog_read');
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
try {
    $asset = $id === false ? null : barCatalogAsset($pdo, $id);
    if ($asset === null) {
        throw new RuntimeException('Fotografie nebyla nalezena.');
    }
    $path = barTransferAssetPath($asset);
} catch (RuntimeException $error) {
    http_response_code(404);
    exit;
}
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . $asset['mime_type']);
header('Content-Disposition: inline');
readfile($path);
