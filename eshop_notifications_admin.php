<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/includes/club_event_notification.php';
header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');
if (!isset($_SESSION['trener_id']) || !roleAtLeast('admin')) {
    header('Location: login.php');
    exit;
}
function notificationAdminH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Formulář vypršel. Obnovte stránku.';
    } else {
        try {
            $result = clubEventNotificationAdminRetry(
                $pdo,
                (int)($_POST['notification_id'] ?? 0),
                (int)$_SESSION['trener_id'],
                (string)($_POST['reason'] ?? ''),
                ($_POST['confirm_retry'] ?? '') === '1'
            );
            $_SESSION['flash_notification_success'] = $result['changed']
                ? 'Oznámení bylo bezpečně vráceno na začátek fronty.'
                : 'Oznámení už čeká na nejbližší spuštění workeru.';
            header('Location: eshop_notifications_admin.php', true, 303);
            exit;
        } catch (PDOException $exception) {
            error_log('eshop_notifications_admin.php: ' . $exception->getMessage());
            $errors[] = 'Databázová operace selhala bez částečného zápisu.';
        } catch (InvalidArgumentException | ClubEventNotificationException $exception) {
            $errors[] = $exception->getMessage();
        }
    }
}
$success = (string)($_SESSION['flash_notification_success'] ?? '');
unset($_SESSION['flash_notification_success']);
$status = (string)($_GET['status'] ?? '');
$preview = null;
try {
    $summary = clubEventNotificationAdminSummary($pdo);
    $rows = clubEventNotificationAdminList($pdo, $status);
    if (isset($_GET['preview'])) {
        $preview = clubEventNotificationAdminPreview($pdo, (int)$_GET['preview']);
    }
} catch (InvalidArgumentException | ClubEventNotificationException $exception) {
    $errors[] = $exception->getMessage();
    $status = '';
    $summary = clubEventNotificationAdminSummary($pdo);
    $rows = clubEventNotificationAdminList($pdo);
}
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Fronta transakčních e-mailů</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"></head><body class="bg-light">
<?php include __DIR__ . '/hlavicka.php'; ?><main class="container-fluid py-4" style="max-width:1450px"><div class="d-flex justify-content-between align-items-center mb-3"><div><h1 class="h4 mb-0"><i class="bi bi-envelope-exclamation me-2 text-primary"></i>Fronta transakčních e-mailů</h1><div class="small text-muted">Jedna společná fronta pro oznámení z čekací listiny a potvrzení přijaté platby.</div></div><a href="eshop_admin.php" class="btn btn-outline-secondary btn-sm">Administrace e-shopu</a></div>
<?php foreach($errors as $error):?><div class="alert alert-danger"><?=notificationAdminH($error)?></div><?php endforeach;?><?php if($success!==''):?><div class="alert alert-success"><?=notificationAdminH($success)?></div><?php endif;?>
<div class="row g-3 mb-3"><?php foreach(['pending'=>'Čeká','processing'=>'Zpracovává se','failed'=>'Vyžaduje zásah','sent'=>'Odesláno'] as $key=>$label):?><div class="col-6 col-lg-3"><a class="text-decoration-none text-reset" href="?status=<?=$key?>"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-muted"><?=notificationAdminH($label)?></div><div class="h3 mb-0"><?=(int)$summary[$key]?></div></div></div></a></div><?php endforeach;?></div>
<div class="alert alert-info small">Ruční opakování nikdy neodesílá e-mail z webového požadavku. Pouze auditovaně vrátí položku do fronty; odeslání provede CRON worker mimo databázovou transakci.</div>
<?php if($preview!==null):?><section class="card border-primary shadow-sm mb-3"><div class="card-header bg-primary-subtle d-flex justify-content-between align-items-center"><strong>Náhled přesně uložené zprávy #<?=(int)$preview['id']?></strong><a href="eshop_notifications_admin.php<?= $status!==''?'?status='.rawurlencode($status):'' ?>" class="btn btn-sm btn-outline-secondary">Zavřít náhled</a></div><div class="card-body"><dl class="row small"><dt class="col-sm-2">Příjemce</dt><dd class="col-sm-10"><?=notificationAdminH($preview['recipient_email'])?></dd><dt class="col-sm-2">Předmět</dt><dd class="col-sm-10"><?=notificationAdminH($preview['subject_plain'])?></dd></dl><pre class="mb-0 p-3 bg-body-tertiary border rounded" style="white-space:pre-wrap"><?=notificationAdminH($preview['body_plain'])?></pre></div></section><?php endif;?>
<div class="card border-0 shadow-sm"><div class="card-header bg-white d-flex justify-content-between"><strong><?=notificationAdminH($status===''?'Neodeslaná oznámení':'Filtr: '.$status)?></strong><a href="eshop_notifications_admin.php" class="btn btn-sm btn-outline-secondary">Neodeslaná</a></div><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Stav</th><th>Typ / případ</th><th>Příjemce</th><th>Pokusy</th><th>Dostupné</th><th>Poslední chyba</th><th>Náhled</th><th>Ruční opakování</th></tr></thead><tbody><?php foreach($rows as $row):?><tr><td><span class="badge text-bg-<?=$row['status']==='failed'?'danger':($row['status']==='sent'?'success':($row['status']==='processing'?'info':'warning'))?>"><?=notificationAdminH($row['status'])?></span></td><td><strong><?=notificationAdminH($row['notification_type']==='shop_payment_received'?'Přijatá platba':'Povýšení z čekací listiny')?></strong><div class="small text-muted"><?=notificationAdminH($row['order_public_code']??$row['event_name']??'')?></div><?php if(!empty($row['child_last_name'])||!empty($row['child_first_name'])):?><div class="small text-muted"><?=notificationAdminH(trim((string)$row['child_last_name'].' '.(string)$row['child_first_name']))?></div><?php endif;?></td><td><?=notificationAdminH($row['recipient_email'])?></td><td><?=(int)$row['attempts']?></td><td><?=notificationAdminH($row['available_at'])?></td><td class="small"><?=notificationAdminH($row['last_error']??'')?></td><td><a class="btn btn-sm btn-outline-secondary" href="?preview=<?=(int)$row['id']?><?=$status!==''?'&amp;status='.rawurlencode($status):''?>">Zobrazit text</a></td><td><?php if(in_array($row['status'],['failed','pending'],true)):?><form method="post" class="d-flex flex-column gap-1" style="min-width:250px"><?=csrf_field()?><input type="hidden" name="notification_id" value="<?=(int)$row['id']?>"><input class="form-control form-control-sm" name="reason" maxlength="1000" required placeholder="Důvod ručního opakování"><label class="small"><input type="checkbox" name="confirm_retry" value="1" required> Potvrzuji nové zařazení</label><button class="btn btn-sm btn-outline-primary">Vrátit do fronty</button></form><?php else:?><span class="text-muted small">Nelze během zpracování ani po odeslání</span><?php endif;?></td></tr><?php endforeach;?><?php if($rows===[]):?><tr><td colspan="8" class="text-center text-muted py-4">V tomto filtru nejsou žádná oznámení.</td></tr><?php endif;?></tbody></table></div></div></main></body></html>
