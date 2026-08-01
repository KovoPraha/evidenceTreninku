<?php
require_once __DIR__ . '/includes/session_security.php';
/**
 * ajax_nova_oznameni.php
 * Rychlé vytvoření oznámení pro skupiny z plánovače.
 * POST JSON: csrf_token, nazev, obsah, datum, skupiny_ids[]
 */
app_session_start();
if (!isset($_SESSION['trener_id'])) { http_response_code(403); echo json_encode(['ok'=>false,'msg'=>'Nepřihlášen']); exit; }
require_once 'includes/funkce.php';
if (!canAccess('oznameni')) { http_response_code(403); echo json_encode(['ok'=>false,'msg'=>'Přístup odepřen']); exit; }
require_once 'db.php';
require_once 'csrf_helper.php';

header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true) ?: [];

if (!csrf_verify($data['csrf_token'] ?? '')) {
    echo json_encode(['ok'=>false,'msg'=>'Neplatný CSRF token']); exit;
}

$trenerId   = (int)$_SESSION['trener_id'];
$nazev      = trim($data['nazev'] ?? '');
$obsah      = trim($data['obsah'] ?? '');
$datum      = trim($data['datum'] ?? date('Y-m-d'));
$skupinyIds = array_values(array_filter(array_map('intval', $data['skupiny_ids'] ?? [])));

if (!$nazev)             { echo json_encode(['ok'=>false,'msg'=>'Chybí titulek']); exit; }
if (!$obsah)             { echo json_encode(['ok'=>false,'msg'=>'Chybí text']); exit; }
if (empty($skupinyIds))  { echo json_encode(['ok'=>false,'msg'=>'Vyberte skupiny']); exit; }
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) $datum = date('Y-m-d');

// Escapovat obsah pro bezpečné uložení jako HTML
$obsahHtml = nl2br(htmlspecialchars($obsah, ENT_QUOTES, 'UTF-8'));

$pdo->beginTransaction();
try {
    $pdo->prepare("
        INSERT INTO oznameni (nazev, obsah_html, datum, vlozil_trener_id)
        VALUES (?,?,?,?)
    ")->execute([$nazev, $obsahHtml, $datum, $trenerId]);
    $ozId = (int)$pdo->lastInsertId();

    $stTgt = $pdo->prepare("
        INSERT IGNORE INTO oznameni_targets (oznameni_id, target_type, target_id) VALUES (?,?,?)
    ");
    foreach ($skupinyIds as $skId) {
        $stTgt->execute([$ozId, 'skupina', $skId]);
    }

    $pdo->commit();
    echo json_encode(['ok'=>true,'id'=>$ozId]);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('ajax_nova_oznameni: ' . $e->getMessage());
    echo json_encode(['ok'=>false,'msg'=>'Chyba při ukládání']);
}
