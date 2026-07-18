<?php
session_start();
if (!isset($_SESSION['trener_id'])) { http_response_code(403); echo json_encode(['error' => 'Unauthorized']); exit; }
require_once 'db.php';
header('Content-Type: application/json; charset=utf-8');

$sportId = (int)($_GET['sportoviste_id'] ?? 0);
$datum   = $_GET['datum']  ?? '';
$casOd   = $_GET['cas_od'] ?? '';
$casDo   = $_GET['cas_do'] ?? '';
$exclId  = (int)($_GET['excl_id'] ?? 0); // rezervace k vyloučení (při editaci)

if (!$sportId || !$datum || !$casOd || !$casDo) {
    echo json_encode(['obsazeno' => 0, 'max' => 5]);
    exit;
}

$stMax = $pdo->prepare("SELECT max_kapacita FROM sportovist WHERE id=?");
$stMax->execute([$sportId]);
$max = (int)($stMax->fetchColumn() ?: 5);

// Pouze týmové rezervace (lekce_id IS NULL) — individuální lekce neblokují kapacitu
$sql = "SELECT COALESCE(SUM(kapacita_dilu), 0) AS obsazeno
        FROM rezervace_sportovist
        WHERE sportoviste_id = ? AND datum = ?
          AND cas_od < ? AND cas_do > ?
          AND lekce_id IS NULL";
$params = [$sportId, $datum, $casDo, $casOd];

if ($exclId > 0) {
    $sql .= " AND id <> ?";
    $params[] = $exclId;
}

$st = $pdo->prepare($sql);
$st->execute($params);
$obsazeno = (int)$st->fetchColumn();

echo json_encode(['obsazeno' => $obsazeno, 'max' => $max]);
