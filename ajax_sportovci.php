<?php

require_once __DIR__ . '/includes/session_security.php';
app_session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (!isset($_SESSION['trener_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Neautorizováno']);
    exit;
}
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/sportovec_search.php';
if (!is_string($_GET['q'] ?? '') || mb_strlen($_GET['q'] ?? '', 'UTF-8') > 200) {
    http_response_code(400);
    echo json_encode(['error' => 'Zadejte hledaný text do 200 znaků.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$limit = isset($_GET['limit']) && is_string($_GET['limit']) && ctype_digit($_GET['limit'])
    ? (int)$_GET['limit'] : 20;
try {
    echo json_encode(sportovecSearch($pdo, $_GET['q'] ?? '', $limit, ($_GET['browse'] ?? '') === '1', ($_GET['context'] ?? '') === 'training'), JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    error_log('ajax_sportovci: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Vyhledávání momentálně není dostupné.'], JSON_UNESCAPED_UNICODE);
}
