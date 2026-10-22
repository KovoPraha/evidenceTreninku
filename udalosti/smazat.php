<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/funkce.php';
require_once __DIR__ . '/../csrf_helper.php';
require_once __DIR__ . '/../includes/financial_event_guard.php';

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
        $pdo->beginTransaction();
        $udalost = financialEventLock($pdo, $id);
        financialEventAssertOpen($udalost);

        if ($udalost) {
            $pdo->prepare("DELETE FROM ucto_udalosti WHERE id = ?")->execute([$id]);
            zapisAuditLog($pdo, $_SESSION['trener_id'], 'Smazání události', 'ucto_udalosti', $id, json_encode($udalost));
            $pdo->commit();
            $_SESSION['flash_success'] = 'Událost smazána.';
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('udalosti/smazat.php: ' . $e->getMessage());
        $_SESSION['flash_error'] = $e instanceof FinancialEventConflict ? $e->getMessage() : 'Chyba při mazání.';
    }
}

header('Location: seznam.php');
exit;
