<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/bar/offers.php';
require_once dirname(__DIR__) . '/includes/bar/transfer_assets.php';
if (!isset($pdo) || !$pdo instanceof PDO) {
    throw new RuntimeException('Databázové připojení není dostupné.');
}
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
$id = (int)($_GET['id'] ?? 0);
$menu = barPublicMenu($pdo);
if ($id < 1 || !in_array($id, array_map('intval', array_column($menu['products'], 'image_id')), true)) {
    http_response_code(404);
    exit;
}
$query = $pdo->prepare('SELECT * FROM kis_bar_assets WHERE id=?');
$query->execute([$id]);
$asset = $query->fetch(PDO::FETCH_ASSOC);
try {
    $path = $asset ? barTransferAssetPath($asset) : throw new RuntimeException('Fotografie chybí.');
} catch (RuntimeException $error) {
    http_response_code(404);
    exit;
}
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . $asset['mime_type']);
readfile($path);
