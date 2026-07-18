<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/funkce.php';
require_once __DIR__ . '/../csrf_helper.php';

if (!isset($_SESSION['trener_id']) || !canAccess('servis')) {
    header('Location: ../login.php');
    exit;
}

if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Neplatný CSRF token.';
    header('Location: seznam.php?id=' . (int)($_POST['vozidlo_id'] ?? 0));
    exit;
}

$id         = (int)($_POST['id'] ?? 0);
$vozidlo_id = (int)($_POST['vozidlo_id'] ?? 0);
$popis      = trim($_POST['popis'] ?? '');
$provedeno  = $_POST['provedeno_dne'] ?? '';
$planovana  = $_POST['planovana_kontrola'] ?? null;

if (empty($popis) || empty($provedeno)) {
    $_SESSION['flash_error'] = 'Popis a datum provedení jsou povinné.';
    header('Location: formular.php?vozidlo_id=' . $vozidlo_id . ($id ? '&id=' . $id : ''));
    exit;
}

if (empty($planovana)) $planovana = null;

$dokument_path = null;
$old_data = null;

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM ucto_servis WHERE id = ?");
    $stmt->execute([$id]);
    $old_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $dokument_path = $old_data['dokument'] ?? null;
}

// Upload dokumentu s MIME validací
if (!empty($_FILES['dokument']['name']) && $_FILES['dokument']['error'] === UPLOAD_ERR_OK) {
    $allowedMime = ['application/pdf', 'image/jpeg', 'image/png'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $_FILES['dokument']['tmp_name']);
    finfo_close($finfo);

    if (in_array($mime, $allowedMime)) {
        $upload_dir = __DIR__ . '/../uploads/servis/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // Přípona z OVĚŘENÉHO MIME, ne z názvu souboru (jinak lze uložit spustitelný .php)
        $mimeExt = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
        $ext = $mimeExt[$mime] ?? 'bin';
        $new_name = 'servis_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        $target_file = $upload_dir . $new_name;

        if (move_uploaded_file($_FILES['dokument']['tmp_name'], $target_file)) {
            // Přejmenuj starý dokument
            if ($id > 0 && !empty($old_data['dokument'])) {
                $oldFile = __DIR__ . '/../' . $old_data['dokument'];
                if (file_exists($oldFile)) {
                    $smazany = dirname($oldFile) . '/smazano_' . basename($oldFile);
                    rename($oldFile, $smazany);
                }
            }
            $dokument_path = 'uploads/servis/' . $new_name;
        }
    } else {
        $_SESSION['flash_error'] = 'Nepodporovaný typ souboru. Povolené: PDF, JPG, PNG.';
        header('Location: formular.php?vozidlo_id=' . $vozidlo_id . ($id ? '&id=' . $id : ''));
        exit;
    }
}

try {
    if ($id > 0) {
        $stmt = $pdo->prepare("
            UPDATE ucto_servis
            SET popis = ?, provedeno_dne = ?, planovana_kontrola = ?, dokument = ?
            WHERE id = ?
        ");
        $stmt->execute([$popis, $provedeno, $planovana, $dokument_path, $id]);
        zapisAuditLog($pdo, $_SESSION['trener_id'], 'Úprava servisního záznamu', 'ucto_servis', $id, json_encode($old_data));
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO ucto_servis (vozidlo_id, popis, provedeno_dne, planovana_kontrola, dokument, vytvoril_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$vozidlo_id, $popis, $provedeno, $planovana, $dokument_path, $_SESSION['trener_id']]);
        $new_id = $pdo->lastInsertId();
        zapisAuditLog($pdo, $_SESSION['trener_id'], 'Přidání servisního záznamu', 'ucto_servis', $new_id, json_encode($_POST));
    }

    $_SESSION['flash_success'] = 'Servisní záznam uložen.';
} catch (Exception $e) {
    error_log('servis/uloz.php: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Chyba při ukládání.';
}

header("Location: seznam.php?id=" . $vozidlo_id);
exit;
