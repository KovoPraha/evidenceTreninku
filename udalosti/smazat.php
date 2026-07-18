<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/funkce.php';
require_once __DIR__ . '/../csrf_helper.php';

if (!isset($_SESSION['trener_id']) || !canAccess('udalosti')) {
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
        $stmt = $pdo->prepare("SELECT * FROM ucto_udalosti WHERE id = ?");
        $stmt->execute([$id]);
        $udalost = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($udalost) {
            $pdo->prepare("DELETE FROM ucto_udalosti WHERE id = ?")->execute([$id]);
            zapisAuditLog($pdo, $_SESSION['trener_id'], 'Smazání události', 'ucto_udalosti', $id, json_encode($udalost));
            $_SESSION['flash_success'] = 'Událost smazána.';
        }
    } catch (Exception $e) {
        error_log('udalosti/smazat.php: ' . $e->getMessage());
        $_SESSION['flash_error'] = 'Chyba při mazání.';
    }
}

header('Location: seznam.php');
exit;
