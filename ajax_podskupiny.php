<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['trener_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

require_once __DIR__ . '/db.php';

$gid = $_GET['skupina_id'] ?? '';
if ($gid === '' || !ctype_digit($gid)) {
    echo json_encode(['ok' => true, 'items' => []]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT id, nazev
    FROM podskupiny
    WHERE skupina_id = ?
    ORDER BY poradi, nazev
");
$stmt->execute([(int)$gid]);

echo json_encode([
    'ok' => true,
    'items' => $stmt->fetchAll(PDO::FETCH_ASSOC),
]);
