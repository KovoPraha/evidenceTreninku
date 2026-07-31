<?php
/**
 * Kontrolní endpoint pro nasazení: stav databázových migrací.
 * Volá ho GitHub Actions po nahrání souborů — ověří, že se migrace
 * opravdu aplikovaly (auto_migrace.php běží při načtení db.php).
 *
 * GET /bin/stav.php?token=<DEPLOY_TOKEN z config.php>
 * Odpověď: JSON { ok, verze_db, verze_kod, vse_aplikovano }
 */

header('Content-Type: application/json; charset=utf-8');

$koren = dirname(__DIR__);

if (!is_file($koren . '/config.php')) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'chyba' => 'Na serveru chybí config.php.'], JSON_UNESCAPED_UNICODE);
    exit;
}
require_once $koren . '/config.php';

if (!defined('DEPLOY_TOKEN') || DEPLOY_TOKEN === '') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'chyba' => 'V config.php chybí konstanta DEPLOY_TOKEN.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!hash_equals((string)DEPLOY_TOKEN, (string)($_GET['token'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'chyba' => 'Neplatný token.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Načtení db.php spustí auto-migraci (includes/auto_migrace.php)
require $koren . '/db.php';
require_once $koren . '/includes/schema_version.php';

$verze_db = '';
try {
    $stmt = $pdo->query("SELECT hodnota FROM nastaveni WHERE klic = 'schema_version'");
    $verze_db = (string)($stmt ? $stmt->fetchColumn() : '');
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'chyba' => 'Nelze přečíst verzi schématu: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'ok'             => true,
    'verze_db'       => $verze_db !== '' ? $verze_db : '(nenastavena)',
    'verze_kod'      => SCHEMA_VERSION,
    'vse_aplikovano' => $verze_db === SCHEMA_VERSION,
    'php'            => PHP_VERSION,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
