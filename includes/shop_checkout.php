<?php
declare(strict_types=1);

require_once __DIR__.'/shop_coupon.php';

final class ShopCheckoutException extends RuntimeException
{
}

require_once __DIR__.'/shop_beneficiary.php';
require_once __DIR__.'/club_program.php';
require_once __DIR__.'/club_event_shop.php';
require_once __DIR__.'/public_velodrome_shop.php';
require_once __DIR__.'/shop_member_pricing.php';
require_once __DIR__.'/shop_payment_notification.php';

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

/** @return array<string,mixed> */
function shopCartDetail(PDO $pdo, int $accountId): array
{
    $cart = shopCartGetOrCreate($pdo, $accountId);
    $beneficiarySelect = shopBeneficiaryColumnExists($pdo,'shop_cart_items')
        ? 'ci.beneficiary_sportovec_id'
        : 'NULL AS beneficiary_sportovec_id';
    $statement = $pdo->prepare(
        'SELECT ci.id AS cart_item_id,ci.quantity,'.$beneficiarySelect.',p.id AS product_id,pub.public_name,'
        . 'v.id AS variant_id,v.sku,v.attributes_json,v.amount_minor,v.currency,v.catalog_status '
        . 'FROM shop_cart_items ci JOIN shop_variants v ON v.id=ci.variant_id '
        . 'JOIN shop_products p ON p.id=v.product_id '
        . 'JOIN shop_product_publications pub ON pub.product_id=p.id WHERE ci.cart_id=? ORDER BY ci.id'
    );
    $statement->execute([(int)$cart['id']]);
    $items = $statement->fetchAll(PDO::FETCH_ASSOC);
    $total = 0;$currency = null;
    foreach ($items as &$item) {
        shopMemberPriceApplyToItem($pdo,$accountId,$item);
        $item['line_amount_minor'] = (int)$item['amount_minor'] * (int)$item['quantity'];
        $total += $item['line_amount_minor'];
        $currency ??= (string)$item['currency'];
        $decoded = json_decode((string)$item['attributes_json'], true);
        $item['attributes'] = is_array($decoded) ? $decoded : [];
    }
    unset($item);
    $eventItems = clubEventShopCartItems($pdo, (int)$cart['id']);
    foreach ($eventItems as $item) {
        $total += (int)$item['line_amount_minor'];
        $currency ??= (string)$item['currency'];
        if ($currency !== (string)$item['currency']) throw new ShopCheckoutException('Cart currency mismatch.');
    }
    $velodromeItems = publicVelodromeShopCartItems($pdo, (int)$cart['id']);
    foreach ($velodromeItems as $item) {
        $total += (int)$item['line_amount_minor'];
        $currency ??= (string)$item['currency'];
        if ($currency !== (string)$item['currency']) {
            throw new ShopCheckoutException('Jeden košík nesmí míchat různé měny.');
        }
    }
    $coupon=null;$couponError=null;$couponBreakdown=null;
    if($cart['coupon_id']!==null){try{$couponBreakdown=shopCouponBreakdownFromItems($pdo,$items,$eventItems,$velodromeItems);$coupon=shopCouponQuoteById($pdo,(int)$cart['coupon_id'],$couponBreakdown);}catch(ShopCouponException $exception){$couponError=$exception->getMessage();}}
    $discount=$coupon!==null?(int)$coupon['discount_minor']:0;
    return ['cart'=>$cart,'items'=>$items,'event_items'=>$eventItems,'velodrome_items'=>$velodromeItems,'subtotal_minor'=>$total,'discount_minor'=>$discount,'total_minor'=>$total-$discount,'currency'=>$currency,'coupon'=>$coupon,'coupon_error'=>$couponError,'fingerprint'=>shopCartFingerprint($items,$coupon,$velodromeItems,$eventItems)];
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
    $checkoutLockName=null;
    if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'){
        $checkoutLockName='shop_checkout:'.substr($keyHash,0,48);
        $lock=$pdo->prepare('SELECT GET_LOCK(?,5)');$lock->execute([$checkoutLockName]);
        if((int)$lock->fetchColumn()!==1)throw new ShopCheckoutException('Objednávku právě zpracovává jiný požadavek. Zkuste ji prosím za okamžik znovu.');
    }
    try{
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
        $beneficiarySelect=shopBeneficiaryColumnExists($pdo,'shop_cart_items')
            ? 'ci.beneficiary_sportovec_id'
            : 'NULL AS beneficiary_sportovec_id';
        $sql = 'SELECT ci.id AS cart_item_id,ci.quantity,'.$beneficiarySelect.',p.id AS product_id,p.offer_type,p.catalog_status AS product_status,'
            . 'pub.status AS publication_status,pub.public_name,v.id AS variant_id,v.* FROM shop_cart_items ci '
            . 'JOIN shop_variants v ON v.id=ci.variant_id JOIN shop_products p ON p.id=v.product_id '
            . 'JOIN shop_product_publications pub ON pub.product_id=p.id WHERE ci.cart_id=? ORDER BY v.id';
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql') $sql.=' FOR UPDATE';
        $itemsStatement=$pdo->prepare($sql);$itemsStatement->execute([(int)$cart['id']]);
        $items=$itemsStatement->fetchAll(PDO::FETCH_ASSOC);
        foreach($items as &$item)shopMemberPriceApplyToItem($pdo,$accountId,$item);
        unset($item);
        // Lock order: cart -> catalog variants -> club events -> self profile -> lessons ASC -> reservations.
        $eventItems=clubEventShopLockCheckoutItems($pdo,(int)$cart['id'],$accountId);
        $velodromeItems=publicVelodromeShopLockCheckoutItems($pdo,(int)$cart['id'],$accountId);
        if ($items===[] && $eventItems===[] && $velodromeItems===[]) throw new ShopCheckoutException('Prázdný košík nelze objednat.');
        $total=0;$currency=null;
        foreach($items as $item){
            if($item['beneficiary_sportovec_id']!==null)shopBeneficiaryAssertAccessible($pdo,$accountId,(int)$item['beneficiary_sportovec_id'],true);
            if(!shopCheckoutVariantIsSaleable($item)) throw new ShopCheckoutException('Některá položka už není dostupná. Obnovte košík.');
            $quantity=(int)$item['quantity'];$unit=(int)$item['amount_minor'];
            if($quantity<1||$quantity>99||$unit<0) throw new ShopCheckoutException('Košík obsahuje neplatné množství nebo cenu.');
            $currency??=(string)$item['currency'];if($currency!==$item['currency']) throw new ShopCheckoutException('Objednávka nesmí míchat měny.');
            $line=$unit*$quantity;if($line<0||$total>PHP_INT_MAX-$line) throw new ShopCheckoutException('Celková částka je mimo podporovaný rozsah.');
            $total+=$line;
        }
        foreach($velodromeItems as $item){
            $unit=(int)$item['amount_minor'];
            if($unit<1)throw new ShopCheckoutException('Placený termín obsahuje neplatnou cenu.');
            $currency??=(string)$item['currency'];if($currency!==$item['currency'])throw new ShopCheckoutException('Objednávka nesmí míchat měny.');
            if($total>PHP_INT_MAX-$unit)throw new ShopCheckoutException('Celková částka je mimo podporovaný rozsah.');
            $total+=$unit;
        }
        foreach($eventItems as $item){
            $unit=(int)$item['amount_minor'];
            if($unit<1)throw new ShopCheckoutException('Placená událost obsahuje neplatnou cenu.');
            $currency??=(string)$item['currency'];if($currency!==$item['currency'])throw new ShopCheckoutException('Objednávka nesmí míchat měny.');
            if($total>PHP_INT_MAX-$unit)throw new ShopCheckoutException('Celková částka je mimo podporovaný rozsah.');
            $total+=$unit;
        }
        $subtotal=$total;$coupon=null;$discount=0;
        if($cart['coupon_id']!==null){try{$couponBreakdown=shopCouponBreakdownFromItems($pdo,$items,$eventItems,$velodromeItems);$coupon=shopCouponQuoteById($pdo,(int)$cart['coupon_id'],$couponBreakdown,true);$discount=(int)$coupon['discount_minor'];}catch(ShopCouponException $exception){throw new ShopCheckoutException($exception->getMessage(),0,$exception);}}
        $total=$subtotal-$discount;
        if(!hash_equals($expectedCartFingerprint,shopCartFingerprint($items,$coupon,$velodromeItems,$eventItems)))throw new ShopCheckoutException('Cena, obsah nebo kupón košíku se změnily. Zkontrolujte nový souhrn a odešlete jej znovu.');
        if($currency!=='CZK'||$total<1) throw new ShopCheckoutException('První bankovní checkout podporuje pouze kladnou částku v CZK.');
        $publicCode='KP'.date('ymd').strtoupper(bin2hex(random_bytes(5)));
        $dueAt=(new DateTimeImmutable('now +'.$bank['due_days'].' days'))->setTime(23,59,59)->format('Y-m-d H:i:s');
        $orderValues=[$publicCode,$accountId,(int)$cart['id'],$keyHash,trim((string)$account['jmeno'].' '.(string)$account['prijmeni']),(string)$account['email'],$subtotal,$discount,$total,$currency];
        if(shopOrderExpirationAvailable($pdo)){
            $insert=$pdo->prepare('INSERT INTO shop_orders(public_code,account_id,source_cart_id,idempotency_key_hash,status,payment_status,fulfillment_method,customer_name_snapshot,customer_email_snapshot,subtotal_minor,discount_minor,total_minor,currency,placed_at,payment_expires_at) '
                . "VALUES (?,?,?,?,'placed','pending','personal_pickup',?,?,?,?,?,?,CURRENT_TIMESTAMP,?)");
            $orderValues[]=$dueAt;
        }else{
            $insert=$pdo->prepare('INSERT INTO shop_orders(public_code,account_id,source_cart_id,idempotency_key_hash,status,payment_status,fulfillment_method,customer_name_snapshot,customer_email_snapshot,subtotal_minor,discount_minor,total_minor,currency,placed_at) '
                . "VALUES (?,?,?,?,'placed','pending','personal_pickup',?,?,?,?,?,?,CURRENT_TIMESTAMP)");
        }
        $insert->execute($orderValues);
        $orderId=(int)$pdo->lastInsertId();
        foreach($items as $item){
            $quantity=(int)$item['quantity'];$line=(int)$item['amount_minor']*$quantity;
            $managedStock=$item['stock_quantity_decimal']!==null;
            if($managedStock){
                $reserve=$pdo->prepare('UPDATE shop_variants SET stock_quantity_decimal=stock_quantity_decimal-?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND stock_quantity_decimal>=?');
                $reserve->execute([$quantity,(int)$item['id'],$quantity]);
                if($reserve->rowCount()!==1) throw new ShopCheckoutException('Mezitím se vyprodala položka '.$item['sku'].'. Objednávka nebyla vytvořena.');
            }
            if(shopBeneficiaryColumnExists($pdo,'shop_order_items')){
                $orderItem=$pdo->prepare('INSERT INTO shop_order_items(order_id,product_id,variant_id,beneficiary_sportovec_id,product_name_snapshot,sku_snapshot,attributes_json_snapshot,quantity,unit_amount_minor,line_amount_minor,currency,includes_vat_snapshot,vat_rate_basis_points_snapshot) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
                $orderItem->execute([$orderId,(int)$item['product_id'],(int)$item['id'],$item['beneficiary_sportovec_id']!==null?(int)$item['beneficiary_sportovec_id']:null,(string)$item['public_name'],(string)$item['sku'],(string)$item['attributes_json'],$quantity,(int)$item['amount_minor'],$line,(string)$item['currency'],$item['includes_vat'],$item['vat_rate_basis_points']]);
            }else{
                $orderItem=$pdo->prepare('INSERT INTO shop_order_items(order_id,product_id,variant_id,product_name_snapshot,sku_snapshot,attributes_json_snapshot,quantity,unit_amount_minor,line_amount_minor,currency,includes_vat_snapshot,vat_rate_basis_points_snapshot) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
                $orderItem->execute([$orderId,(int)$item['product_id'],(int)$item['id'],(string)$item['public_name'],(string)$item['sku'],(string)$item['attributes_json'],$quantity,(int)$item['amount_minor'],$line,(string)$item['currency'],$item['includes_vat'],$item['vat_rate_basis_points']]);
            }
            $orderItemId=(int)$pdo->lastInsertId();
            if($managedStock){
                $stock=$pdo->prepare('SELECT stock_quantity_decimal FROM shop_variants WHERE id=?');$stock->execute([(int)$item['id']]);$stockAfter=(string)$stock->fetchColumn();
                $pdo->prepare('INSERT INTO shop_inventory_movements(variant_id,order_id,order_item_id,movement_type,quantity_delta_decimal,stock_after_decimal) VALUES (?,?,?,\'reserve\',?,?)')
                    ->execute([(int)$item['id'],$orderId,$orderItemId,(string)(-$quantity),$stockAfter]);
            }
        }
        $eventOrderItems=clubEventShopCreateOrderItemsInTransaction($pdo,$orderId,$accountId,$eventItems);
        $velodromeOrderItems=publicVelodromeShopCreateOrderItemsInTransaction($pdo,$orderId,$accountId,$velodromeItems);
        if($coupon!==null){
            $usage=$pdo->prepare('UPDATE shop_coupons SET usage_count=usage_count+1,updated_at=CURRENT_TIMESTAMP WHERE id=? AND (usage_limit_total IS NULL OR usage_count<usage_limit_total)');$usage->execute([(int)$coupon['id']]);
            if($usage->rowCount()!==1)throw new ShopCheckoutException('Limit použití kupónu byl mezitím vyčerpán.');
            $pdo->prepare('INSERT INTO shop_coupon_redemptions(coupon_id,order_id,account_id,code_snapshot,discount_type_snapshot,value_snapshot,discount_minor,eligible_subtotal_minor,applicability_mask_snapshot) VALUES (?,?,?,?,?,?,?,?,?)')
                ->execute([(int)$coupon['id'],$orderId,$accountId,(string)$coupon['code'],(string)$coupon['discount_type'],(int)$coupon['value_minor_or_basis_points'],$discount,(int)$coupon['eligible_subtotal_minor'],(int)$coupon['applicability_mask']]);
        }
        $variableSymbol=shopPaymentVariableSymbol($orderId);
        $spd=shopPaymentSpdPayload($bank['iban'],$total,$currency,$variableSymbol,'OBJEDNAVKA '.$publicCode);
        $pdo->prepare('INSERT INTO payments(payable_type,payable_id,method,status,amount_minor,currency,variable_symbol,iban_snapshot,bic_snapshot,account_label_snapshot,spd_payload,due_at) '
            . "VALUES ('shop_order',?,'bank_transfer','pending',?,?,?,?,?,?,?,?)")
            ->execute([$orderId,$total,$currency,$variableSymbol,$bank['iban'],$bank['bic']!==''?$bank['bic']:null,$bank['account_label'],$spd,$dueAt]);
        $placeNote='Objednávka vytvořena serverovým checkoutem.'.($eventOrderItems>0?' Obsahuje '.$eventOrderItems.' placenou klubovou událost.':'').($velodromeOrderItems>0?' Obsahuje '.$velodromeOrderItems.' rezervaci velodromu.':'').($coupon!==null?' Kupón '.$coupon['code'].' poskytl slevu '.$discount.' minor units.':'');
        $pdo->prepare('INSERT INTO shop_order_events(order_id,actor_type,actor_id,action,from_status,to_status,note) VALUES (?,\'account\',?,\'place\',NULL,\'placed\',?)')->execute([$orderId,$accountId,$placeNote]);
        $pdo->prepare("UPDATE shop_carts SET status='converted',active_account_id=NULL,converted_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE id=?")
            ->execute([(int)$cart['id']]);
        $pdo->commit();
        return shopOrderByCode($pdo,$accountId,$publicCode)+['replayed'=>false];
    }catch(Throwable $exception){
        if($pdo->inTransaction())$pdo->rollBack();
        if($exception instanceof InvalidArgumentException||$exception instanceof ShopCheckoutException)throw $exception;
        if($exception instanceof PublicVelodromeShopException||$exception instanceof ClubEventShopException||$exception instanceof ClubEventRegistrationException)throw new ShopCheckoutException($exception->getMessage(),0,$exception);
        throw new ShopCheckoutException('Objednávka se nepodařila vytvořit bez částečného zápisu.',0,$exception);
    }
    }finally{
        if($checkoutLockName!==null){
            try{$release=$pdo->prepare('SELECT RELEASE_LOCK(?)');$release->execute([$checkoutLockName]);}
            catch(Throwable $releaseError){error_log('shop_checkout lock release: '.$releaseError->getMessage());}
        }
    }
}

/** @return array<string,mixed> */
function shopOrderByCode(PDO $pdo,int $accountId,string $publicCode): array
{
    $statement=$pdo->prepare('SELECT o.*,p.id AS payment_id,p.method,p.status AS payment_record_status,p.variable_symbol,p.iban_snapshot,p.bic_snapshot,p.account_label_snapshot,p.spd_payload,p.due_at,p.paid_at,p.refund_sent_at,p.refund_reference,r.code_snapshot AS coupon_code_snapshot,r.discount_minor AS coupon_discount_minor FROM shop_orders o JOIN payments p ON p.payable_type=\'shop_order\' AND p.payable_id=o.id LEFT JOIN shop_coupon_redemptions r ON r.order_id=o.id WHERE o.account_id=? AND o.public_code=?');
    $statement->execute([$accountId,$publicCode]);$order=$statement->fetch(PDO::FETCH_ASSOC);
    if(!$order)throw new ShopCheckoutException('Objednávka nebyla nalezena.');
    $items=$pdo->prepare('SELECT * FROM shop_order_items WHERE order_id=? ORDER BY id');$items->execute([(int)$order['id']]);
    $order['items']=$items->fetchAll(PDO::FETCH_ASSOC);$order['event_items']=clubEventShopOrderRows($pdo,(int)$order['id']);$order['velodrome_items']=publicVelodromeShopOrderRows($pdo,(int)$order['id']);return $order;
}

/** @return list<array<string,mixed>> */
function shopOrderListForAccount(PDO $pdo,int $accountId,int $limit=100):array
{
    if($accountId<1)throw new InvalidArgumentException('Přehled objednávek vyžaduje přihlášený účet.');
    $limit=max(1,min(200,$limit));
    $eventItemCount=clubEventShopAvailable($pdo)?'(SELECT COUNT(*) FROM club_event_order_items ei WHERE ei.order_id=o.id)':'0';
    $velodromeItemCount=publicVelodromeShopAvailable($pdo)?'(SELECT COUNT(*) FROM public_velodrome_order_items vi WHERE vi.order_id=o.id)':'0';
    $statement=$pdo->prepare('SELECT o.id,o.public_code,o.status,o.payment_status,o.total_minor,o.currency,o.placed_at,o.created_at,o.cancelled_at,o.ready_at,o.completed_at,'
        .'p.status AS payment_record_status,p.variable_symbol,p.due_at,p.paid_at,p.refund_sent_at,p.refund_reference,r.code_snapshot AS coupon_code_snapshot,'
        .'((SELECT COUNT(*) FROM shop_order_items oi WHERE oi.order_id=o.id)+'.$eventItemCount.'+'.$velodromeItemCount.') AS item_count '
        .'FROM shop_orders o JOIN payments p ON p.payable_type=\'shop_order\' AND p.payable_id=o.id LEFT JOIN shop_coupon_redemptions r ON r.order_id=o.id '
        .'WHERE o.account_id=? ORDER BY o.created_at DESC,o.id DESC LIMIT '.$limit);
    $statement->execute([$accountId]);return $statement->fetchAll(PDO::FETCH_ASSOC);
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

/** @param list<array<string,mixed>> $items @param array<string,mixed>|null $coupon */
function shopCartFingerprint(array $items,?array $coupon=null,array $velodromeItems=[],array $eventItems=[]):string
{
    $contract=[];
    foreach($items as$item)$contract[]=[
        'variant_id'=>(int)$item['variant_id'],
        'quantity'=>(int)$item['quantity'],
        'amount_minor'=>(int)$item['amount_minor'],
        'currency'=>(string)$item['currency'],
        'beneficiary_sportovec_id'=>$item['beneficiary_sportovec_id']===null?null:(int)$item['beneficiary_sportovec_id'],
    ];
    usort($contract,static fn(array $a,array $b):int=>$a['variant_id']<=>$b['variant_id']);
    $couponContract=$coupon===null?null:['id'=>(int)$coupon['id'],'code'=>(string)$coupon['code'],'discount_minor'=>(int)$coupon['discount_minor'],'eligible_subtotal_minor'=>(int)($coupon['eligible_subtotal_minor']??0),'applicability_mask'=>(int)($coupon['applicability_mask']??SHOP_COUPON_GOODS)];
    $velodromeContract=publicVelodromeShopFingerprintItems($velodromeItems);
    usort($velodromeContract,static fn(array $a,array $b):int=>[$a['lesson_id'],$a['beneficiary_sportovec_id']]<=>[$b['lesson_id'],$b['beneficiary_sportovec_id']]);
    $eventContract=clubEventShopFingerprintItems($eventItems);
    usort($eventContract,static fn(array $a,array $b):int=>[$a['event_id'],$a['beneficiary_sportovec_id']]<=>[$b['event_id'],$b['beneficiary_sportovec_id']]);
    return hash('sha256',(string)json_encode(['items'=>$contract,'event_items'=>$eventContract,'velodrome_items'=>$velodromeContract,'coupon'=>$couponContract],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
}

/** @return list<array<string,mixed>> */
function shopOrderAdminList(PDO $pdo,int $limit=200,string $query=''):array
{
    $limit=max(1,min(500,$limit));
    $query=trim($query);
    $sql='SELECT o.*,p.id AS payment_id,p.status AS payment_record_status,p.variable_symbol,p.due_at,p.paid_at,p.refund_sent_at,p.refund_reference,p.refund_confirmed_by_trainer_id,p.refund_confirmation_note,r.code_snapshot AS coupon_code_snapshot,'
        .'e.action AS last_event_action,e.actor_id AS last_event_actor_id,e.note AS last_event_note,e.created_at AS last_event_at '
        .'FROM shop_orders o JOIN payments p ON p.payable_type=\'shop_order\' AND p.payable_id=o.id LEFT JOIN shop_coupon_redemptions r ON r.order_id=o.id '
        .'LEFT JOIN shop_order_events e ON e.id=(SELECT MAX(e2.id) FROM shop_order_events e2 WHERE e2.order_id=o.id) ';
    $parameters=[];
    if($query!==''){
        $sql.='WHERE o.public_code LIKE ? OR o.customer_name_snapshot LIKE ? OR o.customer_email_snapshot LIKE ? OR p.variable_symbol LIKE ? ';
        $needle='%'.$query.'%';$parameters=[$needle,$needle,$needle,$needle];
    }
    $sql.='ORDER BY CASE p.status WHEN \'pending\' THEN 0 WHEN \'refund_required\' THEN 1 ELSE 2 END,o.created_at DESC,o.id DESC LIMIT '.$limit;
    $statement=$pdo->prepare($sql);$statement->execute($parameters);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function shopOrderExpirationAvailable(PDO $pdo):bool
{
    if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'){
        $statement=$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='shop_orders' AND COLUMN_NAME IN ('payment_expires_at','expired_at')");
        return (int)$statement->fetchColumn()===2;
    }
    foreach($pdo->query('PRAGMA table_info(shop_orders)')->fetchAll(PDO::FETCH_ASSOC)as$column){$names[]=(string)$column['name'];}
    return isset($names)&&in_array('payment_expires_at',$names,true)&&in_array('expired_at',$names,true);
}

/** @return list<array<string,mixed>> */
function shopOrderExpirationPreview(PDO $pdo,DateTimeImmutable $now,int $limit=200):array
{
    if(!shopOrderExpirationAvailable($pdo))throw new ShopCheckoutException('Expirace objednávek zatím není migrována.');
    $limit=max(1,min(500,$limit));
    $velodromeCount=publicVelodromeShopAvailable($pdo)
        ? '(SELECT COUNT(*) FROM public_velodrome_order_items vi WHERE vi.order_id=o.id)'
        : '0';
    $statement=$pdo->prepare(
        "SELECT o.id,o.public_code,o.customer_name_snapshot,o.customer_email_snapshot,o.total_minor,o.currency,"
        . "o.payment_expires_at,p.due_at,{$velodromeCount} AS velodrome_items "
        . "FROM shop_orders o JOIN payments p ON p.payable_type='shop_order' AND p.payable_id=o.id "
        . "WHERE o.status='placed' AND o.payment_status='pending' AND p.status='pending' "
        . 'AND COALESCE(o.payment_expires_at,p.due_at) IS NOT NULL '
        . 'AND COALESCE(o.payment_expires_at,p.due_at)<=? ORDER BY COALESCE(o.payment_expires_at,p.due_at),o.id LIMIT '.$limit
    );
    $statement->execute([$now->format('Y-m-d H:i:s')]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array{examined:int,expired:int,unchanged:int,failed:int,results:list<array<string,mixed>>} */
function shopOrderExpireBatch(PDO $pdo,DateTimeImmutable $now,bool $apply=false,int $limit=200):array
{
    $candidates=shopOrderExpirationPreview($pdo,$now,$limit);
    $summary=['examined'=>count($candidates),'expired'=>0,'unchanged'=>0,'failed'=>0,'results'=>[]];
    foreach($candidates as$candidate){
        if(!$apply){$summary['results'][]=['order_id'=>(int)$candidate['id'],'public_code'=>(string)$candidate['public_code'],'status'=>'dry-run'];continue;}
        try{
            $result=shopOrderExpirePending($pdo,(int)$candidate['id'],$now,true);
            $key=$result['changed']?'expired':'unchanged';$summary[$key]++;
            $summary['results'][]=['order_id'=>(int)$candidate['id'],'public_code'=>(string)$candidate['public_code'],'status'=>$key];
        }catch(ShopCheckoutException $exception){
            $summary['failed']++;$summary['results'][]=['order_id'=>(int)$candidate['id'],'public_code'=>(string)$candidate['public_code'],'status'=>'rejected','error'=>$exception->getMessage()];
        }
    }
    return $summary;
}

/** @return array<string,mixed> */
function shopOrderExpirePending(PDO $pdo,int $orderId,DateTimeImmutable $now,bool $confirmed):array
{
    if($orderId<1||!$confirmed)throw new InvalidArgumentException('Expirace vyžaduje objednávku a výslovné potvrzení.');
    $pdo->beginTransaction();
    try{
        $paymentSql="SELECT * FROM payments WHERE payable_type='shop_order' AND payable_id=?";
        if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$paymentSql.=' FOR UPDATE';
        $paymentStatement=$pdo->prepare($paymentSql);$paymentStatement->execute([$orderId]);$payment=$paymentStatement->fetch(PDO::FETCH_ASSOC);
        $order=shopOrderAdminLockOrder($pdo,$orderId);
        if(!$payment||!$order)throw new ShopCheckoutException('Objednávka nebo její platba nebyla nalezena.');
        if($order['status']==='cancelled'&&$order['payment_status']==='cancelled'&&$payment['status']==='cancelled'){
            $pdo->commit();return ['order_id'=>$orderId,'payment_status'=>'cancelled','changed'=>false,'restocked_items'=>0,'velodrome_items'=>0,'velodrome_cancelled'=>0];
        }
        if($order['status']!=='placed'||$order['payment_status']!=='pending'||$payment['status']!=='pending'){
            throw new ShopCheckoutException('Expirovat lze pouze nezaplacenou objednávku čekající na platbu.');
        }
        $deadline=(string)($order['payment_expires_at']??$payment['due_at']??'');
        if($deadline===''||new DateTimeImmutable($deadline)>$now)throw new ShopCheckoutException('Lhůta pro zaplacení objednávky ještě neuplynula.');
        $reason='Automatická expirace nezaplacené objednávky po lhůtě '.$deadline.'.';
        $result=shopOrderCancelLocked($pdo,$payment,$order,'system',null,$reason,'expire',$now);
        $pdo->commit();return $result;
    }catch(Throwable $exception){
        if($pdo->inTransaction())$pdo->rollBack();
        if($exception instanceof InvalidArgumentException||$exception instanceof ShopCheckoutException)throw $exception;
        throw new ShopCheckoutException('Expirace selhala bez částečného zápisu.',0,$exception);
    }
}

/** @return array{order_id:int,payment_status:string,changed:bool} */
function shopOrderAdminConfirmBankPayment(PDO $pdo,int $paymentId,int $actorTrainerId,string $reason,bool $confirmed):array
{
    $reason=trim($reason);
    if($paymentId<1||$actorTrainerId<1||$reason===''||!$confirmed)throw new InvalidArgumentException('Potvrzení platby vyžaduje platbu, administrátora, důvod a výslovné potvrzení.');
    if(mb_strlen($reason,'UTF-8')>1000)throw new InvalidArgumentException('Důvod smí mít nejvýše 1000 znaků.');
    return shopOrderConfirmPayment($pdo,$paymentId,'bank_transfer','trainer',$actorTrainerId,$reason);
}

/** @return array<string,mixed> */
function shopOrderConfirmPayment(PDO $pdo,int $paymentId,string $source,string $actorType,?int $actorId,string $reason):array
{
    shopOrderValidatePaymentActor($paymentId,$source,$actorType,$actorId,$reason);
    $pdo->beginTransaction();
    try{
        $result=shopOrderConfirmPaymentInTransaction($pdo,$paymentId,$source,$actorType,$actorId,$reason);
        $pdo->commit();return $result;
    }catch(Throwable $exception){
        if($pdo->inTransaction())$pdo->rollBack();
        if($exception instanceof InvalidArgumentException||$exception instanceof ShopCheckoutException)throw $exception;
        throw new ShopCheckoutException('Potvrzení platby selhalo bez částečného zápisu.',0,$exception);
    }
}

/** @return array<string,mixed> */
function shopOrderConfirmPaymentInTransaction(PDO $pdo,int $paymentId,string $source,string $actorType,?int $actorId,string $reason):array
{
    shopOrderValidatePaymentActor($paymentId,$source,$actorType,$actorId,$reason);
    if(!$pdo->inTransaction())throw new LogicException('Kanonický přechod platby vyžaduje aktivní transakci.');
    $sql='SELECT * FROM payments WHERE id=?';if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$sql.=' FOR UPDATE';
    $statement=$pdo->prepare($sql);$statement->execute([$paymentId]);$payment=$statement->fetch(PDO::FETCH_ASSOC);
    if(!$payment||$payment['payable_type']!=='shop_order')throw new ShopCheckoutException('Platba objednávky nebyla nalezena.');
    if($source==='bank_transfer'&&$payment['method']!=='bank_transfer')throw new ShopCheckoutException('Bankovní platba objednávky nebyla nalezena.');
    if($source==='stripe'&&empty($payment['stripe_checkout_session_id']))throw new ShopCheckoutException('Stripe relace platby nebyla nalezena.');
    $orderId=(int)$payment['payable_id'];$order=shopOrderAdminLockOrder($pdo,$orderId);
    if(!$order)throw new ShopCheckoutException('Objednávka platby nebyla nalezena.');
    if($payment['status']==='paid'){
        if($order['payment_status']!=='paid'||!in_array($order['status'],['processing','ready','completed'],true)||(string)($payment['payment_source']??$payment['method'])!==$source)throw new ShopCheckoutException('Stav potvrzené platby a objednávky není konzistentní.');
        $programSync=['program_items'=>0,'created'=>0];
        if(clubProgramLifecycleAvailable($pdo))try{$programSync=clubProgramActivatePaidOrderInTransaction($pdo,$orderId,$actorId,$actorType);}catch(ClubProgramException $exception){throw new ShopCheckoutException($exception->getMessage(),0,$exception);}
        try{$eventSync=clubEventShopActivatePaidOrderInTransaction($pdo,$orderId,$actorId,$actorType);}catch(ClubEventShopException $exception){throw new ShopCheckoutException($exception->getMessage(),0,$exception);}
        try{$velodromeSync=publicVelodromeShopActivatePaidOrderInTransaction($pdo,$orderId,$actorId,$actorType);}catch(PublicVelodromeShopException $exception){throw new ShopCheckoutException($exception->getMessage(),0,$exception);}
        shopPaymentNotificationEnqueue($pdo,$orderId);
        return ['order_id'=>$orderId,'payment_status'=>'paid','changed'=>false]+$programSync+['velodrome_items'=>$velodromeSync['items'],'velodrome_activated'=>$velodromeSync['activated']];
    }
    if($payment['status']!=='pending'||$order['payment_status']!=='pending'||$order['status']!=='placed')throw new ShopCheckoutException('Platbu nebo objednávku v tomto stavu nelze potvrdit.');
    $trainerId=$actorType==='trainer'?$actorId:null;
    $pdo->prepare("UPDATE payments SET method=?,payment_source=?,status='paid',paid_at=CURRENT_TIMESTAMP,confirmed_by_trainer_id=?,confirmation_note=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")
        ->execute([$source,$source,$trainerId,$reason,$paymentId]);
    $pdo->prepare("UPDATE shop_orders SET payment_status='paid',status='processing',updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$orderId]);
    $action=$source==='stripe'?'confirm_stripe_payment':'confirm_bank_payment';
    $pdo->prepare('INSERT INTO shop_order_events(order_id,actor_type,actor_id,action,from_status,to_status,note) VALUES (?,?,?,?,\'placed\',\'processing\',?)')
        ->execute([$orderId,$actorType,$actorId,$action,$reason]);
    $programSync=['program_items'=>0,'created'=>0];
    if(clubProgramLifecycleAvailable($pdo))try{$programSync=clubProgramActivatePaidOrderInTransaction($pdo,$orderId,$actorId,$actorType);}catch(ClubProgramException $exception){throw new ShopCheckoutException($exception->getMessage(),0,$exception);}
    try{$eventSync=clubEventShopActivatePaidOrderInTransaction($pdo,$orderId,$actorId,$actorType);}catch(ClubEventShopException $exception){throw new ShopCheckoutException($exception->getMessage(),0,$exception);}
    try{$velodromeSync=publicVelodromeShopActivatePaidOrderInTransaction($pdo,$orderId,$actorId,$actorType);}catch(PublicVelodromeShopException $exception){throw new ShopCheckoutException($exception->getMessage(),0,$exception);}
    shopPaymentNotificationEnqueue($pdo,$orderId);
    return ['order_id'=>$orderId,'payment_status'=>'paid','changed'=>true]+$programSync+['velodrome_items'=>$velodromeSync['items'],'velodrome_activated'=>$velodromeSync['activated']];
}

function shopOrderValidatePaymentActor(int $paymentId,string $source,string $actorType,?int $actorId,string $reason):void
{
    $reason=trim($reason);
    if($paymentId<1||!in_array($source,['bank_transfer','stripe'],true)||!in_array($actorType,['trainer','system'],true)||($actorType==='trainer'&&($actorId??0)<1)||($actorType==='system'&&$actorId!==null)||$reason==='')throw new InvalidArgumentException('Potvrzení platby nemá platný zdroj, auditora nebo důvod.');
    if(mb_strlen($reason,'UTF-8')>1000)throw new InvalidArgumentException('Důvod smí mít nejvýše 1000 znaků.');
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
            if(!in_array($payment['status'],['cancelled','refund_required','refunded'],true)||$order['payment_status']!==$payment['status'])throw new ShopCheckoutException('Stornovaná objednávka má nekonzistentní stav platby.');
            try{$eventSync=clubEventShopCancelOrderInTransaction($pdo,$orderId,$actorTrainerId,$reason);}catch(ClubEventShopException $exception){throw new ShopCheckoutException($exception->getMessage(),0,$exception);}
            try{$velodromeSync=publicVelodromeShopCancelOrderInTransaction($pdo,$orderId,$actorTrainerId,$reason);}catch(PublicVelodromeShopException $exception){throw new ShopCheckoutException($exception->getMessage(),0,$exception);}
            $pdo->commit();return ['order_id'=>$orderId,'payment_status'=>(string)$payment['status'],'restocked_items'=>0,'changed'=>false,'velodrome_items'=>$velodromeSync['items'],'velodrome_cancelled'=>$velodromeSync['cancelled']];
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
        $programSync=['cancelled'=>0,'rosters_ended'=>0];
        if(clubProgramLifecycleAvailable($pdo))try{$programSync=clubProgramCancelOrderInTransaction($pdo,$orderId,$actorTrainerId,$reason);}catch(ClubProgramException $exception){throw new ShopCheckoutException($exception->getMessage(),0,$exception);}
        try{$eventSync=clubEventShopCancelOrderInTransaction($pdo,$orderId,$actorTrainerId,$reason);}catch(ClubEventShopException $exception){throw new ShopCheckoutException($exception->getMessage(),0,$exception);}
        try{$velodromeSync=publicVelodromeShopCancelOrderInTransaction($pdo,$orderId,$actorTrainerId,$reason);}catch(PublicVelodromeShopException $exception){throw new ShopCheckoutException($exception->getMessage(),0,$exception);}
        $pdo->commit();return ['order_id'=>$orderId,'payment_status'=>$paymentStatus,'restocked_items'=>$restocked,'changed'=>true]+$programSync+['velodrome_items'=>$velodromeSync['items'],'velodrome_cancelled'=>$velodromeSync['cancelled']];
    }catch(Throwable $exception){
        if($pdo->inTransaction())$pdo->rollBack();
        if($exception instanceof InvalidArgumentException||$exception instanceof ShopCheckoutException)throw $exception;
        throw new ShopCheckoutException('Storno selhalo bez částečného zápisu.',0,$exception);
    }
}

/**
 * Expiration-only cancellation core. Caller must hold payment then order locks and own the transaction.
 * Lock order continues with inventory order items/variants, program rows, then velodrome lessons/reservations.
 * @param array<string,mixed> $payment
 * @param array<string,mixed> $order
 * @return array<string,mixed>
 */
function shopOrderCancelLocked(PDO $pdo,array $payment,array $order,string $actorType,?int $actorId,string $reason,string $action,DateTimeImmutable $now):array
{
    if($actorType!=='system'||$action!=='expire')throw new LogicException('Toto jadro je vyhrazeno pro auditovanou expiraci.');
    $orderId=(int)$order['id'];
    if($order['status']!=='placed'||$order['payment_status']!=='pending'||$payment['status']!=='pending'){
        throw new ShopCheckoutException('Expirovat lze pouze nezaplacenou objednávku čekající na platbu.');
    }
    $programSync=['cancelled'=>0,'rosters_ended'=>0];
    if(clubProgramLifecycleAvailable($pdo))try{clubProgramAssertOrderHasNoActiveEnrollments($pdo,$orderId);}catch(ClubProgramException $exception){throw new ShopCheckoutException($exception->getMessage(),0,$exception);}
    $timestamp=$now->format('Y-m-d H:i:s');
    $restocked=shopOrderAdminRestock($pdo,$orderId);
    $pdo->prepare("UPDATE payments SET status='cancelled',updated_at=? WHERE id=?")
        ->execute([$timestamp,(int)$payment['id']]);
    $pdo->prepare("UPDATE shop_orders SET status='cancelled',payment_status='cancelled',cancelled_at=?,expired_at=?,updated_at=? WHERE id=?")
        ->execute([$timestamp,$timestamp,$timestamp,$orderId]);
    $pdo->prepare("INSERT INTO shop_order_events(order_id,actor_type,actor_id,action,from_status,to_status,note,created_at) VALUES (?,? ,?,'expire','placed','cancelled',?,?)")
        ->execute([$orderId,$actorType,$actorId,$reason,$timestamp]);
    // Pending orders never activate program enrollment; the pre-mutation assertion above enforces it.
    try{$eventSync=clubEventShopCancelOrderInTransaction($pdo,$orderId,$actorId,$reason,$actorType,$now);}catch(ClubEventShopException $exception){throw new ShopCheckoutException($exception->getMessage(),0,$exception);}
    try{$velodromeSync=publicVelodromeShopCancelOrderInTransaction($pdo,$orderId,$actorId,$reason,$actorType,$now);}catch(PublicVelodromeShopException $exception){throw new ShopCheckoutException($exception->getMessage(),0,$exception);}
    return ['order_id'=>$orderId,'payment_status'=>'cancelled','restocked_items'=>$restocked,'changed'=>true]+$programSync+['velodrome_items'=>$velodromeSync['items'],'velodrome_cancelled'=>$velodromeSync['cancelled']];
}

/** @return array{order_id:int,payment_status:string,changed:bool} */
function shopOrderAdminConfirmRefund(PDO $pdo,int $orderId,int $actorTrainerId,string $reference,string $reason,bool $confirmed):array
{
    $reason=shopOrderAdminValidateAction($orderId,$actorTrainerId,$reason,$confirmed,'Potvrzení vratky');
    $reference=trim($reference);
    $referenceControlCheck=preg_match('/[\x00-\x1F\x7F]/u',$reference);
    if($reference===''||mb_strlen($reference,'UTF-8')>255||$referenceControlCheck!==0)throw new InvalidArgumentException('Vratka vyžaduje platnou bankovní referenci bez řídicích znaků, nejvýše 255 znaků.');
    $pdo->beginTransaction();
    try{
        $paymentSql="SELECT * FROM payments WHERE payable_type='shop_order' AND payable_id=?";
        if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$paymentSql.=' FOR UPDATE';
        $paymentStatement=$pdo->prepare($paymentSql);$paymentStatement->execute([$orderId]);$payment=$paymentStatement->fetch(PDO::FETCH_ASSOC);
        $order=shopOrderAdminLockOrder($pdo,$orderId);
        if(!$payment||!$order||$payment['method']!=='bank_transfer')throw new ShopCheckoutException('Objednávka nebo její bankovní platba nebyla nalezena.');
        if(clubProgramLifecycleAvailable($pdo))try{clubProgramAssertOrderHasNoActiveEnrollments($pdo,$orderId);}catch(ClubProgramException $exception){throw new ShopCheckoutException($exception->getMessage(),0,$exception);}
        try{clubEventShopAssertRefundableInTransaction($pdo,$orderId);}catch(ClubEventShopException $exception){throw new ShopCheckoutException($exception->getMessage(),0,$exception);}
        try{publicVelodromeShopAssertRefundableInTransaction($pdo,$orderId);}catch(PublicVelodromeShopException $exception){throw new ShopCheckoutException($exception->getMessage(),0,$exception);}
        if($payment['status']==='refunded'){
            if($order['status']!=='cancelled'||$order['payment_status']!=='refunded'||$payment['refund_sent_at']===null||$payment['refund_reference']===null)throw new ShopCheckoutException('Dokončená vratka má nekonzistentní stav.');
            $pdo->commit();return ['order_id'=>$orderId,'payment_status'=>'refunded','changed'=>false];
        }
        if($payment['status']!=='refund_required'||$order['status']!=='cancelled'||$order['payment_status']!=='refund_required')throw new ShopCheckoutException('Vratku lze potvrdit pouze u stornované zaplacené objednávky čekající na vrácení peněz.');
        $pdo->prepare("UPDATE payments SET status='refunded',refund_sent_at=CURRENT_TIMESTAMP,refund_reference=?,refund_confirmed_by_trainer_id=?,refund_confirmation_note=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")
            ->execute([$reference,$actorTrainerId,$reason,(int)$payment['id']]);
        $pdo->prepare("UPDATE shop_orders SET payment_status='refunded',updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$orderId]);
        $note='Bankovní reference: '.$reference.'. '.$reason;
        $pdo->prepare('INSERT INTO shop_order_events(order_id,actor_type,actor_id,action,from_status,to_status,note) VALUES (?,\'trainer\',?,\'confirm_refund\',\'cancelled\',\'cancelled\',?)')
            ->execute([$orderId,$actorTrainerId,$note]);
        $pdo->commit();return ['order_id'=>$orderId,'payment_status'=>'refunded','changed'=>true];
    }catch(Throwable $exception){
        if($pdo->inTransaction())$pdo->rollBack();
        if($exception instanceof InvalidArgumentException||$exception instanceof ShopCheckoutException)throw $exception;
        throw new ShopCheckoutException('Potvrzení vratky selhalo bez částečného zápisu.',0,$exception);
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
