<?php
require_once __DIR__ . '/includes/session_security.php';
/**
 * AJAX endpoint: inline editace poznámky tréninku
 * POST: trenink_id (int), poznamka (string)
 * Odpovídá JSON: {ok: bool, message: string}
 */
app_session_start();
require_once __DIR__ . '/includes/funkce.php';
require_once __DIR__ . '/csrf_helper.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['trener_id'])) {
    echo json_encode(['ok' => false, 'message' => 'Nepřihlášen.']);
    exit;
}
if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Neplatný CSRF token.']);
    exit;
}
require_once __DIR__ . '/db.php';

$treninkId = $_POST['trenink_id'] ?? '';
$poznamka  = $_POST['poznamka'] ?? '';

if (!ctype_digit((string)$treninkId) || (int)$treninkId <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Neplatné ID tréninku.']);
    exit;
}
$treninkId = (int)$treninkId;
$userId    = (int)$_SESSION['trener_id'];
$isAdmin   = canAccess('sprava_treninku');

// Ověření oprávnění: musí být přiřazen k tréninku nebo admin
if (!$isAdmin) {
    $stmtAuth = $pdo->prepare('SELECT 1 FROM trenink_trener WHERE trenink_id = :tid AND trener_id = :uid LIMIT 1');
    $stmtAuth->execute([':tid' => $treninkId, ':uid' => $userId]);
    if (!$stmtAuth->fetchColumn()) {
        echo json_encode(['ok' => false, 'message' => 'Nemáte oprávnění upravit tento trénink.']);
        exit;
    }
}

$poznamkaClean = trim((string)$poznamka);

try {
    $stmt = $pdo->prepare('UPDATE treninky SET poznamka = :poz WHERE id = :id');
    $stmt->execute([
        ':poz' => $poznamkaClean === '' ? null : $poznamkaClean,
        ':id'  => $treninkId,
    ]);
    echo json_encode(['ok' => true, 'message' => 'Uloženo.', 'poznamka' => $poznamkaClean]);
} catch (Exception $e) {
    error_log('ajax_update_poznamka: '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Poznámku se nepodařilo bezpečně uložit.']);
}
