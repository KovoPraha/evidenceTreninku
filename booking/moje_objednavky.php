<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/includes/session_security.php';app_session_start();
if(!isset($_SESSION['verejny_uzivatel_id'])){header('Location: prihlaseni.php?redirect=moje_objednavky.php');exit;}
require_once dirname(__DIR__).'/db.php';require_once dirname(__DIR__).'/includes/shop_checkout.php';

function myOrdersH(mixed $value):string{return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function myOrdersMoney(int $minor,string $currency):string{return number_format($minor/100,2,',',' ').' '.myOrdersH($currency);}
function myOrdersStatus(string $status):array{return [
    'placed'=>['warning','Čeká na platbu'],'processing'=>['info','Připravuje se'],'ready'=>['success','Připraveno k odběru'],
    'completed'=>['secondary','Vydáno'],'cancelled'=>['danger','Stornováno'],
][$status]??['secondary',$status];}
function myOrdersPayment(string $status):string{return ['pending'=>'Čeká na platbu','paid'=>'Zaplaceno','cancelled'=>'Předpis zrušen','refund_required'=>'Čeká na vrácení peněz','refunded'=>'Peníze vráceny'][$status]??$status;}

$orders=shopOrderListForAccount($pdo,(int)$_SESSION['verejny_uzivatel_id']);
?>
<!doctype html>
<html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Moje objednávky</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><?php appUiAssets(); ?></head>
<body class="bg-light"><?php publicShellNav(); ?><main class="container py-4" style="max-width:1000px">
<div class="d-flex justify-content-between align-items-center mb-3"><div><h1 class="h3 mb-0"><i class="bi bi-bag-check me-2 text-success"></i>Moje objednávky</h1><div class="text-muted">Objednávky přihlášeného účtu, stav platby, přípravy a případné vratky.</div></div><a href="eshop.php" class="btn btn-outline-success">Zpět do e-shopu</a></div>
<?php if($orders===[]):?><div class="alert alert-light border">Zatím nemáte žádnou objednávku. <a href="eshop.php">Přejít do e-shopu</a>.</div><?php endif;?>
<div class="row g-3"><?php foreach($orders as$order):[$style,$label]=myOrdersStatus((string)$order['status']);?><div class="col-12"><article class="card border-0 shadow-sm"><div class="card-body"><div class="row align-items-center g-3">
<div class="col-md-3"><strong><?=myOrdersH($order['public_code'])?></strong><div class="small text-muted"><?=myOrdersH($order['placed_at'])?></div></div>
<div class="col-md-2"><span class="badge text-bg-<?=$style?>"><?=myOrdersH($label)?></span></div>
<div class="col-md-3"><div><?=myOrdersH(myOrdersPayment((string)$order['payment_record_status']))?></div><?php if($order['refund_sent_at']!==null):?><div class="small text-muted">Vráceno <?=myOrdersH($order['refund_sent_at'])?></div><?php endif;?></div>
<div class="col-md-2"><strong><?=myOrdersMoney((int)$order['total_minor'],(string)$order['currency'])?></strong><div class="small text-muted"><?=(int)$order['item_count']?> položek<?php if($order['coupon_code_snapshot']!==null):?> · kupón <?=myOrdersH($order['coupon_code_snapshot'])?><?php endif;?></div></div>
<div class="col-md-2 text-md-end"><a class="btn btn-sm btn-outline-primary" href="objednavka.php?code=<?=rawurlencode((string)$order['public_code'])?>">Detail</a></div>
</div></div></article></div><?php endforeach;?></div>
</main><?php publicShellFooter(); ?></body></html>
