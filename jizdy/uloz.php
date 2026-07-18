<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/funkce.php';
require_once __DIR__ . '/../csrf_helper.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['trener_id']) || !canAccess('jizdy')) {
    echo json_encode(['status' => 'error', 'message' => 'Přístup odepřen.']);
    exit;
}

if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    echo json_encode(['status' => 'error', 'message' => 'Neplatný CSRF token.']);
    exit;
}

$vozidlo_id    = (int)($_POST['vozidlo_id'] ?? 0);
$ridic_id      = (!empty($_POST['ridic_id']) && $_POST['ridic_id'] !== '0') ? (int)$_POST['ridic_id'] : null;
$ridic_text    = trim($_POST['ridic_text'] ?? '');
$fazejizdy     = $_POST['fazejizdy'] ?? '';
$stav_km       = isset($_POST['stav_km']) ? (int)$_POST['stav_km'] : null;
$poloha        = trim($_POST['poloha'] ?? '');
$poznamka      = trim($_POST['poznamka'] ?? '');

if (!$vozidlo_id) {
    echo json_encode(['status' => 'error', 'message' => 'Musíte vybrat vozidlo.']);
    exit;
}
if (!in_array($fazejizdy, ['start', 'end'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Neplatná fáze jízdy.']);
    exit;
}
if ($stav_km === null || $stav_km < 0) {
    echo json_encode(['status' => 'error', 'message' => 'Musíte zadat stav tachometru.']);
    exit;
}

try {
    $now = date('Y-m-d H:i:s');
    $userId = $_SESSION['trener_id'];

    if ($fazejizdy === 'start') {
        // Kontrola: není už otevřená jízda pro toto vozidlo?
        $chk = $pdo->prepare("SELECT id FROM ucto_jizdy WHERE vozidlo_id = ? AND datum_konec IS NULL LIMIT 1");
        $chk->execute([$vozidlo_id]);
        if ($chk->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'Pro toto vozidlo již existuje otevřená jízda. Nejprve ji ukončete.']);
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO ucto_jizdy (vozidlo_id, datum_start, tachometr_start, poloha_start, ridic_id, ridic_text, poznamka)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$vozidlo_id, $now, $stav_km, $poloha, $ridic_id, $ridic_text, $poznamka]);
        $jizdaId = $pdo->lastInsertId();
        zapisAuditLog($pdo, $userId, 'Zahájení jízdy', 'ucto_jizdy', $jizdaId, json_encode($_POST));

    } else {
        // Ukončení: najdi otevřenou jízdu
        $stmt = $pdo->prepare("
            SELECT id, tachometr_start FROM ucto_jizdy
            WHERE vozidlo_id = ? AND datum_konec IS NULL
            ORDER BY datum_start DESC LIMIT 1
        ");
        $stmt->execute([$vozidlo_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            echo json_encode(['status' => 'error', 'message' => 'Nenalezena otevřená jízda pro zvolené vozidlo.']);
            exit;
        }

        if ($stav_km < (int)$row['tachometr_start']) {
            echo json_encode(['status' => 'error', 'message' => 'Konečný stav tachometru nesmí být nižší než počáteční (' . $row['tachometr_start'] . ' km).']);
            exit;
        }

        $jizdaId = (int)$row['id'];
        $u = $pdo->prepare("
            UPDATE ucto_jizdy
            SET datum_konec = ?, tachometr_konec = ?, poloha_konec = ?, ridic_id = ?, ridic_text = ?, poznamka = CONCAT(COALESCE(poznamka,''), CASE WHEN poznamka IS NOT NULL AND poznamka != '' AND ? != '' THEN ' | ' ELSE '' END, ?)
            WHERE id = ?
        ");
        $u->execute([$now, $stav_km, $poloha, $ridic_id, $ridic_text, $poznamka, $poznamka, $jizdaId]);
        zapisAuditLog($pdo, $userId, 'Ukončení jízdy', 'ucto_jizdy', $jizdaId, json_encode($_POST));
    }

    echo json_encode(['status' => 'ok']);
} catch (Exception $e) {
    error_log('jizdy/uloz.php: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Chyba při ukládání.']);
}
