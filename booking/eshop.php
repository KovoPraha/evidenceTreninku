<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/session_security.php';
app_session_start();
if (!isset($_SESSION['verejny_uzivatel_id'])) {
    header('Location: prihlaseni.php?redirect=eshop.php');
    exit;
}
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/csrf_helper.php';
require_once dirname(__DIR__) . '/includes/shop_checkout.php';
function shopPublicH(mixed $value):string{return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function shopPublicMoney(int $minor,string $currency='CZK'):string{return number_format($minor/100,2,',',' ').' '.shopPublicH($currency);}
$accountId=(int)$_SESSION['verejny_uzivatel_id'];$errors=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!csrf_verify((string)($_POST['csrf_token']??'')))$errors[]='Formulář vypršel. Obnovte stránku.';
    else try{
        $action=(string)($_POST['action']??'');
        if($action==='add'){
            $detail=shopCartDetail($pdo,$accountId);$current=0;foreach($detail['items']as$item)if((int)$item['variant_id']===(int)($_POST['variant_id']??0))$current=(int)$item['quantity'];
            shopCartSetQuantity($pdo,$accountId,(int)($_POST['variant_id']??0),min(99,$current+1));$_SESSION['flash_shop']='Položka byla přidána do košíku.';
        }elseif($action==='quantity'){
            shopCartSetQuantity($pdo,$accountId,(int)($_POST['variant_id']??0),(int)($_POST['quantity']??0));$_SESSION['flash_shop']='Košík byl upraven.';
        }elseif($action==='checkout'){
            $key=(string)($_POST['checkout_key']??'');
            if(!isset($_SESSION['shop_checkout_key'])||!hash_equals((string)$_SESSION['shop_checkout_key'],$key))throw new ShopCheckoutException('Checkout klíč vypršel. Obnovte stránku.');
            $order=shopCheckoutPlace($pdo,$accountId,$key,shopBankSettingsFromConfig(),(string)($_POST['cart_fingerprint']??''));unset($_SESSION['shop_checkout_key']);
            header('Location: objednavka.php?code='.rawurlencode((string)$order['public_code']),true,303);exit;
        }else throw new InvalidArgumentException('Neplatná akce.');
        header('Location: eshop.php',true,303);exit;
    }catch(PDOException $exception){error_log('booking/eshop.php: '.$exception->getMessage());$errors[]='Databázová operace selhala bez částečného zápisu.';}
    catch(InvalidArgumentException|ShopCheckoutException $exception){$errors[]=$exception->getMessage();}
}
$success=(string)($_SESSION['flash_shop']??'');unset($_SESSION['flash_shop']);
$products=shopStorefrontProducts($pdo);$cart=shopCartDetail($pdo,$accountId);
if(!isset($_SESSION['shop_checkout_key'])||preg_match('/^[a-f0-9]{32}$/D',(string)$_SESSION['shop_checkout_key'])!==1)$_SESSION['shop_checkout_key']=bin2hex(random_bytes(16));
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Klubový e-shop</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"></head><body class="bg-light"><main class="container py-4"><div class="d-flex justify-content-between align-items-center mb-3"><div><h1 class="h3 mb-0">Klubový e-shop</h1><div class="text-muted">První řízený nákup aktivního zboží – osobní odběr a bankovní převod.</div></div><div class="d-flex gap-2"><a href="moje_objednavky.php" class="btn btn-outline-success btn-sm">Moje objednávky</a><a href="krouzky.php" class="btn btn-outline-secondary btn-sm">Kroužky</a><a href="odhlaseni.php" class="btn btn-outline-secondary btn-sm">Odhlásit</a></div></div>
<?php foreach($errors as$error):?><div class="alert alert-danger"><?=shopPublicH($error)?></div><?php endforeach;?><?php if($success!==''):?><div class="alert alert-success"><?=shopPublicH($success)?></div><?php endif;?>
<div class="row g-4"><section class="col-lg-8"><h2 class="h5">Aktivní zboží</h2><div class="row g-3"><?php foreach($products as$product):?><div class="col-md-6"><div class="card h-100 border-0 shadow-sm"><div class="card-body"><h3 class="h6"><?=shopPublicH($product['public_name'])?></h3><p class="small text-muted"><?=nl2br(shopPublicH($product['public_summary']))?></p><div class="small"><code><?=shopPublicH($product['sku'])?></code><?php foreach($product['attributes']as$key=>$value):?> · <?=shopPublicH($key)?>: <?=shopPublicH(is_scalar($value)?$value:'')?><?php endforeach;?></div><div class="d-flex justify-content-between align-items-center mt-3"><strong><?=shopPublicMoney((int)$product['amount_minor'],(string)$product['currency'])?></strong><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="add"><input type="hidden" name="variant_id" value="<?=(int)$product['variant_id']?>"><button class="btn btn-sm btn-primary">Přidat</button></form></div></div></div></div><?php endforeach;?><?php if($products===[]):?><div class="col-12"><div class="alert alert-light border">Zatím není aktivní žádné běžné zboží.</div></div><?php endif;?></div></section>
<aside class="col-lg-4"><div class="card border-0 shadow-sm sticky-top" style="top:1rem"><div class="card-header bg-white fw-semibold">Košík</div><div class="card-body"><?php foreach($cart['items']as$item):?><div class="border-bottom pb-2 mb-2"><strong><?=shopPublicH($item['public_name'])?></strong><div class="small text-muted"><?=shopPublicH($item['sku'])?></div><form method="post" class="d-flex gap-2 align-items-center mt-1"><?=csrf_field()?><input type="hidden" name="action" value="quantity"><input type="hidden" name="variant_id" value="<?=(int)$item['variant_id']?>"><input class="form-control form-control-sm" style="width:80px" type="number" name="quantity" min="0" max="99" value="<?=(int)$item['quantity']?>"><button class="btn btn-sm btn-outline-secondary">Uložit</button><span class="ms-auto"><?=shopPublicMoney((int)$item['line_amount_minor'],(string)$item['currency'])?></span></form></div><?php endforeach;?><?php if($cart['items']===[]):?><div class="text-muted">Košík je prázdný.</div><?php else:?><div class="d-flex justify-content-between fs-5 mt-3"><strong>Celkem</strong><strong><?=shopPublicMoney($cart['total_minor'],(string)$cart['currency'])?></strong></div><div class="small text-muted my-2">Osobní odběr · platba bankovním převodem</div><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="checkout"><input type="hidden" name="checkout_key" value="<?=shopPublicH($_SESSION['shop_checkout_key'])?>"><input type="hidden" name="cart_fingerprint" value="<?=shopPublicH($cart['fingerprint'])?>"><button class="btn btn-success w-100">Vytvořit objednávku</button></form><?php endif;?></div></div></aside></div></main></body></html>
