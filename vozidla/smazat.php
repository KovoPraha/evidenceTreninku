<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/funkce.php';
require_once __DIR__ . '/../csrf_helper.php';
require_once __DIR__ . '/../includes/financial_event_guard.php';

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
        $pdo->beginTransaction();
        // Deleting a vehicle sets receipt.vozidlo_id to NULL through its FK.
        // Lock the parent first so no new receipt can acquire this reference.
        $stmt = $pdo->prepare("SELECT * FROM ucto_vozidla WHERE id = ?" . financialEventLockSuffix($pdo));
        $stmt->execute([$id]);
        $vozidlo = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($vozidlo) {
            $receipts = $pdo->prepare('SELECT * FROM ucto_uctenky WHERE vozidlo_id = ? ORDER BY id'
                . financialEventLockSuffix($pdo));
            $receipts->execute([$id]);
            foreach ($receipts->fetchAll(PDO::FETCH_ASSOC) as $receipt) {
                financialReceiptAssertMutable($pdo, $receipt, null);
            }
            $pdo->prepare("DELETE FROM ucto_vozidla WHERE id = ?")->execute([$id]);
            zapisAuditLog($pdo, $_SESSION['trener_id'], 'Smazání vozidla', 'ucto_vozidla', $id, json_encode($vozidlo));
            $_SESSION['flash_success'] = 'Vozidlo bylo smazáno.';
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('vozidla/smazat.php: ' . $e->getMessage());
        $_SESSION['flash_error'] = $e instanceof FinancialEventConflict
            ? $e->getMessage() . ' Vozidlo nebylo smazáno.'
            : 'Chyba při mazání vozidla.';
    }
}

header('Location: seznam.php');
exit;
