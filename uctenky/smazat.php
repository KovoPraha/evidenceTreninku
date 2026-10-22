<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/funkce.php';
require_once __DIR__ . '/../csrf_helper.php';
require_once __DIR__ . '/../includes/private_storage.php';
require_once __DIR__ . '/../includes/financial_event_guard.php';
require_once __DIR__ . '/../includes/financial_receipt_files.php';

if (!isset($_SESSION['trener_id']) || !canAccess('uctenky')) {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Neplatný požadavek.';
    header('Location: seznam.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$fileMutations = fileMutationBegin();

if ($id > 0) {
    try {
        $pdo->beginTransaction();
        $zaznam = financialReceiptLock($pdo, $id);
        financialReceiptAssertMutable($pdo, $zaznam, null);
        if (!empty($zaznam['obrazek_path'])) {
            $path = privateStorageResolve((string)$zaznam['obrazek_path']);
            if ($path !== null) fileMutationRetire($fileMutations, $path);
        }
        $pdo->prepare("DELETE FROM ucto_uctenky WHERE id = ?")->execute([$id]);
        zapisAuditLog($pdo, $_SESSION['trener_id'], 'Smazání účtenky', 'ucto_uctenky', $id, json_encode($zaznam));
        $pdo->commit();
        financialReceiptFilesCommitted($fileMutations);
        $_SESSION['flash_success'] = 'Účtenka smazána.';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        fileMutationRollback($fileMutations);
        error_log('uctenky/smazat.php: ' . $e->getMessage());
        $_SESSION['flash_error'] = $e instanceof FinancialEventConflict ? $e->getMessage() : 'Chyba při mazání.';
    }
}

header('Location: seznam.php');
exit;
