<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/funkce.php';
require_once __DIR__ . '/../csrf_helper.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['trener_id']) || !canAccess('udalosti')) {
    echo json_encode(['status' => 'error', 'message' => 'Přístup odepřen.']);
    exit;
}

if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    echo json_encode(['status' => 'error', 'message' => 'Neplatný CSRF token.']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);

if (!$id) {
    echo json_encode(['status' => 'error', 'message' => 'Neplatný požadavek.']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE ucto_udalosti SET stav = 'uzavrena' WHERE id = ?");
    $stmt->execute([$id]);

    zapisAuditLog($pdo, $_SESSION['trener_id'], 'Uzavření události', 'ucto_udalosti', $id, 'stav = uzavrena');

    echo json_encode(['status' => 'ok']);
} catch (Exception $e) {
    error_log('udalosti/uzavrit.php: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Chyba při uzavírání.']);
}
