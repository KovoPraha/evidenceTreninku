<?php
declare(strict_types=1);

require_once __DIR__.'/includes/init.php';
require_once __DIR__.'/csrf_helper.php';
require_once __DIR__.'/includes/shop_checkout.php';
if(!isset($_SESSION['trener_id'])||!roleAtLeast('admin')){header('Location: login.php');exit;}
function expiryH(mixed $value):string{return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
$errors=[];$success='';$now=new DateTimeImmutable('now');
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!csrf_verify((string)($_POST['csrf_token']??'')))$errors[]='Formular vyprsel. Obnovte stranku.';
    else try{
        if(($_POST['confirm_expire']??'')!=='1')throw new InvalidArgumentException('Expiraci je nutne vyslovne potvrdit.');
        $ids=array_values(array_unique(array_filter(array_map('intval',(array)($_POST['order_ids']??[])),static fn(int$id):bool=>$id>0)));
        if($ids===[])throw new InvalidArgumentException('Vyberte alespon jednu objednavku z nahledu.');
        $expired=0;$unchanged=0;
        foreach($ids as$id){$result=shopOrderExpirePending($pdo,$id,$now,true);$result['changed']?$expired++:$unchanged++;}
        $_SESSION['flash_expiry']='Expirovano '.$expired.' objednavek; beze zmeny '.$unchanged.'.';header('Location: eshop_order_expiry_admin.php',true,303);exit;
    }catch(InvalidArgumentException|ShopCheckoutException $exception){$errors[]=$exception->getMessage();}
}
$success=(string)($_SESSION['flash_expiry']??'');unset($_SESSION['flash_expiry']);$orders=shopOrderExpirationPreview($pdo,$now);
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Expirace objednavek</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light"><?php include __DIR__.'/hlavicka.php';?><main class="container py-4" style="max-width:1200px">
<div class="d-flex justify-content-between align-items-center mb-3"><div><h1 class="h4 mb-1">Expirace nezaplacenych objednavek</h1><div class="text-muted small">Nahled k <?=expiryH($now->format('Y-m-d H:i:s'))?>. Zaplacene a dokoncene objednavky se nikdy nenabizeji.</div></div><a class="btn btn-outline-secondary btn-sm" href="eshop_orders_admin.php">Zpet na objednavky</a></div>
<?php foreach($errors as$error):?><div class="alert alert-danger"><?=expiryH($error)?></div><?php endforeach;?><?php if($success!==''):?><div class="alert alert-success"><?=expiryH($success)?></div><?php endif;?>
<div class="alert alert-warning small">Potvrzeni pouzije stejny transakcni storno lifecycle: zrusi platebni predpis, prave jednou vrati sklad a uvolni drzenou kapacitu velodromu. Kazda objednavka se pred zmenou znovu zamkne a overi.</div>
<form method="post"><?=csrf_field()?><div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th></th><th>Objednavka</th><th>Zakaznik</th><th>Castka</th><th>Deadline</th><th>Velodrom</th></tr></thead><tbody>
<?php foreach($orders as$order):?><tr><td><input class="form-check-input" type="checkbox" name="order_ids[]" value="<?=(int)$order['id']?>"></td><td><code><?=expiryH($order['public_code'])?></code></td><td><?=expiryH($order['customer_name_snapshot'])?><div class="small text-muted"><?=expiryH($order['customer_email_snapshot'])?></div></td><td><?=number_format((int)$order['total_minor']/100,2,',',' ')?> <?=expiryH($order['currency'])?></td><td><?=expiryH($order['payment_expires_at']??$order['due_at'])?></td><td><?=(int)$order['velodrome_items']?> rezervaci</td></tr><?php endforeach;?>
<?php if($orders===[]):?><tr><td colspan="6" class="text-center text-muted py-4">Zadna nezaplacena objednavka po splatnosti.</td></tr><?php endif;?></tbody></table></div></div>
<?php if($orders!==[]):?><div class="border border-danger-subtle rounded bg-white p-3 mt-3"><label class="d-block"><input type="checkbox" name="confirm_expire" value="1" required> Potvrzuji expiraci vybranych nezaplacenych objednavek a uvolneni jejich rezervaci.</label><button class="btn btn-outline-danger mt-2">Expirovat vybrane</button></div><?php endif;?></form>
</main></body></html>
