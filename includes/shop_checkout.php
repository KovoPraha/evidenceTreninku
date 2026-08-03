<?php
declare(strict_types=1);

final class ShopCheckoutException extends RuntimeException
{
}

/** @return list<array<string,mixed>> */
function shopStorefrontProducts(PDO $pdo): array
{
    $rows = $pdo->query(
        "SELECT p.id AS product_id,pub.public_name,pub.public_summary,v.id AS variant_id,v.sku,"
        . 'v.attributes_json,v.amount_minor,v.currency,v.stock_quantity_decimal '
        . 'FROM shop_products p JOIN shop_product_publications pub ON pub.product_id=p.id '
        . 'JOIN shop_variants v ON v.product_id=p.id '
        . "WHERE p.offer_type='goods' AND p.catalog_status='active' AND pub.status='active' "
        . "AND v.catalog_status='active' AND (v.visible=1 OR v.visible IS NULL) "
        . "AND v.price_mode='fixed' AND v.amount_minor>=0 AND v.currency='CZK' "
        . 'ORDER BY pub.public_name,v.sku,v.id'
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $decoded = json_decode((string)$row['attributes_json'], true);
        $row['attributes'] = is_array($decoded) ? $decoded : [];
    }
    return $rows;
}

/** @return array<string,mixed> */
function shopCartGetOrCreate(PDO $pdo, int $accountId): array
{
    if ($accountId < 1) {
        throw new InvalidArgumentException('Košík vyžaduje přihlášený účet.');
    }
    $statement = $pdo->prepare("SELECT * FROM shop_carts WHERE active_account_id=? AND status='active'");
    $statement->execute([$accountId]);
    $cart = $statement->fetch(PDO::FETCH_ASSOC);
    if ($cart) return $cart;
    try {
        $insert = $pdo->prepare(
            "INSERT INTO shop_carts(cart_key,account_id,active_account_id,status) VALUES (?,?,?,'active')"
        );
        $insert->execute([bin2hex(random_bytes(16)), $accountId, $accountId]);
    } catch (PDOException $exception) {
        if ((string)$exception->getCode() !== '23000') throw $exception;
    }
    $statement->execute([$accountId]);
    $cart = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$cart) throw new ShopCheckoutException('Aktivní košík se nepodařilo bezpečně založit.');
    return $cart;
}

/** @return array{cart:array<string,mixed>,items:list<array<string,mixed>>,total_minor:int,currency:?string} */
function shopCartDetail(PDO $pdo, int $accountId): array
{
    $cart = shopCartGetOrCreate($pdo, $accountId);
    $statement = $pdo->prepare(
        'SELECT ci.id AS cart_item_id,ci.quantity,p.id AS product_id,pub.public_name,'
        . 'v.id AS variant_id,v.sku,v.attributes_json,v.amount_minor,v.currency,v.catalog_status '
        . 'FROM shop_cart_items ci JOIN shop_variants v ON v.id=ci.variant_id '
        . 'JOIN shop_products p ON p.id=v.product_id '
        . 'JOIN shop_product_publications pub ON pub.product_id=p.id WHERE ci.cart_id=? ORDER BY ci.id'
    );
    $statement->execute([(int)$cart['id']]);
    $items = $statement->fetchAll(PDO::FETCH_ASSOC);
    $total = 0;$currency = null;
    foreach ($items as &$item) {
        $item['line_amount_minor'] = (int)$item['amount_minor'] * (int)$item['quantity'];
        $total += $item['line_amount_minor'];
        $currency ??= (string)$item['currency'];
        $decoded = json_decode((string)$item['attributes_json'], true);
        $item['attributes'] = is_array($decoded) ? $decoded : [];
    }
    return ['cart'=>$cart,'items'=>$items,'total_minor'=>$total,'currency'=>$currency,'fingerprint'=>shopCartFingerprint($items)];
}

function shopCartSetQuantity(PDO $pdo, int $accountId, int $variantId, int $quantity): void
{
    if ($accountId < 1 || $variantId < 1 || $quantity < 0 || $quantity > 99) {
        throw new InvalidArgumentException('Neplatná položka nebo množství košíku.');
    }
    $pdo->beginTransaction();
    try {
        $cart = shopCartLockActive($pdo, $accountId);
        if ($quantity === 0) {
            $pdo->prepare('DELETE FROM shop_cart_items WHERE cart_id=? AND variant_id=?')
                ->execute([(int)$cart['id'],$variantId]);
        } else {
            $variant = shopCheckoutLockVariant($pdo, $variantId);
            if (!$variant || !shopCheckoutVariantIsSaleable($variant)) {
                throw new ShopCheckoutException('Varianta není aktuálně dostupná pro nákup.');
            }
            if ($cart['currency'] !== null && $cart['currency'] !== $variant['currency']) {
                throw new ShopCheckoutException('Jeden košík nesmí míchat různé měny.');
            }
            $existing = $pdo->prepare('SELECT id FROM shop_cart_items WHERE cart_id=? AND variant_id=?');
            $existing->execute([(int)$cart['id'],$variantId]);
            if ($existing->fetchColumn()) {
                $pdo->prepare(
                    'UPDATE shop_cart_items SET quantity=?,updated_at=CURRENT_TIMESTAMP WHERE cart_id=? AND variant_id=?'
                )->execute([$quantity,(int)$cart['id'],$variantId]);
            } else {
                $pdo->prepare('INSERT INTO shop_cart_items(cart_id,variant_id,quantity) VALUES (?,?,?)')
                    ->execute([(int)$cart['id'],$variantId,$quantity]);
            }
            $pdo->prepare('UPDATE shop_carts SET currency=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')
                ->execute([(string)$variant['currency'],(int)$cart['id']]);
        }
        $remaining = $pdo->prepare('SELECT COUNT(*) FROM shop_cart_items WHERE cart_id=?');
        $remaining->execute([(int)$cart['id']]);
        if ((int)$remaining->fetchColumn() === 0) {
            $pdo->prepare('UPDATE shop_carts SET currency=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=?')
                ->execute([(int)$cart['id']]);
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof InvalidArgumentException || $exception instanceof ShopCheckoutException) throw $exception;
        throw new ShopCheckoutException('Košík se nepodařilo změnit bez částečného zápisu.',0,$exception);
    }
}

/** @param array{iban:string,bic:string,account_label:string,due_days:int} $bank @return array<string,mixed> */
function shopCheckoutPlace(
    PDO $pdo,
    int $accountId,
    string $idempotencyKey,
    array $bank,
    string $expectedCartFingerprint
): array
{
    if ($accountId < 1 || preg_match('/^[a-f0-9]{32}$/D',$idempotencyKey)!==1
        || preg_match('/^[a-f0-9]{64}$/D',$expectedCartFingerprint)!==1
    ) {
        throw new InvalidArgumentException('Checkout vyžaduje účet a platný jednorázový klíč.');
    }
    $keyHash = hash('sha256',$accountId.':'.$idempotencyKey);
    $pdo->beginTransaction();
    try {
        $existing = $pdo->prepare('SELECT * FROM shop_orders WHERE idempotency_key_hash=? AND account_id=?');
        $existing->execute([$keyHash,$accountId]);
        $order = $existing->fetch(PDO::FETCH_ASSOC);
        if ($order) { $pdo->commit(); return $order + ['replayed'=>true]; }
        $bank = shopBankValidateSettings($bank);
        $cart = shopCartLockActive($pdo,$accountId);
        $account = $pdo->prepare('SELECT jmeno,prijmeni,email,aktivni,email_overeno FROM verejni_uzivatele WHERE id=?');
        $account->execute([$accountId]);$account=$account->fetch(PDO::FETCH_ASSOC);
        if (!$account || (int)$account['aktivni']!==1 || (int)$account['email_overeno']!==1
            || !filter_var((string)$account['email'],FILTER_VALIDATE_EMAIL)) {
            throw new ShopCheckoutException('Objednávku může vytvořit pouze aktivní účet s ověřeným e-mailem.');
        }
        $sql = 'SELECT ci.quantity,p.id AS product_id,p.offer_type,p.catalog_status AS product_status,'
            . 'pub.status AS publication_status,pub.public_name,v.id AS variant_id,v.* FROM shop_cart_items ci '
            . 'JOIN shop_variants v ON v.id=ci.variant_id JOIN shop_products p ON p.id=v.product_id '
            . 'JOIN shop_product_publications pub ON pub.product_id=p.id WHERE ci.cart_id=? ORDER BY v.id';
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql') $sql.=' FOR UPDATE';
        $itemsStatement=$pdo->prepare($sql);$itemsStatement->execute([(int)$cart['id']]);
        $items=$itemsStatement->fetchAll(PDO::FETCH_ASSOC);
        if ($items===[]) throw new ShopCheckoutException('Prázdný košík nelze objednat.');
        $total=0;$currency=null;
        foreach($items as $item){
            if(!shopCheckoutVariantIsSaleable($item)) throw new ShopCheckoutException('Některá položka už není dostupná. Obnovte košík.');
            $quantity=(int)$item['quantity'];$unit=(int)$item['amount_minor'];
            if($quantity<1||$quantity>99||$unit<0) throw new ShopCheckoutException('Košík obsahuje neplatné množství nebo cenu.');
            $currency??=(string)$item['currency'];if($currency!==$item['currency']) throw new ShopCheckoutException('Objednávka nesmí míchat měny.');
            $line=$unit*$quantity;if($line<0||$total>PHP_INT_MAX-$line) throw new ShopCheckoutException('Celková částka je mimo podporovaný rozsah.');
            $total+=$line;
        }
        if(!hash_equals($expectedCartFingerprint,shopCartFingerprint($items)))throw new ShopCheckoutException('Cena nebo obsah košíku se změnily. Zkontrolujte nový souhrn a odešlete jej znovu.');
        if($currency!=='CZK'||$total<1) throw new ShopCheckoutException('První bankovní checkout podporuje pouze kladnou částku v CZK.');
        $publicCode='KP'.date('ymd').strtoupper(bin2hex(random_bytes(5)));
        $insert=$pdo->prepare('INSERT INTO shop_orders(public_code,account_id,source_cart_id,idempotency_key_hash,status,payment_status,fulfillment_method,customer_name_snapshot,customer_email_snapshot,subtotal_minor,discount_minor,total_minor,currency,placed_at) '
            . "VALUES (?,?,?,?,'placed','pending','personal_pickup',?,?,?,0,?,?,CURRENT_TIMESTAMP)");
        $insert->execute([$publicCode,$accountId,(int)$cart['id'],$keyHash,trim((string)$account['jmeno'].' '.(string)$account['prijmeni']),(string)$account['email'],$total,$total,$currency]);
        $orderId=(int)$pdo->lastInsertId();
        foreach($items as $item){
            $quantity=(int)$item['quantity'];$line=(int)$item['amount_minor']*$quantity;
            $managedStock=$item['stock_quantity_decimal']!==null;
            if($managedStock){
                $reserve=$pdo->prepare('UPDATE shop_variants SET stock_quantity_decimal=stock_quantity_decimal-?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND stock_quantity_decimal>=?');
                $reserve->execute([$quantity,(int)$item['id'],$quantity]);
                if($reserve->rowCount()!==1) throw new ShopCheckoutException('Mezitím se vyprodala položka '.$item['sku'].'. Objednávka nebyla vytvořena.');
            }
            $orderItem=$pdo->prepare('INSERT INTO shop_order_items(order_id,product_id,variant_id,product_name_snapshot,sku_snapshot,attributes_json_snapshot,quantity,unit_amount_minor,line_amount_minor,currency,includes_vat_snapshot,vat_rate_basis_points_snapshot) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
            $orderItem->execute([$orderId,(int)$item['product_id'],(int)$item['id'],(string)$item['public_name'],(string)$item['sku'],(string)$item['attributes_json'],$quantity,(int)$item['amount_minor'],$line,(string)$item['currency'],$item['includes_vat'],$item['vat_rate_basis_points']]);
            $orderItemId=(int)$pdo->lastInsertId();
            if($managedStock){
                $stock=$pdo->prepare('SELECT stock_quantity_decimal FROM shop_variants WHERE id=?');$stock->execute([(int)$item['id']]);$stockAfter=(string)$stock->fetchColumn();
                $pdo->prepare('INSERT INTO shop_inventory_movements(variant_id,order_id,order_item_id,movement_type,quantity_delta_decimal,stock_after_decimal) VALUES (?,?,?,\'reserve\',?,?)')
                    ->execute([(int)$item['id'],$orderId,$orderItemId,(string)(-$quantity),$stockAfter]);
            }
        }
        $variableSymbol=shopPaymentVariableSymbol($orderId);
        $dueAt=(new DateTimeImmutable('now +'.$bank['due_days'].' days'))->setTime(23,59,59)->format('Y-m-d H:i:s');
        $spd=shopPaymentSpdPayload($bank['iban'],$total,$currency,$variableSymbol,'OBJEDNAVKA '.$publicCode);
        $pdo->prepare('INSERT INTO payments(payable_type,payable_id,method,status,amount_minor,currency,variable_symbol,iban_snapshot,bic_snapshot,account_label_snapshot,spd_payload,due_at) '
            . "VALUES ('shop_order',?,'bank_transfer','pending',?,?,?,?,?,?,?,?)")
            ->execute([$orderId,$total,$currency,$variableSymbol,$bank['iban'],$bank['bic']!==''?$bank['bic']:null,$bank['account_label'],$spd,$dueAt]);
        $pdo->prepare('INSERT INTO shop_order_events(order_id,actor_type,actor_id,action,from_status,to_status,note) '
            . "VALUES (?,'account',?,'place',NULL,'placed','Objednávka vytvořena serverovým checkoutem.')")
            ->execute([$orderId,$accountId]);
        $pdo->prepare("UPDATE shop_carts SET status='converted',active_account_id=NULL,converted_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE id=?")
            ->execute([(int)$cart['id']]);
        $pdo->commit();
        return shopOrderByCode($pdo,$accountId,$publicCode)+['replayed'=>false];
    }catch(Throwable $exception){
        if($pdo->inTransaction())$pdo->rollBack();
        if($exception instanceof InvalidArgumentException||$exception instanceof ShopCheckoutException)throw $exception;
        throw new ShopCheckoutException('Objednávka se nepodařila vytvořit bez částečného zápisu.',0,$exception);
    }
}

/** @return array<string,mixed> */
function shopOrderByCode(PDO $pdo,int $accountId,string $publicCode): array
{
    $statement=$pdo->prepare('SELECT o.*,p.id AS payment_id,p.method,p.status AS payment_record_status,p.variable_symbol,p.iban_snapshot,p.bic_snapshot,p.account_label_snapshot,p.spd_payload,p.due_at FROM shop_orders o JOIN payments p ON p.payable_type=\'shop_order\' AND p.payable_id=o.id WHERE o.account_id=? AND o.public_code=?');
    $statement->execute([$accountId,$publicCode]);$order=$statement->fetch(PDO::FETCH_ASSOC);
    if(!$order)throw new ShopCheckoutException('Objednávka nebyla nalezena.');
    $items=$pdo->prepare('SELECT * FROM shop_order_items WHERE order_id=? ORDER BY id');$items->execute([(int)$order['id']]);
    $order['items']=$items->fetchAll(PDO::FETCH_ASSOC);return $order;
}

/** @return array{iban:string,bic:string,account_label:string,due_days:int} */
function shopBankSettingsFromConfig(): array
{
    return shopBankValidateSettings([
        'iban'=>defined('SHOP_BANK_IBAN')?(string)SHOP_BANK_IBAN:'',
        'bic'=>defined('SHOP_BANK_BIC')?(string)SHOP_BANK_BIC:'',
        'account_label'=>defined('SHOP_BANK_ACCOUNT_LABEL')?(string)SHOP_BANK_ACCOUNT_LABEL:'',
        'due_days'=>defined('SHOP_BANK_DUE_DAYS')?(int)SHOP_BANK_DUE_DAYS:7,
    ]);
}

/** @param array<string,mixed> $settings @return array{iban:string,bic:string,account_label:string,due_days:int} */
function shopBankValidateSettings(array $settings): array
{
    $iban=strtoupper((string)preg_replace('/\s+/','',(string)($settings['iban']??'')));
    $bic=strtoupper(trim((string)($settings['bic']??'')));$label=trim((string)($settings['account_label']??''));$days=(int)($settings['due_days']??0);
    if(!shopBankIbanValid($iban)||($bic!==''&&preg_match('/^[A-Z0-9]{8}([A-Z0-9]{3})?$/D',$bic)!==1)||$label===''||mb_strlen($label,'UTF-8')>255||$days<1||$days>30){
        throw new ShopCheckoutException('Bankovní checkout není bezpečně nakonfigurován. Zkontrolujte IBAN, BIC, název účtu a splatnost.');
    }
    return ['iban'=>$iban,'bic'=>$bic,'account_label'=>$label,'due_days'=>$days];
}

function shopBankIbanValid(string $iban): bool
{
    if(preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/D',$iban)!==1)return false;
    $rearranged=substr($iban,4).substr($iban,0,4);$remainder=0;
    foreach(str_split($rearranged)as$character){$digits=ctype_alpha($character)?(string)(ord($character)-55):$character;foreach(str_split($digits)as$digit)$remainder=($remainder*10+(int)$digit)%97;}
    return $remainder===1;
}

function shopPaymentVariableSymbol(int $orderId): string
{
    if($orderId<1||strlen((string)$orderId)>10)throw new ShopCheckoutException('Objednávka nemůže získat platný variabilní symbol.');
    return str_pad((string)$orderId,10,'0',STR_PAD_LEFT);
}

function shopPaymentSpdPayload(string $iban,int $amountMinor,string $currency,string $variableSymbol,string $message): string
{
    if(!shopBankIbanValid($iban)||$amountMinor<1||preg_match('/^[A-Z]{3}$/D',$currency)!==1||preg_match('/^[0-9]{1,10}$/D',$variableSymbol)!==1)throw new InvalidArgumentException('Neplatné údaje pro QR platbu.');
    $message=preg_replace('/[^A-Z0-9 ._-]/','',strtoupper($message));$message=substr(trim((string)$message),0,60);
    return 'SPD*1.0*ACC:'.$iban.'*AM:'.number_format($amountMinor/100,2,'.','').'*CC:'.$currency.'*X-VS:'.$variableSymbol.'*MSG:'.$message;
}

function shopPaymentQrDataUri(string $payload): string
{
    require_once dirname(__DIR__).'/vendor/autoload.php';
    $writer=new Endroid\QrCode\Writer\SvgWriter();
    $qrCode=new Endroid\QrCode\QrCode(data:$payload,size:280,margin:12);
    return $writer->write($qrCode)->getDataUri();
}

/** @param list<array<string,mixed>> $items */
function shopCartFingerprint(array $items):string
{
    $contract=[];
    foreach($items as$item)$contract[]=[
        'variant_id'=>(int)$item['variant_id'],
        'quantity'=>(int)$item['quantity'],
        'amount_minor'=>(int)$item['amount_minor'],
        'currency'=>(string)$item['currency'],
    ];
    usort($contract,static fn(array $a,array $b):int=>$a['variant_id']<=>$b['variant_id']);
    return hash('sha256',(string)json_encode($contract,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
}

/** @return list<array<string,mixed>> */
function shopOrderAdminList(PDO $pdo,int $limit=200):array
{
    $limit=max(1,min(500,$limit));
    return $pdo->query('SELECT o.*,p.id AS payment_id,p.status AS payment_record_status,p.variable_symbol,p.due_at,p.paid_at,'
        .'e.action AS last_event_action,e.actor_id AS last_event_actor_id,e.note AS last_event_note,e.created_at AS last_event_at '
        .'FROM shop_orders o JOIN payments p ON p.payable_type=\'shop_order\' AND p.payable_id=o.id '
        .'LEFT JOIN shop_order_events e ON e.id=(SELECT MAX(e2.id) FROM shop_order_events e2 WHERE e2.order_id=o.id) '
        .'ORDER BY CASE p.status WHEN \'pending\' THEN 0 WHEN \'refund_required\' THEN 1 ELSE 2 END,o.created_at DESC,o.id DESC LIMIT '.$limit)->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array{order_id:int,payment_status:string,changed:bool} */
function shopOrderAdminConfirmBankPayment(PDO $pdo,int $paymentId,int $actorTrainerId,string $reason,bool $confirmed):array
{
    $reason=trim($reason);
    if($paymentId<1||$actorTrainerId<1||$reason===''||!$confirmed)throw new InvalidArgumentException('Potvrzení platby vyžaduje platbu, administrátora, důvod a výslovné potvrzení.');
    if(mb_strlen($reason,'UTF-8')>1000)throw new InvalidArgumentException('Důvod smí mít nejvýše 1000 znaků.');
    $pdo->beginTransaction();
    try{
        $sql='SELECT * FROM payments WHERE id=?';if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$sql.=' FOR UPDATE';
        $statement=$pdo->prepare($sql);$statement->execute([$paymentId]);$payment=$statement->fetch(PDO::FETCH_ASSOC);
        if(!$payment||$payment['payable_type']!=='shop_order'||$payment['method']!=='bank_transfer')throw new ShopCheckoutException('Bankovní platba objednávky nebyla nalezena.');
        $orderId=(int)$payment['payable_id'];
        $orderSql='SELECT * FROM shop_orders WHERE id=?';if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$orderSql.=' FOR UPDATE';
        $orderStatement=$pdo->prepare($orderSql);$orderStatement->execute([$orderId]);$order=$orderStatement->fetch(PDO::FETCH_ASSOC);
        if(!$order)throw new ShopCheckoutException('Objednávka platby nebyla nalezena.');
        if($payment['status']==='paid'){
            if($order['payment_status']!=='paid'||!in_array($order['status'],['processing','ready','completed'],true))throw new ShopCheckoutException('Stav potvrzené platby a objednávky není konzistentní.');
            $pdo->commit();return ['order_id'=>$orderId,'payment_status'=>'paid','changed'=>false];
        }
        if($payment['status']!=='pending'||$order['payment_status']!=='pending'||$order['status']!=='placed')throw new ShopCheckoutException('Platbu nebo objednávku v tomto stavu nelze potvrdit.');
        $pdo->prepare("UPDATE payments SET status='paid',paid_at=CURRENT_TIMESTAMP,confirmed_by_trainer_id=?,confirmation_note=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")
            ->execute([$actorTrainerId,$reason,$paymentId]);
        $pdo->prepare("UPDATE shop_orders SET payment_status='paid',status='processing',updated_at=CURRENT_TIMESTAMP WHERE id=?")
            ->execute([$orderId]);
        $pdo->prepare('INSERT INTO shop_order_events(order_id,actor_type,actor_id,action,from_status,to_status,note) VALUES (?,\'trainer\',?,\'confirm_bank_payment\',\'placed\',\'processing\',?)')
            ->execute([$orderId,$actorTrainerId,$reason]);
        $pdo->commit();return ['order_id'=>$orderId,'payment_status'=>'paid','changed'=>true];
    }catch(Throwable $exception){
        if($pdo->inTransaction())$pdo->rollBack();
        if($exception instanceof InvalidArgumentException||$exception instanceof ShopCheckoutException)throw $exception;
        throw new ShopCheckoutException('Potvrzení platby selhalo bez částečného zápisu.',0,$exception);
    }
}

/** @return array{order_id:int,payment_status:string,restocked_items:int,changed:bool} */
function shopOrderAdminCancel(PDO $pdo,int $orderId,int $actorTrainerId,string $reason,bool $confirmed):array
{
    $reason=shopOrderAdminValidateAction($orderId,$actorTrainerId,$reason,$confirmed,'Storno');
    $pdo->beginTransaction();
    try{
        $paymentSql="SELECT * FROM payments WHERE payable_type='shop_order' AND payable_id=?";
        if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$paymentSql.=' FOR UPDATE';
        $paymentStatement=$pdo->prepare($paymentSql);$paymentStatement->execute([$orderId]);$payment=$paymentStatement->fetch(PDO::FETCH_ASSOC);
        $order=shopOrderAdminLockOrder($pdo,$orderId);
        if(!$payment||!$order)throw new ShopCheckoutException('Objednávka nebo její platba nebyla nalezena.');
        if($order['status']==='cancelled'){
            if(!in_array($payment['status'],['cancelled','refund_required'],true)||$order['payment_status']!==$payment['status'])throw new ShopCheckoutException('Stornovaná objednávka má nekonzistentní stav platby.');
            $pdo->commit();return ['order_id'=>$orderId,'payment_status'=>(string)$payment['status'],'restocked_items'=>0,'changed'=>false];
        }
        if(!in_array($order['status'],['placed','processing','ready'],true))throw new ShopCheckoutException('Objednávku v tomto stavu nelze stornovat.');
        $paid=in_array($order['status'],['processing','ready'],true);
        if($paid&&($order['payment_status']!=='paid'||$payment['status']!=='paid'))throw new ShopCheckoutException('Zaplacená objednávka má nekonzistentní stav platby.');
        if(!$paid&&($order['payment_status']!=='pending'||$payment['status']!=='pending'))throw new ShopCheckoutException('Nezaplacená objednávka má nekonzistentní stav platby.');
        $paymentStatus=$paid?'refund_required':'cancelled';
        $restocked=shopOrderAdminRestock($pdo,$orderId);
        $pdo->prepare('UPDATE payments SET status=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$paymentStatus,(int)$payment['id']]);
        $pdo->prepare('UPDATE shop_orders SET status=\'cancelled\',payment_status=?,cancelled_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$paymentStatus,$orderId]);
        $note=$paid?$reason.' Platba vyžaduje samostatné vrácení zákazníkovi.':$reason;
        $pdo->prepare('INSERT INTO shop_order_events(order_id,actor_type,actor_id,action,from_status,to_status,note) VALUES (?,\'trainer\',?,\'cancel\',?,\'cancelled\',?)')
            ->execute([$orderId,$actorTrainerId,(string)$order['status'],$note]);
        $pdo->commit();return ['order_id'=>$orderId,'payment_status'=>$paymentStatus,'restocked_items'=>$restocked,'changed'=>true];
    }catch(Throwable $exception){
        if($pdo->inTransaction())$pdo->rollBack();
        if($exception instanceof InvalidArgumentException||$exception instanceof ShopCheckoutException)throw $exception;
        throw new ShopCheckoutException('Storno selhalo bez částečného zápisu.',0,$exception);
    }
}

/** @return array{order_id:int,status:string,changed:bool} */
function shopOrderAdminMarkReady(PDO $pdo,int $orderId,int $actorTrainerId,string $reason,bool $confirmed):array
{
    return shopOrderAdminFulfillmentTransition($pdo,$orderId,$actorTrainerId,$reason,$confirmed,'processing','ready','mark_ready','ready_at','Příprava');
}

/** @return array{order_id:int,status:string,changed:bool} */
function shopOrderAdminCompletePickup(PDO $pdo,int $orderId,int $actorTrainerId,string $reason,bool $confirmed):array
{
    return shopOrderAdminFulfillmentTransition($pdo,$orderId,$actorTrainerId,$reason,$confirmed,'ready','completed','complete_pickup','completed_at','Výdej');
}

/** @return array{order_id:int,status:string,changed:bool} */
function shopOrderAdminFulfillmentTransition(PDO $pdo,int $orderId,int $actorTrainerId,string $reason,bool $confirmed,string $from,string $to,string $action,string $timestampColumn,string $label):array
{
    $reason=shopOrderAdminValidateAction($orderId,$actorTrainerId,$reason,$confirmed,$label);
    if(!in_array($timestampColumn,['ready_at','completed_at'],true))throw new LogicException('Nepovolený časový sloupec přechodu.');
    $pdo->beginTransaction();
    try{
        $paymentSql="SELECT status FROM payments WHERE payable_type='shop_order' AND payable_id=?";
        if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$paymentSql.=' FOR UPDATE';
        $paymentStatement=$pdo->prepare($paymentSql);$paymentStatement->execute([$orderId]);
        if($paymentStatement->fetchColumn()!=='paid')throw new ShopCheckoutException('Výdejový stav nelze změnit bez konzistentní zaplacené platby.');
        $order=shopOrderAdminLockOrder($pdo,$orderId);
        if(!$order)throw new ShopCheckoutException('Objednávka nebyla nalezena.');
        if($order['status']===$to){$pdo->commit();return ['order_id'=>$orderId,'status'=>$to,'changed'=>false];}
        if($order['status']!==$from||$order['payment_status']!=='paid')throw new ShopCheckoutException('Objednávka není ve stavu povoleném pro tuto akci.');
        $pdo->prepare("UPDATE shop_orders SET status=?,{$timestampColumn}=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$to,$orderId]);
        $pdo->prepare('INSERT INTO shop_order_events(order_id,actor_type,actor_id,action,from_status,to_status,note) VALUES (?,\'trainer\',?,?,?,?,?)')
            ->execute([$orderId,$actorTrainerId,$action,$from,$to,$reason]);
        $pdo->commit();return ['order_id'=>$orderId,'status'=>$to,'changed'=>true];
    }catch(Throwable $exception){
        if($pdo->inTransaction())$pdo->rollBack();
        if($exception instanceof InvalidArgumentException||$exception instanceof ShopCheckoutException)throw $exception;
        throw new ShopCheckoutException($label.' selhala bez částečného zápisu.',0,$exception);
    }
}

/** @return array<string,mixed>|false */
function shopOrderAdminLockOrder(PDO $pdo,int $orderId):array|false
{
    $sql='SELECT * FROM shop_orders WHERE id=?';if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$sql.=' FOR UPDATE';
    $statement=$pdo->prepare($sql);$statement->execute([$orderId]);return $statement->fetch(PDO::FETCH_ASSOC);
}

function shopOrderAdminValidateAction(int $orderId,int $actorTrainerId,string $reason,bool $confirmed,string $label):string
{
    $reason=trim($reason);
    if($orderId<1||$actorTrainerId<1||$reason===''||!$confirmed)throw new InvalidArgumentException($label.' vyžaduje objednávku, administrátora, poznámku a výslovné potvrzení.');
    if(mb_strlen($reason,'UTF-8')>1000)throw new InvalidArgumentException('Poznámka smí mít nejvýše 1000 znaků.');
    return $reason;
}

function shopOrderAdminRestock(PDO $pdo,int $orderId):int
{
    $sql="SELECT oi.id AS order_item_id,oi.variant_id,oi.quantity FROM shop_order_items oi "
        ."JOIN shop_inventory_movements reserve ON reserve.order_item_id=oi.id AND reserve.movement_type='reserve' "
        ."WHERE oi.order_id=? ORDER BY oi.id";
    if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$sql.=' FOR UPDATE';
    $statement=$pdo->prepare($sql);$statement->execute([$orderId]);$items=$statement->fetchAll(PDO::FETCH_ASSOC);$count=0;
    foreach($items as$item){
        $restock=$pdo->prepare("SELECT id FROM shop_inventory_movements WHERE order_item_id=? AND movement_type='restock'");$restock->execute([(int)$item['order_item_id']]);
        if($restock->fetchColumn()!==false)continue;
        $variantSql='SELECT stock_quantity_decimal FROM shop_variants WHERE id=?';if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$variantSql.=' FOR UPDATE';
        $variant=$pdo->prepare($variantSql);$variant->execute([(int)$item['variant_id']]);
        if($variant->fetchColumn()===false)throw new ShopCheckoutException('Skladová varianta stornované položky nebyla nalezena.');
        $update=$pdo->prepare('UPDATE shop_variants SET stock_quantity_decimal=stock_quantity_decimal+?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND stock_quantity_decimal IS NOT NULL');
        $update->execute([(int)$item['quantity'],(int)$item['variant_id']]);
        if($update->rowCount()!==1)throw new ShopCheckoutException('Sklad stornované položky není spravovaný nebo jej nelze vrátit.');
        $stock=$pdo->prepare('SELECT stock_quantity_decimal FROM shop_variants WHERE id=?');$stock->execute([(int)$item['variant_id']]);
        $pdo->prepare('INSERT INTO shop_inventory_movements(variant_id,order_id,order_item_id,movement_type,quantity_delta_decimal,stock_after_decimal) VALUES (?,?,?,\'restock\',?,?)')
            ->execute([(int)$item['variant_id'],$orderId,(int)$item['order_item_id'],(string)(int)$item['quantity'],(string)$stock->fetchColumn()]);
        $count++;
    }
    return $count;
}

/** @return array<string,mixed> */
function shopCartLockActive(PDO $pdo,int $accountId): array
{
    shopCartGetOrCreate($pdo,$accountId);
    $sql="SELECT * FROM shop_carts WHERE active_account_id=? AND status='active'";
    if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$sql.=' FOR UPDATE';
    $statement=$pdo->prepare($sql);$statement->execute([$accountId]);$cart=$statement->fetch(PDO::FETCH_ASSOC);
    if(!$cart)throw new ShopCheckoutException('Aktivní košík nebyl nalezen.');return $cart;
}

/** @return array<string,mixed>|false */
function shopCheckoutLockVariant(PDO $pdo,int $variantId): array|false
{
    $sql='SELECT v.*,p.offer_type,p.catalog_status AS product_status,pub.status AS publication_status,pub.public_name FROM shop_variants v JOIN shop_products p ON p.id=v.product_id JOIN shop_product_publications pub ON pub.product_id=p.id WHERE v.id=?';
    if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$sql.=' FOR UPDATE';
    $statement=$pdo->prepare($sql);$statement->execute([$variantId]);return $statement->fetch(PDO::FETCH_ASSOC);
}

/** @param array<string,mixed> $variant */
function shopCheckoutVariantIsSaleable(array $variant): bool
{
    return ($variant['offer_type']??null)==='goods'&&($variant['product_status']??null)==='active'&&($variant['publication_status']??null)==='active'&&($variant['catalog_status']??null)==='active'&&($variant['visible']===null||(int)$variant['visible']===1)&&($variant['price_mode']??null)==='fixed'&&$variant['amount_minor']!==null&&(int)$variant['amount_minor']>=0&&($variant['currency']??null)==='CZK';
}
