<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/funkce.php';
require_once __DIR__ . '/../csrf_helper.php';

if (!isset($_SESSION['trener_id']) || !canAccess('servis')) {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Neplatný požadavek.';
    header('Location: ../vozidla/seznam.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$vozidlo_id = (int)($_POST['vozidlo_id'] ?? 0);

if ($id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM ucto_servis WHERE id = ?");
        $stmt->execute([$id]);
        $zaznam = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($zaznam) {
            if (!empty($zaznam['dokument'])) {
                $filePath = __DIR__ . '/../' . $zaznam['dokument'];
                if (file_exists($filePath)) {
                    $smazano = dirname($filePath) . '/smazano_' . basename($filePath);
                    rename($filePath, $smazano);
                }
            }

            $pdo->prepare("DELETE FROM ucto_servis WHERE id = ?")->execute([$id]);
            zapisAuditLog($pdo, $_SESSION['trener_id'], 'Smazání servisního záznamu', 'ucto_servis', $id, json_encode($zaznam));
            $_SESSION['flash_success'] = 'Servisní záznam smazán.';
        }
    } catch (Exception $e) {
        error_log('servis/smazat.php: ' . $e->getMessage());
        $_SESSION['flash_error'] = 'Chyba při mazání.';
    }
}

header("Location: seznam.php?id=" . $vozidlo_id);
exit;
