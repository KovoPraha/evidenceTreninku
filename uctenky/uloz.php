<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/funkce.php';
require_once __DIR__ . '/../csrf_helper.php';

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

try {
    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM ucto_uctenky WHERE id = ?");
        $stmt->execute([$id]);
        $old_data = $stmt->fetch(PDO::FETCH_ASSOC);

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
        $allowedMime = ['image/jpeg', 'image/png', 'application/pdf'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['obrazek']['tmp_name']);
        finfo_close($finfo);

        if (in_array($mime, $allowedMime)) {
            $upload_dir = __DIR__ . '/../uploads/uctenky/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $ext = strtolower(pathinfo($_FILES['obrazek']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) $ext = 'jpg';
            $filename = "uctenka_{$id}_" . time() . '_' . rand(1000, 9999) . ".{$ext}";
            $full_path = $upload_dir . $filename;

            if (move_uploaded_file($_FILES['obrazek']['tmp_name'], $full_path)) {
                // Přejmenuj starý soubor
                if ($old_data && !empty($old_data['obrazek_path'])) {
                    $oldFile = __DIR__ . '/../' . ltrim($old_data['obrazek_path'], '/');
                    if (file_exists($oldFile)) {
                        $smazany = dirname($oldFile) . '/smazano_' . basename($oldFile);
                        rename($oldFile, $smazany);
                    }
                }

                $obrazek_path = 'uploads/uctenky/' . $filename;
                $pdo->prepare("UPDATE ucto_uctenky SET obrazek_path = ? WHERE id = ?")->execute([$obrazek_path, $id]);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Nepodporovaný typ souboru. Povolené: JPG, PNG, PDF.']);
            exit;
        }
    }

    zapisAuditLog(
        $pdo,
        $_SESSION['trener_id'],
        $id > 0 && $old_data ? 'Úprava účtenky' : 'Přidání účtenky',
        'ucto_uctenky',
        $id,
        json_encode($old_data ?? $_POST)
    );

    echo json_encode(['status' => 'ok']);
} catch (Exception $e) {
    error_log('uctenky/uloz.php: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Chyba při ukládání.']);
}
