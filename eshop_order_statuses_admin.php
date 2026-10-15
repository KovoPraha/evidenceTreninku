<?php
declare(strict_types=1);

require_once __DIR__.'/includes/init.php';
require_once __DIR__.'/csrf_helper.php';
require_once __DIR__.'/includes/shop_order_customer_status.php';
if (!isset($_SESSION['trener_id']) || !roleAtLeast('admin')) { header('Location: login.php'); exit; }
header('Cache-Control: no-store, private, max-age=0');
function statusAdminH(mixed $value): string { return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }

$errors=[];
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!csrf_verify((string)($_POST['csrf_token']??''))) $errors[]='Formulář vypršel. Obnovte stránku.';
    else {
        try {
            if (($_POST['confirm_action']??'')!=='1') throw new InvalidArgumentException('Změnu nejprve potvrďte.');
            $result=shopOrderCustomerStatusSave($pdo,(int)$_SESSION['trener_id'],(int)($_POST['definition_id']??0),$_POST,(string)($_POST['reason']??''));
            $_SESSION['flash_status_admin']=$result['changed']?'Stav a nastavení e-mailu byly uloženy.':'Nic se nezměnilo.';
            header('Location: eshop_order_statuses_admin.php#status-'.(int)$result['id'],true,303);exit;
        } catch (PDOException $exception) {
            error_log('eshop_order_statuses_admin.php: '.$exception->getMessage());
            $errors[]='Stav se nepodařilo uložit. Zkontrolujte, zda název již neexistuje.';
        } catch (InvalidArgumentException|ShopOrderCustomerStatusException $exception) { $errors[]=$exception->getMessage(); }
    }
}
$success=(string)($_SESSION['flash_status_admin']??'');unset($_SESSION['flash_status_admin']);
$definitions=shopOrderCustomerStatusDefinitions($pdo);
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Stavy objednávek</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous"></head>
<body class="bg-light"><?php include __DIR__.'/hlavicka.php';?><main class="container py-4" style="max-width:1100px"><div class="d-flex justify-content-between align-items-start gap-3 mb-3"><div><h1 class="h4">Zákaznické stavy objednávek</h1><p class="text-muted mb-0">Nová objednávka začíná ve stavu <strong>Vytvořená objednávka</strong>. Zde přidáte další stavy do výběru administrátora.</p></div><a class="btn btn-outline-secondary" href="eshop_orders_admin.php">Zpět na objednávky</a></div>
<div class="alert alert-info small">Stav objednávky není potvrzení platby. Úhradu určuje jen ověřená platba z banky nebo platební služby. Přepnutí zákaznického stavu nemění platbu, sklad ani výdej. Akce „Připraveno k odběru“ již má vlastní e-mail; neposílejte stejnou zprávu ještě přes další stav.</div>
<?php foreach($errors as $error):?><div class="alert alert-danger" role="alert"><?=statusAdminH($error)?></div><?php endforeach;?><?php if($success!==''):?><div class="alert alert-success" role="status"><?=statusAdminH($success)?></div><?php endif;?>
<section class="card border-0 shadow-sm mb-4"><div class="card-header bg-white fw-semibold">Přidat stav</div><div class="card-body"><form method="post" class="row g-3"><?=csrf_field()?><input type="hidden" name="definition_id" value="0"><div class="col-md-6"><label class="form-label" for="new-label">Název stavu</label><input class="form-control" id="new-label" name="label" maxlength="80" required placeholder="např. Objednávka přijata"></div><div class="col-md-6 d-flex align-items-end"><label class="form-check"><input class="form-check-input" type="checkbox" name="email_enabled" value="1"> <span class="form-check-label">Při přepnutí do tohoto stavu zařadit e-mail zákazníkovi</span></label></div><div class="col-md-6"><label class="form-label" for="new-subject">Předmět e-mailu</label><input class="form-control" id="new-subject" name="email_subject" maxlength="180" placeholder="Objednávka {order_code}: {status}"></div><div class="col-12"><label class="form-label" for="new-body">Text e-mailu</label><textarea class="form-control" id="new-body" name="email_body" rows="5" maxlength="5000" placeholder="Dobrý den, {customer_name},&#10;&#10;vaše objednávka {order_code} byla přijata..."></textarea><div class="form-text">Proměnné: <code>{customer_name}</code>, <code>{order_code}</code>, <code>{status}</code>. Text se vloží do e-mailové fronty jen při skutečné změně stavu.</div></div><div class="col-md-8"><label class="form-label" for="new-reason">Důvod založení</label><input class="form-control" id="new-reason" name="reason" maxlength="1000" required></div><div class="col-md-4 d-flex align-items-end justify-content-end gap-2"><label class="form-check"><input class="form-check-input" type="checkbox" name="confirm_action" value="1" required> Potvrzuji</label><button class="btn btn-primary">Přidat stav</button></div></form></div></section>
<h2 class="h5">Existující stavy</h2>
<?php if($definitions===[]):?><p class="text-muted">Zatím není vytvořen žádný vlastní stav.</p><?php endif;?>
<?php foreach($definitions as $definition):?><section class="card border-0 shadow-sm mb-3" id="status-<?=(int)$definition['id']?>"><div class="card-header bg-white d-flex justify-content-between"><strong><?=statusAdminH($definition['label'])?></strong><span class="badge text-bg-<?=((int)$definition['active']===1?'success':'secondary')?>"><?=((int)$definition['active']===1?'Ve výběru':'Deaktivován')?></span></div><div class="card-body"><form method="post" class="row g-3"><?=csrf_field()?><input type="hidden" name="definition_id" value="<?=(int)$definition['id']?>"><input type="hidden" name="label" value="<?=statusAdminH($definition['label'])?>"><div class="col-md-6"><label class="form-check"><input class="form-check-input" type="checkbox" name="active" value="1" <?=((int)$definition['active']===1?'checked':'')?>> <span class="form-check-label">Stav je dostupný pro další objednávky</span></label></div><div class="col-md-6"><label class="form-check"><input class="form-check-input" type="checkbox" name="email_enabled" value="1" <?=((int)$definition['email_enabled']===1?'checked':'')?>> <span class="form-check-label">Odesílat e-mail při změně na tento stav</span></label></div><div class="col-md-6"><label class="form-label">Předmět</label><input class="form-control" name="email_subject" maxlength="180" value="<?=statusAdminH($definition['email_subject'])?>"></div><div class="col-12"><label class="form-label">Text e-mailu</label><textarea class="form-control" name="email_body" rows="5" maxlength="5000"><?=statusAdminH($definition['email_body'])?></textarea></div><div class="col-md-8"><label class="form-label">Důvod úpravy</label><input class="form-control" name="reason" maxlength="1000" required></div><div class="col-md-4 d-flex align-items-end justify-content-end gap-2"><label class="form-check"><input class="form-check-input" type="checkbox" name="confirm_action" value="1" required> Potvrzuji</label><button class="btn btn-outline-primary">Uložit</button></div></form><div class="small text-muted mt-2">Název je neměnný kvůli historii objednávek. Nechtěný stav deaktivujte a založte nový.</div></div></section><?php endforeach;?>
</main></body></html>
