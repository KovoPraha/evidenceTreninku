<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/includes/session_security.php';app_session_start();
if(!isset($_SESSION['verejny_uzivatel_id'])){header('Location: prihlaseni.php?redirect=moje_objednavky.php');exit;}
require_once dirname(__DIR__).'/db.php';require_once dirname(__DIR__).'/includes/shop_checkout.php';
require_once dirname(__DIR__).'/includes/shop_public_navigation.php';
require_once dirname(__DIR__).'/includes/ui_format.php';
require_once dirname(__DIR__).'/includes/shop_payment_presentation.php';

function myOrdersH(mixed $value):string{return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function myOrdersMoney(int $minor,string $currency):string{return number_format($minor/100,2,',',' ').' '.myOrdersH($currency==='CZK'?'Kč':$currency);}
function myOrdersStatus(string $status):array{return [
    'placed'=>['warning','Vytvořená objednávka'],'processing'=>['info','Připravuje se'],'ready'=>['success','Připraveno k odběru'],
    'completed'=>['secondary','Vydáno'],'cancelled'=>['danger','Stornováno'],
][$status]??['secondary',$status];}
function myOrdersPayment(string $status):string{return ['pending'=>'Čeká na platbu','paid'=>'Zaplaceno','cancelled'=>'Předpis zrušen','refund_required'=>'Čeká na vrácení peněz','partially_refunded'=>'Část platby vrácena','refunded'=>'Peníze vráceny'][$status]??$status;}

$orders=shopOrderListForAccount($pdo,(int)$_SESSION['verejny_uzivatel_id']);
?>
<!doctype html>
<html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Moje objednávky</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous"><?php appUiAssets(); ?></head>
<body class="bg-light"><?php publicShellNav();shopPublicNavigation($pdo); ?><main class="container py-4" style="max-width:1000px">
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h1 class="h3 mb-0"><i class="bi bi-bag-check me-2 text-success"></i>Moje objednávky</h1><div class="text-muted">Objednávky přihlášeného účtu, stav platby, přípravy a případné vratky.</div></div><?php if($orders!==[]):?><a href="eshop.php" class="btn btn-outline-success">Nakupovat v e-shopu</a><?php endif;?></div>
<?php if($orders===[]):?><div class="card border-0 shadow-sm"><div class="card-body"><p>Zatím nemáte žádnou objednávku.</p><a class="btn btn-primary" href="eshop.php">Prohlédnout e-shop</a></div></div><?php endif;?>
<div class="row g-3"><?php foreach($orders as$order):$paymentView=shopOrderPaymentPresentation($pdo,$order);[$style,$label]=myOrdersStatus((string)$order['status']);$label=shopOrderSystemStatusLabel($order);?><div class="col-12"><article class="card border-0 shadow-sm"><div class="card-body"><div class="row align-items-center g-3">
<div class="col-md-3"><strong><?=myOrdersH($order['public_code'])?></strong><div class="small text-muted"><?=myOrdersH(uiFormatDateTime((string)$order['placed_at']))?></div></div>
<div class="col-md-2"><span class="badge text-bg-<?=$style?>"><?=myOrdersH($label)?></span><?php if(!empty($order['customer_status_label'])&&in_array($order['status'],['placed','processing'],true)):?><div class="small text-muted mt-1">Upřesnění: <?=myOrdersH($order['customer_status_label'])?></div><?php endif;?></div>
<div class="col-md-3"><div class="<?=$paymentView['status']==='paid'?'text-success fw-semibold':''?>"><?=$paymentView['status']==='paid'?'● ':''?><?=myOrdersH(myOrdersPayment((string)$paymentView['status']))?></div><?php if($paymentView['status']==='pending'):?><div class="small text-muted">Čeká na úhradu nebo spárování bankovní platby.</div><?php endif;?><?php if($paymentView['refunded_on']!==null):?><div class="small text-muted">Vráceno <?=myOrdersH($paymentView['refunded_on'])?></div><?php endif;?></div>
<div class="col-md-2"><strong><?=myOrdersMoney((int)$order['total_minor'],(string)$order['currency'])?></strong><div class="small text-muted"><?=(int)$order['item_count']?> <?= (int)$order['item_count']===1?'položka':((int)$order['item_count']>=2&&(int)$order['item_count']<=4?'položky':'položek') ?><?php if($order['coupon_code_snapshot']!==null):?> · kupón <?=myOrdersH($order['coupon_code_snapshot'])?><?php endif;?></div></div>
<div class="col-md-2 text-md-end"><a class="btn btn-sm btn-outline-primary" href="objednavka.php?code=<?=rawurlencode((string)$order['public_code'])?>">Detail</a></div>
</div></div></article></div><?php endforeach;?></div>
</main><?php publicShellFooter(); ?></body></html>
