<?php
declare(strict_types=1);

require_once __DIR__.'/club_event_registration.php';

final class ClubEventShopException extends RuntimeException {}

function clubEventShopAvailable(PDO $pdo): bool
{
    $driver=(string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if($driver==='mysql'){
        $s=$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('club_event_cart_items','club_event_order_items')");
        return (int)$s->fetchColumn()===2;
    }
    return (int)$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name IN ('club_event_cart_items','club_event_order_items')")->fetchColumn()===2;
}

/** @return array<string,mixed> */
function clubEventShopVariant(PDO $pdo,int $eventId,int $variantId,bool $lock=false):array
{
    $sql="SELECT v.*,p.id AS product_id,p.catalog_status AS product_status,p.offer_type,e.name AS event_name,e.* "
        ."FROM club_events e JOIN shop_product_event_links l ON l.event_id=e.id JOIN shop_products p ON p.id=l.product_id "
        ."JOIN shop_variants v ON v.product_id=p.id WHERE e.id=? AND v.id=?";
    if($lock&&(string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$sql.=' FOR UPDATE';
    $s=$pdo->prepare($sql);$s->execute([$eventId,$variantId]);$row=$s->fetch(PDO::FETCH_ASSOC);
    if(!$row||$row['event_type']!=='club_event'||$row['pricing_policy']!=='product_variants'||$row['status']!=='open'
        ||$row['product_status']!=='active'||$row['catalog_status']!=='active'||$row['price_mode']!=='fixed'
        ||(int)$row['amount_minor']<1||$row['currency']!=='CZK'||($row['visible']!==null&&(int)$row['visible']!==1)){
        throw new ClubEventShopException('Placená událost nebo zvolená cena už není dostupná.');
    }
    clubEventAssertRegistrationWindow($row);
    return $row;
}

/** @return array{id:int,created:bool} */
function clubEventShopAddToCart(PDO $pdo,int $accountId,int $eventId,int $sportovecId,int $variantId,string $consentVersion,bool $consented):array
{
    $consentVersion=trim($consentVersion);
    if($accountId<1||$eventId<1||$sportovecId<1||$variantId<1||$consentVersion===''||!$consented)
        throw new InvalidArgumentException('Přidání vyžaduje událost, schválenou osobu, cenu a potvrzený souhlas.');
    if(!clubEventShopAvailable($pdo))throw new ClubEventShopException('Shop napojení událostí zatím není migrováno.');
    $relation=clubEventEligibleRelation($pdo,$accountId,$sportovecId);
    if(!$relation)throw new ClubEventShopException('Tuto osobu nemáte schválenou pro přihlášení.');
    if(!clubEventRosterEligibility($pdo,$eventId,$sportovecId))throw new ClubEventShopException('Osoba není členem cílové soupisky události.');
    $pdo->beginTransaction();
    try{
        $cart=shopCartLockActive($pdo,$accountId);
        $event=clubEventShopVariant($pdo,$eventId,$variantId,true);
        clubEventShopAssertConsent($event,$consentVersion);
        $relation=clubEventEligibleRelation($pdo,$accountId,$sportovecId);
        $eligibility=clubEventRosterEligibility($pdo,$eventId,$sportovecId);
        if(!$relation||!$eligibility)throw new ClubEventShopException('Oprávnění nebo soupiska se mezitím změnily.');
        clubEventAssertAge($pdo,$eventId,$relation,$event);
        clubEventShopAssertNoActiveRegistration($pdo,$eventId,$sportovecId);
        $existing=$pdo->prepare('SELECT id FROM club_event_cart_items WHERE cart_id=? AND event_id=? AND beneficiary_sportovec_id=?');
        $existing->execute([(int)$cart['id'],$eventId,$sportovecId]);$id=(int)$existing->fetchColumn();$created=$id<1;
        if($created){
            $pdo->prepare('INSERT INTO club_event_cart_items(cart_id,event_id,variant_id,beneficiary_sportovec_id,consent_version) VALUES (?,?,?,?,?)')
                ->execute([(int)$cart['id'],$eventId,$variantId,$sportovecId,$consentVersion]);$id=(int)$pdo->lastInsertId();
        }else{
            $pdo->prepare('UPDATE club_event_cart_items SET variant_id=?,consent_version=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')
                ->execute([$variantId,$consentVersion,$id]);
        }
        $pdo->commit();return ['id'=>$id,'created'=>$created];
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        if($e instanceof InvalidArgumentException||$e instanceof ClubEventShopException||$e instanceof ClubEventRegistrationException)throw $e;
        error_log('club_event_shop add: '.$e->getMessage());
        throw new ClubEventShopException('Událost se nepodařilo vložit do košíku bez částečné změny.',0,$e);
    }
}

function clubEventShopRemoveFromCart(PDO $pdo,int $accountId,int $cartItemId):bool
{
    if($accountId<1||$cartItemId<1)throw new InvalidArgumentException('Odebrání vyžaduje účet a položku.');
    $pdo->beginTransaction();try{$cart=shopCartLockActive($pdo,$accountId);$s=$pdo->prepare('DELETE FROM club_event_cart_items WHERE id=? AND cart_id=?');$s->execute([$cartItemId,(int)$cart['id']]);$changed=$s->rowCount()===1;$pdo->commit();return $changed;}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

/** @return list<array<string,mixed>> */
function clubEventShopCartItems(PDO $pdo,int $cartId):array
{
    if(!clubEventShopAvailable($pdo)||$cartId<1)return [];
    $s=$pdo->prepare('SELECT ci.id AS cart_item_id,ci.event_id,ci.variant_id,ci.beneficiary_sportovec_id,ci.consent_version,e.name AS event_name,v.sku,v.amount_minor,v.currency,s.jmeno,s.prijmeni FROM club_event_cart_items ci JOIN club_events e ON e.id=ci.event_id JOIN shop_variants v ON v.id=ci.variant_id JOIN sportovci s ON s.id=ci.beneficiary_sportovec_id WHERE ci.cart_id=? ORDER BY ci.event_id,ci.id');
    $s->execute([$cartId]);$rows=$s->fetchAll(PDO::FETCH_ASSOC);foreach($rows as &$row){$row['quantity']=1;$row['line_amount_minor']=(int)$row['amount_minor'];}unset($row);return $rows;
}

/** @return list<array<string,mixed>> */
function clubEventShopLockCheckoutItems(PDO $pdo,int $cartId,int $accountId):array
{
    if(!clubEventShopAvailable($pdo))return [];
    $sql='SELECT * FROM club_event_cart_items WHERE cart_id=? ORDER BY event_id,id'.((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'');
    $s=$pdo->prepare($sql);$s->execute([$cartId]);$rows=$s->fetchAll(PDO::FETCH_ASSOC);$result=[];$requestedByEvent=[];
    foreach($rows as $row){
        $event=clubEventShopVariant($pdo,(int)$row['event_id'],(int)$row['variant_id'],true);clubEventShopAssertConsent($event,(string)$row['consent_version']);
        $relation=clubEventEligibleRelation($pdo,$accountId,(int)$row['beneficiary_sportovec_id']);$eligibility=clubEventRosterEligibility($pdo,(int)$row['event_id'],(int)$row['beneficiary_sportovec_id']);
        if(!$relation||!$eligibility)throw new ClubEventShopException('Oprávnění nebo cílová soupiska události se změnily.');
        clubEventAssertAge($pdo,(int)$row['event_id'],$relation,$event);clubEventShopAssertNoActiveRegistration($pdo,(int)$row['event_id'],(int)$row['beneficiary_sportovec_id']);
        $eventId=(int)$row['event_id'];$capacity=clubEventEffectiveCapacity($pdo,$eventId,(int)$event['capacity']);$requested=$requestedByEvent[$eventId]??0;
        if(clubEventShopOccupiedCount($pdo,$eventId)+$requested >= $capacity)throw new ClubEventShopException('Kapacita placené události nestačí pro všechny osoby v košíku.');
        $requestedByEvent[$eventId]=$requested+1;
        $result[]=$row+$event+['relation_role'=>(string)$relation['relation_role'],'eligibility'=>$eligibility];
    }
    return $result;
}

/** @param list<array<string,mixed>> $items */
function clubEventShopFingerprintItems(array $items):array
{
    return array_map(static fn(array $i):array=>['event_id'=>(int)$i['event_id'],'variant_id'=>(int)$i['variant_id'],'beneficiary_sportovec_id'=>(int)$i['beneficiary_sportovec_id'],'consent_version'=>(string)$i['consent_version'],'amount_minor'=>(int)$i['amount_minor'],'currency'=>(string)$i['currency']],$items);
}

/** @param list<array<string,mixed>> $items */
function clubEventShopCreateOrderItemsInTransaction(PDO $pdo,int $orderId,int $accountId,array $items):int
{
    $created=0;
    foreach($items as $i){
        $pdo->prepare('INSERT INTO club_event_registrations(event_id,account_id,sportovec_id,relation_role_snapshot,status,registered_at,consent_version_snapshot,consent_text_snapshot,consented_at,cancellation_policy_snapshot,cancellation_deadline_snapshot,eligibility_team_ids_snapshot,eligibility_reason_snapshot) VALUES (?,?,?,?,\'payment_pending\',CURRENT_TIMESTAMP,?,?,CURRENT_TIMESTAMP,?,?,?,?)')
            ->execute([(int)$i['event_id'],$accountId,(int)$i['beneficiary_sportovec_id'],(string)$i['relation_role'],(string)$i['terms_version'],(string)$i['consent_text_plain'],(string)$i['cancellation_policy_plain'],(string)$i['cancellation_deadline_at'],clubEventRosterEligibilityJson($i['eligibility']),(string)$i['eligibility']['reason']]);
        $registrationId=(int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO club_event_order_items(order_id,registration_id,event_id,variant_id,beneficiary_sportovec_id,event_name_snapshot,sku_snapshot,consent_version_snapshot,consent_text_snapshot,cancellation_policy_snapshot,cancellation_deadline_snapshot,eligibility_team_ids_snapshot,quantity,unit_amount_minor,line_amount_minor,currency) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1,?,?,?)')
            ->execute([$orderId,$registrationId,(int)$i['event_id'],(int)$i['variant_id'],(int)$i['beneficiary_sportovec_id'],(string)$i['event_name'],(string)$i['sku'],(string)$i['terms_version'],(string)$i['consent_text_plain'],(string)$i['cancellation_policy_plain'],(string)$i['cancellation_deadline_at'],clubEventRosterEligibilityJson($i['eligibility']),(int)$i['amount_minor'],(int)$i['amount_minor'],(string)$i['currency']]);
        clubEventRegistrationAudit($pdo,$registrationId,'account',$accountId,'shop_order_hold',null,'payment_pending','Kapacita držena objednávkou #'.$orderId.'; čeká na úhradu.');$created++;
    }
    return $created;
}

/** @return array{items:int,activated:int} */
function clubEventShopActivatePaidOrderInTransaction(PDO $pdo,int $orderId,int $actorTrainerId):array
{
    $items=clubEventShopOrderRows($pdo,$orderId);$activated=0;
    foreach($items as $i){clubEventLock($pdo,(int)$i['event_id']);$r=clubEventShopLockRegistration($pdo,(int)$i['registration_id']);if($r['status']==='confirmed')continue;if($r['status']!=='payment_pending')throw new ClubEventShopException('Přihláška objednávky není ve stavu pro přijetí platby.');$pdo->prepare("UPDATE club_event_registrations SET status='confirmed',updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([(int)$r['id']]);clubEventRegistrationAudit($pdo,(int)$r['id'],'trainer',$actorTrainerId,'shop_payment_paid','payment_pending','confirmed','Platba potvrzena na objednávce #'.$orderId.'.');$activated++;}
    return ['items'=>count($items),'activated'=>$activated];
}

/** @return array{items:int,cancelled:int} */
function clubEventShopCancelOrderInTransaction(PDO $pdo,int $orderId,?int $actorId,string $reason,string $actorType='trainer',?DateTimeImmutable $now=null):array
{
    if(!in_array($actorType,['trainer','system'],true)||($actorType==='trainer'&&($actorId??0)<1)||($actorType==='system'&&$actorId!==null))throw new InvalidArgumentException('Neplatná auditní identita storna placené události.');
    $items=clubEventShopOrderRows($pdo,$orderId);$cancelled=0;
    foreach($items as $i){clubEventLock($pdo,(int)$i['event_id']);$r=clubEventShopLockRegistration($pdo,(int)$i['registration_id']);if($r['status']==='cancelled')continue;if(!in_array($r['status'],['payment_pending','confirmed'],true))throw new ClubEventShopException('Přihlášku objednávky nelze bezpečně stornovat.');$from=(string)$r['status'];$timestamp=($now??new DateTimeImmutable())->format('Y-m-d H:i:s');$pdo->prepare("UPDATE club_event_registrations SET status='cancelled',cancelled_at=?,cancellation_note=?,updated_at=? WHERE id=?")->execute([$timestamp,$reason,$timestamp,(int)$r['id']]);clubEventRegistrationAudit($pdo,(int)$r['id'],$actorType,$actorId,$actorType==='system'?'shop_order_expire':'shop_order_cancel',$from,'cancelled',$reason,$now);if($from==='confirmed')clubEventPromoteNextWaitlisted($pdo,(int)$i['event_id']);$cancelled++;}
    return ['items'=>count($items),'cancelled'=>$cancelled];
}

function clubEventShopAssertRefundableInTransaction(PDO $pdo,int $orderId):void{foreach(clubEventShopOrderRows($pdo,$orderId)as$i){$r=clubEventShopLockRegistration($pdo,(int)$i['registration_id']);if($r['status']!=='cancelled')throw new ClubEventShopException('Vratku nelze potvrdit před zrušením přihlášky.');}}
function clubEventShopRegistrationIsOrderLinked(PDO $pdo,int $registrationId):bool{if(!clubEventShopAvailable($pdo))return false;$s=$pdo->prepare('SELECT 1 FROM club_event_order_items WHERE registration_id=?');$s->execute([$registrationId]);return(bool)$s->fetchColumn();}
/** @return list<array<string,mixed>> */
function clubEventShopOrderRows(PDO $pdo,int $orderId):array{if(!clubEventShopAvailable($pdo))return[];$sql='SELECT * FROM club_event_order_items WHERE order_id=? ORDER BY event_id,id'.((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'');$s=$pdo->prepare($sql);$s->execute([$orderId]);return$s->fetchAll(PDO::FETCH_ASSOC);}
/** @return array<string,mixed> */
function clubEventShopLockRegistration(PDO $pdo,int $id):array{$sql='SELECT * FROM club_event_registrations WHERE id=?'.((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'');$s=$pdo->prepare($sql);$s->execute([$id]);$r=$s->fetch(PDO::FETCH_ASSOC);if(!$r)throw new ClubEventShopException('Přihláška objednávky nebyla nalezena.');return$r;}
function clubEventShopOccupiedCount(PDO $pdo,int $eventId):int{$s=$pdo->prepare("SELECT COUNT(*) FROM club_event_registrations WHERE event_id=? AND status IN ('confirmed','payment_pending')");$s->execute([$eventId]);return(int)$s->fetchColumn();}
function clubEventShopAssertNoActiveRegistration(PDO $pdo,int $eventId,int $sportovecId):void{$sql='SELECT id,status FROM club_event_registrations WHERE event_id=? AND sportovec_id=?'.((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'');$s=$pdo->prepare($sql);$s->execute([$eventId,$sportovecId]);$row=$s->fetch(PDO::FETCH_ASSOC);if(!$row)return;if(in_array($row['status'],['confirmed','waitlisted','payment_pending'],true))throw new ClubEventShopException('Tato osoba už je přihlášena, čeká na místo nebo na úhradu.');if(clubEventShopRegistrationIsOrderLinked($pdo,(int)$row['id']))throw new ClubEventShopException('Tato osoba už měla událost objednanou. Opakování po stornu musí posoudit administrátor.');}
/** @param array<string,mixed> $event */
function clubEventShopAssertConsent(array $event,string $version):void{if(empty($event['terms_version'])||!hash_equals((string)$event['terms_version'],$version)||empty($event['consent_text_plain'])||empty($event['cancellation_policy_plain'])||empty($event['cancellation_deadline_at']))throw new ClubEventShopException('Podmínky události se změnily. Obnovte stránku a potvrďte aktuální znění.');}

/** @return list<array<string,mixed>> */
function clubEventOpenPaidList(PDO $pdo):array
{
    $rows=$pdo->query("SELECT e.*,v.id AS variant_id,v.sku,v.amount_minor FROM club_events e JOIN shop_product_event_links l ON l.event_id=e.id JOIN shop_products p ON p.id=l.product_id JOIN shop_variants v ON v.product_id=p.id WHERE e.event_type='club_event' AND e.status='open' AND e.pricing_policy='product_variants' AND p.catalog_status='active' AND v.catalog_status='active' AND v.price_mode='fixed' AND v.amount_minor>0 AND v.currency='CZK' AND (v.visible IS NULL OR v.visible=1) AND (e.registration_starts_at IS NULL OR e.registration_starts_at<=CURRENT_TIMESTAMP) AND (e.registration_ends_at IS NULL OR e.registration_ends_at>=CURRENT_TIMESTAMP) ORDER BY e.registration_starts_at,e.id,v.id")->fetchAll(PDO::FETCH_ASSOC);
    foreach($rows as &$event){$event['sessions']=clubEventSessions($pdo,(int)$event['id']);$event['effective_capacity']=clubEventEffectiveCapacity($pdo,(int)$event['id'],(int)$event['capacity']);$event['remaining_capacity']=max(0,$event['effective_capacity']-clubEventShopOccupiedCount($pdo,(int)$event['id']));}unset($event);return$rows;
}
