<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/funkce.php';
require_once __DIR__ . '/../csrf_helper.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['trener_id']) || !canAccess('vozidla')) {
    echo json_encode(['status' => 'error', 'message' => 'Přístup odepřen.']);
    exit;
}

if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    echo json_encode(['status' => 'error', 'message' => 'Neplatný CSRF token.']);
    exit;
}

$id            = (int)($_POST['id'] ?? 0);
$znacka_model  = trim($_POST['znacka_model'] ?? '');
$spz           = trim($_POST['spz'] ?? '');
$rok           = $_POST['rok_vyroby'] ?? null;
$palivo        = trim($_POST['palivo'] ?? '');
$stk           = $_POST['stk_datum'] ?? null;
$znamka        = $_POST['dalnicni_znamka_datum'] ?? null;
$poznamka      = trim($_POST['poznamka'] ?? '');

if ($znacka_model === '' || $spz === '') {
    echo json_encode(['status' => 'error', 'message' => 'Značka/model a SPZ jsou povinné.']);
    exit;
}

if (empty($stk))    $stk = null;
if (empty($znamka)) $znamka = null;
if (empty($rok))    $rok = null;

try {
    if ($id > 0) {
        $stmt = $pdo->prepare("
            UPDATE ucto_vozidla
            SET znacka_model = ?, spz = ?, rok_vyroby = ?, palivo = ?,
                stk_datum = ?, dalnicni_znamka_datum = ?, poznamka = ?
            WHERE id = ?
        ");
        $stmt->execute([$znacka_model, $spz, $rok, $palivo, $stk, $znamka, $poznamka, $id]);
        zapisAuditLog($pdo, $_SESSION['trener_id'], 'Úprava vozidla', 'ucto_vozidla', $id, json_encode($_POST));
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO ucto_vozidla (znacka_model, spz, rok_vyroby, palivo, stk_datum, dalnicni_znamka_datum, poznamka)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$znacka_model, $spz, $rok, $palivo, $stk, $znamka, $poznamka]);
        $newId = $pdo->lastInsertId();
        zapisAuditLog($pdo, $_SESSION['trener_id'], 'Přidání vozidla', 'ucto_vozidla', $newId, json_encode($_POST));
    }
    echo json_encode(['status' => 'ok']);
} catch (Exception $e) {
    error_log('vozidla/uloz.php: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Chyba při ukládání.']);
}
