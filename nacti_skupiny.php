<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['trener_id'])) {
    http_response_code(403);
    echo json_encode([]);
    exit;
}

require_once __DIR__ . '/db.php';

$skupina_id = $_GET['skupina_id'] ?? null;
if (!$skupina_id || !is_numeric($skupina_id)) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("SELECT id, nazev FROM podskupiny WHERE skupina_id = ? ORDER BY poradi, nazev");
$stmt->execute([(int)$skupina_id]);

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
