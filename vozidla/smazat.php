<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/funkce.php';
require_once __DIR__ . '/../csrf_helper.php';

if (!isset($_SESSION['trener_id']) || !canAccess('vozidla')) {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Neplatný požadavek.';
    header('Location: seznam.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);

if ($id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM ucto_vozidla WHERE id = ?");
        $stmt->execute([$id]);
        $vozidlo = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($vozidlo) {
            $pdo->prepare("DELETE FROM ucto_vozidla WHERE id = ?")->execute([$id]);
            zapisAuditLog($pdo, $_SESSION['trener_id'], 'Smazání vozidla', 'ucto_vozidla', $id, json_encode($vozidlo));
            $_SESSION['flash_success'] = 'Vozidlo bylo smazáno.';
        }
    } catch (Exception $e) {
        error_log('vozidla/smazat.php: ' . $e->getMessage());
        $_SESSION['flash_error'] = 'Chyba při mazání vozidla.';
    }
}

header('Location: seznam.php');
exit;
