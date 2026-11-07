<?php

declare(strict_types=1);

// Machine-only entry point: no session, human cookie fallback, migration or schema bootstrap.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
require_once __DIR__ . '/includes/external_sync_ingest.php';

try {
    $authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!is_string($authorization) || !preg_match('/^Bearer ([a-f0-9]{64})$/D', $authorization, $match)) {
        externalSyncReject('unauthorized', 401);
    }
    if (is_file(__DIR__ . '/config.php')) {
        require_once __DIR__ . '/config.php';
    }
    if (!defined('KIS_EXTERNAL_SYNC_ENABLED') || KIS_EXTERNAL_SYNC_ENABLED !== true) {
        externalSyncReject('integration_disabled', 503);
    }
    // No trust in client forwarded headers. Configure TLS at the existing web server.
    if (($_SERVER['HTTPS'] ?? '') !== 'on' && ($_SERVER['HTTPS'] ?? '') !== '1') {
        externalSyncReject('https_required', 403);
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        externalSyncReject('post_required', 405);
    }
    if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
        externalSyncReject('json_required', 415);
    }
    $stream = fopen('php://input', 'rb');
    $raw = stream_get_contents($stream, 1048577);
    fclose($stream);
    if ($raw === false || strlen($raw) > 1048576) {
        externalSyncReject('payload_too_large', 413);
    }
    if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS')) {
        externalSyncReject('storage_unavailable', 503);
    }
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $action = $_GET['action'] ?? '';
    if (!is_string($action)) {
        externalSyncReject('unknown_action', 400);
    }
    echo externalSyncJson(externalSyncDispatch($pdo, $match[1], $action, $raw));
} catch (ExternalSyncException $error) {
    http_response_code($error->getCode());
    echo externalSyncJson(['error' => $error->getMessage()]);
} catch (Throwable) {
    // Do not expose PDO messages, SQL arguments, credentials or the request body.
    http_response_code(503);
    echo '{"error":"storage_unavailable"}';
}
