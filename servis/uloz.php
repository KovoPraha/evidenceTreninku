<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/funkce.php';
require_once __DIR__ . '/../csrf_helper.php';
require_once __DIR__ . '/../includes/private_storage.php';

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
$newDocumentKey = null;
$oldDocumentToDelete = null;

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM ucto_servis WHERE id = ?");
    $stmt->execute([$id]);
    $old_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $dokument_path = $old_data['dokument'] ?? null;
}

// Dokument je ulozen mimo webroot; prime URL k internim dokladum neexistuje.
if (!empty($_FILES['dokument']['name']) && $_FILES['dokument']['error'] === UPLOAD_ERR_OK) {
    $size = filesize((string)$_FILES['dokument']['tmp_name']);
    if (!is_int($size) || $size < 1 || $size > 10 * 1024 * 1024) {
        $_SESSION['flash_error'] = 'Dokument musí mít nejvýše 10 MB.';
        header('Location: formular.php?vozidlo_id=' . $vozidlo_id . ($id ? '&id=' . $id : ''));
        exit;
    }
    try {
        $newDocumentKey = privateStorageStore(
            (string)$_FILES['dokument']['tmp_name'],
            PRIVATE_STORAGE_SERVICE_DOCUMENTS
        );
        $dokument_path = $newDocumentKey;
        $oldDocumentToDelete = $id > 0 ? (string)($old_data['dokument'] ?? '') : null;
    } catch (Throwable $exception) {
        error_log('servis/uloz.php private upload: ' . $exception->getMessage());
        $_SESSION['flash_error'] = 'Dokument musí být skutečný PDF, JPG nebo PNG soubor.';
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

    if (is_string($oldDocumentToDelete) && $oldDocumentToDelete !== '') {
        if (str_starts_with($oldDocumentToDelete, 'private://')) {
            privateStorageSoftDelete($oldDocumentToDelete);
        } else {
            $legacy = __DIR__ . '/../' . ltrim($oldDocumentToDelete, '/\\');
            if (is_file($legacy)) rename($legacy, dirname($legacy) . '/smazano_' . basename($legacy));
        }
    }
    $_SESSION['flash_success'] = 'Servisní záznam uložen.';
} catch (Throwable $e) {
    if (is_string($newDocumentKey) && $newDocumentKey !== '') {
        try { privateStorageSoftDelete($newDocumentKey); } catch (Throwable) {}
    }
    error_log('servis/uloz.php: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Chyba při ukládání.';
}

header("Location: seznam.php?id=" . $vozidlo_id);
exit;
