<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/includes/session_security.php';
app_session_start();
app_session_send_auth_no_store_headers();
if (!isset($_SESSION['verejny_uzivatel_id'])) {
    header('Location: prihlaseni.php?redirect='.rawurlencode('sportovni_prehled.php'),true,303);
    exit;
}
require_once dirname(__DIR__).'/db.php';
require_once dirname(__DIR__).'/includes/shop_checkout.php';
require_once dirname(__DIR__).'/includes/ui_shell.php';

function chargePaymentH(mixed $value):string
{
    return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
}

$chargeId=(int)($_GET['id']??0);
$accountId=(int)$_SESSION['verejny_uzivatel_id'];
$query=$pdo->prepare("SELECT c.id,c.title_snapshot,c.amount_minor,c.currency,c.due_on,c.status,c.public_code,
    p.amount_minor AS payment_amount_minor,p.status AS payment_status,p.variable_symbol,p.iban_snapshot,
    p.account_label_snapshot,p.spd_payload,p.paid_at
    FROM club_member_charges c JOIN payments p ON p.payable_type='member_charge' AND p.payable_id=c.id
    WHERE c.id=? AND c.payer_account_id=? LIMIT 1");
$query->execute([$chargeId,$accountId]);
$charge=$query->fetch(PDO::FETCH_ASSOC);
if(!$charge){http_response_code(404);exit('Předpis nebyl nalezen.');}
$qr=null;
if($charge['status']==='pending' && (string)$charge['spd_payload']!==''){
    try{$qr=shopPaymentQrDataUri((string)$charge['spd_payload']);}
    catch(Throwable $exception){error_log('booking/platba_predpisu.php QR: '.get_class($exception));}
}
?><!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Platba předpisu — KOVO Praha</title><?php appUiAssets();?></head><body class="bg-light"><?php publicShellNav();?><main class="container py-4" style="max-width:760px"><a class="btn btn-sm btn-outline-secondary mb-3" href="sportovni_prehled.php">← Zpět na sportovní přehled</a><h1 class="h4">Platba za klubovou aktivitu</h1><section class="card border-0 shadow-sm"><div class="card-body p-4"><h2 class="h5"><?=chargePaymentH($charge['title_snapshot'])?></h2><p class="text-muted">Předpis <?=chargePaymentH($charge['public_code'])?></p><?php if($charge['status']==='pending'):?><div class="alert alert-info">Účast je potvrzená, platbu zatím evidujeme jako neuhrazenou. Bankovní převod se projeví až po potvrzení oddílem.</div><dl class="row mb-3"><dt class="col-sm-4">Částka</dt><dd class="col-sm-8 fw-bold"><?=chargePaymentH(number_format((int)$charge['amount_minor']/100,2,',',' '))?> <?=chargePaymentH($charge['currency'])?></dd><dt class="col-sm-4">Splatnost</dt><dd class="col-sm-8"><?=chargePaymentH($charge['due_on'])?></dd><dt class="col-sm-4">Variabilní symbol</dt><dd class="col-sm-8"><code><?=chargePaymentH($charge['variable_symbol'])?></code></dd><dt class="col-sm-4">IBAN</dt><dd class="col-sm-8"><code><?=chargePaymentH($charge['iban_snapshot'])?></code></dd><?php if($charge['account_label_snapshot']):?><dt class="col-sm-4">Účet</dt><dd class="col-sm-8"><?=chargePaymentH($charge['account_label_snapshot'])?></dd><?php endif;?></dl><?php if($qr!==null):?><img src="<?=chargePaymentH($qr)?>" width="280" height="280" alt="QR kód pro bankovní platbu" class="img-fluid border rounded p-2"><?php else:?><p class="small text-muted">QR kód není právě dostupný. Použijte uvedený IBAN, částku a variabilní symbol.</p><?php endif;?><?php elseif($charge['status']==='paid'):?><div class="alert alert-success">Platba je evidována jako uhrazená<?=!empty($charge['paid_at'])?' od '.chargePaymentH(substr((string)$charge['paid_at'],0,10)):''?>.</div><?php else:?><div class="alert alert-secondary">Stav předpisu: <?=chargePaymentH($charge['status'])?>. Pokud potřebujete vysvětlení nebo vrácení platby, kontaktujte oddíl.</div><?php endif;?></div></section></main><?php publicShellFooter();?></body></html>
