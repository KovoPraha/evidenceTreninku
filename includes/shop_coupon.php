<?php
declare(strict_types=1);

final class ShopCouponException extends RuntimeException{}

function shopCouponNormalizeCode(string $code):string
{
    $code=strtoupper(trim($code));
    if(preg_match('/^[A-Z0-9][A-Z0-9_-]{3,31}$/D',$code)!==1)throw new InvalidArgumentException('Kód musí mít 4–32 znaků A–Z, 0–9, pomlčku nebo podtržítko.');
    return $code;
}

/** @return array<string,mixed> */
function shopCouponAdminCreate(PDO $pdo,int $actorTrainerId,string $code,string $type,int $value,int $minimumOrderMinor,?int $maximumDiscountMinor,?int $usageLimit,string $validFrom,string $validUntil,string $note,bool $confirmed):array
{
    $code=shopCouponNormalizeCode($code);$note=trim($note);$validFrom=trim($validFrom);$validUntil=trim($validUntil);
    if($actorTrainerId<1||!$confirmed||$note===''||mb_strlen($note,'UTF-8')>1000)throw new InvalidArgumentException('Vytvoření kupónu vyžaduje administrátora, poznámku a výslovné potvrzení.');
    if(!in_array($type,['fixed','percentage'],true))throw new InvalidArgumentException('Nepodporovaný typ slevy.');
    if($minimumOrderMinor<0||$minimumOrderMinor>1000000000)throw new InvalidArgumentException('Minimální objednávka je mimo podporovaný rozsah.');
    if($usageLimit!==null&&($usageLimit<1||$usageLimit>1000000))throw new InvalidArgumentException('Limit použití musí být 1 až 1 000 000 nebo prázdný.');
    if($type==='fixed'){
        if($value<1||$value>1000000000||$maximumDiscountMinor!==null)throw new InvalidArgumentException('Pevná sleva nebo její limit nejsou platné.');
    }elseif($value<1||$value>9900||($maximumDiscountMinor!==null&&($maximumDiscountMinor<1||$maximumDiscountMinor>1000000000))){
        throw new InvalidArgumentException('Procentní sleva musí být 0,01–99 % a maximální sleva musí být kladná.');
    }
    $from=shopCouponAdminDate($validFrom);$until=shopCouponAdminDate($validUntil);
    if($from!==null&&$until!==null&&$from>=$until)throw new InvalidArgumentException('Konec platnosti musí být později než začátek.');
    $pdo->beginTransaction();
    try{
        $existing=$pdo->prepare('SELECT id FROM shop_coupons WHERE code=?');$existing->execute([$code]);
        if($existing->fetchColumn()!==false)throw new ShopCouponException('Kupón s tímto kódem už existuje; jeho ekonomická pravidla jsou neměnná.');
        $insert=$pdo->prepare('INSERT INTO shop_coupons(code,discount_type,value_minor_or_basis_points,currency,minimum_order_minor,maximum_discount_minor,usage_limit_total,valid_from,valid_until,active,created_by_trainer_id) VALUES (?,?,?,\'CZK\',?,?,?,?,?,1,?)');
        $insert->execute([$code,$type,$value,$minimumOrderMinor,$maximumDiscountMinor,$usageLimit,$from,$until,$actorTrainerId]);$couponId=(int)$pdo->lastInsertId();
        $coupon=shopCouponAdminFind($pdo,$couponId,false);$after=shopCouponAuditSnapshot($coupon);
        $pdo->prepare('INSERT INTO shop_coupon_events(coupon_id,actor_trainer_id,action,before_json,after_json,note) VALUES (?,?,\'create\',NULL,?,?)')
            ->execute([$couponId,$actorTrainerId,json_encode($after,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$note]);
        $pdo->commit();return $coupon;
    }catch(Throwable $exception){
        if($pdo->inTransaction())$pdo->rollBack();
        if($exception instanceof InvalidArgumentException||$exception instanceof ShopCouponException)throw $exception;
        throw new ShopCouponException('Kupón se nepodařilo vytvořit bez částečného zápisu.',0,$exception);
    }
}

/** @return array{id:int,active:bool,changed:bool} */
function shopCouponAdminSetActive(PDO $pdo,int $couponId,int $actorTrainerId,bool $active,string $note,bool $confirmed):array
{
    $note=trim($note);if($couponId<1||$actorTrainerId<1||!$confirmed||$note===''||mb_strlen($note,'UTF-8')>1000)throw new InvalidArgumentException('Změna kupónu vyžaduje kupón, administrátora, poznámku a potvrzení.');
    $pdo->beginTransaction();
    try{
        $coupon=shopCouponAdminFind($pdo,$couponId,true);$before=shopCouponAuditSnapshot($coupon);
        if((bool)$coupon['active']===$active){$pdo->commit();return ['id'=>$couponId,'active'=>$active,'changed'=>false];}
        $pdo->prepare('UPDATE shop_coupons SET active=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$active?1:0,$couponId]);
        $coupon['active']=$active?1:0;$after=shopCouponAuditSnapshot($coupon);
        $pdo->prepare('INSERT INTO shop_coupon_events(coupon_id,actor_trainer_id,action,before_json,after_json,note) VALUES (?,?,?,?,?,?)')
            ->execute([$couponId,$actorTrainerId,$active?'activate':'deactivate',json_encode($before,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),json_encode($after,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$note]);
        $pdo->commit();return ['id'=>$couponId,'active'=>$active,'changed'=>true];
    }catch(Throwable $exception){
        if($pdo->inTransaction())$pdo->rollBack();
        if($exception instanceof InvalidArgumentException||$exception instanceof ShopCouponException)throw $exception;
        throw new ShopCouponException('Stav kupónu se nepodařilo změnit bez částečného zápisu.',0,$exception);
    }
}

/** @return list<array<string,mixed>> */
function shopCouponAdminList(PDO $pdo):array
{
    return $pdo->query('SELECT c.*,e.action AS last_action,e.actor_trainer_id AS last_actor_id,e.note AS last_note,e.created_at AS last_event_at FROM shop_coupons c LEFT JOIN shop_coupon_events e ON e.id=(SELECT MAX(e2.id) FROM shop_coupon_events e2 WHERE e2.coupon_id=c.id) ORDER BY c.active DESC,c.created_at DESC,c.id DESC')->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array<string,mixed> */
function shopCouponApplyToCart(PDO $pdo,int $accountId,string $code):array
{
    $code=shopCouponNormalizeCode($code);$pdo->beginTransaction();
    try{
        $cart=shopCartLockActive($pdo,$accountId);$subtotal=shopCouponCartSubtotal($pdo,(int)$cart['id']);
        $sql='SELECT * FROM shop_coupons WHERE code=?';if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$sql.=' FOR UPDATE';
        $statement=$pdo->prepare($sql);$statement->execute([$code]);$coupon=$statement->fetch(PDO::FETCH_ASSOC);
        if(!$coupon)throw new ShopCouponException('Kupón nebyl nalezen.');$quote=shopCouponValidateRow($coupon,$subtotal);
        $pdo->prepare('UPDATE shop_carts SET coupon_id=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([(int)$coupon['id'],(int)$cart['id']]);
        $pdo->commit();return $quote;
    }catch(Throwable $exception){
        if($pdo->inTransaction())$pdo->rollBack();
        if($exception instanceof InvalidArgumentException||$exception instanceof ShopCouponException)throw $exception;
        throw new ShopCouponException('Kupón se nepodařilo použít bez částečného zápisu.',0,$exception);
    }
}

function shopCouponRemoveFromCart(PDO $pdo,int $accountId):void
{
    $pdo->beginTransaction();try{$cart=shopCartLockActive($pdo,$accountId);$pdo->prepare('UPDATE shop_carts SET coupon_id=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([(int)$cart['id']]);$pdo->commit();}catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();throw $exception;}
}

/** @return array<string,mixed>|null */
function shopCouponQuoteById(PDO $pdo,?int $couponId,int $subtotal,bool $lock=false):?array
{
    if($couponId===null||$couponId<1)return null;$sql='SELECT * FROM shop_coupons WHERE id=?';if($lock&&(string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$sql.=' FOR UPDATE';
    $statement=$pdo->prepare($sql);$statement->execute([$couponId]);$coupon=$statement->fetch(PDO::FETCH_ASSOC);if(!$coupon)throw new ShopCouponException('Použitý kupón už neexistuje.');return shopCouponValidateRow($coupon,$subtotal);
}

/** @return array<string,mixed> */
function shopCouponValidateRow(array $coupon,int $subtotal):array
{
    $now=new DateTimeImmutable();
    if((int)$coupon['active']!==1)throw new ShopCouponException('Kupón není aktivní.');
    if($coupon['currency']!=='CZK')throw new ShopCouponException('Kupón nepodporuje měnu košíku.');
    if($coupon['valid_from']!==null&&$now<new DateTimeImmutable((string)$coupon['valid_from']))throw new ShopCouponException('Platnost kupónu ještě nezačala.');
    if($coupon['valid_until']!==null&&$now>new DateTimeImmutable((string)$coupon['valid_until']))throw new ShopCouponException('Platnost kupónu skončila.');
    if($subtotal<(int)$coupon['minimum_order_minor'])throw new ShopCouponException('Košík nedosahuje minimální částky pro tento kupón.');
    if($coupon['usage_limit_total']!==null&&(int)$coupon['usage_count']>=(int)$coupon['usage_limit_total'])throw new ShopCouponException('Limit použití kupónu byl vyčerpán.');
    $value=(int)$coupon['value_minor_or_basis_points'];
    if($coupon['discount_type']==='fixed')$discount=$value;
    elseif($coupon['discount_type']==='percentage'){
        if($value<1||$value>9900||$subtotal>intdiv(PHP_INT_MAX,$value))throw new ShopCouponException('Procentní sleva je mimo podporovaný rozsah.');
        $discount=intdiv($subtotal*$value,10000);if($coupon['maximum_discount_minor']!==null)$discount=min($discount,(int)$coupon['maximum_discount_minor']);
    }else throw new ShopCouponException('Kupón má nepodporovaný typ slevy.');
    if($discount<1)throw new ShopCouponException('Sleva kupónu je pro tento košík nulová.');
    if($discount>=$subtotal)throw new ShopCouponException('První bankovní checkout nepodporuje objednávku plně uhrazenou kupónem.');
    $coupon['discount_minor']=$discount;return $coupon;
}

function shopCouponCartSubtotal(PDO $pdo,int $cartId):int
{
    $statement=$pdo->prepare('SELECT ci.quantity,v.amount_minor,v.currency FROM shop_cart_items ci JOIN shop_variants v ON v.id=ci.variant_id WHERE ci.cart_id=? ORDER BY ci.id');$statement->execute([$cartId]);$total=0;
    foreach($statement->fetchAll(PDO::FETCH_ASSOC)as$item){if($item['currency']!=='CZK'||(int)$item['quantity']<1||(int)$item['amount_minor']<0)throw new ShopCouponException('Košík není platný pro kupón.');$line=(int)$item['quantity']*(int)$item['amount_minor'];if($line<0||$total>PHP_INT_MAX-$line)throw new ShopCouponException('Součet košíku je mimo podporovaný rozsah.');$total+=$line;}
    if($total<1)throw new ShopCouponException('Kupón nelze použít na prázdný košík.');return $total;
}

/** @return array<string,mixed> */
function shopCouponAdminFind(PDO $pdo,int $couponId,bool $lock):array
{
    $sql='SELECT * FROM shop_coupons WHERE id=?';if($lock&&(string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$sql.=' FOR UPDATE';$statement=$pdo->prepare($sql);$statement->execute([$couponId]);$coupon=$statement->fetch(PDO::FETCH_ASSOC);if(!$coupon)throw new ShopCouponException('Kupón nebyl nalezen.');return $coupon;
}

function shopCouponAdminDate(string $value):?string
{
    if($value==='')return null;$date=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$value);$errors=DateTimeImmutable::getLastErrors();if(!$date||($errors!==false&&($errors['warning_count']>0||$errors['error_count']>0))||$date->format('Y-m-d H:i:s')!==$value)throw new InvalidArgumentException('Platnost kupónu musí mít formát RRRR-MM-DD HH:MM:SS.');return $value;
}

/** @return array<string,mixed> */
function shopCouponAuditSnapshot(array $coupon):array
{
    return array_intersect_key($coupon,array_flip(['id','code','discount_type','value_minor_or_basis_points','currency','minimum_order_minor','maximum_discount_minor','usage_limit_total','usage_count','valid_from','valid_until','active']));
}
