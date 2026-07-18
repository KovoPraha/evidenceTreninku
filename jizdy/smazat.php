<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/funkce.php';
require_once __DIR__ . '/../csrf_helper.php';

if (!isset($_SESSION['trener_id']) || !canAccess('jizdy')) {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Neplatný požadavek.';
    header('Location: seznam.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$vozidlo_id = (int)($_POST['vozidlo_id'] ?? 0);

if ($id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM ucto_jizdy WHERE id = ?");
        $stmt->execute([$id]);
        $jizda = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($jizda) {
            $pdo->prepare("DELETE FROM ucto_jizdy WHERE id = ?")->execute([$id]);
            zapisAuditLog($pdo, $_SESSION['trener_id'], 'Smazání jízdy', 'ucto_jizdy', $id, json_encode($jizda));
            $_SESSION['flash_success'] = 'Jízda byla smazána.';
        }
    } catch (Exception $e) {
        error_log('jizdy/smazat.php: ' . $e->getMessage());
        $_SESSION['flash_error'] = 'Chyba při mazání jízdy.';
    }
}

$redirect = 'seznam.php';
if ($vozidlo_id > 0) $redirect .= '?vozidlo_id=' . $vozidlo_id;
header('Location: ' . $redirect);
exit;
