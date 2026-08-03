<?php
declare(strict_types=1);

require_once __DIR__.'/includes/init.php';
require_once __DIR__.'/csrf_helper.php';
require_once __DIR__.'/includes/shop_checkout.php';

if(!isset($_SESSION['trener_id'])||!roleAtLeast('admin')){header('Location: login.php');exit;}

function orderAdminH(mixed $value):string{return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function orderAdminMoney(int $minor,string $currency):string{return number_format($minor/100,2,',',' ').' '.orderAdminH($currency);}
function orderAdminStatusLabel(string $status):string{return ['placed'=>'Čeká na platbu','processing'=>'Připravuje se','ready'=>'Připraveno','completed'=>'Vydáno','cancelled'=>'Stornováno'][$status]??$status;}
function orderAdminPaymentLabel(string $status):string{return ['pending'=>'Čeká','paid'=>'Zaplaceno','cancelled'=>'Zrušeno','refund_required'=>'Vrátit platbu','refunded'=>'Vráceno'][$status]??$status;}

$errors=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!csrf_verify((string)($_POST['csrf_token']??''))){
        $errors[]='Formulář vypršel. Obnovte stránku.';
    }else{
        try{
            $action=(string)($_POST['action']??'');$actor=(int)$_SESSION['trener_id'];$reason=(string)($_POST['reason']??'');$confirmed=($_POST['confirm_action']??'')==='1';
            if($action==='confirm_payment'){
                $result=shopOrderAdminConfirmBankPayment($pdo,(int)($_POST['payment_id']??0),$actor,$reason,$confirmed);
                $message=$result['changed']?'Platba byla potvrzena a objednávka přešla do přípravy.':'Platba už byla potvrzena.';
            }elseif($action==='cancel'){
                $result=shopOrderAdminCancel($pdo,(int)($_POST['order_id']??0),$actor,$reason,$confirmed);
                if(!$result['changed'])$message='Objednávka už byla stornována.';
                elseif($result['payment_status']==='refund_required')$message='Objednávka byla stornována a sklad vrácen. Přijatou platbu je nutné samostatně vrátit zákazníkovi.';
                else $message='Objednávka byla stornována, platební předpis zrušen a sklad vrácen.';
            }elseif($action==='ready'){
                $result=shopOrderAdminMarkReady($pdo,(int)($_POST['order_id']??0),$actor,$reason,$confirmed);
                $message=$result['changed']?'Objednávka je připravena k osobnímu odběru.':'Objednávka už je připravena.';
            }elseif($action==='complete'){
                $result=shopOrderAdminCompletePickup($pdo,(int)($_POST['order_id']??0),$actor,$reason,$confirmed);
                $message=$result['changed']?'Výdej byl potvrzen a objednávka dokončena.':'Objednávka už byla vydána.';
            }elseif($action==='confirm_refund'){
                $result=shopOrderAdminConfirmRefund($pdo,(int)($_POST['order_id']??0),$actor,(string)($_POST['refund_reference']??''),$reason,$confirmed);
                $message=$result['changed']?'Odeslaná vratka byla auditovaně potvrzena.':'Vratka už byla potvrzena.';
            }else{
                throw new InvalidArgumentException('Neznámá akce objednávky.');
            }
            $_SESSION['flash_order_admin']=$message;header('Location: eshop_orders_admin.php',true,303);exit;
        }catch(PDOException $exception){
            error_log('eshop_orders_admin.php: '.$exception->getMessage());$errors[]='Databázová operace selhala bez částečného zápisu.';
        }catch(InvalidArgumentException|ShopCheckoutException $exception){
            $errors[]=$exception->getMessage();
        }
    }
}
$success=(string)($_SESSION['flash_order_admin']??'');unset($_SESSION['flash_order_admin']);$orders=shopOrderAdminList($pdo);
?>
<!doctype html>
<html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Objednávky K4</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"></head>
<body class="bg-light"><?php include __DIR__.'/hlavicka.php';?>
<main class="container-fluid py-4" style="max-width:1550px">
<div class="d-flex justify-content-between align-items-center mb-3"><div><h1 class="h4 mb-0"><i class="bi bi-receipt me-2 text-primary"></i>Objednávky K4</h1><div class="small text-muted">Platba, příprava, osobní výdej a auditované storno se skladovou kompenzací.</div></div><a href="eshop_admin.php" class="btn btn-outline-secondary btn-sm">Administrace e-shopu</a></div>
<?php foreach($errors as$error):?><div class="alert alert-danger"><?=orderAdminH($error)?></div><?php endforeach;?>
<?php if($success!==''):?><div class="alert alert-success"><?=orderAdminH($success)?></div><?php endif;?>
<div class="alert alert-warning small">Zaplacené storno vrátí zboží do skladu, ale peníze označí jako <strong>Vrátit platbu</strong>. Teprve samostatné ověření bankovní vratky může finanční část uzavřít.</div>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Objednávka</th><th>Zákazník</th><th>Částka / VS</th><th>Objednávka</th><th>Platba</th><th>Poslední audit</th><th style="min-width:330px">Bezpečná akce</th></tr></thead><tbody>
<?php foreach($orders as$order):?><tr>
<td><code><?=orderAdminH($order['public_code'])?></code><div class="small text-muted"><?=orderAdminH($order['created_at'])?></div></td>
<td><?=orderAdminH($order['customer_name_snapshot'])?><div class="small"><?=orderAdminH($order['customer_email_snapshot'])?></div></td>
<td><?=orderAdminMoney((int)$order['total_minor'],(string)$order['currency'])?><div><code>VS <?=orderAdminH($order['variable_symbol'])?></code></div><?php if($order['coupon_code_snapshot']!==null):?><div class="small text-success">kupón <?=orderAdminH($order['coupon_code_snapshot'])?> · sleva <?=orderAdminMoney((int)$order['discount_minor'],(string)$order['currency'])?></div><?php endif;?></td>
<td><strong><?=orderAdminH(orderAdminStatusLabel((string)$order['status']))?></strong><?php if($order['ready_at']!==null):?><div class="small text-muted">Připraveno <?=orderAdminH($order['ready_at'])?></div><?php endif;?><?php if($order['completed_at']!==null):?><div class="small text-muted">Vydáno <?=orderAdminH($order['completed_at'])?></div><?php endif;?><?php if($order['cancelled_at']!==null):?><div class="small text-muted">Storno <?=orderAdminH($order['cancelled_at'])?></div><?php endif;?></td>
<td><span class="badge text-bg-<?=in_array($order['payment_record_status'],['paid','refunded'],true)?'success':($order['payment_record_status']==='refund_required'?'danger':'warning')?>"><?=orderAdminH(orderAdminPaymentLabel((string)$order['payment_record_status']))?></span><?php if($order['refund_sent_at']!==null):?><div class="small text-muted"><?=orderAdminH($order['refund_sent_at'])?><br>ref. <?=orderAdminH($order['refund_reference'])?></div><?php endif;?></td>
<td class="small" style="max-width:260px"><code><?=orderAdminH($order['last_event_action'])?></code><?php if($order['last_event_actor_id']!==null):?> · admin #<?=(int)$order['last_event_actor_id']?><?php endif;?><div><?=orderAdminH($order['last_event_note'])?></div><div class="text-muted"><?=orderAdminH($order['last_event_at'])?></div></td>
<td>
<?php if($order['status']==='placed'&&$order['payment_record_status']==='pending'):?>
<form method="post" class="border rounded p-2 mb-2"><?=csrf_field()?><input type="hidden" name="action" value="confirm_payment"><input type="hidden" name="payment_id" value="<?=(int)$order['payment_id']?>"><label class="small fw-semibold">Potvrdit bankovní platbu</label><input class="form-control form-control-sm my-1" name="reason" maxlength="1000" required placeholder="Jak byla platba ověřena"><label class="small d-block"><input type="checkbox" name="confirm_action" value="1" required> Ověřil/a jsem platbu v bance</label><button class="btn btn-sm btn-outline-success mt-1">Přijmout platbu</button></form>
<?php elseif($order['status']==='processing'):?>
<form method="post" class="border rounded p-2 mb-2"><?=csrf_field()?><input type="hidden" name="action" value="ready"><input type="hidden" name="order_id" value="<?=(int)$order['id']?>"><label class="small fw-semibold">Dokončit přípravu</label><input class="form-control form-control-sm my-1" name="reason" maxlength="1000" required placeholder="Kde a kým bylo připraveno"><label class="small d-block"><input type="checkbox" name="confirm_action" value="1" required> Zboží je skutečně připravené</label><button class="btn btn-sm btn-outline-primary mt-1">Označit jako připravené</button></form>
<?php elseif($order['status']==='ready'):?>
<form method="post" class="border rounded p-2 mb-2"><?=csrf_field()?><input type="hidden" name="action" value="complete"><input type="hidden" name="order_id" value="<?=(int)$order['id']?>"><label class="small fw-semibold">Potvrdit osobní výdej</label><input class="form-control form-control-sm my-1" name="reason" maxlength="1000" required placeholder="Komu a kým bylo vydáno"><label class="small d-block"><input type="checkbox" name="confirm_action" value="1" required> Zboží bylo skutečně předáno</label><button class="btn btn-sm btn-outline-success mt-1">Dokončit výdej</button></form>
<?php endif;?>
<?php if($order['payment_record_status']==='refund_required'):?>
<form method="post" class="border border-warning rounded p-2 mb-2"><?=csrf_field()?><input type="hidden" name="action" value="confirm_refund"><input type="hidden" name="order_id" value="<?=(int)$order['id']?>"><label class="small fw-semibold">Potvrdit odeslanou vratku</label><input class="form-control form-control-sm my-1" name="refund_reference" maxlength="255" required placeholder="Reference bankovní transakce"><input class="form-control form-control-sm my-1" name="reason" maxlength="1000" required placeholder="Jak a kým byla vratka ověřena"><label class="small d-block"><input type="checkbox" name="confirm_action" value="1" required> Peníze byly skutečně odeslány</label><button class="btn btn-sm btn-warning mt-1">Potvrdit vratku</button></form>
<?php endif;?>
<?php if(in_array($order['status'],['placed','processing','ready'],true)):?>
<form method="post" class="border border-danger-subtle rounded p-2"><?=csrf_field()?><input type="hidden" name="action" value="cancel"><input type="hidden" name="order_id" value="<?=(int)$order['id']?>"><label class="small fw-semibold text-danger">Stornovat objednávku</label><input class="form-control form-control-sm my-1" name="reason" maxlength="1000" required placeholder="Důvod storna"><label class="small d-block"><input type="checkbox" name="confirm_action" value="1" required> Potvrzuji storno a vrácení skladu</label><button class="btn btn-sm btn-outline-danger mt-1">Stornovat</button></form>
<?php elseif(in_array($order['status'],['completed','cancelled'],true)&&$order['payment_record_status']!=='refund_required'):?><span class="small text-muted">Objednávka je v koncovém stavu.</span><?php endif;?>
</td></tr><?php endforeach;?>
<?php if($orders===[]):?><tr><td colspan="7" class="text-center text-muted py-4">Zatím není žádná objednávka.</td></tr><?php endif;?>
</tbody></table></div></div></main></body></html>
