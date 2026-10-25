<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/funkce.php';
require_once __DIR__ . '/../csrf_helper.php';
require_once __DIR__ . '/../includes/private_storage.php';
require_once __DIR__ . '/../includes/financial_event_guard.php';
require_once __DIR__ . '/../includes/financial_receipt_files.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['trener_id']) || !canAccess('uctenky')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Přístup odepřen.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf_token'] ?? '')) {
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

$expenseCaseId=max(0,(int)($_POST['expense_case_id']??0));
$old_data = null;
$fileMutations = fileMutationBegin();

try {
    $pdo->beginTransaction();
    if($expenseCaseId){
        require_once __DIR__.'/../includes/club_administration.php';
        clubAdminFinanceActor($pdo);clubExpenseOpenCase($pdo,$expenseCaseId);
        $expenseCase=clubExpenseOne($pdo,'club_expense_cases',$expenseCaseId);
        if((int)($expenseCase['legacy_event_id']??0)!==(int)$udalost_id)throw new DomainException('Účtenka musí odpovídat výslovně propojené provozní akci.');
    }
    if(isset($_FILES['obrazek'])&&!in_array((int)$_FILES['obrazek']['error'],[UPLOAD_ERR_OK,UPLOAD_ERR_NO_FILE],true))throw new DomainException('Příloha se nenahrála. Vyberte ji znovu a opakujte uložení.');
    if ($id > 0) {
        $old_data = financialReceiptLock($pdo, $id);
    }
    financialReceiptAssertMutable($pdo, $old_data, $udalost_id);

    if ($id > 0) {

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
        $obrazek_path = privateStorageStore(
            (string)$_FILES['obrazek']['tmp_name'],
            PRIVATE_STORAGE_RECEIPTS
        );
        $newPath = privateStorageResolve($obrazek_path);
        if ($newPath === null) throw new RuntimeException('Novou přílohu nelze ověřit.');
        $fileMutations['finalized'][] = $newPath;
        if ($old_data && !empty($old_data['obrazek_path'])) {
            $oldPath = privateStorageResolve((string)$old_data['obrazek_path']);
            if ($oldPath !== null) fileMutationRetire($fileMutations, $oldPath);
        }
        $pdo->prepare("UPDATE ucto_uctenky SET obrazek_path = ? WHERE id = ?")->execute([$obrazek_path, $id]);
    }

    zapisAuditLog(
        $pdo,
        $_SESSION['trener_id'],
        $id > 0 && $old_data ? 'Úprava účtenky' : 'Přidání účtenky',
        'ucto_uctenky',
        $id,
        json_encode($old_data ?? $_POST)
    );

    $pdo->commit();
    financialReceiptFilesCommitted($fileMutations);
    echo json_encode(['status'=>'ok','next'=>$expenseCaseId?'../club_expenses_admin.php?'.http_build_query(['case_id'=>$expenseCaseId,'receipt_id'=>(int)$id]).'#claim-form':'seznam.php']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fileMutationRollback($fileMutations);
    error_log('uctenky/uloz.php: ' . $e->getMessage());
    $message = $e instanceof PDOException ? 'Chyba při ukládání.' : $e->getMessage();
    echo json_encode(['status' => 'error', 'message' => $message]);
}
