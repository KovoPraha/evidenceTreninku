<?php
declare(strict_types=1);
require_once __DIR__.'/club_finance.php';

function accountingAdjustmentLock(PDO $pdo):string
{
    if (!$pdo->inTransaction()) throw new LogicException('Opravy vyžadují transakci.');
    return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
}

function accountingAdjustmentActor(PDO $pdo,int $actor,string $reason,bool $confirmed,string $requestKey):void
{
    if (!$confirmed || mb_strlen(trim($reason))<10 || mb_strlen($reason)>1000 || preg_match('/^[A-Za-z0-9:_.-]{8,100}$/D',$requestKey)!==1) {
        throw new DomainException('Potvrďte úkon, vyplňte důvod (10–1000 znaků) a platný identifikátor požadavku.');
    }
    $lock=accountingAdjustmentLock($pdo);
    $st=$pdo->prepare('SELECT id FROM treneri WHERE id=? AND aktivni=1'.$lock);
    $st->execute([$actor]);
    if (!$st->fetchColumn()) throw new DomainException('Úkon vyžaduje aktivní oprávnění Hospodář a platby.');
    $st=$pdo->prepare("SELECT trainer_id FROM staff_user_positions WHERE trainer_id=? AND position_code='finance_manager'".$lock);$st->execute([$actor]);
    if (!$st->fetchColumn()) {$st=$pdo->prepare('SELECT trainer_id FROM staff_superadmins WHERE trainer_id=?'.$lock);$st->execute([$actor]);if (!$st->fetchColumn()) throw new DomainException('Úkon vyžaduje aktivní oprávnění Hospodář a platby.');}
}

function accountingAdjustmentDate(string $date):void
{
    $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
    if (!$parsed || $parsed->format('Y-m-d')!==$date || $date>date('Y-m-d')) throw new DomainException('Uveďte platné skutečné datum, které není v budoucnu.');
}

function accountingAdjustmentHash(array $input):string
{
    return hash('sha256',json_encode($input,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
}

/** Transactions also work when called by another canonical financial action. */
function accountingAdjustmentTransaction(PDO $pdo,callable $operation):mixed
{
    $own=!$pdo->inTransaction();
    if ($own) $pdo->beginTransaction();
    try {
        if (clubFinanceTableExists($pdo,'accounting_ledger_mutex')) $pdo->exec('UPDATE accounting_ledger_mutex SET revision=revision+1 WHERE id=1');
        $result=$operation();if ($own) $pdo->commit();return $result;
    }
    catch(Throwable $e) {if ($own && $pdo->inTransaction()) $pdo->rollBack();throw $e;}
}

function accountingAdjustmentRow(PDO $pdo,string $table,int $id):array
{
    if (!in_array($table,['accounting_movements','accounting_documents','accounting_refund_decisions'],true)) throw new LogicException('Neplatná tabulka.');
    $st=$pdo->prepare('SELECT * FROM '.$table.' WHERE id=?'.accountingAdjustmentLock($pdo));$st->execute([$id]);
    $row=$st->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new DomainException('Podklad opravy nebyl nalezen.');
    return $row;
}

/** Same order as legacy confirmation: lock payment before the accounting movement. */
function accountingAdjustmentLockLegacyPayment(PDO $pdo,int $movementId):void
{
    if (!clubFinanceTableExists($pdo,'payments')) return;
    $st=$pdo->prepare('SELECT case_type,case_id FROM accounting_movements WHERE id=?');$st->execute([$movementId]);$movement=$st->fetch(PDO::FETCH_ASSOC);
    if (!$movement) return;
    $links=['(payable_type=? AND payable_id=?)'];$args=[$movement['case_type'],$movement['case_id']];
    if (clubFinanceTableExists($pdo,'accounting_movement_sources')) {
        $links[]="id IN (SELECT source_id FROM accounting_movement_sources WHERE movement_id=? AND source_type='payments')";$args[]=$movementId;
    }
    // Split bank receipts do not have a single case_id or a direct payment alias.
    // Keep the original positive allocation as evidence even after its release.
    if (clubFinanceTableExists($pdo,'accounting_allocations')) {
        $links[]='EXISTS (SELECT 1 FROM accounting_allocations a WHERE a.movement_id=? AND a.case_type=payments.payable_type AND a.case_id=payments.payable_id AND a.amount_minor>0)';$args[]=$movementId;
    }
    $st=$pdo->prepare('SELECT * FROM payments WHERE ('.implode(' OR ',$links).')'.accountingAdjustmentLock($pdo));$st->execute($args);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $payment) {
        if ($payment['status']==='refunded' || !empty($payment['refund_sent_at'])) throw new DomainException('Původní platba již má potvrzenou celou vratku. Nejdříve vypořádejte původní podklady.');
    }
}

/** Cash remaining, allocation and unpaid decisions are distinct; a release is not a cash outflow. */
function accountingAdjustmentBalance(PDO $pdo,int $movementId):array
{
    $st=$pdo->prepare('SELECT * FROM accounting_movements WHERE id=?');$st->execute([$movementId]);$movement=$st->fetch(PDO::FETCH_ASSOC);
    if (!$movement || (int)$movement['amount_minor']<=0) throw new DomainException('Vratka vyžaduje původní doložený příjem.');
    $sum=static function(string $sql,array $args)use($pdo):int {$s=$pdo->prepare($sql);$s->execute($args);return (int)$s->fetchColumn();};
    $refunded=-$sum('SELECT COALESCE(SUM(amount_minor),0) FROM accounting_movements WHERE reversal_of_id=? AND amount_minor<0',[$movementId]);
    $allocated=$sum('SELECT COALESCE(SUM(amount_minor),0) FROM accounting_allocations WHERE movement_id=?',[$movementId]);
    $reserved=$sum("SELECT COALESCE(SUM(d.amount_minor-COALESCE((SELECT SUM(p.amount_minor) FROM accounting_refund_payments p WHERE p.decision_id=d.id),0)),0) FROM accounting_refund_decisions d WHERE d.original_movement_id=? AND NOT EXISTS(SELECT 1 FROM accounting_adjustment_events e WHERE e.decision_id=d.id AND e.event_type='decision_cancelled')",[$movementId]);
    return ['movement_id'=>$movementId,'income_minor'=>(int)$movement['amount_minor'],'refunded_minor'=>$refunded,'allocated_minor'=>$allocated,'reserved_minor'=>$reserved,
        'remaining_cash_minor'=>(int)$movement['amount_minor']-$refunded,'available_minor'=>max(0,(int)$movement['amount_minor']-$refunded-$allocated-$reserved),'currency'=>$movement['currency']];
}

/** Case-level view: correction reduces the claim; releasing allocation reduces settlement once. */
function accountingAdjustmentDocumentBalance(PDO $pdo,int $documentId):array
{
    $s=$pdo->prepare('SELECT * FROM accounting_documents WHERE id=?');$s->execute([$documentId]);$doc=$s->fetch(PDO::FETCH_ASSOC);
    if (!$doc || $doc['status']!=='issued' || (int)$doc['total_minor']<=0) throw new DomainException('Vyberte původní vydaný doklad.');
    $s=$pdo->prepare("SELECT COALESCE(SUM(total_minor),0) FROM accounting_documents WHERE original_document_id=? AND status='issued'");$s->execute([$documentId]);$corrections=(int)$s->fetchColumn();
    $caseType=$doc['source_type']==='order'?'shop_order':$doc['source_type'];
    $s=$pdo->prepare('SELECT COALESCE(SUM(a.amount_minor),0) FROM accounting_allocations a JOIN accounting_movements m ON m.id=a.movement_id WHERE a.case_type=? AND a.case_id=? AND m.currency=? AND m.amount_minor>0');$s->execute([$caseType,$doc['source_id'],$doc['currency']]);$allocated=(int)$s->fetchColumn();
    $principal=(int)$doc['total_minor']+$corrections;
    return ['document_id'=>$documentId,'original_minor'=>(int)$doc['total_minor'],'correction_minor'=>$corrections,'claim_minor'=>$principal,'allocated_minor'=>$allocated,'outstanding_minor'=>max(0,$principal-$allocated),'allocation_excess_minor'=>max(0,$allocated-$principal),'currency'=>$doc['currency']];
}

/** Deciding a refund does not pay money, change payment status, or touch stock. */
function accountingRefundDecide(PDO $pdo,array $input,int $actor,string $reason,bool $confirmed):array
{
    return accountingAdjustmentTransaction($pdo,static function()use($pdo,$input,$actor,$reason,$confirmed):array {
        $key=(string)($input['request_key']??'');accountingAdjustmentActor($pdo,$actor,$reason,$confirmed,$key);
        $movementId=(int)($input['original_movement_id']??0);$amount=$input['amount_minor']??null;
        $originalId=(int)($input['original_document_id']??0);$correctionId=(int)($input['correction_document_id']??0);$date=(string)($input['decided_on']??'');
        if (!is_int($amount) || $amount<=0 || $amount>100000000000) throw new DomainException('Částka vratky musí být kladný počet haléřů.');
        accountingAdjustmentDate($date);
        $hash=accountingAdjustmentHash([$movementId,$amount,$originalId,$correctionId,$date,trim($reason)]);
        accountingAdjustmentLockLegacyPayment($pdo,$movementId);
        $movement=accountingAdjustmentRow($pdo,'accounting_movements',$movementId);
        $existing=$pdo->prepare('SELECT * FROM accounting_refund_decisions WHERE request_key=?');$existing->execute([$key]);$old=$existing->fetch(PDO::FETCH_ASSOC);
        if ($old) {if (!hash_equals($old['input_hash'],$hash)) throw new DomainException('Identifikátor již patří jinému rozhodnutí.');return $old;}
        if ($date<$movement['effective_on']) throw new DomainException('Rozhodnutí nemůže předcházet příjmu.');
        if ($originalId) {
            $document=accountingAdjustmentRow($pdo,'accounting_documents',$originalId);
            if ($document['status']!=='issued' || (int)$document['total_minor']<=0 || $document['currency']!==$movement['currency']) throw new DomainException('Vyberte vydaný původní doklad ve stejné měně.');
            $caseType=$document['source_type']==='order'?'shop_order':$document['source_type'];
            $linked=($caseType===$movement['case_type'] && (int)$document['source_id']===(int)$movement['case_id']);
            if (!$linked) {$s=$pdo->prepare('SELECT COUNT(*) FROM accounting_allocations WHERE movement_id=? AND case_type=? AND case_id=? AND amount_minor>0');$s->execute([$movementId,$caseType,$document['source_id']]);$linked=(bool)$s->fetchColumn();}
            if (!$linked) throw new DomainException('Doklad není navázán na tento příjem.');
        }
        if ($correctionId) {
            if (!$originalId) throw new DomainException('Oprava vyžaduje původní doklad.');
            $correction=accountingAdjustmentRow($pdo,'accounting_documents',$correctionId);
            if ($correction['status']!=='issued' || (int)$correction['original_document_id']!==$originalId || (int)$correction['total_minor']>=0) throw new DomainException('Vyberte vydaný opravný doklad tohoto originálu.');
            $s=$pdo->prepare("SELECT COALESCE(SUM(amount_minor),0) FROM accounting_refund_decisions d WHERE correction_document_id=? AND NOT EXISTS(SELECT 1 FROM accounting_adjustment_events e WHERE e.decision_id=d.id AND e.event_type='decision_cancelled')");$s->execute([$correctionId]);
            if ((int)$s->fetchColumn()+$amount>abs((int)$correction['total_minor'])) throw new DomainException('Vratky překračují částku opravného dokladu.');
        }
        $balance=accountingAdjustmentBalance($pdo,$movementId);
        if ($amount>$balance['available_minor']) throw new DomainException('Částka překračuje volný příjem. Nejdříve doloženě uvolněte příslušný příděl; již rezervovanou vratku nelze rozhodnout podruhé.');
        $pdo->prepare('INSERT INTO accounting_refund_decisions(original_movement_id,original_document_id,correction_document_id,amount_minor,currency,decided_on,reason,actor_trainer_id,request_key,input_hash) VALUES(?,?,?,?,?,?,?,?,?,?)')
            ->execute([$movementId,$originalId?:null,$correctionId?:null,$amount,$movement['currency'],$date,trim($reason),$actor,$key,$hash]);
        return accountingAdjustmentRow($pdo,'accounting_refund_decisions',(int)$pdo->lastInsertId());
    });
}

function accountingRefundPay(PDO $pdo,int $decisionId,array $input,int $actor,string $reason,bool $confirmed):array
{
    require_once __DIR__.'/accounting_ledger.php';
    return accountingAdjustmentTransaction($pdo,static function()use($pdo,$decisionId,$input,$actor,$reason,$confirmed):array {
        $key=(string)($input['request_key']??'');accountingAdjustmentActor($pdo,$actor,$reason,$confirmed,$key);
        // A replay after the final installment must still succeed even though the canonical payment is now refunded.
        $s=$pdo->prepare('SELECT m.*,p.decision_id FROM accounting_movements m JOIN accounting_refund_payments p ON p.movement_id=m.id WHERE m.request_key=?');$s->execute([$key]);$replay=$s->fetch(PDO::FETCH_ASSOC);
        if ($replay) {
            if ((int)$replay['decision_id']!==$decisionId || !is_int($input['amount_minor']??null) || -(int)$replay['amount_minor']!==$input['amount_minor'] || $replay['effective_on']!==($input['effective_on']??'') || (int)$replay['account_id']!==(int)($input['account_id']??0) || $replay['source_reference']!==trim((string)($input['source_reference']??'')) || $replay['evidence']!==trim((string)($input['evidence']??'')) || $replay['reason']!==trim($reason)) throw new DomainException('Identifikátor již patří jiné výplatě.');
            return $replay;
        }
        $lookup=$pdo->prepare('SELECT original_movement_id FROM accounting_refund_decisions WHERE id=?');$lookup->execute([$decisionId]);$movementId=(int)$lookup->fetchColumn();
        accountingAdjustmentLockLegacyPayment($pdo,$movementId);
        $original=accountingAdjustmentRow($pdo,'accounting_movements',$movementId);
        $decision=accountingAdjustmentRow($pdo,'accounting_refund_decisions',$decisionId);
        $amount=$input['amount_minor']??null;$date=(string)($input['effective_on']??'');
        if (!is_int($amount) || $amount<=0) throw new DomainException('Uveďte skutečně vyplacenou částku.');
        accountingAdjustmentDate($date);
        if ($date<$decision['decided_on']) throw new DomainException('Výplata nemůže předcházet rozhodnutí.');
        $s=$pdo->prepare("SELECT COUNT(*) FROM accounting_adjustment_events WHERE decision_id=? AND event_type='decision_cancelled'");$s->execute([$decisionId]);
        if ($s->fetchColumn()) throw new DomainException('Zrušené rozhodnutí nelze vyplatit.');
        $s=$pdo->prepare('SELECT COALESCE(SUM(amount_minor),0) FROM accounting_refund_payments WHERE decision_id=?');$s->execute([$decisionId]);
        if ((int)$s->fetchColumn()+$amount>(int)$decision['amount_minor']) throw new DomainException('Výplata překračuje zbývající schválenou vratku.');
        $balance=accountingAdjustmentBalance($pdo,$movementId);
        if ($amount>$balance['remaining_cash_minor']-$balance['allocated_minor']) throw new DomainException('Příjem byl mezitím přidělen nebo vyplacen.');
        $movement=accountingLedgerRecord($pdo,[
            'account_id'=>(int)($input['account_id']??0),'kind'=>'refund','amount_minor'=>-$amount,'currency'=>$decision['currency'],
            'effective_on'=>$date,'source_context'=>'adjustment-refund','source_reference'=>(string)($input['source_reference']??''),
            'evidence'=>(string)($input['evidence']??''),'reversal_of_id'=>$movementId,
            'case_type'=>$original['case_type'],'case_id'=>$original['case_id'],'request_key'=>$key,'refund_decision_id'=>$decisionId,
        ],$actor,$reason,$confirmed);
        $s=$pdo->prepare('SELECT decision_id FROM accounting_refund_payments WHERE movement_id=?');$s->execute([$movement['id']]);$already=$s->fetchColumn();
        if ($already && (int)$already!==$decisionId) throw new DomainException('Pohyb již vypořádal jinou vratku.');
        if (!$already) $pdo->prepare('INSERT INTO accounting_refund_payments(decision_id,movement_id,amount_minor,effective_on) VALUES(?,?,?,?)')->execute([$decisionId,$movement['id'],$amount,$date]);
        accountingAdjustmentCompleteCanonicalRefund($pdo,$original,$actor,$reason,$decision);
        return $movement;
    });
}

/** Reconcile only an exact, fully repaid cancelled order; this adds no money and touches no stock. */
function accountingAdjustmentCompleteCanonicalRefund(PDO $pdo,array $original,int $actor,string $reason,?array $decision=null):void
{
    if (!clubFinanceTableExists($pdo,'shop_orders') || !clubFinanceTableExists($pdo,'payments')) return;
    $orderId=($original['case_type']??'')==='shop_order'?(int)$original['case_id']:0;
    if(!empty($decision['original_document_id'])){
        $s=$pdo->prepare("SELECT source_id FROM accounting_documents WHERE id=? AND source_type='order' AND status='issued'");$s->execute([$decision['original_document_id']]);
        $linked=$s->fetchColumn();if($linked!==false)$orderId=(int)$linked;
    }
    if($orderId<1)return;
    $s=$pdo->prepare("SELECT * FROM payments WHERE payable_type='shop_order' AND payable_id=?".accountingAdjustmentLock($pdo));$s->execute([$orderId]);$payment=$s->fetch(PDO::FETCH_ASSOC);
    if (!$payment || $payment['status']!=='refund_required' || $payment['currency']!==$original['currency']) return;
    $paid=accountingLedgerOrderRefundCoverage($pdo,$orderId,(string)$payment['currency']);
    if ($paid['total']!==(int)$payment['amount_minor']) return;
    require_once __DIR__.'/shop_checkout.php';
    $order=shopOrderAdminLockOrder($pdo,$orderId);
    if (!$order || $order['status']!=='cancelled' || $order['payment_status']!=='refund_required') return;
    $legacyReference='accounting-ledger:'.$original['id'];
    $reference=accountingLedgerRefundDerived($pdo,array_replace($payment,['refund_reference'=>$legacyReference]))?$legacyReference:'accounting-order:'.$orderId;
    shopOrderCompleteRefundInTransaction($pdo,$payment,$order,$actor,$reference,'Úplná vratka doložena účetními výplatami objednávky #'.$orderId.'. '.$reason,$paid['last_date'].' 00:00:00');
}

function accountingRefundCancel(PDO $pdo,int $decisionId,int $actor,string $reason,bool $confirmed,string $key):void
{
    accountingAdjustmentTransaction($pdo,static function()use($pdo,$decisionId,$actor,$reason,$confirmed,$key):void {
        accountingAdjustmentActor($pdo,$actor,$reason,$confirmed,$key);
        $s=$pdo->prepare('SELECT original_movement_id FROM accounting_refund_decisions WHERE id=?');$s->execute([$decisionId]);
        accountingAdjustmentRow($pdo,'accounting_movements',(int)$s->fetchColumn());
        accountingAdjustmentRow($pdo,'accounting_refund_decisions',$decisionId);
        $s=$pdo->prepare('SELECT COUNT(*) FROM accounting_refund_payments WHERE decision_id=?');$s->execute([$decisionId]);
        if ($s->fetchColumn()) throw new DomainException('Již vyplácenou vratku nelze zrušit.');
        $s=$pdo->prepare("SELECT id FROM accounting_adjustment_events WHERE decision_id=? AND event_type='decision_cancelled'");$s->execute([$decisionId]);
        if ($s->fetchColumn()) return;
        $pdo->prepare("INSERT INTO accounting_adjustment_events(decision_id,event_type,reason,actor_trainer_id,request_key,input_hash) VALUES(?,'decision_cancelled',?,?,?,?)")->execute([$decisionId,trim($reason),$actor,$key,accountingAdjustmentHash([$decisionId,trim($reason)])]);
    });
}

/** Physical evidence only: no available-stock adjustment and no automatic cash movement. */
function accountingReturnRecord(PDO $pdo,int $documentId,string $lineKey,int $quantity,string $state,int $actor,string $reason,bool $confirmed,string $key):int
{
    return accountingAdjustmentTransaction($pdo,static function()use($pdo,$documentId,$lineKey,$quantity,$state,$actor,$reason,$confirmed,$key):int {
        accountingAdjustmentActor($pdo,$actor,$reason,$confirmed,$key);
        if (!in_array($state,['awaiting_return','received_inspection','received_damaged','return_not_required'],true)) throw new DomainException('Neplatný stav fyzické vratky.');
        $doc=accountingAdjustmentRow($pdo,'accounting_documents',$documentId);
        if ($doc['status']!=='issued' || !(int)$doc['original_document_id']) throw new DomainException('Fyzickou vratku přiřaďte k vydanému opravnému dokladu.');
        $snapshot=json_decode($doc['snapshot_json'],true,512,JSON_THROW_ON_ERROR);$line=null;
        foreach ($snapshot['lines']??[] as $candidate) if (($candidate['line_key']??'')===$lineKey) $line=$candidate;
        if (!$line || $quantity<=0 || $quantity>abs((int)$line['quantity'])) throw new DomainException('Množství musí odpovídat položce opravného dokladu.');
        $hash=accountingAdjustmentHash([$documentId,$lineKey,$quantity,$state,trim($reason)]);
        $s=$pdo->prepare('SELECT id,input_hash FROM accounting_adjustment_events WHERE request_key=?');$s->execute([$key]);$existing=$s->fetch(PDO::FETCH_ASSOC);
        if ($existing) {if (!hash_equals($existing['input_hash'],$hash)) throw new DomainException('Požadavek již patří jinému fyzickému záznamu.');return (int)$existing['id'];}
        $pdo->prepare('INSERT INTO accounting_adjustment_events(document_id,event_type,line_key,quantity,reason,actor_trainer_id,request_key,input_hash) VALUES(?,?,?,?,?,?,?,?)')->execute([$documentId,$state,$lineKey,$quantity,trim($reason),$actor,$key,$hash]);
        return (int)$pdo->lastInsertId();
    });
}

/** Called inside legacy full-refund transaction after locking the same payment. */
function accountingAdjustmentAssertLegacyRefundAllowed(PDO $pdo,int $orderId):void
{
    if (!clubFinanceTableExists($pdo,'accounting_movements') || !clubFinanceTableExists($pdo,'accounting_refund_decisions')) return;
    $aliases=clubFinanceTableExists($pdo,'accounting_movement_sources')&&clubFinanceTableExists($pdo,'payments');
    $links=["(case_type='shop_order' AND case_id=?)"];$args=[$orderId];
    if($aliases){$links[]="id IN (SELECT ms.movement_id FROM accounting_movement_sources ms JOIN payments p ON p.id=ms.source_id WHERE ms.source_type='payments' AND p.payable_type='shop_order' AND p.payable_id=?)";$args[]=$orderId;}
    if(clubFinanceTableExists($pdo,'accounting_allocations')){$links[]="id IN (SELECT movement_id FROM accounting_allocations WHERE case_type='shop_order' AND case_id=? AND amount_minor>0)";$args[]=$orderId;}
    $s=$pdo->prepare('SELECT id FROM accounting_movements WHERE ('.implode(' OR ',$links).') AND amount_minor>0'.accountingAdjustmentLock($pdo));$s->execute($args);
    foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $b=accountingAdjustmentBalance($pdo,(int)$id);
        if ($b['refunded_minor']>0 || $b['reserved_minor']>0) throw new DomainException('Objednávka má rozpracovanou nebo vyplacenou účetní vratku. Dokončete její zbývající část v Opravách a vratkách; celou platbu nelze potvrdit znovu.');
    }
}
