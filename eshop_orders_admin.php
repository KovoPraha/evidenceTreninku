<?php
declare(strict_types=1);

require_once __DIR__.'/includes/init.php';
require_once __DIR__.'/csrf_helper.php';
require_once __DIR__.'/includes/shop_checkout.php';

if(!isset($_SESSION['trener_id'])||!roleAtLeast('admin')){header('Location: login.php');exit;}

function orderAdminH(mixed $value):string{return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function orderAdminMoney(int $minor,string $currency):string{return number_format($minor/100,2,',',' ').' '.orderAdminH($currency);}
function orderAdminStatusLabel(string $status):string{return ['placed'=>'Čeká na platbu','processing'=>'Připravuje se','ready'=>'Připraveno','completed'=>'Vydáno','cancelled'=>'Stornováno'][$status]??$status;}
function orderAdminPaymentLabel(string $status):string{return ['pending'=>'Čeká','paid'=>'Zaplaceno','cancelled'=>'Zrušeno','refund_required'=>'Čeká na vrácení hospodářem','refunded'=>'Vráceno'][$status]??$status;}
function orderAdminActionLabel(?string $action):string{return ['checkout_created'=>'Objednávka vytvořena','confirm_bank_payment'=>'Bankovní platba potvrzena','mark_ready'=>'Příprava dokončena','complete_pickup'=>'Osobní výdej potvrzen','cancel'=>'Objednávka stornována','confirm_refund'=>'Vrácení platby potvrzeno','expire'=>'Nezaplacená objednávka expirována'][$action??'']??'Záznam změny';}

$query=trim((string)($_GET['q']??''));
$errors=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!csrf_verify((string)($_POST['csrf_token']??''))){
        $errors[]='Formulář vypršel. Obnovte stránku.';
    }else{
        try{
            $action=(string)($_POST['action']??'');$actor=(int)$_SESSION['trener_id'];$reason=(string)($_POST['reason']??'');$confirmed=($_POST['confirm_action']??'')==='1';
            if($action==='cancel'){
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
            }else{
                throw new InvalidArgumentException('Neznámá akce objednávky.');
            }
            $_SESSION['flash_order_admin']=$message;
            $target='eshop_orders_admin.php'.($query!==''?'?q='.rawurlencode($query):'').'#order-'.(int)$result['order_id'];
            header('Location: '.$target,true,303);exit;
        }catch(PDOException $exception){
            error_log('eshop_orders_admin.php: '.$exception->getMessage());$errors[]='Databázová operace selhala bez částečného zápisu.';
        }catch(InvalidArgumentException|ShopCheckoutException $exception){
            $errors[]=$exception->getMessage();
        }
    }
}
$success=(string)($_SESSION['flash_order_admin']??'');unset($_SESSION['flash_order_admin']);$orders=shopOrderAdminList($pdo,200,$query);$orderItems=shopOrderAdminItemMap($pdo,array_column($orders,'id'));
?>
<!doctype html>
<html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Objednávky K4</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"></head>
<body class="bg-light"><?php include __DIR__.'/hlavicka.php';?>
<main class="container-fluid py-4" style="max-width:1550px">
<div class="d-flex justify-content-between align-items-center mb-3"><div><h1 class="h4 mb-0"><i class="bi bi-receipt me-2 text-primary"></i>Objednávky K4</h1><div class="small text-muted">Příprava, osobní výdej a auditované provozní storno. Peníze ověřuje samostatně hospodář.</div></div><div class="d-flex gap-2"><a href="eshop_order_expiry_admin.php" class="btn btn-outline-warning btn-sm">Expirace nezaplacených</a></div></div>
<?php foreach($errors as$error):?><div class="alert alert-danger"><?=orderAdminH($error)?></div><?php endforeach;?>
<?php if($success!==''):?><div class="alert alert-success"><?=orderAdminH($success)?></div><?php endif;?>
<div class="alert alert-info small">Platbu ani vratku zde nelze potvrdit. Po zaplaceném stornu vznikne úkol pro pozici <strong>Hospodář a platby</strong>.</div>
<form method="get" class="card card-body border-0 shadow-sm mb-3" role="search">
<label for="order-search" class="form-label fw-semibold">Najít objednávku</label>
<div class="input-group"><input id="order-search" name="q" class="form-control" value="<?=orderAdminH($query)?>" maxlength="100" placeholder="Kód objednávky, zákazník, e-mail nebo variabilní symbol"><button class="btn btn-primary" type="submit">Hledat</button><?php if($query!==''):?><a class="btn btn-outline-secondary" href="eshop_orders_admin.php">Zrušit filtr</a><?php endif;?></div>
<?php if($query!==''):?><div class="form-text">Počet výsledků: <?=count($orders)?>.</div><?php endif;?>
</form>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Objednávka</th><th>Zákazník</th><th>Částka / VS</th><th>Stav</th><th>Platba</th><th>Poslední změna</th><th style="min-width:330px">Dostupné akce</th></tr></thead><tbody>
<?php foreach($orders as$order):?><?php $items=$orderItems[(int)$order['id']]??[];?><tr id="order-<?=(int)$order['id']?>">
<td><code><?=orderAdminH($order['public_code'])?></code><div class="small text-muted"><?=orderAdminH($order['created_at'])?></div></td>
<td><?=orderAdminH($order['customer_name_snapshot'])?><div class="small"><?=orderAdminH($order['customer_email_snapshot'])?></div></td>
<td><?=orderAdminMoney((int)$order['total_minor'],(string)$order['currency'])?><div><code>VS <?=orderAdminH($order['variable_symbol'])?></code></div><?php if($order['coupon_code_snapshot']!==null):?><div class="small text-success">kupón <?=orderAdminH($order['coupon_code_snapshot'])?> · sleva <?=orderAdminMoney((int)$order['discount_minor'],(string)$order['currency'])?></div><?php endif;?></td>
<td><strong><?=orderAdminH(orderAdminStatusLabel((string)$order['status']))?></strong><?php if($order['ready_at']!==null):?><div class="small text-muted">Připraveno <?=orderAdminH($order['ready_at'])?></div><?php endif;?><?php if($order['completed_at']!==null):?><div class="small text-muted">Vydáno <?=orderAdminH($order['completed_at'])?></div><?php endif;?><?php if($order['cancelled_at']!==null):?><div class="small text-muted">Storno <?=orderAdminH($order['cancelled_at'])?></div><?php endif;?></td>
<td><span class="badge text-bg-<?=in_array($order['payment_record_status'],['paid','refunded'],true)?'success':'warning'?>"><?=orderAdminH(orderAdminPaymentLabel((string)$order['payment_record_status']))?></span><?php if($order['refund_sent_at']!==null):?><div class="small text-muted"><?=orderAdminH($order['refund_sent_at'])?><br>ref. <?=orderAdminH($order['refund_reference'])?></div><?php endif;?></td>
<td class="small" style="max-width:260px"><strong><?=orderAdminH(orderAdminActionLabel($order['last_event_action']))?></strong><?php if($order['last_event_actor_id']!==null):?> · administrátor<?php endif;?><div><?=orderAdminH($order['last_event_note'])?></div><div class="text-muted"><?=orderAdminH($order['last_event_at'])?></div></td>
<td>
<?php if($order['status']==='processing'):?>
<form method="post" class="border rounded p-2 mb-2"><?=csrf_field()?><input type="hidden" name="action" value="ready"><input type="hidden" name="order_id" value="<?=(int)$order['id']?>"><label class="small fw-semibold">Dokončit přípravu</label><input class="form-control form-control-sm my-1" name="reason" maxlength="1000" required aria-label="Poznámka k přípravě <?=orderAdminH($order['public_code'])?>" placeholder="Kde a kým bylo připraveno"><label class="small d-block"><input type="checkbox" name="confirm_action" value="1" required> Zboží je skutečně připravené</label><button class="btn btn-sm btn-outline-primary mt-1">Označit jako připravené</button></form>
<?php elseif($order['status']==='ready'):?>
<form method="post" class="border rounded p-2 mb-2"><?=csrf_field()?><input type="hidden" name="action" value="complete"><input type="hidden" name="order_id" value="<?=(int)$order['id']?>"><label class="small fw-semibold">Potvrdit osobní výdej</label><input class="form-control form-control-sm my-1" name="reason" maxlength="1000" required aria-label="Poznámka k výdeji <?=orderAdminH($order['public_code'])?>" placeholder="Komu a kým bylo vydáno"><label class="small d-block"><input type="checkbox" name="confirm_action" value="1" required> Zboží bylo skutečně předáno</label><button class="btn btn-sm btn-outline-success mt-1">Dokončit výdej</button></form>
<?php endif;?>
<?php if($order['status']==='placed'&&$order['payment_record_status']==='pending'):?><span class="small text-muted d-block mb-2">Čeká na ověření hospodářem.</span><?php endif;?>
<?php if($order['payment_record_status']==='refund_required'):?><div class="border border-warning-subtle bg-warning-subtle rounded p-2 mb-2"><strong class="small d-block">Objednávka je stornovaná. Peníze ještě nebyly vráceny.</strong><?php if(staffCanUsePosition('finance_manager')):?><form method="post" action="prepnout_pracovni_pozici.php" class="mt-2" data-refund-handoff><?=csrf_field()?><input type="hidden" name="position" value="finance_manager"><input type="hidden" name="next" value="eshop_payments_admin.php"><input type="hidden" name="reason" value="Dokončení vratky objednávky <?=orderAdminH($order['public_code'])?>"><button type="submit" class="btn btn-sm btn-warning">Přepnout na Hospodáře a otevřít vratku</button></form><?php else:?><span class="small">Vrácení peněz provádí pracovní pozice <strong>Hospodář a platby</strong>. Požádejte správce o její přiřazení.</span><?php endif;?></div><?php endif;?>
<?php if(in_array($order['status'],['placed','processing','ready'],true)):?>
<form method="post" class="border border-danger-subtle rounded p-2"><?=csrf_field()?><input type="hidden" name="action" value="cancel"><input type="hidden" name="order_id" value="<?=(int)$order['id']?>"><label class="small fw-semibold text-danger">Stornovat objednávku</label><input class="form-control form-control-sm my-1" name="reason" maxlength="1000" required aria-label="Důvod storna <?=orderAdminH($order['public_code'])?>" placeholder="Důvod storna"><label class="small d-block"><input type="checkbox" name="confirm_action" value="1" required> Potvrzuji storno a vrácení skladu</label><button class="btn btn-sm btn-outline-danger mt-1">Stornovat</button></form>
<?php elseif(in_array($order['status'],['completed','cancelled'],true)&&$order['payment_record_status']!=='refund_required'):?><span class="small text-muted">Objednávka je v koncovém stavu.</span><?php endif;?>
</td></tr>
<tr class="table-light"><td colspan="7" class="p-3"><strong class="small text-uppercase text-muted">Co přesně připravit</strong>
<?php if($items!==[]):?><div class="table-responsive mt-2"><table class="table table-sm table-bordered bg-white mb-0"><thead><tr><th>Položka</th><th>SKU / typ</th><th>Pro koho</th><th class="text-end">Množství</th><th class="text-end">Cena za kus</th><th class="text-end">Celkem</th></tr></thead><tbody>
<?php foreach($items as$item):?><?php $attributes=json_decode((string)($item['detail_json']??''),true);?><tr><td><strong><?=orderAdminH($item['line_name'])?></strong><?php if($item['starts_at']!==null):?><div class="small text-muted"><?=orderAdminH($item['starts_at'])?>–<?=orderAdminH($item['ends_at'])?></div><?php endif;?><?php if(is_array($attributes)&&$attributes!==[]):?><div class="small text-muted"><?=orderAdminH(implode(' · ',array_map(static fn($key,$value):string=>(string)$key.': '.(is_scalar($value)?(string)$value:'—'),array_keys($attributes),$attributes)))?></div><?php endif;?></td><td><code><?=orderAdminH($item['sku'])?></code><div class="small text-muted"><?=orderAdminH(['catalog'=>'zboží / kroužek','velodrome'=>'lekce na velodromu','event'=>'klubová akce'][$item['line_type']]??$item['line_type'])?></div></td><td><?=trim((string)($item['jmeno']??'').' '.(string)($item['prijmeni']??''))!==''?orderAdminH(trim((string)$item['jmeno'].' '.(string)$item['prijmeni'])):'—'?></td><td class="text-end fw-semibold"><?=(int)$item['quantity']?></td><td class="text-end"><?=orderAdminMoney((int)$item['unit_amount_minor'],(string)$item['currency'])?></td><td class="text-end fw-semibold"><?=orderAdminMoney((int)$item['line_amount_minor'],(string)$item['currency'])?></td></tr><?php endforeach;?></tbody></table></div><?php else:?><div class="alert alert-warning py-2 mb-0 mt-2">Objednávka nemá žádnou dohledatelnou položku. Před změnou stavu ji prověřte.</div><?php endif;?></td></tr><?php endforeach;?>
<?php if($orders===[]):?><tr><td colspan="7" class="text-center text-muted py-4"><?=$query!==''?'Tomuto hledání neodpovídá žádná objednávka.':'Zatím není žádná objednávka.'?></td></tr><?php endif;?>
</tbody></table></div></div></main></body></html>
