<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/includes/session_security.php';app_session_start();
header('Cache-Control: no-store, max-age=0');header('Pragma: no-cache');header('Referrer-Policy: no-referrer');
require_once dirname(__DIR__).'/db.php';require_once dirname(__DIR__).'/csrf_helper.php';require_once dirname(__DIR__).'/includes/shop_checkout.php';require_once dirname(__DIR__).'/includes/shop_guest_checkout.php';require_once dirname(__DIR__).'/includes/stripe_gateway.php';require_once dirname(__DIR__).'/includes/sumup_gateway.php';
require_once dirname(__DIR__).'/includes/shop_public_navigation.php';

function orderPublicH(mixed $value):string{return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function orderPublicMoney(int $minor,string $currency):string{return number_format($minor/100,2,',',' ').' '.orderPublicH($currency);}

$paymentError='';
try{
    $guestAccess=(string)($_GET['access']??'');$isGuestAccess=$guestAccess!=='';
    if($isGuestAccess){$order=shopGuestOrderByCode($pdo,(string)($_GET['code']??''),$guestAccess);}
    elseif(isset($_SESSION['verejny_uzivatel_id'])){$order=shopOrderByCode($pdo,(int)$_SESSION['verejny_uzivatel_id'],(string)($_GET['code']??''));}
    else{header('Location: prihlaseni.php?redirect=eshop.php');exit;}
    if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
        if($isGuestAccess)throw new InvalidArgumentException('Nákup bez účtu podporuje bezpečnou bankovní platbu podle zobrazených údajů.');
        if(!csrf_verify((string)($_POST['csrf_token']??'')))throw new InvalidArgumentException('Formulář vypršel. Obnovte stránku.');
        $action=(string)($_POST['action']??'');
        if($action==='sumup_checkout'){
            if(!sumupIsEnabled())throw new SumUpGatewayDisabledException('Platba přes SumUp není dostupná.');
            if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
            $client=new SumUpApiGatewayClient((string)(defined('SUMUP_API_KEY')?SUMUP_API_KEY:''));
            $checkout=sumupCreateCheckout($pdo,(int)$order['id'],(int)$_SESSION['verejny_uzivatel_id'],$client);
            header('Location: '.$checkout['url'],true,303);exit;
        }
        if($action==='stripe_checkout'){
            if(!stripeIsEnabled())throw new StripeGatewayDisabledException('Platba kartou není dostupná.');
            if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
            $client=new StripeSdkGatewayClient((string)(defined('STRIPE_SECRET_KEY')?STRIPE_SECRET_KEY:''));
            $session=stripeCreateCheckoutSession($pdo,(int)$order['id'],(int)$_SESSION['verejny_uzivatel_id'],$client);
            header('Location: '.$session['url'],true,303);exit;
        }
        throw new InvalidArgumentException('Neplatná platební akce.');
    }
    $qr=$order['payment_record_status']==='pending'?shopPaymentQrDataUri((string)$order['spd_payload']):null;
}catch(ShopCheckoutException $exception){
    http_response_code(404);exit('Objednávka nebyla nalezena.');
}catch(StripeGatewayException|SumUpGatewayException|InvalidArgumentException $exception){
    $reference=strtoupper(substr(bin2hex(random_bytes(6)),0,10));
    error_log('order_checkout ref='.$reference.' order_id='.(int)($order['id']??0).' account_id='.(int)($_SESSION['verejny_uzivatel_id']??0).' error='.$exception->getMessage());
    $paymentError=$exception instanceof StripeGatewayDisabledException
        || $exception instanceof SumUpGatewayDisabledException
        || $exception instanceof InvalidArgumentException
        ? $exception->getMessage()
        : 'Platební služba nyní neodpověděla. Objednávka ani bankovní platba se tím nezměnila.';
    if(!str_contains($paymentError,'Kód chyby:'))$paymentError.=' Kód pro správce: '.$reference.'.';
    $qr=$order['payment_record_status']==='pending'?shopPaymentQrDataUri((string)$order['spd_payload']):null;
}
$sumupAvailable=!$isGuestAccess&&sumupIsEnabled()&&shopPaymentPolicyAllowsSumUp($order['accepted_payment_methods']??null)&&$order['status']==='placed'&&$order['payment_record_status']==='pending';
$stripeAvailable=!$isGuestAccess&&!$sumupAvailable&&stripeIsEnabled()&&$order['status']==='placed'&&$order['payment_record_status']==='pending';
$isQuickProgram=($order['checkout_mode']??'')==='quick_program';
$pendingAthleteReview=false;foreach($order['items']as$item)if((int)($item['athlete_registration_request_id']??0)>0&&$item['beneficiary_sportovec_id']===null){$pendingAthleteReview=true;break;}
$messages=[
    'placed'=>['warning',$sumupAvailable?'Objednávka čeká na úhradu. Můžete zaplatit online přes SumUp nebo bankovním převodem.':($stripeAvailable?'Objednávka čeká na úhradu. Můžete zaplatit kartou přes Stripe nebo bankovním převodem.':'Objednávka čeká na bankovní platbu. Pro správné spárování použijte uvedený variabilní symbol.')],
    'processing'=>['info','Platba byla přijata a objednávku připravujeme.'],
    'ready'=>['success','Objednávka je připravena k osobnímu odběru.'],
    'completed'=>['secondary','Objednávka byla osobně vydána a dokončena.'],
    'cancelled'=>['danger',$order['payment_record_status']==='refund_required'?'Objednávka byla stornována. Přijatá platba čeká na samostatné vrácení.':($order['payment_record_status']==='refunded'?'Objednávka byla stornována a přijatá platba byla vrácena.':'Objednávka byla stornována a platební předpis již není platný.')],
];
if($isQuickProgram)$messages=[
    'placed'=>['warning','Místo je dočasně rezervované a objednávka čeká na bankovní platbu. Pro správné spárování použijte uvedený variabilní symbol.'],
    'processing'=>['info',$pendingAthleteReview?'Platba byla přijata. Údaje sportovce nyní zkontroluje klub; do té doby je místo nadále rezervované.':'Platba byla přijata a přihláška sportovce je aktivní.'],
    'ready'=>['success','Přihláška sportovce je potvrzena.'],
    'completed'=>['success','Přihláška sportovce je dokončena.'],
    'cancelled'=>$messages['cancelled'],
];
[$messageStyle,$messageText]=$messages[$order['status']]??['secondary','Aktuální stav objednávky: '.(string)$order['status']];
?>
<!doctype html>
<html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Objednávka <?=orderPublicH($order['public_code'])?></title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous"><?php appUiAssets(); ?></head>
<body class="bg-light"><?php publicShellNav();shopPublicNavigation($pdo); ?><main class="container py-4" style="max-width:900px">
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h3 mb-0">Objednávka <?=orderPublicH($order['public_code'])?></h1><div class="d-flex gap-2"><?php if(!$isGuestAccess):?><a href="moje_objednavky.php" class="btn btn-outline-primary">Moje objednávky</a><?php endif;?><a href="eshop.php" class="btn btn-outline-secondary">Hlavní stránka e-shopu</a></div></div>
<?php if($isGuestAccess):?><div class="alert alert-info small"><?=$isQuickProgram?'Toto je bezpečný odkaz na přihlášku a platbu. Účet jsme vytvořili automaticky; ověřte e-mail pomocí odkazu, který jsme vám poslali.':'Toto je bezpečný odkaz na nákup bez účtu. Uložte si e-mail s odkazem; stav objednávky se zde průběžně aktualizuje.'?></div><?php endif;?>
<?php if($paymentError!==''):?><div class="alert alert-danger"><strong>Platební bránu se nepodařilo otevřít.</strong><br><?=orderPublicH($paymentError)?><div class="small mt-2">Objednávka zůstává ve stavu čeká na úhradu. Můžete akci zopakovat nebo bezpečně použít bankovní převod níže.</div></div><?php endif;?>
<?php if(($_GET['sumup']??'')==='return'&&$order['payment_record_status']==='pending'):?><div class="alert alert-info">SumUp platbu ověřujeme. Stav objednávky se změní až po potvrzení platební služby.</div><?php endif;?>
<?php if(($_GET['stripe']??'')==='cancelled'&&$order['payment_record_status']==='pending'):?><div class="alert alert-info">Platba kartou nebyla dokončena. Můžete ji zkusit znovu nebo použít bankovní převod.</div><?php endif;?>
<div class="alert alert-<?=$messageStyle?>"><?=orderPublicH($messageText)?></div>
<div class="row g-3"><div class="col-md-7"><div class="card border-0 shadow-sm"><div class="card-header bg-white fw-semibold">Neměnný obsah objednávky</div><div class="card-body">
<?php foreach($order['items']as$item):?><div class="d-flex justify-content-between border-bottom py-2"><span><?=orderPublicH($item['product_name_snapshot'])?> × <?=(int)$item['quantity']?><br><code><?=orderPublicH($item['sku_snapshot'])?></code><?php if((int)($item['athlete_registration_request_id']??0)>0):?><br><small class="<?=$item['beneficiary_sportovec_id']===null?'text-warning':'text-success'?>"><?=$item['beneficiary_sportovec_id']===null?'Údaje sportovce čekají na kontrolu klubu.':'Sportovec byl ověřen a připojen k přihlášce.'?></small><?php endif;?></span><strong><?=orderPublicMoney((int)$item['line_amount_minor'],(string)$item['currency'])?></strong></div><?php endforeach;?>
<?php foreach($order['event_items']as$item):?><div class="d-flex justify-content-between border-bottom py-2"><span><?=orderPublicH($item['event_name_snapshot'])?><br><small><?=orderPublicH('Účastník #'.$item['beneficiary_sportovec_id'].' · souhlas '.$item['consent_version_snapshot'].' · přihláška #'.$item['registration_id'])?></small></span><strong><?=orderPublicMoney((int)$item['line_amount_minor'],(string)$item['currency'])?></strong></div><?php endforeach;?>
<?php foreach($order['velodrome_items']as$item):?><div class="d-flex justify-content-between border-bottom py-2"><span><?=orderPublicH($item['lesson_name_snapshot'])?><br><small><?=orderPublicH($item['lesson_date_snapshot'].' '.substr((string)$item['starts_at_snapshot'],0,5).'–'.substr((string)$item['ends_at_snapshot'],0,5))?> · rezervace #<?=(int)$item['reservation_id']?></small></span><strong><?=orderPublicMoney((int)$item['line_amount_minor'],(string)$item['currency'])?></strong></div><?php endforeach;?>
<div class="d-flex justify-content-between pt-3"><span>Mezisoučet</span><span><?=orderPublicMoney((int)$order['subtotal_minor'],(string)$order['currency'])?></span></div><?php if((int)$order['discount_minor']>0):?><div class="d-flex justify-content-between text-success"><span>Sleva <?=orderPublicH($order['coupon_code_snapshot'])?></span><span>− <?=orderPublicMoney((int)$order['discount_minor'],(string)$order['currency'])?></span></div><?php endif;?><div class="d-flex justify-content-between fs-5 pt-2"><strong>Celkem</strong><strong><?=orderPublicMoney((int)$order['total_minor'],(string)$order['currency'])?></strong></div></div></div></div>
<div class="col-md-5"><div class="card border-0 shadow-sm"><div class="card-header bg-white fw-semibold"><?=$qr!==null?'Bankovní převod':'Stav platby'?></div><div class="card-body text-center">
<?php if($qr!==null):?><img class="img-fluid" src="<?=orderPublicH($qr)?>" alt="QR platba"><dl class="text-start small mt-2"><dt>Účet</dt><dd><?=orderPublicH($order['account_label_snapshot'])?><br><code><?=orderPublicH($order['iban_snapshot'])?></code></dd><dt>Variabilní symbol</dt><dd><code><?=orderPublicH($order['variable_symbol'])?></code></dd><dt>Částka</dt><dd><?=orderPublicMoney((int)$order['total_minor'],(string)$order['currency'])?></dd><dt>Splatnost</dt><dd><?=orderPublicH($order['due_at'])?></dd></dl><?php if($sumupAvailable):?><hr><form method="post" class="payment-checkout-form" data-provider-name="SumUp"><?=csrf_field()?><input type="hidden" name="action" value="sumup_checkout"><button class="btn btn-primary w-100">Zaplatit online přes SumUp</button></form><div class="small text-muted mt-2">Platba proběhne na zabezpečené stránce SumUp.</div><?php elseif($stripeAvailable):?><hr><form method="post" class="payment-checkout-form" data-provider-name="Stripe"><?=csrf_field()?><input type="hidden" name="action" value="stripe_checkout"><button class="btn btn-primary w-100">Zaplatit kartou</button></form><div class="small text-muted mt-2">Platba proběhne na zabezpečené stránce Stripe.</div><?php endif;?><div class="alert alert-info small text-start mt-3 mb-0 d-none" id="payment-checkout-status" role="status" aria-live="polite"></div>
<?php else:?><p class="mb-0"><?=orderPublicH(['paid'=>'Platba přijata','cancelled'=>'Platební předpis zrušen','refund_required'=>'Čeká na vrácení platby','refunded'=>'Platba byla vrácena'][$order['payment_record_status']]??(string)$order['payment_record_status'])?></p><?php if($order['refund_sent_at']!==null):?><div class="small text-muted mt-2">Odesláno <?=orderPublicH($order['refund_sent_at'])?></div><?php endif;?><?php endif;?>
</div></div></div></div></main><script>
document.querySelectorAll('.payment-checkout-form').forEach(form => form.addEventListener('submit', event => {
    if (event.defaultPrevented) return;
    const provider = form.dataset.providerName || 'platební bránu';
    const status = document.getElementById('payment-checkout-status');
    const button = form.querySelector('button[type="submit"], button:not([type])');
    if (status) {
        status.classList.remove('d-none', 'alert-warning');
        status.classList.add('alert-info');
        status.textContent = 'Otevíráme zabezpečenou platební bránu ' + provider + '. Obvykle to trvá jen několik sekund.';
    }
    if (button) {
        button.disabled = true;
        button.dataset.originalText = button.textContent;
        button.textContent = 'Otevírám platební bránu…';
    }
    window.setTimeout(() => {
        if (document.visibilityState === 'hidden') return;
        document.body.classList.remove('app-loading');
        form.removeAttribute('aria-busy');
        delete form.dataset.appSubmitting;
        if (button) {
            button.disabled = false;
            button.textContent = button.dataset.originalText || 'Zkusit znovu';
        }
        if (status) {
            status.classList.remove('alert-info');
            status.classList.add('alert-warning');
            status.textContent = 'Platební brána se neotevřela v očekávaném čase. Můžete pokus bezpečně zopakovat nebo použít bankovní převod.';
        }
    }, 20000);
}));
</script><?php publicShellFooter(); ?></body></html>
