<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/bar/menu_view.php';
if (!isset($pdo) || !$pdo instanceof PDO) {
    throw new RuntimeException('Databázové připojení není dostupné.');
}
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header("Content-Security-Policy: default-src 'none'; style-src 'self'; script-src 'self' 'nonce-" . app_csp_nonce()
    . "'; img-src 'self'; base-uri 'none'; form-action 'none'; frame-ancestors 'self'");
header('X-Content-Type-Options: nosniff');
$view = $_GET['view'] ?? null;
if ($view !== null && !in_array($view, ['text', 'photos'], true)) {
    http_response_code(400);
    exit('Neplatné zobrazení.');
}
$menu = barPublicMenu($pdo);
$setting = $pdo->query("SELECT hodnota FROM nastaveni WHERE klic='bar_display_settings'")->fetchColumn();
$menu['display_settings'] = $setting === false ? [] : json_decode((string)$setting, true, 8, JSON_THROW_ON_ERROR);
echo barMenuHtml($menu, false, $view);
