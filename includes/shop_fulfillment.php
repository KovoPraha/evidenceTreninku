<?php
declare(strict_types=1);
require_once __DIR__.'/shop_inventory.php';

function shopFulfillmentAvailable(PDO $pdo):bool
{
    return clubFinanceTableExists($pdo,'shop_fulfillments');
}
function shopFulfillmentHasManagedItems(PDO $pdo,int $orderId):bool
{
    $s=$pdo->prepare('SELECT variant_id FROM shop_order_items WHERE order_id=?');$s->execute([$orderId]);
    foreach($s->fetchAll(PDO::FETCH_COLUMN) as $id)if(shopInventoryManaged($pdo,(int)$id))return true;
    return false;
}
function shopFulfillmentIssuedQuantity(PDO $pdo,int $orderId):int
{
    if(!shopFulfillmentAvailable($pdo))return 0;
    $s=$pdo->prepare('SELECT COALESCE(SUM(i.quantity),0) FROM shop_fulfillment_items i JOIN shop_fulfillments f ON f.id=i.fulfillment_id WHERE f.order_id=?');$s->execute([$orderId]);return (int)$s->fetchColumn();
}
function shopFulfillmentDate(string $value):string
{
    $dt=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$value,new DateTimeZone('Europe/Prague'));
    if(!$dt||$dt->format('Y-m-d H:i:s')!==$value||$dt>new DateTimeImmutable('+5 minutes',new DateTimeZone('Europe/Prague')))throw new InvalidArgumentException('Zadejte skutečný platný čas předání, nejvýše nyní.');
    return $value;
}
/** Quantity map is keyed by the immutable order-item id. */
function shopFulfillmentIssue(PDO $pdo,int $orderId,array $quantities,int $actor,string $recipient,string $occurredAt,string $reason,string $key,string $channel='pickup'):array
{
    $reason=shopOrderAdminValidateAction($orderId,$actor,$reason,true,'Položkový výdej');
    $pdo->beginTransaction();
    try{
        shopFulfillmentLockFinance($pdo);
        $sql="SELECT status FROM payments WHERE payable_type='shop_order' AND payable_id=?";if($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$sql.=' FOR UPDATE';
        $s=$pdo->prepare($sql);$s->execute([$orderId]);if($s->fetchColumn()!=='paid')throw new ShopCheckoutException('Výdej je možný jen po potvrzení úhrady.');
        $order=shopOrderAdminLockOrder($pdo,$orderId);if(!$order)throw new ShopCheckoutException('Objednávka neexistuje.');
        $result=shopFulfillmentIssueInTransaction($pdo,$order,$quantities,$actor,$recipient,$occurredAt,$reason,$key,$channel);
        $pdo->commit();return $result;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function shopFulfillmentIssueInTransaction(PDO $pdo,array $order,array $quantities,int $actor,string $recipient,string $occurredAt,string $reason,string $key,string $channel='pickup'):array
{
    if(!$pdo->inTransaction())throw new LogicException('Výdej vyžaduje transakci.');
    shopInventoryActor($pdo,$actor,'order_operator');
    $recipient=trim($recipient);$reason=trim($reason);$orderId=(int)$order['id'];
    if($actor<1||$recipient===''||mb_strlen($recipient)>200||$reason===''||mb_strlen($reason)>1000||!preg_match('/^[a-zA-Z0-9:_-]{8,100}$/D',$key)||!in_array($channel,['pickup','carrier'],true))throw new InvalidArgumentException('Výdej vyžaduje příjemce, pracovníka, důvod a identifikátor.');
    $occurredAt=shopFulfillmentDate($occurredAt);if(!empty($order['placed_at'])&&substr($occurredAt,0,10)<substr($order['placed_at'],0,10))throw new ShopCheckoutException('Předání nemůže předcházet objednávce.');$normalized=[];
    foreach($quantities as $id=>$qty){if((int)$id<1||filter_var($qty,FILTER_VALIDATE_INT)===false||(int)$qty<0||((int)$qty)>100000)throw new InvalidArgumentException('Neplatné vydané množství.');if((int)$qty>0)$normalized[(int)$id]=(int)$qty;}
    if($normalized===[])throw new InvalidArgumentException('Vyberte alespoň jeden kus k předání.');ksort($normalized,SORT_NUMERIC);
    $fingerprint=hash('sha256',json_encode([$orderId,$normalized,$recipient,$occurredAt,$reason,$channel,$actor],JSON_THROW_ON_ERROR));
    $s=$pdo->prepare('SELECT * FROM shop_fulfillments WHERE request_key=?');$s->execute([$key]);$old=$s->fetch(PDO::FETCH_ASSOC);
    if($old){if(!hash_equals($old['fingerprint'],$fingerprint))throw new ShopCheckoutException('Identifikátor výdeje už patří jiným údajům.');return ['order_id'=>$orderId,'fulfillment_id'=>(int)$old['id'],'changed'=>false];}
    if(!in_array($order['status'],['processing','ready'],true)||$order['payment_status']!=='paid')throw new ShopCheckoutException('Objednávka není zaplacená nebo je uzavřená.');
    if(($channel==='pickup')!==!in_array($order['fulfillment_method'],['ppl_box','packeta_zbox'],true))throw new ShopCheckoutException('Způsob předání neodpovídá objednávce.');
    $s=$pdo->prepare('SELECT oi.*,p.offer_type FROM shop_order_items oi JOIN shop_products p ON p.id=oi.product_id WHERE oi.order_id=? ORDER BY oi.variant_id,oi.id');$s->execute([$orderId]);$items=$s->fetchAll(PDO::FETCH_ASSOC);$matched=0;
    foreach($items as $item){if(!isset($normalized[(int)$item['id']]))continue;$matched++;if($item['offer_type']!=='goods'||!shopInventoryManaged($pdo,(int)$item['variant_id']))throw new ShopCheckoutException('Položka nemá ověřený fyzický sklad; služba není zboží.');}
    if($matched!==count($normalized))throw new ShopCheckoutException('Výdej obsahuje cizí položku.');
    shopFulfillmentAssertFinancialQuantity($pdo,$order,$items,$normalized);
    $pdo->prepare('INSERT INTO shop_fulfillments(order_id,request_key,fingerprint,channel,recipient,occurred_at,actor_trainer_id,reason) VALUES(?,?,?,?,?,?,?,?)')->execute([$orderId,$key,$fingerprint,$channel,$recipient,$occurredAt,$actor,$reason]);$id=(int)$pdo->lastInsertId();
    foreach($items as $item){$itemId=(int)$item['id'];if(!isset($normalized[$itemId]))continue;
        $movement=shopInventoryIssueInTransaction($pdo,$itemId,$normalized[$itemId],'issue:'.$id.':'.$itemId,$actor,$reason,['fulfillment_id'=>$id,'recipient'=>$recipient,'occurred_at'=>$occurredAt,'effective_on'=>substr($occurredAt,0,10)]);
        $pdo->prepare('INSERT INTO shop_fulfillment_items(fulfillment_id,order_item_id,variant_id,quantity,inventory_movement_id,cost_minor) VALUES(?,?,?,?,?,?)')->execute([$id,$itemId,(int)$item['variant_id'],$normalized[$itemId],(int)($movement['id']??$movement['movement_id']),$movement['cost_minor']??null]);
    }
    $remaining=0;$nonGoods=false;
    foreach($items as $item){if($item['offer_type']!=='goods'){$nonGoods=true;continue;}if(!shopInventoryManaged($pdo,(int)$item['variant_id'])){$remaining++;continue;}$r=shopInventoryReservation($pdo,(int)$item['id'],true);$remaining+=(int)($r['remaining_quantity']??$item['quantity']);}
    $status=$remaining===0&&!$nonGoods?'completed':(string)$order['status'];
    if($status==='completed')$pdo->prepare("UPDATE shop_orders SET status='completed',completed_at=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$occurredAt,$orderId]);
    $pdo->prepare("INSERT INTO shop_order_events(order_id,actor_type,actor_id,action,from_status,to_status,note) VALUES (?,'trainer',?,'item_issue',?,?,?)")->execute([$orderId,$actor,$order['status'],$status,$reason.' | Výdej #'.$id.'; příjemce '.$recipient]);
    return ['order_id'=>$orderId,'fulfillment_id'=>$id,'status'=>$status,'changed'=>true,'remaining_quantity'=>$remaining];
}
function shopFulfillmentDispatchManagedInTransaction(PDO $pdo,array $order,int $actor,string $carrier,string $reason,string $key):void
{
    if(!shopFulfillmentHasManagedItems($pdo,(int)$order['id']))return;
    $s=$pdo->prepare('SELECT oi.id,oi.variant_id,p.offer_type FROM shop_order_items oi JOIN shop_products p ON p.id=oi.product_id WHERE oi.order_id=? ORDER BY oi.variant_id,oi.id');$s->execute([(int)$order['id']]);$quantities=[];
    foreach($s->fetchAll(PDO::FETCH_ASSOC) as $item){if($item['offer_type']!=='goods')continue;if(!shopInventoryManaged($pdo,(int)$item['variant_id']))throw new ShopCheckoutException('Zásilka obsahuje neověřený sklad. Dokončete převod variant.');$r=shopInventoryReservation($pdo,(int)$item['id'],true);if(!$r)throw new ShopCheckoutException('Zásilka nemá doloženou rezervaci.');if((int)$r['remaining_quantity']>0)$quantities[(int)$item['id']]=(int)$r['remaining_quantity'];}
    if($quantities!==[])shopFulfillmentIssueInTransaction($pdo,$order,$quantities,$actor,$carrier,date('Y-m-d H:i:s'),$reason,'dispatch:'.$key,'carrier');
}
/** Carrier exceptions do not change stock or refund money. */
function shopFulfillmentDeliveryRecord(PDO $pdo,int $orderId,string $status,int $actor,string $recipient,string $occurredAt,string $reason,string $key):array
{
    $reason=shopOrderAdminValidateAction($orderId,$actor,$reason,true,'Doručení');$occurredAt=shopFulfillmentDate($occurredAt);$recipient=trim($recipient);
    if(!in_array($status,['delivered','undelivered','lost','damaged'],true)||!preg_match('/^[a-zA-Z0-9:_-]{8,100}$/D',$key)||$recipient===''||mb_strlen($recipient)>200)throw new InvalidArgumentException('Doplňte stav, příjemce nebo kontakt a identifikátor.');
    $fp=hash('sha256',json_encode([$orderId,$status,$actor,$recipient,$occurredAt,$reason],JSON_THROW_ON_ERROR));$pdo->beginTransaction();
    try{shopInventoryActor($pdo,$actor,'order_operator');$order=shopOrderAdminLockOrder($pdo,$orderId);if(!$order||!in_array($order['fulfillment_method'],['ppl_box','packeta_zbox'],true)||empty($order['ppl_dispatched_at'])&&empty($order['packeta_deposited_at']))throw new ShopCheckoutException('Zásilka dosud nebyla předána dopravci.');
        $dispatched=(string)($order['ppl_dispatched_at']?:$order['packeta_deposited_at']);if($occurredAt<$dispatched)throw new ShopCheckoutException('Doručení nemůže předcházet předání dopravci.');
        $s=$pdo->prepare('SELECT id,fingerprint FROM shop_delivery_events WHERE request_key=?');$s->execute([$key]);$old=$s->fetch(PDO::FETCH_ASSOC);if($old){if(!hash_equals($old['fingerprint'],$fp))throw new ShopCheckoutException('Identifikátor už patří jinému záznamu.');$pdo->commit();return ['order_id'=>$orderId,'id'=>(int)$old['id'],'changed'=>false];}
        $pdo->prepare('INSERT INTO shop_delivery_events(order_id,request_key,fingerprint,status,occurred_at,actor_trainer_id,recipient,reason) VALUES(?,?,?,?,?,?,?,?)')->execute([$orderId,$key,$fp,$status,$occurredAt,$actor,$recipient,$reason]);$id=(int)$pdo->lastInsertId();$pdo->commit();return ['order_id'=>$orderId,'id'=>$id,'changed'=>true];
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

/** Same first lock as refund decisions. Call before payment/order locks. */
function shopFulfillmentLockFinance(PDO $pdo):void
{
    if(!$pdo->inTransaction())throw new LogicException('Finanční zámek výdeje vyžaduje transakci.');
    if(clubFinanceTableExists($pdo,'accounting_ledger_mutex'))$pdo->exec('UPDATE accounting_ledger_mutex SET revision=revision+1 WHERE id=1');
}
function shopFulfillmentAssertFinancialQuantity(PDO $pdo,array $order,array $items,array $quantities):void
{
    $orderId=(int)$order['id'];$corrected=[];
    if(clubFinanceTableExists($pdo,'accounting_documents')){
        $s=$pdo->prepare("SELECT snapshot_json FROM accounting_documents WHERE source_type='order' AND source_id=? AND kind='correction' AND status<>'void'".shopInventoryLock($pdo));$s->execute([$orderId]);
        foreach($s->fetchAll(PDO::FETCH_COLUMN) as $json)foreach(json_decode($json,true,512,JSON_THROW_ON_ERROR)['lines'] as $line){$key=(string)$line['line_key'];$corrected[$key]=($corrected[$key]??0)+(int)$line['quantity'];}
        foreach($items as $item){$id=(int)$item['id'];if(!isset($quantities[$id])||empty($corrected['item:'.$id]))continue;$r=shopInventoryReservation($pdo,$id,true);$s=$pdo->prepare("SELECT COALESCE(SUM(physical_delta),0) FROM shop_stock_movements WHERE kind='return' AND order_item_id=?".shopInventoryLock($pdo));$s->execute([$id]);$returned=(int)$s->fetchColumn();
            if((int)($r['issued_quantity']??0)-$returned+$quantities[$id]>(int)$item['quantity']-$corrected['item:'.$id])throw new ShopCheckoutException('Oprava účetního dokladu snížila množství k plnění. Nejdříve vypořádejte opravu a rezervaci s hospodářem.');
        }
    }
    if(clubFinanceTableExists($pdo,'accounting_refund_decisions')&&clubFinanceTableExists($pdo,'accounting_movements')){
        $s=$pdo->prepare("SELECT COALESCE(SUM(d.amount_minor),0) FROM accounting_refund_decisions d JOIN accounting_movements m ON m.id=d.original_movement_id WHERE NOT EXISTS(SELECT 1 FROM accounting_adjustment_events e WHERE e.decision_id=d.id AND e.event_type='decision_cancelled') AND ((m.case_type='shop_order' AND m.case_id=?) OR EXISTS(SELECT 1 FROM accounting_movement_sources src JOIN payments p ON p.id=src.source_id AND src.source_type='payments' WHERE src.movement_id=m.id AND p.payable_type='shop_order' AND p.payable_id=?))".shopInventoryLock($pdo));$s->execute([$orderId,$orderId]);
        if((int)$s->fetchColumn()>=(int)$order['total_minor'])throw new ShopCheckoutException('Celá platba je určena k vrácení. Výdej vyžaduje nejprve vypořádání s hospodářem.');
    }
}

/** A pending payment label does not prove no actual money has been allocated. */
function shopFulfillmentAssertUnpaid(PDO $pdo,int $orderId):void
{
    if(clubFinanceTableExists($pdo,'club_finance_allocations')){$s=$pdo->prepare('SELECT COALESCE(SUM(amount_minor),0) FROM club_finance_allocations WHERE order_id=?'.shopInventoryLock($pdo));$s->execute([$orderId]);if((int)$s->fetchColumn()>0)throw new ShopCheckoutException('Objednávka má skutečnou částečnou úhradu. Expiraci nebo storno nejdříve vypořádá hospodář.');}
    if(clubFinanceTableExists($pdo,'accounting_allocations')){$s=$pdo->prepare("SELECT COALESCE(SUM(amount_minor),0) FROM accounting_allocations WHERE case_type='shop_order' AND case_id=?".shopInventoryLock($pdo));$s->execute([$orderId]);if((int)$s->fetchColumn()>0)throw new ShopCheckoutException('Objednávka má přidělený finanční příjem. Uvolnění rezervace musí nejdříve prověřit hospodář.');}
}
/** Release only the proven unissued remainder; money and original item snapshots stay unchanged. */
function shopFulfillmentReleaseRemainder(PDO $pdo,int $orderId,int $itemId,int $correctionId,int $actor,string $reason,string $key):array
{
    $reason=shopOrderAdminValidateAction($orderId,$actor,$reason,true,'Uvolnění zbytku');if($itemId<1||$correctionId<1||!preg_match('/^[a-zA-Z0-9:_-]{8,100}$/D',$key))throw new InvalidArgumentException('Vyberte položku a vydaný opravný doklad.');
    $pdo->beginTransaction();try{
        shopFulfillmentLockFinance($pdo);$s=$pdo->prepare("SELECT status FROM payments WHERE payable_type='shop_order' AND payable_id=?".shopInventoryLock($pdo));$s->execute([$orderId]);if($s->fetchColumn()!=='paid')throw new ShopCheckoutException('Nevydaný zbytek touto cestou lze uvolnit jen u zaplacené objednávky.');
        $order=shopOrderAdminLockOrder($pdo,$orderId);if(!$order||!in_array($order['status'],['processing','ready'],true))throw new ShopCheckoutException('Objednávka není otevřená pro položkové vypořádání.');shopInventoryActor($pdo,$actor,'order_operator');
        $s=$pdo->prepare('SELECT * FROM shop_order_items WHERE id=? AND order_id=?');$s->execute([$itemId,$orderId]);$item=$s->fetch(PDO::FETCH_ASSOC);if(!$item||!shopInventoryManaged($pdo,(int)$item['variant_id']))throw new ShopCheckoutException('Položka není sledovaným zbožím této objednávky.');
        shopInventoryLockBalance($pdo,(int)$item['variant_id']);$r=shopInventoryReservation($pdo,$itemId,true);if(!$r)throw new ShopCheckoutException('Rezervace položky chybí.');
        $s=$pdo->prepare("SELECT * FROM accounting_documents WHERE id=? AND source_type='order' AND source_id=? AND kind='correction' AND status='issued'".shopInventoryLock($pdo));$s->execute([$correctionId,$orderId]);$doc=$s->fetch(PDO::FETCH_ASSOC);if(!$doc)throw new ShopCheckoutException('Vyžaduje vydaný opravný doklad k této objednávce.');
        $selectedQty=0;foreach(json_decode($doc['snapshot_json'],true,512,JSON_THROW_ON_ERROR)['lines'] as $line)if($line['line_key']==='item:'.$itemId)$selectedQty+=(int)$line['quantity'];
        if($selectedQty<1)throw new ShopCheckoutException('Vybraný opravný doklad neobsahuje rušené množství této položky.');
        $s=$pdo->prepare("SELECT snapshot_json FROM accounting_documents WHERE source_type='order' AND source_id=? AND kind='correction' AND status='issued'".shopInventoryLock($pdo));$s->execute([$orderId]);$qty=0;foreach($s->fetchAll(PDO::FETCH_COLUMN) as $json)foreach(json_decode($json,true,512,JSON_THROW_ON_ERROR)['lines'] as $line)if($line['line_key']==='item:'.$itemId)$qty+=(int)$line['quantity'];
        $s=$pdo->prepare("SELECT COALESCE(SUM(physical_delta),0) FROM shop_stock_movements WHERE kind='return' AND order_item_id=?".shopInventoryLock($pdo));$s->execute([$itemId]);$returned=(int)$s->fetchColumn();
        $remaining=(int)$r['remaining_quantity'];$meta=['correction_document_id'=>$correctionId,'order_item_id'=>$itemId];
        if($remaining===0){$s=$pdo->prepare("SELECT id,meta_json FROM shop_stock_movements WHERE request_key=? AND kind='release' AND order_item_id=?".shopInventoryLock($pdo));$s->execute([$key,$itemId]);$old=$s->fetch(PDO::FETCH_ASSOC);if($old&&(int)(json_decode($old['meta_json'],true)['correction_document_id']??0)===$correctionId){$pdo->commit();return ['order_id'=>$orderId,'changed'=>false,'movement_id'=>(int)$old['id']];}throw new ShopCheckoutException('Nevydaný zbytek už byl vypořádán.');}
        if($qty-$returned-(int)$r['released_quantity']<$remaining)throw new ShopCheckoutException('Opravný doklad nepokrývá celý nevydaný zbytek po odečtení již vrácených kusů.');
        $movement=shopInventoryReleaseInTransaction($pdo,$itemId,$key,$actor,$reason,$meta);
        $pdo->prepare("INSERT INTO shop_order_events(order_id,actor_type,actor_id,action,from_status,to_status,note) VALUES (?,'trainer',?,'release_remainder',?,?,?)")->execute([$orderId,$actor,$order['status'],$order['status'],$reason.' | Nevydáno '.$remaining.' ks; opravný doklad #'.$correctionId.'; pohyb #'.$movement['id'].'. Peníze se tím nevracejí.']);
        $pdo->commit();return ['order_id'=>$orderId,'changed'=>true,'movement_id'=>(int)$movement['id'],'released_quantity'=>$remaining];
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

/** Read model does not rewrite commercial order status or pretend a refund happened. */
function shopFulfillmentResolution(PDO $pdo,int $orderId):array
{
    $out=['managed_items'=>0,'unverified_items'=>0,'issued_quantity'=>0,'released_quantity'=>0,'remaining_quantity'=>0,'settled'=>false];
    $s=$pdo->prepare("SELECT oi.id,oi.variant_id FROM shop_order_items oi JOIN shop_products p ON p.id=oi.product_id WHERE oi.order_id=? AND p.offer_type='goods'");$s->execute([$orderId]);
    foreach($s->fetchAll(PDO::FETCH_ASSOC) as $item){if(!shopInventoryManaged($pdo,(int)$item['variant_id'])){$out['unverified_items']++;continue;}$r=shopInventoryReservation($pdo,(int)$item['id']);if(!$r){$out['unverified_items']++;continue;}$out['managed_items']++;foreach(['issued_quantity','released_quantity','remaining_quantity'] as $field)$out[$field]+=(int)$r[$field];}
    $out['settled']=$out['managed_items']>0&&$out['unverified_items']===0&&$out['remaining_quantity']===0;return$out;
}
function shopFulfillmentResolutionLabel(array $resolution):string
{
    if(!$resolution['settled']||$resolution['released_quantity']<1)return '';
    return 'Předání vypořádáno, '.$resolution['released_quantity'].' kusů nevydáno. Finanční vypořádání se řeší samostatně.';
}
