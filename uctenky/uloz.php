<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/funkce.php';
require_once __DIR__ . '/../csrf_helper.php';
require_once __DIR__ . '/../includes/private_storage.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['trener_id']) || !canAccess('uctenky')) {
    echo json_encode(['status' => 'error', 'message' => 'Přístup odepřen.']);
    exit;
}

if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    echo json_encode(['status' => 'error', 'message' => 'Neplatný CSRF token.']);
    exit;
}

$id             = (int)($_POST['id'] ?? 0);
$castka         = floatval($_POST['castka'] ?? 0);
$platba         = $_POST['platba'] ?? 'hotove';
$kategorie      = trim($_POST['kategorie'] ?? '');
$vozidlo_id     = ($_POST['vozidlo_id'] ?? '') !== '' ? (int)$_POST['vozidlo_id'] : null;
$udalost_id     = ($_POST['udalost_id'] ?? '') !== '' ? (int)$_POST['udalost_id'] : null;
$poznamka       = trim($_POST['poznamka'] ?? '');
$nahrano_kym    = ($_POST['nahrano_kym'] ?? '') !== '' ? (int)$_POST['nahrano_kym'] : null;
$nahrano_jmenem = trim($_POST['nahrano_jmenem'] ?? '');
$datum          = $_POST['datum'] ?? date('Y-m-d H:i:s');

if ($castka <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Částka musí být větší než 0.']);
    exit;
}
if ($kategorie === '') {
    echo json_encode(['status' => 'error', 'message' => 'Kategorie musí být vybrána.']);
    exit;
}

$old_data = null;
$newReceiptKey = null;
$oldReceiptToDelete = null;

try {
    $pdo->beginTransaction();
    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM ucto_uctenky WHERE id = ?");
        $stmt->execute([$id]);
        $old_data = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$old_data) throw new RuntimeException('Účtenka nebyla nalezena.');

        $stmt = $pdo->prepare("UPDATE ucto_uctenky SET
            castka = ?, platba = ?, kategorie = ?, vozidlo_id = ?, udalost_id = ?,
            poznamka = ?, nahrano_kym = ?, nahrano_jmenem = ?, datum = ?
        WHERE id = ?");
        $stmt->execute([
            $castka, $platba, $kategorie,
            $vozidlo_id, $udalost_id,
            $poznamka, $nahrano_kym, $nahrano_jmenem,
            $datum, $id
        ]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO ucto_uctenky
            (castka, platba, kategorie, vozidlo_id, udalost_id, poznamka, nahrano_kym, nahrano_jmenem, datum)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $castka, $platba, $kategorie,
            $vozidlo_id, $udalost_id,
            $poznamka, $nahrano_kym, $nahrano_jmenem,
            $datum
        ]);
        $id = $pdo->lastInsertId();
    }

    // Upload obrázku s MIME validací
    if (!empty($_FILES['obrazek']['name']) && $_FILES['obrazek']['error'] === UPLOAD_ERR_OK) {
        $obrazek_path = privateStorageStore(
            (string)$_FILES['obrazek']['tmp_name'],
            PRIVATE_STORAGE_RECEIPTS
        );
        $newReceiptKey = $obrazek_path;
        if ($old_data && !empty($old_data['obrazek_path'])) {
            $oldReceiptToDelete = (string)$old_data['obrazek_path'];
        }
        $pdo->prepare("UPDATE ucto_uctenky SET obrazek_path = ? WHERE id = ?")->execute([$obrazek_path, $id]);
    }

    zapisAuditLog(
        $pdo,
        $_SESSION['trener_id'],
        $id > 0 && $old_data ? 'Úprava účtenky' : 'Přidání účtenky',
        'ucto_uctenky',
        $id,
        json_encode($old_data ?? $_POST)
    );

    $pdo->commit();
    if (is_string($oldReceiptToDelete) && $oldReceiptToDelete !== '') {
        try { privateStorageSoftDelete($oldReceiptToDelete); } catch (Throwable $cleanupException) {
            error_log('uctenky/uloz.php old receipt cleanup: ' . $cleanupException->getMessage());
        }
    }
    echo json_encode(['status' => 'ok']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if (is_string($newReceiptKey) && $newReceiptKey !== '') {
        try { privateStorageSoftDelete($newReceiptKey); } catch (Throwable) {}
    }
    error_log('uctenky/uloz.php: ' . $e->getMessage());
    $message = $e instanceof PDOException ? 'Chyba při ukládání.' : $e->getMessage();
    echo json_encode(['status' => 'error', 'message' => $message]);
}
