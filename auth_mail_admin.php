<?php
declare(strict_types=1);

require_once __DIR__.'/includes/init.php';
require_once __DIR__.'/csrf_helper.php';
require_once __DIR__.'/includes/auth_mail_outbox.php';
header('Cache-Control: no-store, private, max-age=0');
if (!isset($_SESSION['trener_id']) || !roleAtLeast('admin')) {
    header('Location: login.php');
    exit;
}
function authMailAdminH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
$errors=[];
if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
    if (!csrf_verify((string)($_POST['csrf_token']??''))) $errors[]='Formulář vypršel. Obnovte stránku.';
    else {
        try {
            authMailAdminRetry($pdo,(int)($_POST['message_id']??0),(int)$_SESSION['trener_id'],(string)($_POST['reason']??''),($_POST['confirm_retry']??'')==='1');
            $_SESSION['flash_auth_mail']='Platný odkaz byl auditovaně vrácen do fronty.';
            header('Location: auth_mail_admin.php?status=failed',true,303);
            exit;
        } catch (InvalidArgumentException|AuthMailOutboxException $exception) {
            $errors[]=$exception->getMessage();
        } catch (Throwable $exception) {
            error_log('auth_mail_admin.php: '.get_class($exception));
            $errors[]='Databázová operace se nepodařila.';
        }
    }
}
$status=(string)($_GET['status']??'');
try {
    $summary=authMailSummary($pdo);
    $rows=authMailAdminList($pdo,$status);
} catch (InvalidArgumentException $exception) {
    $status='';$errors[]=$exception->getMessage();
    $summary=authMailSummary($pdo);$rows=authMailAdminList($pdo);
}
$success=(string)($_SESSION['flash_auth_mail']??'');unset($_SESSION['flash_auth_mail']);
?><!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Ověřovací a obnovovací e-maily</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous"></head><body class="bg-light">
<?php include __DIR__.'/hlavicka.php';?><main class="container-fluid py-4" style="max-width:1450px"><div class="d-flex justify-content-between align-items-center mb-3"><div><h1 class="h4 mb-1">Ověřovací a obnovovací e-maily</h1><p class="text-muted mb-0">Obsah odkazů je zašifrovaný; administrace nezobrazuje token ani text zprávy.</p></div><a href="eshop_notifications_admin.php" class="btn btn-outline-secondary btn-sm">Ostatní e-maily</a></div>
<?php foreach($errors as $error):?><div class="alert alert-danger"><?=authMailAdminH($error)?></div><?php endforeach;?><?php if($success!==''):?><div class="alert alert-success"><?=authMailAdminH($success)?></div><?php endif;?>
<div class="row g-3 mb-3"><?php foreach(['pending'=>'Čeká','processing'=>'Zpracovává se','failed'=>'Vyžaduje zásah','sent'=>'Odesláno','cancelled'=>'Neplatné / nahrazené'] as $key=>$label):?><div class="col-6 col-lg"><a class="text-decoration-none text-reset" href="?status=<?=$key?>"><div class="card shadow-sm border-0"><div class="card-body"><div class="small text-muted"><?=authMailAdminH($label)?></div><div class="h3 mb-0"><?=(int)($summary[$key]??0)?></div></div></div></a></div><?php endforeach;?></div>
<div class="alert alert-info small">Opakování pouze zařadí stále platný odkaz do fronty. Pokud již vypršel, požádejte uživatele o nový odkaz; starý znovu neodesílejte.</div>
<div class="card border-0 shadow-sm"><div class="card-header d-flex justify-content-between"><strong><?=authMailAdminH($status===''?'Čekající a selhané zprávy':'Filtr: '.$status)?></strong><a href="auth_mail_admin.php" class="btn btn-sm btn-outline-secondary">Aktivní fronta</a></div><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Stav</th><th>Typ</th><th>Příjemce</th><th>Pokusy</th><th>Další pokus</th><th>Chyba</th><th>Opakování</th></tr></thead><tbody><?php foreach($rows as $row):?><tr><td><span class="badge text-bg-<?=($row['status']==='failed'?'danger':($row['status']==='sent'?'success':'secondary'))?>"><?=authMailAdminH($row['status'])?></span></td><td><?=authMailAdminH($row['kind']==='verify'?'Ověření účtu':'Obnova hesla')?> <span class="text-muted">#<?=(int)$row['id']?></span></td><td><?=authMailAdminH($row['recipient_email'])?></td><td><?=(int)$row['attempts']?></td><td><?=authMailAdminH($row['available_at'])?></td><td class="small"><?=authMailAdminH($row['last_error']??'')?></td><td><?php if($row['status']==='failed'):?><form method="post" class="d-flex flex-column gap-1" style="min-width:240px"><?=csrf_field()?><input type="hidden" name="message_id" value="<?=(int)$row['id']?>"><input class="form-control form-control-sm" name="reason" maxlength="1000" required placeholder="Důvod opakování"><label class="small"><input type="checkbox" name="confirm_retry" value="1" required> Potvrzuji opakování</label><button class="btn btn-outline-primary btn-sm">Vrátit do fronty</button></form><?php else:?><span class="text-muted small">–</span><?php endif;?></td></tr><?php endforeach;?><?php if($rows===[]):?><tr><td colspan="7" class="text-center text-muted py-4">Žádné zprávy v tomto filtru.</td></tr><?php endif;?></tbody></table></div></div></main></body></html>
