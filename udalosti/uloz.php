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

$id          = (int)($_POST['id'] ?? 0);
$nazev       = trim($_POST['nazev'] ?? '');
$popis       = trim($_POST['popis'] ?? '');
$datum_od    = $_POST['datum_od'] ?? null;
$datum_do    = $_POST['datum_do'] ?? null;
$typ         = $_POST['typ'] ?? 'jine';
$vytvoril_id = $_SESSION['trener_id'];

$zalohova_castka = isset($_POST['zalohova_castka']) && $_POST['zalohova_castka'] !== '' ? (float)$_POST['zalohova_castka'] : null;
$zalohu_predal   = trim($_POST['zalohu_predal'] ?? '');
$stav            = $_POST['stav'] ?? 'otevrena';

if (!$nazev || !$datum_od || !$datum_do || !$typ) {
    echo json_encode(['status' => 'error', 'message' => 'Chybí povinné údaje (název, datum od, datum do, typ).']);
    exit;
}

try {
    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM ucto_udalosti WHERE id = ?");
        $stmt->execute([$id]);
        $old_data = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("
            UPDATE ucto_udalosti
            SET nazev = ?, popis = ?, datum_od = ?, datum_do = ?, typ = ?,
                zalohova_castka = ?, zalohu_predal = ?, stav = ?
            WHERE id = ?
        ");
        $stmt->execute([$nazev, $popis, $datum_od, $datum_do, $typ, $zalohova_castka, $zalohu_predal, $stav, $id]);
        zapisAuditLog($pdo, $vytvoril_id, 'Úprava události', 'ucto_udalosti', $id, json_encode($old_data));
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO ucto_udalosti
            (nazev, popis, datum_od, datum_do, typ, vytvoril_id, vytvoreno, zalohova_castka, zalohu_predal, stav)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)
        ");
        $stmt->execute([$nazev, $popis, $datum_od, $datum_do, $typ, $vytvoril_id, $zalohova_castka, $zalohu_predal, $stav]);
        $new_id = $pdo->lastInsertId();
        zapisAuditLog($pdo, $vytvoril_id, 'Přidání události', 'ucto_udalosti', $new_id, json_encode($_POST));
    }

    echo json_encode(['status' => 'ok']);
} catch (Exception $e) {
    error_log('udalosti/uloz.php: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Chyba při ukládání.']);
}
