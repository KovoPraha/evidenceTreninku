<?php
declare(strict_types=1);
require_once __DIR__.'/shop_guest_checkout.php';

/** Personal sale uses canonical order/payment/stock; recording cash remains a finance action. */
function shopAssistedSalePlace(PDO $pdo,int $variantId,int $quantity,array $customer,int $actor,string $reason,string $key,string $accessToken,array $bank,?int $accountId=null,?int $beneficiaryId=null,string $payer='',string $recipient=''):array
{
    $reason=shopOrderAdminValidateAction(1,$actor,$reason,($customer['confirmed']??'')==='1','Osobní prodej');
    $payer=trim($payer);$recipient=trim($recipient);
    if($payer===''||$recipient===''||mb_strlen($payer)>200||mb_strlen($recipient)>200)throw new InvalidArgumentException('Doplňte plátce a příjemce zboží.');
    $expectedPrice=isset($customer['expected_unit_minor'])?(int)$customer['expected_unit_minor']:null;
    $customer=shopGuestCustomerValidate($customer,false)+['confirmed'=>'1'];
    $fingerprint=hash('sha256',json_encode([$variantId,$quantity,$customer,$actor,$reason,$accountId,$beneficiaryId,$payer,$recipient],JSON_THROW_ON_ERROR));
    $context=['actor_id'=>$actor,'account_id'=>$accountId,'beneficiary_id'=>$beneficiaryId,'payer_name'=>$payer,'recipient_name'=>$recipient,'reason'=>$reason,'fingerprint'=>$fingerprint,'expected_price'=>$expectedPrice];
    $result=shopGuestCheckoutPlace($pdo,$variantId,$quantity,$customer,$key,$accessToken,$bank,'personal_pickup','',0,null,'',0,null,$context);
    return $result+['sale_saved'=>true,'money_confirmed'=>$result['payment_status']==='paid','fulfillment_recorded'=>shopFulfillmentIssuedQuantity($pdo,(int)$result['id'])>0];
}
function shopAssistedSaleValidateInTransaction(PDO $pdo,array $context,array $variant,array $customer):void
{
    if(!$pdo->inTransaction()||!shopFulfillmentAvailable($pdo))throw new LogicException('Osobní prodej vyžaduje připravenou evidenci a transakci.');
    $actor=(int)($context['actor_id']??0);shopInventoryActor($pdo,$actor,'order_operator');$s=$pdo->prepare('SELECT id FROM treneri WHERE id=?');$s->execute([$actor]);if(!$s->fetchColumn())throw new ShopCheckoutException('Pracovník prodeje neexistuje.');
    if(!shopInventoryManaged($pdo,(int)$variant['id']))throw new ShopCheckoutException('Osobní prodej vyžaduje ověřený fyzický sklad varianty.');
    $account=(int)($context['account_id']??0);$beneficiary=(int)($context['beneficiary_id']??0);
    if($account>0){$s=$pdo->prepare('SELECT id FROM verejni_uzivatele WHERE id=? AND aktivni=1 AND email_overeno=1');$s->execute([$account]);if(!$s->fetchColumn())throw new ShopCheckoutException('Zvolený zákaznický účet není aktivní a ověřený.');}
    if($beneficiary>0){if($account<1)throw new ShopCheckoutException('Příjemce z evidence vyžaduje doloženou vazbu zákaznického účtu.');shopBeneficiaryAssertAccessible($pdo,$account,$beneficiary,true);}
}
function shopAssistedSaleRecordInTransaction(PDO $pdo,int $orderId,array $context,array $variant):void
{
    $quote=$variant['member_price']??['public_amount_minor'=>(int)$variant['amount_minor'],'effective_amount_minor'=>(int)$variant['amount_minor'],'is_member_price'=>false];
    $pdo->prepare('INSERT INTO shop_assisted_sales(order_id,actor_trainer_id,account_id,beneficiary_sportovec_id,payer_name,recipient_name,price_snapshot_json,request_fingerprint,reason) VALUES(?,?,?,?,?,?,?,?,?)')->execute([$orderId,(int)$context['actor_id'],$context['account_id'],$context['beneficiary_id'],$context['payer_name'],$context['recipient_name'],json_encode($quote,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$context['fingerprint'],$context['reason']]);
    if((int)($context['beneficiary_id']??0)>0){$pdo->prepare('UPDATE shop_order_items SET beneficiary_sportovec_id=? WHERE order_id=?')->execute([(int)$context['beneficiary_id'],$orderId]);$pdo->prepare('UPDATE shop_orders SET finance_sportovec_id=? WHERE id=?')->execute([(int)$context['beneficiary_id'],$orderId]);}
    $pdo->prepare("INSERT INTO shop_order_events(order_id,actor_type,actor_id,action,from_status,to_status,note) VALUES (?,'trainer',?,'assisted_sale','placed','placed',?)")->execute([$orderId,(int)$context['actor_id'],$context['reason'].' | Osobní prodej. Platba a výdej čekají na samostatné potvrzení.']);
}
function shopAssistedSaleAssertReplay(PDO $pdo,string $code,array $context):void
{
    $s=$pdo->prepare('SELECT a.request_fingerprint FROM shop_assisted_sales a JOIN shop_orders o ON o.id=a.order_id WHERE o.public_code=?');$s->execute([$code]);$fp=$s->fetchColumn();
    if(!is_string($fp)||!hash_equals($fp,(string)$context['fingerprint']))throw new ShopCheckoutException('Stejný požadavek už vytvořil jiný prodej. Původní objednávku nejdříve zkontrolujte.');
}
