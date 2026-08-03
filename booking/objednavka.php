<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/includes/session_security.php';app_session_start();
if(!isset($_SESSION['verejny_uzivatel_id'])){header('Location: prihlaseni.php?redirect=eshop.php');exit;}
require_once dirname(__DIR__).'/db.php';require_once dirname(__DIR__).'/includes/shop_checkout.php';

function orderPublicH(mixed $value):string{return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function orderPublicMoney(int $minor,string $currency):string{return number_format($minor/100,2,',',' ').' '.orderPublicH($currency);}

try{
    $order=shopOrderByCode($pdo,(int)$_SESSION['verejny_uzivatel_id'],(string)($_GET['code']??''));
    $qr=$order['payment_record_status']==='pending'?shopPaymentQrDataUri((string)$order['spd_payload']):null;
}catch(ShopCheckoutException $exception){
    http_response_code(404);exit('Objednávka nebyla nalezena.');
}
$messages=[
    'placed'=>['warning','Objednávka čeká na bankovní platbu. Pro správné spárování použijte uvedený variabilní symbol.'],
    'processing'=>['info','Platba byla přijata a objednávku připravujeme.'],
    'ready'=>['success','Objednávka je připravena k osobnímu odběru.'],
    'completed'=>['secondary','Objednávka byla osobně vydána a dokončena.'],
    'cancelled'=>['danger',$order['payment_record_status']==='refund_required'?'Objednávka byla stornována. Přijatá platba čeká na samostatné vrácení.':'Objednávka byla stornována a platební předpis již není platný.'],
];
[$messageStyle,$messageText]=$messages[$order['status']]??['secondary','Aktuální stav objednávky: '.(string)$order['status']];
?>
<!doctype html>
<html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Objednávka <?=orderPublicH($order['public_code'])?></title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light"><main class="container py-4" style="max-width:900px">
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h3 mb-0">Objednávka <?=orderPublicH($order['public_code'])?></h1><a href="eshop.php" class="btn btn-outline-secondary">Zpět do e-shopu</a></div>
<div class="alert alert-<?=$messageStyle?>"><?=orderPublicH($messageText)?></div>
<div class="row g-3"><div class="col-md-7"><div class="card border-0 shadow-sm"><div class="card-header bg-white fw-semibold">Neměnný obsah objednávky</div><div class="card-body">
<?php foreach($order['items']as$item):?><div class="d-flex justify-content-between border-bottom py-2"><span><?=orderPublicH($item['product_name_snapshot'])?> × <?=(int)$item['quantity']?><br><code><?=orderPublicH($item['sku_snapshot'])?></code></span><strong><?=orderPublicMoney((int)$item['line_amount_minor'],(string)$item['currency'])?></strong></div><?php endforeach;?>
<div class="d-flex justify-content-between fs-5 pt-3"><strong>Celkem</strong><strong><?=orderPublicMoney((int)$order['total_minor'],(string)$order['currency'])?></strong></div></div></div></div>
<div class="col-md-5"><div class="card border-0 shadow-sm"><div class="card-header bg-white fw-semibold"><?=$qr!==null?'Bankovní převod':'Stav platby'?></div><div class="card-body text-center">
<?php if($qr!==null):?><img class="img-fluid" src="<?=orderPublicH($qr)?>" alt="QR platba"><dl class="text-start small mt-2"><dt>Účet</dt><dd><?=orderPublicH($order['account_label_snapshot'])?><br><code><?=orderPublicH($order['iban_snapshot'])?></code></dd><dt>Variabilní symbol</dt><dd><code><?=orderPublicH($order['variable_symbol'])?></code></dd><dt>Částka</dt><dd><?=orderPublicMoney((int)$order['total_minor'],(string)$order['currency'])?></dd><dt>Splatnost</dt><dd><?=orderPublicH($order['due_at'])?></dd></dl>
<?php else:?><p class="mb-0"><?=orderPublicH(['paid'=>'Platba přijata','cancelled'=>'Platební předpis zrušen','refund_required'=>'Čeká na vrácení platby'][$order['payment_record_status']]??(string)$order['payment_record_status'])?></p><?php endif;?>
</div></div></div></div></main></body></html>
