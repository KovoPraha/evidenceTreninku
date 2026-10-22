<?php
declare(strict_types=1);

final class AccountingLedgerException extends RuntimeException {}

function accountingLedgerTable(PDO $pdo,string $table):bool
{
    if(!preg_match('/^[a-z_]+$/D',$table))return false;
    try{$pdo->query('SELECT 1 FROM '.$table.' LIMIT 0');return true;}catch(PDOException){return false;}
}
function accountingLedgerLock(PDO $pdo):string{return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';}
function accountingLedgerDate(string $value):string
{
    $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value,new DateTimeZone('Europe/Prague'));
    if(!$date||$date->format('Y-m-d')!==$value||$value>(new DateTimeImmutable('today',new DateTimeZone('Europe/Prague')))->format('Y-m-d'))throw new AccountingLedgerException('Datum musí být skutečné datum, nejvýše dnešní.');
    return $value;
}
function accountingLedgerAudit(int $actor,string $reason,bool $confirmed):string
{
    $reason=trim($reason);
    if($actor<1||!$confirmed||mb_strlen($reason)<10||mb_strlen($reason)>1000)throw new AccountingLedgerException('Zápis vyžaduje pracovníka, výslovné potvrzení a důvod (10–1000 znaků).');
    return $reason;
}
function accountingLedgerActor(PDO $pdo,int $actor):void
{
    if(!$pdo->inTransaction())throw new LogicException('Finanční oprávnění se ověřuje v transakci.');
    $q=$pdo->prepare("SELECT t.id FROM treneri t WHERE t.id=? AND t.aktivni=1 AND (EXISTS(SELECT 1 FROM staff_user_positions p WHERE p.trainer_id=t.id AND p.position_code='finance_manager') OR EXISTS(SELECT 1 FROM staff_superadmins s WHERE s.trainer_id=t.id))".accountingLedgerLock($pdo));$q->execute([$actor]);
    if(!$q->fetchColumn())throw new AccountingLedgerException('Úkon vyžaduje aktivní oprávnění Hospodář a platby.');
}
function accountingLedgerText(string $value,int $max=160):string
{
    $value=trim($value);if($value===''||mb_strlen($value)>$max||preg_match('/[\x00-\x1f]/',$value))throw new AccountingLedgerException('Chybí platný identifikátor nebo podklad.');return $value;
}
/** A single transaction guard serializes source aliases, allocations and account closings. */
function accountingLedgerTransaction(PDO $pdo,callable $operation):mixed
{
    $own=!$pdo->inTransaction();if($own)$pdo->beginTransaction();
    try{
        $pdo->exec('UPDATE accounting_ledger_mutex SET revision=revision+1 WHERE id=1');
        $result=$operation();if($own)$pdo->commit();return $result;
    }catch(Throwable $error){if($own&&$pdo->inTransaction())$pdo->rollBack();throw $error;}
}
function accountingLedgerCreateAccount(PDO $pdo,array $input,int $actor,string $reason,bool $confirmed):array
{
    $reason=accountingLedgerAudit($actor,$reason,$confirmed);
    $code=accountingLedgerText((string)($input['code']??''),60);$name=accountingLedgerText((string)($input['name']??''),160);
    $kind=(string)($input['kind']??'');$currency=(string)($input['currency']??'CZK');
    if(!in_array($kind,['cash','bank','provider'],true)||$currency!=='CZK'||!is_int($input['opening_minor']??null)||abs($input['opening_minor'])>1000000000000)throw new AccountingLedgerException('Účet vyžaduje druh, CZK a počáteční zůstatek v haléřích.');
    $date=accountingLedgerDate((string)($input['opened_on']??''));$evidence=accountingLedgerText((string)($input['evidence']??''),2000);
    return accountingLedgerTransaction($pdo,function()use($pdo,$input,$actor,$reason,$code,$name,$kind,$currency,$date,$evidence){
        accountingLedgerActor($pdo,$actor);
        $q=$pdo->prepare('SELECT * FROM accounting_accounts WHERE code=?');$q->execute([$code]);$existing=$q->fetch(PDO::FETCH_ASSOC);
        if($existing){if($existing['code']!==$code||$existing['name']!==$name||$existing['kind']!==$kind||$existing['currency']!==$currency||$existing['opened_on']!==$date||(int)$existing['opening_minor']!==$input['opening_minor']||$existing['evidence']!==$evidence)throw new AccountingLedgerException('Kód účtu již existuje s jiným počátečním stavem nebo podkladem.');return $existing;}
        $pdo->prepare('INSERT INTO accounting_accounts(code,name,kind,currency,opened_on,opening_minor,evidence,reason,actor_trainer_id) VALUES(?,?,?,?,?,?,?,?,?)')->execute([$code,$name,$kind,$currency,$date,$input['opening_minor'],$evidence,$reason,$actor]);
        return accountingLedgerAccount($pdo,(int)$pdo->lastInsertId());
    });
}
function accountingLedgerAccount(PDO $pdo,int $id):array
{
    $query=$pdo->prepare('SELECT * FROM accounting_accounts WHERE id=?');$query->execute([$id]);$account=$query->fetch(PDO::FETCH_ASSOC);
    if(!$account)throw new AccountingLedgerException('Účet nebo pokladna nebyly nalezeny.');return $account;
}
function accountingLedgerAssertOpen(PDO $pdo,int $accountId,string $date):void
{
    $account=accountingLedgerAccount($pdo,$accountId);
    if($date<(string)$account['opened_on'])throw new AccountingLedgerException('Pohyb předchází doloženému počátečnímu stavu.');
    $q=$pdo->prepare('SELECT MAX(as_of) FROM accounting_reconciliations WHERE account_id=? AND is_closed=1');$q->execute([$accountId]);$closed=$q->fetchColumn();
    if(is_string($closed)&&$date<=$closed)throw new AccountingLedgerException('Období účtu je uzavřené. Původní pohyby se nepřepisují.');
}
/** Signed integer minor units; no provider API or payment-status transition is invoked. */
function accountingLedgerRecord(PDO $pdo,array $input,int $actor,string $reason,bool $confirmed):array
{
    $reason=accountingLedgerAudit($actor,$reason,$confirmed);
    $amount=$input['amount_minor']??null;$kind=(string)($input['kind']??'');
    if(!is_int($amount)||$amount===0||abs($amount)>1000000000000||!in_array($kind,['income','expense','refund','transfer','fee','reversal'],true)||(($kind==='income')&&$amount<0)||(in_array($kind,['expense','refund','fee'],true)&&$amount>0))throw new AccountingLedgerException('Neplatný druh nebo částka pohybu v haléřích.');
    $clean=['account_id'=>(int)($input['account_id']??0),'kind'=>$kind,'amount_minor'=>$amount,'currency'=>(string)($input['currency']??'CZK'),'effective_on'=>accountingLedgerDate((string)($input['effective_on']??'')),
        'source_context'=>accountingLedgerText((string)($input['source_context']??''),80),'source_reference'=>accountingLedgerText((string)($input['source_reference']??''),160),
        'request_key'=>accountingLedgerText((string)($input['request_key']??''),100),'reversal_of_id'=>isset($input['reversal_of_id'])?(int)$input['reversal_of_id']:null,'refund_decision_id'=>isset($input['refund_decision_id'])?(int)$input['refund_decision_id']:null,
        'transfer_key'=>isset($input['transfer_key'])?accountingLedgerText((string)$input['transfer_key'],100):null,
        'case_type'=>isset($input['case_type'])?accountingLedgerText((string)$input['case_type'],40):null,'case_id'=>isset($input['case_id'])?(int)$input['case_id']:null,
        'evidence'=>accountingLedgerText((string)($input['evidence']??''),2000)];
    if(($clean['case_type']===null)!==($clean['case_id']===null)||($clean['case_id']!==null&&$clean['case_id']<1))throw new AccountingLedgerException('Neplatná vazba na případ.');
    if($kind==='transfer'&&$clean['transfer_key']===null)throw new AccountingLedgerException('Převod musí mít společnou identitu obou stran.');
    if($kind==='refund'&&$clean['reversal_of_id']===null)throw new AccountingLedgerException('Vratka musí odkazovat na původní příjem.');
    $fingerprint=hash('sha256',json_encode(isset($input['operation_fingerprint'])?[$clean,'operation_fingerprint'=>(string)$input['operation_fingerprint']]:$clean,JSON_THROW_ON_ERROR));
    return accountingLedgerTransaction($pdo,function()use($pdo,$clean,$actor,$reason,$fingerprint){
        accountingLedgerActor($pdo,$actor);
        $q=$pdo->prepare('SELECT * FROM accounting_movements WHERE request_key=? OR (source_context=? AND source_reference=?)');$q->execute([$clean['request_key'],$clean['source_context'],$clean['source_reference']]);$existing=$q->fetchAll(PDO::FETCH_ASSOC);
        if($existing){if(count($existing)!==1||!hash_equals($existing[0]['fingerprint'],$fingerprint))throw new AccountingLedgerException('Pohyb již existuje s jinými údaji; opakování nemůže přepsat evidenci.');return $existing[0];}
        $account=accountingLedgerAccount($pdo,$clean['account_id']);
        if($account['currency']!==$clean['currency'])throw new AccountingLedgerException('Měna pohybu neodpovídá účtu.');
        accountingLedgerAssertOpen($pdo,$clean['account_id'],$clean['effective_on']);
        if($clean['reversal_of_id']!==null){
            $q=$pdo->prepare('SELECT * FROM accounting_movements WHERE id=?');$q->execute([$clean['reversal_of_id']]);$original=$q->fetch(PDO::FETCH_ASSOC);
            if(!$original||$original['currency']!==$clean['currency']||((int)$original['amount_minor']>0)===($clean['amount_minor']>0)||$clean['effective_on']<$original['effective_on'])throw new AccountingLedgerException('Opravný pohyb neodpovídá původnímu pohybu.');
            $q=$pdo->prepare('SELECT COALESCE(SUM(ABS(amount_minor)),0) FROM accounting_movements WHERE reversal_of_id=?');$q->execute([$clean['reversal_of_id']]);
            if((int)$q->fetchColumn()+abs($clean['amount_minor'])>abs((int)$original['amount_minor']))throw new AccountingLedgerException('Součet vratek nebo oprav přesahuje původní peněžní pohyb.');
            // Every correction shares the allocation guard. Calling it a reversal,
            // income or expense must not consume money already assigned to a case.
            $q=$pdo->prepare('SELECT COALESCE(SUM(amount_minor),0) FROM accounting_allocations WHERE movement_id=?');$q->execute([$original['id']]);$allocated=(int)$q->fetchColumn();
            $q=$pdo->prepare('SELECT COALESCE(SUM(ABS(amount_minor)),0) FROM accounting_movements WHERE reversal_of_id=?');$q->execute([$original['id']]);$returned=(int)$q->fetchColumn();
            $reserved=accountingLedgerReservedMinor($pdo,(int)$original['id'],$clean['kind']==='refund'?$clean['refund_decision_id']:null);
            if($allocated+$returned+$reserved+abs($clean['amount_minor'])>abs((int)$original['amount_minor']))throw new AccountingLedgerException('Nejprve doložte uvolnění rozdělení; oprava nesmí spotřebovat přidělené nebo vyhrazené peníze.');
            if($clean['kind']==='refund'){
                $legacyPaymentId=null;
                if($clean['source_context']==='legacy-refund'){
                    if(!ctype_digit($clean['source_reference']))throw new AccountingLedgerException('Neplatná identita původní vratky.');
                    $legacyPaymentId=(int)$clean['source_reference'];$legacy=accountingLedgerSource($pdo,'legacy_refund',$legacyPaymentId);
                    if($legacy['amount_minor']!==$clean['amount_minor']||$legacy['currency']!==$clean['currency'])throw new AccountingLedgerException('Původní vratka neodpovídá částce nebo měně.');
                }
                if(accountingLedgerTable($pdo,'payments')){
                    $q=$pdo->prepare("SELECT p.* FROM payments p WHERE p.id IN (SELECT source_id FROM accounting_movement_sources WHERE movement_id=? AND source_type='payments') OR (p.payable_type=? AND p.payable_id=?)".accountingLedgerLock($pdo));$q->execute([$original['id'],$original['case_type'],$original['case_id']]);
                    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $payment)if(($payment['status']==='refunded'||!empty($payment['refund_sent_at']))&&(int)$payment['id']!==$legacyPaymentId)throw new AccountingLedgerException('Původní platba již má doloženou celou vratku.');
                }
                $q=$pdo->prepare('SELECT COALESCE(SUM(amount_minor),0) FROM accounting_allocations WHERE movement_id=?');$q->execute([$original['id']]);$allocated=(int)$q->fetchColumn();
                $q=$pdo->prepare('SELECT COALESCE(SUM(ABS(amount_minor)),0) FROM accounting_movements WHERE reversal_of_id=?');$q->execute([$original['id']]);
                $returned=(int)$q->fetchColumn();$reserved=accountingLedgerReservedMinor($pdo,(int)$original['id'],$clean['refund_decision_id']);
                if($allocated+$returned+$reserved+abs($clean['amount_minor'])>abs((int)$original['amount_minor']))throw new AccountingLedgerException('Nejprve doložte uvolnění rozdělení; vratka nesmí spotřebovat přidělené nebo vyhrazené peníze.');
                if($clean['refund_decision_id']!==null){
                    $q=$pdo->prepare("SELECT d.*,COALESCE((SELECT SUM(p.amount_minor) FROM accounting_refund_payments p WHERE p.decision_id=d.id),0) AS paid FROM accounting_refund_decisions d WHERE d.id=? AND NOT EXISTS (SELECT 1 FROM accounting_adjustment_events e WHERE e.decision_id=d.id AND e.event_type='decision_cancelled')");$q->execute([$clean['refund_decision_id']]);$decision=$q->fetch(PDO::FETCH_ASSOC);
                    if(!$decision||(int)$decision['original_movement_id']!==(int)$original['id']||(int)$decision['paid']+abs($clean['amount_minor'])>(int)$decision['amount_minor'])throw new AccountingLedgerException('Vratka překračuje platné rozhodnutí.');
                }
            }
        }
        $keys=array_keys($clean);$pdo->prepare('INSERT INTO accounting_movements('.implode(',',$keys).',fingerprint,reason,actor_trainer_id) VALUES('.implode(',',array_fill(0,count($keys)+3,'?')).')')->execute([...array_values($clean),$fingerprint,$reason,$actor]);
        $q=$pdo->prepare('SELECT * FROM accounting_movements WHERE id=?');$q->execute([(int)$pdo->lastInsertId()]);return $q->fetch(PDO::FETCH_ASSOC);
    });
}
function accountingLedgerBalance(PDO $pdo,int $accountId,string $asOf,?string $knownAt=null):array
{
    $asOf=accountingLedgerDate($asOf);$account=accountingLedgerAccount($pdo,$accountId);
    if($asOf<$account['opened_on'])throw new AccountingLedgerException('Před počátečním stavem není zůstatek znám.');
    if($knownAt!==null&&$account['recorded_at']>$knownAt)throw new AccountingLedgerException('K vybranému okamžiku ještě nebyl doložen počáteční stav účtu.');
    $sql='SELECT COALESCE(SUM(amount_minor),0) FROM accounting_movements WHERE account_id=? AND effective_on<=?';$params=[$accountId,$asOf];
    if($knownAt!==null){if(!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D',$knownAt))throw new AccountingLedgerException('Neplatný okamžik evidence.');$sql.=' AND recorded_at<=?';$params[]=$knownAt;}
    $q=$pdo->prepare($sql);$q->execute($params);$movements=(int)$q->fetchColumn();
    return ['account_id'=>$accountId,'currency'=>$account['currency'],'as_of'=>$asOf,'opening_minor'=>(int)$account['opening_minor'],'movements_minor'=>$movements,'closing_minor'=>(int)$account['opening_minor']+$movements];
}
function accountingLedgerReconcileAccount(PDO $pdo,int $accountId,string $asOf,int $observedMinor,string $evidence,bool $close,int $actor,string $reason,bool $confirmed):array
{
    $reason=accountingLedgerAudit($actor,$reason,$confirmed);$evidence=accountingLedgerText($evidence,2000);$asOf=accountingLedgerDate($asOf);
    return accountingLedgerTransaction($pdo,function()use($pdo,$accountId,$asOf,$observedMinor,$evidence,$close,$actor,$reason){
        accountingLedgerActor($pdo,$actor);
        accountingLedgerAssertOpen($pdo,$accountId,$asOf);$balance=accountingLedgerBalance($pdo,$accountId,$asOf);$difference=$observedMinor-$balance['closing_minor'];
        if($close&&$difference!==0)throw new AccountingLedgerException('Rozdíl hotovosti nebo výpisu brání uzavření.');
        $pdo->prepare('INSERT INTO accounting_reconciliations(account_id,as_of,ledger_minor,observed_minor,difference_minor,is_closed,evidence,reason,actor_trainer_id) VALUES(?,?,?,?,?,?,?,?,?)')->execute([$accountId,$asOf,$balance['closing_minor'],$observedMinor,$difference,$close?1:0,$evidence,$reason,$actor]);
        return ['id'=>(int)$pdo->lastInsertId(),'difference_minor'=>$difference,'is_closed'=>$close]+$balance;
    });
}
function accountingLedgerAllocate(PDO $pdo,int $movementId,string $caseType,int $caseId,int $amountMinor,string $requestKey,int $actor,string $reason,bool $confirmed,?int $reversesId=null,string $classificationCode='',string $activityCode='',?string $effectiveOn=null):array
{
    $reason=accountingLedgerAudit($actor,$reason,$confirmed);$requestKey=accountingLedgerText($requestKey,100);$caseType=accountingLedgerText($caseType,40);
    if($caseId<1||$amountMinor===0||abs($amountMinor)>1000000000000||mb_strlen($classificationCode)>60||mb_strlen($activityCode)>60)throw new AccountingLedgerException('Neplatné rozdělení pohybu.');
    $effectiveOn=accountingLedgerDate($effectiveOn??(new DateTimeImmutable('today',new DateTimeZone('Europe/Prague')))->format('Y-m-d'));
    $fingerprint=hash('sha256',json_encode([$movementId,$caseType,$caseId,$amountMinor,$reversesId,$classificationCode,$activityCode,$effectiveOn],JSON_THROW_ON_ERROR));
    return accountingLedgerTransaction($pdo,function()use($pdo,$movementId,$caseType,$caseId,$amountMinor,$requestKey,$actor,$reason,$reversesId,$classificationCode,$activityCode,$effectiveOn,$fingerprint){
        accountingLedgerActor($pdo,$actor);
        $q=$pdo->prepare('SELECT * FROM accounting_allocations WHERE request_key=?');$q->execute([$requestKey]);$prior=$q->fetch(PDO::FETCH_ASSOC);
        if($prior){if(!hash_equals($prior['fingerprint'],$fingerprint))throw new AccountingLedgerException('Klíč rozdělení již patří jinému požadavku.');return $prior;}
        $q=$pdo->prepare('SELECT * FROM accounting_movements WHERE id=?');$q->execute([$movementId]);$movement=$q->fetch(PDO::FETCH_ASSOC);
        if(!$movement||$movement['kind']==='transfer'||$effectiveOn<$movement['effective_on'])throw new AccountingLedgerException('Pohyb není dostupný pro toto rozdělení.');
        $table=match($caseType){'shop_order'=>'shop_orders','member_charge'=>'club_member_charges','shop_return'=>'shop_return_cases','expense_claim','expense_claim_return'=>'club_expense_claims','expense_advance_out','expense_advance_in'=>'club_expense_advances','reward_claim'=>'club_reward_claims',default=>null};
        if($table===null||!accountingLedgerTable($pdo,$table))throw new AccountingLedgerException('Rozdělení vyžaduje existující obchodní případ.');
        $q=$pdo->prepare('SELECT currency FROM '.$table.' WHERE id=?');$q->execute([$caseId]);$caseCurrency=$q->fetchColumn();
        if($caseCurrency===false||$caseCurrency!==$movement['currency'])throw new AccountingLedgerException('Případ nebyl nalezen nebo má jinou měnu.');
        if(in_array($caseType,['expense_claim','expense_claim_return','expense_advance_out','expense_advance_in'],true)){
            require_once __DIR__.'/club_expenses.php';
            clubExpenseValidateAllocation($pdo,$movement,$caseType,$caseId,$amountMinor,$reversesId);
        }elseif($caseType==='reward_claim'){
            require_once __DIR__.'/club_rewards.php';
            clubRewardValidateAllocation($pdo,$movement,$caseType,$caseId,$amountMinor,$reversesId);
        }
        if($caseType==='shop_return'){
            if($amountMinor>0&&($movement['kind']!=='income'||(int)$movement['amount_minor']<=0))throw new AccountingLedgerException('Doplatek výměny lze pokrýt pouze skutečným kladným příjmem.');
            $q=$pdo->prepare('SELECT state,exchange_delta_minor,exchange_reservation_id FROM shop_return_cases WHERE id=?');$q->execute([$caseId]);$exchange=$q->fetch(PDO::FETCH_ASSOC);
            if($amountMinor>0&&($exchange['state']==='cancelled'||empty($exchange['exchange_reservation_id'])||(int)$exchange['exchange_delta_minor']<=0))throw new AccountingLedgerException('Výměna nemá platný doplatek.');
            $q=$pdo->prepare("SELECT COALESCE(SUM(amount_minor),0) FROM accounting_allocations WHERE case_type='shop_return' AND case_id=?");$q->execute([$caseId]);
            if($amountMinor>0&&(int)$q->fetchColumn()+$amountMinor>(int)$exchange['exchange_delta_minor'])throw new AccountingLedgerException('Příděly přesahují dohodnutý doplatek výměny.');
        }
        accountingLedgerAssertOpen($pdo,(int)$movement['account_id'],$effectiveOn);
        if($amountMinor<0){
            $q=$pdo->prepare('SELECT * FROM accounting_allocations WHERE id=?');$q->execute([$reversesId]);$original=$q->fetch(PDO::FETCH_ASSOC);
            if(!$original||(int)$original['movement_id']!==$movementId||$original['case_type']!==$caseType||(int)$original['case_id']!==$caseId||(int)$original['amount_minor']<1||$original['classification_code']!==$classificationCode||$original['activity_code']!==$activityCode||$effectiveOn<$original['effective_on'])throw new AccountingLedgerException('Uvolnění musí odkazovat na původní kladné rozdělení a nesmí mu předcházet.');
            $q=$pdo->prepare('SELECT COALESCE(SUM(amount_minor),0) FROM accounting_allocations WHERE reverses_id=?');$q->execute([$reversesId]);
            if((int)$original['amount_minor']+(int)$q->fetchColumn()+$amountMinor<0)throw new AccountingLedgerException('Nelze uvolnit více než původní rozdělení.');
        }elseif($reversesId!==null)throw new AccountingLedgerException('Kladné rozdělení nesmí být uvolněním.');
        $q=$pdo->prepare('SELECT COALESCE(SUM(amount_minor),0) FROM accounting_allocations WHERE movement_id=?');$q->execute([$movementId]);
        $allocated=(int)$q->fetchColumn();$q=$pdo->prepare('SELECT COALESCE(SUM(ABS(amount_minor)),0) FROM accounting_movements WHERE reversal_of_id=?');$q->execute([$movementId]);$returned=(int)$q->fetchColumn();
        if($allocated+$amountMinor+$returned+accountingLedgerReservedMinor($pdo,$movementId)>abs((int)$movement['amount_minor']))throw new AccountingLedgerException('Součet rozdělení přesahuje dostupný peněžní pohyb po vratkách a vyhrazení.');
        $pdo->prepare('INSERT INTO accounting_allocations(movement_id,case_type,case_id,amount_minor,classification_code,activity_code,effective_on,request_key,fingerprint,reverses_id,reason,actor_trainer_id) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$movementId,$caseType,$caseId,$amountMinor,$classificationCode,$activityCode,$effectiveOn,$requestKey,$fingerprint,$reversesId,$reason,$actor]);
        $q=$pdo->prepare('SELECT * FROM accounting_allocations WHERE id=?');$q->execute([(int)$pdo->lastInsertId()]);return $q->fetch(PDO::FETCH_ASSOC);
    });
}
/** Settlement = a linked internal transfer plus a separate fee; never another sales receipt. */
function accountingLedgerTransfer(PDO $pdo,int $from,int $to,int $netMinor,int $feeMinor,string $date,string $reference,string $evidence,int $actor,string $reason,bool $confirmed,?string $receivedOn=null):array
{
    accountingLedgerAudit($actor,$reason,$confirmed);$reference=accountingLedgerText($reference,80);$date=accountingLedgerDate($date);$receivedOn=accountingLedgerDate($receivedOn??$date);
    if($from===$to||$netMinor<1||$feeMinor<0)throw new AccountingLedgerException('Převod vyžaduje dva různé účty a kladnou převáděnou částku.');
    if($receivedOn<$date)throw new AccountingLedgerException('Připsání převodu nesmí předcházet jeho odeslání.');
    return accountingLedgerTransaction($pdo,function()use($pdo,$from,$to,$netMinor,$feeMinor,$date,$reference,$evidence,$actor,$reason,$confirmed,$receivedOn){
        $operationFingerprint=hash('sha256',json_encode([$from,$to,$netMinor,$feeMinor,$date,$receivedOn,$reference,$evidence],JSON_THROW_ON_ERROR));
        $common=['kind'=>'transfer','currency'=>'CZK','effective_on'=>$date,'source_context'=>'internal-transfer','transfer_key'=>$reference,'evidence'=>$evidence,'operation_fingerprint'=>$operationFingerprint];
        $out=accountingLedgerRecord($pdo,$common+['account_id'=>$from,'amount_minor'=>-$netMinor,'source_reference'=>$reference.':out','request_key'=>'transfer:'.$reference.':out'],$actor,$reason,$confirmed);
        $in=accountingLedgerRecord($pdo,array_replace($common,['effective_on'=>$receivedOn])+['account_id'=>$to,'amount_minor'=>$netMinor,'source_reference'=>$reference.':in','request_key'=>'transfer:'.$reference.':in'],$actor,$reason,$confirmed);
        $fee=null;if($feeMinor>0)$fee=accountingLedgerRecord($pdo,['account_id'=>$from,'amount_minor'=>-$feeMinor,'kind'=>'fee','currency'=>'CZK','effective_on'=>$date,'source_context'=>'settlement-fee','source_reference'=>$reference,'request_key'=>'fee:'.$reference,'transfer_key'=>$reference,'evidence'=>$evidence],$actor,$reason,$confirmed);
        return ['out'=>$out,'in'=>$in,'fee'=>$fee];
    });
}

function accountingLedgerReservedMinor(PDO $pdo,int $movementId,?int $excludeDecision=null):int
{
    if(!accountingLedgerTable($pdo,'accounting_refund_decisions'))return 0;
    $sql="SELECT COALESCE(SUM(d.amount_minor-COALESCE((SELECT SUM(p.amount_minor) FROM accounting_refund_payments p WHERE p.decision_id=d.id),0)),0) FROM accounting_refund_decisions d WHERE d.original_movement_id=? AND NOT EXISTS (SELECT 1 FROM accounting_adjustment_events e WHERE e.decision_id=d.id AND e.event_type='decision_cancelled')";$params=[$movementId];
    if($excludeDecision!==null){$sql.=' AND d.id<>?';$params[]=$excludeDecision;}$q=$pdo->prepare($sql);$q->execute($params);return (int)$q->fetchColumn();
}

function accountingLedgerPaymentDerived(PDO $pdo,array $payment):bool
{
    $type=$payment['payable_type']??'';$id=(int)($payment['payable_id']??0);
    $tables=$type==='shop_order'?['club_finance_allocations'=>'order_id']:($type==='member_charge'?['club_finance_allocations'=>'charge_id','member_payer_receipt_allocations'=>'charge_id']:[]);
    foreach($tables as $table=>$column)if(accountingLedgerTable($pdo,$table)){
        $q=$pdo->prepare('SELECT COUNT(*) FROM '.$table.' WHERE '.$column.'=?');$q->execute([$id]);if((int)$q->fetchColumn()>0)return true;
    }
    return false;
}
/** Current source evidence, never a guessed receipt based on a derived paid state. */
function accountingLedgerSource(PDO $pdo,string $sourceType,int $sourceId):array
{
    if(!in_array($sourceType,['payments','club_finance_receipts','member_payer_receipts','fio_account_movements','legacy_refund'],true)||$sourceId<1)throw new AccountingLedgerException('Neznámý zdroj peněžního pohybu.');
    $table=$sourceType==='legacy_refund'?'payments':$sourceType;
    $q=$pdo->prepare('SELECT * FROM '.$table.' WHERE id=?');$q->execute([$sourceId]);$row=$q->fetch(PDO::FETCH_ASSOC);
    if(!$row)throw new AccountingLedgerException('Zdrojový záznam nebyl nalezen.');
    $result=['source_type'=>$sourceType,'source_id'=>$sourceId,'amount_minor'=>(int)$row['amount_minor'],'currency'=>(string)$row['currency'],'effective_on'=>null,'source_context'=>'','source_reference'=>'','case_type'=>null,'case_id'=>null,'account_kind'=>null,'aliases'=>[['source_type'=>$sourceType,'source_id'=>$sourceId]],'flags'=>['unmapped_account']];
    if($sourceType==='payments'||$sourceType==='legacy_refund'){
        if(!in_array($row['status'],['paid','refund_required','refunded'],true))throw new AccountingLedgerException('Platební požadavek není důkazem přijatých peněz.');
        if($sourceType==='legacy_refund'){
            if($row['status']!=='refunded'||empty($row['refund_sent_at']))throw new AccountingLedgerException('Původní odeslání vratky není doloženo.');
            if(accountingLedgerRefundDerived($pdo,$row))throw new AccountingLedgerException('Tato vratka je odvozené dokončení již evidovaných účetních výplat.');
            $result['amount_minor']=-$result['amount_minor'];$result['source_context']='legacy-refund';$result['source_reference']=(string)$sourceId;
        }else{
            if(accountingLedgerPaymentDerived($pdo,$row))throw new AccountingLedgerException('Odvozená úhrada je rozdělení původních příjmů, nikoli další peníze.');
            $result['account_kind']=match($row['payment_source']??$row['method']??''){'cash'=>'cash','bank_transfer'=>'bank','stripe','sumup'=>'provider',default=>null};
            $result['source_context']=empty($row['bank_movement_id'])?'payment':'bank-reference';
            $result['source_reference']=empty($row['bank_movement_id'])?(string)$sourceId:(string)$row['bank_movement_id'];
        }
        $result['case_type']=(string)$row['payable_type'];$result['case_id']=(int)$row['payable_id'];
    }elseif($sourceType==='fio_account_movements'){
        $result['source_context']='bank-reference';$result['source_reference']=(string)$row['fio_movement_id'];$result['effective_on']=(string)$row['booked_on'];
    }else{
        $result['source_context']='bank-reference';$result['source_reference']=(string)$row['reference'];$result['effective_on']=(string)$row['paid_on'];
    }
    if($result['source_context']==='bank-reference'){
        $result['account_kind']='bank';
        foreach(['fio_account_movements'=>'fio_movement_id','club_finance_receipts'=>'reference','member_payer_receipts'=>'reference','payments'=>'bank_movement_id'] as $aliasTable=>$column){
            if(!accountingLedgerTable($pdo,$aliasTable))continue;
            try{$q=$pdo->prepare('SELECT * FROM '.$aliasTable.' WHERE '.$column.'=?');$q->execute([$result['source_reference']]);}catch(PDOException $e){if($aliasTable==='payments'&&str_contains($e->getMessage(),'column'))continue;throw $e;}
            foreach($q->fetchAll(PDO::FETCH_ASSOC) as $alias){
                if($aliasTable==='payments'&&(!in_array($alias['status'],['paid','refund_required','refunded'],true)||accountingLedgerPaymentDerived($pdo,$alias)))continue;
                $date=$aliasTable==='fio_account_movements'?$alias['booked_on']:($alias['paid_on']??null);
                if((int)$alias['amount_minor']!==$result['amount_minor']||(string)$alias['currency']!==$result['currency']||($date!==null&&$result['effective_on']!==null&&$date!==$result['effective_on']))throw new AccountingLedgerException('Zdrojová reference má rozporné částky, měnu nebo data.');
                if($date!==null)$result['effective_on']=$date;
                if($aliasTable==='payments'){$result['case_type']=$alias['payable_type'];$result['case_id']=(int)$alias['payable_id'];}
                $result['aliases'][]=['source_type'=>$aliasTable,'source_id'=>(int)$alias['id']];
            }
        }
    }
    $unique=[];foreach($result['aliases'] as $alias)$unique[$alias['source_type'].':'.$alias['source_id']]=$alias;$result['aliases']=array_values($unique);
    $result['canonical_key']=$result['source_context'].':'.$result['source_reference'];
    if($result['effective_on']===null)$result['flags'][]='unknown_date';
    return $result;
}
function accountingLedgerReconcileSource(PDO $pdo,string $sourceType,int $sourceId,int $accountId,string $effectiveOn,string $evidence,int $actor,string $reason,bool $confirmed,?string $allocationEffectiveOn=null,?int $originalMovementId=null):array
{
    accountingLedgerAudit($actor,$reason,$confirmed);$effectiveOn=accountingLedgerDate($effectiveOn);$evidence=accountingLedgerText($evidence,2000);
    return accountingLedgerTransaction($pdo,function()use($pdo,$sourceType,$sourceId,$accountId,$effectiveOn,$evidence,$actor,$reason,$confirmed,$allocationEffectiveOn,$originalMovementId){
        accountingLedgerActor($pdo,$actor);
        $source=accountingLedgerSource($pdo,$sourceType,$sourceId);
        $account=accountingLedgerAccount($pdo,$accountId);if($source['account_kind']!==null&&$account['kind']!==$source['account_kind'])throw new AccountingLedgerException('Druh cílového účtu neodpovídá doloženému platebnímu kanálu.');
        if($source['effective_on']!==null&&$source['effective_on']!==$effectiveOn)throw new AccountingLedgerException('Datum musí souhlasit s doloženým datem zdroje.');
        $prior=null;foreach($source['aliases'] as $alias){$q=$pdo->prepare('SELECT m.* FROM accounting_movement_sources s JOIN accounting_movements m ON m.id=s.movement_id WHERE s.source_type=? AND s.source_id=?');$q->execute([$alias['source_type'],$alias['source_id']]);$found=$q->fetch(PDO::FETCH_ASSOC);if($found){if($prior!==null&&(int)$prior['id']!==(int)$found['id'])throw new AccountingLedgerException('Zdroj již patří více pohybům.');$prior=$found;}}
        if($prior===null){
            $input=['account_id'=>$accountId,'kind'=>$source['amount_minor']>0?'income':($sourceType==='legacy_refund'?'refund':'expense'),'amount_minor'=>$source['amount_minor'],'currency'=>$source['currency'],'effective_on'=>$effectiveOn,'source_context'=>$source['source_context'],'source_reference'=>$source['source_reference'],'request_key'=>'source:'.hash('sha256',$source['canonical_key']),'evidence'=>$evidence];
            if($source['case_type']!==null){$input['case_type']=$source['case_type'];$input['case_id']=$source['case_id'];}
            if($sourceType==='legacy_refund')$input['reversal_of_id']=accountingLedgerPrepareLegacyRefund($pdo,$source,$originalMovementId,$effectiveOn,$actor,$reason,$confirmed);
            $prior=accountingLedgerRecord($pdo,$input,$actor,$reason,$confirmed);
        }
        if((int)$prior['account_id']!==$accountId||$prior['effective_on']!==$effectiveOn||(int)$prior['amount_minor']!==$source['amount_minor']||$prior['currency']!==$source['currency'])throw new AccountingLedgerException('Zdroj je již přiřazen jinému pohybu nebo účtu.');
        $source['linked_by']=$actor;$source['link_reason']=$reason;accountingLedgerAttachSource($pdo,(int)$prior['id'],$source);
        foreach(accountingLedgerSourceAllocations($pdo,$source) as $allocation){
            $check=$pdo->prepare('SELECT * FROM accounting_allocations WHERE request_key=?');$check->execute([$allocation['request_key']]);$existing=$check->fetch(PDO::FETCH_ASSOC);
            if($existing){if((int)$existing['movement_id']!==(int)$prior['id']||(int)$existing['amount_minor']!==$allocation['amount_minor']||$existing['case_type']!==$allocation['case_type']||(int)$existing['case_id']!==$allocation['case_id'])throw new AccountingLedgerException('Zdrojové rozdělení se změnilo; vyžaduje doloženou opravu, nikoli přepis.');continue;}
            $date=$allocation['effective_on']??$allocationEffectiveOn;
            if($date===null)throw new AccountingLedgerException('Datum původního rozdělení není známé. Doložte je samostatně; účetní datum se neodhaduje.');
            accountingLedgerAllocate($pdo,(int)$prior['id'],$allocation['case_type'],$allocation['case_id'],$allocation['amount_minor'],$allocation['request_key'],$actor,$reason,$confirmed,null,'','',$date);
        }
        return $prior;
    });
}

/** Reconcile old actual refund only against proven original money; release that case, not siblings. */
function accountingLedgerPrepareLegacyRefund(PDO $pdo,array $source,?int $originalMovementId,string $effectiveOn,int $actor,string $reason,bool $confirmed):int
{
    if(!$pdo->inTransaction())throw new LogicException('Převzetí původní vratky vyžaduje transakci.');
    if($originalMovementId===null){$q=$pdo->prepare("SELECT movement_id FROM accounting_movement_sources WHERE source_type='payments' AND source_id=?");$q->execute([$source['source_id']]);$id=$q->fetchColumn();$originalMovementId=$id===false?null:(int)$id;}
    if($originalMovementId===null)throw new AccountingLedgerException('Nejprve doložte původní příjem vratky. U rodinné úhrady výslovně zvolte příjem se souvisejícím rozdělením.');
    $q=$pdo->prepare('SELECT * FROM accounting_movements WHERE id=?');$q->execute([$originalMovementId]);$original=$q->fetch(PDO::FETCH_ASSOC);
    if(!$original||(int)$original['amount_minor']<abs($source['amount_minor'])||$original['currency']!==$source['currency'])throw new AccountingLedgerException('Původní příjem neodpovídá celé historické vratce; rozdělte nejednoznačný podklad ručně.');
    $q=$pdo->prepare("SELECT COUNT(*) FROM accounting_movement_sources WHERE movement_id=? AND source_type='payments' AND source_id=?");$q->execute([$originalMovementId,$source['source_id']]);$linked=(int)$q->fetchColumn()>0;
    $q=$pdo->prepare('SELECT a.*,COALESCE((SELECT SUM(r.amount_minor) FROM accounting_allocations r WHERE r.reverses_id=a.id),0) AS released_minor FROM accounting_allocations a WHERE a.movement_id=? AND a.case_type=? AND a.case_id=? AND a.amount_minor>0 ORDER BY a.id');$q->execute([$originalMovementId,$source['case_type'],$source['case_id']]);$allocations=$q->fetchAll(PDO::FETCH_ASSOC);
    if(!$linked&&!$allocations)throw new AccountingLedgerException('Příjem není průkazně spojen s vraceným případem.');
    $remaining=abs($source['amount_minor']);
    foreach($allocations as $allocation){$release=min($remaining,(int)$allocation['amount_minor']+(int)$allocation['released_minor']);if($release<1)continue;
        accountingLedgerAllocate($pdo,$originalMovementId,$allocation['case_type'],(int)$allocation['case_id'],-$release,'legacy-refund-release:'.$source['source_id'].':'.$allocation['id'],$actor,$reason,$confirmed,(int)$allocation['id'],$allocation['classification_code'],$allocation['activity_code'],$effectiveOn);$remaining-=$release;
    }
    return $originalMovementId;
}
/** Attach an imported bank counterpart to an already documented transfer; does not add money. */
function accountingLedgerLinkSource(PDO $pdo,int $movementId,string $sourceType,int $sourceId,int $actor,string $reason,bool $confirmed):array
{
    accountingLedgerAudit($actor,$reason,$confirmed);
    return accountingLedgerTransaction($pdo,function()use($pdo,$movementId,$sourceType,$sourceId,$actor,$reason){
        accountingLedgerActor($pdo,$actor);
        $source=accountingLedgerSource($pdo,$sourceType,$sourceId);$q=$pdo->prepare('SELECT * FROM accounting_movements WHERE id=?');$q->execute([$movementId]);$movement=$q->fetch(PDO::FETCH_ASSOC);
        if(!$movement||(int)$movement['amount_minor']!==$source['amount_minor']||$movement['currency']!==$source['currency']||$source['effective_on']===null||$movement['effective_on']!==$source['effective_on'])throw new AccountingLedgerException('Přiřazení vyžaduje shodnou částku, měnu a doložené datum.');
        $account=accountingLedgerAccount($pdo,(int)$movement['account_id']);if($source['account_kind']!==null&&$account['kind']!==$source['account_kind'])throw new AccountingLedgerException('Druh účtu neodpovídá zdroji.');
        accountingLedgerAssertOpen($pdo,(int)$movement['account_id'],$movement['effective_on']);
        $source['linked_by']=$actor;$source['link_reason']=$reason;accountingLedgerAttachSource($pdo,$movementId,$source);return $movement;
    });
}
function accountingLedgerAttachSource(PDO $pdo,int $movementId,array $source):void
{
    if(!$pdo->inTransaction())throw new LogicException('Vazba zdroje vyžaduje transakci.');
    foreach($source['aliases'] as $alias){
        $q=$pdo->prepare('SELECT movement_id FROM accounting_movement_sources WHERE source_type=? AND source_id=?');$q->execute([$alias['source_type'],$alias['source_id']]);$existing=$q->fetchColumn();
        if($existing!==false){if((int)$existing!==$movementId)throw new AccountingLedgerException('Stejný zdroj nesmí být vykázán dvakrát.');continue;}
        $pdo->prepare('INSERT INTO accounting_movement_sources(movement_id,source_type,source_id,snapshot_json) VALUES(?,?,?,?)')->execute([$movementId,$alias['source_type'],$alias['source_id'],json_encode($source,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
    }
}
/** Read-only exceptions queue. No historical date, account or paid state is fabricated. */
function accountingLedgerPendingSources(PDO $pdo,int $limit=200):array
{
    $limit=max(1,min(10000,$limit));$result=[];$seen=[];
    foreach(['fio_account_movements','club_finance_receipts','member_payer_receipts','payments','legacy_refund'] as $type){
        $table=$type==='legacy_refund'?'payments':$type;if(!accountingLedgerTable($pdo,$table))continue;
        $where=$type==='payments'?" WHERE s.status IN ('paid','refund_required','refunded')":($type==='legacy_refund'?" WHERE s.status='refunded'":' WHERE 1=1');
        $q=$pdo->prepare('SELECT s.* FROM '.$table.' s'.$where.' AND NOT EXISTS (SELECT 1 FROM accounting_movement_sources a WHERE a.source_type=? AND a.source_id=s.id) ORDER BY s.id LIMIT '.$limit);$q->execute([$type]);
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){
            if($type==='payments'&&accountingLedgerPaymentDerived($pdo,$row))continue;
            if($type==='legacy_refund'&&accountingLedgerRefundDerived($pdo,$row))continue;
            try{$source=accountingLedgerSource($pdo,$type,(int)$row['id']);foreach(accountingLedgerSourceAllocations($pdo,$source) as $allocation)if($allocation['effective_on']===null){$source['flags'][]='unknown_allocation_date';break;}}
            catch(AccountingLedgerException $error){$source=['source_type'=>$type,'source_id'=>(int)$row['id'],'amount_minor'=>(int)$row['amount_minor'],'currency'=>$row['currency'],'effective_on'=>null,'canonical_key'=>$type.':'.$row['id'],'flags'=>['source_conflict'],'error'=>$error->getMessage()];}
            if(isset($seen[$source['canonical_key']]))continue;$seen[$source['canonical_key']]=true;$result[]=$source;
            if(count($result)>=$limit)return $result;
        }
    }
    // A receipt may be mapped before a later allocation. Surface that delta instead of silently
    // reporting its money as free credit. Reconciliation repeats only the missing source links.
    $q=$pdo->query('SELECT s.source_type,s.source_id,s.movement_id,m.amount_minor AS mapped_amount_minor,m.currency AS mapped_currency,m.effective_on AS mapped_effective_on FROM accounting_movement_sources s JOIN accounting_movements m ON m.id=s.movement_id ORDER BY s.id LIMIT '.($limit*5+1));
    $mappedRows=$q->fetchAll(PDO::FETCH_ASSOC);$scanTruncated=count($mappedRows)>$limit*5;if($scanTruncated)array_pop($mappedRows);
    foreach($mappedRows as $mapped){
        try{
            $source=accountingLedgerSource($pdo,$mapped['source_type'],(int)$mapped['source_id']);
            if((int)$mapped['mapped_amount_minor']!==$source['amount_minor']||$mapped['mapped_currency']!==$source['currency']||($source['effective_on']!==null&&$mapped['mapped_effective_on']!==$source['effective_on']))throw new AccountingLedgerException('Původní zdroj se po účetním přiřazení změnil; ověřte částku, měnu a skutečné datum.');
            if(isset($seen[$source['canonical_key']]))continue;
            foreach(accountingLedgerSourceAllocations($pdo,$source) as $allocation){
                $check=$pdo->prepare('SELECT amount_minor FROM accounting_allocations WHERE request_key=?');$check->execute([$allocation['request_key']]);$amount=$check->fetchColumn();
                if($amount===false||(int)$amount!==$allocation['amount_minor']){
                    $source['flags']=['allocation_sync_required'];if($allocation['effective_on']===null)$source['flags'][]='unknown_allocation_date';$source['movement_id']=(int)$mapped['movement_id'];$result[]=$source;$seen[$source['canonical_key']]=true;break;
                }
            }
        }catch(AccountingLedgerException $e){$result[]=['source_type'=>$mapped['source_type'],'source_id'=>(int)$mapped['source_id'],'amount_minor'=>0,'currency'=>'CZK','effective_on'=>null,'canonical_key'=>$mapped['source_type'].':'.$mapped['source_id'],'flags'=>['source_conflict'],'error'=>$e->getMessage()];}
        if(count($result)>=$limit)return $result;
    }
    if($scanTruncated)$result[]=['source_type'=>'coverage','source_id'=>0,'amount_minor'=>0,'currency'=>'CZK','effective_on'=>null,'canonical_key'=>'coverage:allocation-scan','flags'=>['source_scan_truncated'],'error'=>'Kontrola již přiřazených zdrojů přesáhla limit. Úplnost nelze potvrdit.'];
    return $result;
}

function accountingLedgerRefundDerived(PDO $pdo,array $payment):bool
{
    $reference=(string)($payment['refund_reference']??'');if(!str_starts_with($reference,'accounting-ledger:'))return false;
    $id=substr($reference,strlen('accounting-ledger:'));
    if(!ctype_digit($id)||!accountingLedgerTable($pdo,'accounting_refund_payments'))return false;
    $q=$pdo->prepare("SELECT COALESCE(SUM(p.amount_minor),0) FROM accounting_refund_payments p JOIN accounting_refund_decisions d ON d.id=p.decision_id JOIN accounting_movements m ON m.id=d.original_movement_id WHERE d.original_movement_id=? AND m.currency=? AND m.amount_minor=? AND ((m.case_type=? AND m.case_id=?) OR EXISTS(SELECT 1 FROM accounting_movement_sources s WHERE s.movement_id=m.id AND s.source_type='payments' AND s.source_id=?))");$q->execute([(int)$id,$payment['currency'],$payment['amount_minor'],$payment['payable_type'],$payment['payable_id'],$payment['id']]);
    return (int)$q->fetchColumn()===(int)$payment['amount_minor'];
}

/** Exact operational allocation rows are projected, never inferred from a child's paid state. */
function accountingLedgerSourceAllocations(PDO $pdo,array $source):array
{
    if($source['amount_minor']<1)return [];
    $allocations=[];
    foreach($source['aliases'] as $alias){
        $type=$alias['source_type'];$id=(int)$alias['source_id'];
        $table=match($type){'club_finance_receipts'=>'club_finance_allocations','member_payer_receipts'=>'member_payer_receipt_allocations',default=>null};
        if($table!==null&&accountingLedgerTable($pdo,$table)){
            $q=$pdo->prepare('SELECT * FROM '.$table.' WHERE receipt_id=?');$q->execute([$id]);
            foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){
                $caseType=!empty($row['charge_id'])?'member_charge':'shop_order';$caseId=(int)($row['charge_id']??$row['order_id']??0);$date=substr((string)($row['created_at']??''),0,10);
                $key='source-alloc:'.$table.':'.($row['id']??($id.'-'.$caseType.'-'.$caseId));
                $allocations[$key]=['case_type'=>$caseType,'case_id'=>$caseId,'amount_minor'=>(int)$row['amount_minor'],'request_key'=>$key,'effective_on'=>preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date)?$date:null];
            }
        }elseif($type==='payments'){
            $q=$pdo->prepare('SELECT * FROM payments WHERE id=?');$q->execute([$id]);$payment=$q->fetch(PDO::FETCH_ASSOC);
            if(!$payment||accountingLedgerPaymentDerived($pdo,$payment)||!in_array($payment['payable_type'],['shop_order','member_charge'],true))continue;
            $date=substr((string)($payment['paid_at']??''),0,10);$key='source-alloc:payments:'.$id;
            $allocations[$key]=['case_type'=>$payment['payable_type'],'case_id'=>(int)$payment['payable_id'],'amount_minor'=>(int)$payment['amount_minor'],'request_key'=>$key,'effective_on'=>preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date)?$date:null];
        }
    }
    // A direct payment and receipt carrying the same bank reference are duplicate source aliases;
    // prefer the actual receipt allocations, otherwise their paid state would allocate twice.
    $hasReceiptAllocations=(bool)array_filter(array_keys($allocations),static fn($key)=>!str_starts_with($key,'source-alloc:payments:'));
    if($hasReceiptAllocations)$allocations=array_filter($allocations,static fn($row)=>!str_starts_with($row['request_key'],'source-alloc:payments:'));
    return array_values($allocations);
}

/** Separate accounting classification from settlement of customer claims. Each version is complete. */
function accountingLedgerClassify(PDO $pdo,int $movementId,array $splits,string $requestKey,int $actor,string $reason,bool $confirmed):array
{
    $reason=accountingLedgerAudit($actor,$reason,$confirmed);$requestKey=accountingLedgerText($requestKey,100);
    if(!$splits||count($splits)>100)throw new AccountingLedgerException('Členění musí mít 1–100 řádků.');
    foreach($splits as $split)if(!is_array($split)||!is_int($split['rule_id']??null)||!is_int($split['amount_minor']??null)||$split['rule_id']<1||$split['amount_minor']<1)throw new AccountingLedgerException('Členění vyžaduje schválené pravidlo a kladné haléře.');
    $fingerprint=hash('sha256',json_encode([$movementId,$splits],JSON_THROW_ON_ERROR));
    return accountingLedgerTransaction($pdo,function()use($pdo,$movementId,$splits,$requestKey,$actor,$reason,$fingerprint){
        accountingLedgerActor($pdo,$actor);
        $q=$pdo->prepare('SELECT * FROM accounting_classifications WHERE request_key=?');$q->execute([$requestKey]);$prior=$q->fetch(PDO::FETCH_ASSOC);
        if($prior){if(!hash_equals($prior['fingerprint'],$fingerprint))throw new AccountingLedgerException('Požadavek členění má odlišný obsah.');return $prior;}
        $q=$pdo->prepare('SELECT * FROM accounting_movements WHERE id=?');$q->execute([$movementId]);$movement=$q->fetch(PDO::FETCH_ASSOC);
        if(!$movement||$movement['kind']==='transfer')throw new AccountingLedgerException('Vnitřní převod není příjmem ani výdajem pro členění.');
        accountingLedgerAssertOpen($pdo,(int)$movement['account_id'],$movement['effective_on']);
        $snapshot=[];$sum=0;
        foreach($splits as $split){
            $q=$pdo->prepare('SELECT * FROM accounting_rules WHERE id=?');$q->execute([$split['rule_id']]);$rule=$q->fetch(PDO::FETCH_ASSOC);
            if(!$rule||$rule['valid_from']>$movement['effective_on']||(int)$rule['approved_by']<1||trim($rule['approval_evidence'])==='')throw new AccountingLedgerException('Pravidlo účetního členění není schválené pro datum pohybu.');
            $snapshot[]=['rule_id'=>$split['rule_id'],'amount_minor'=>$split['amount_minor'],'category_code'=>$rule['category_code'],'activity'=>$rule['activity'],'title'=>$rule['title'],'approval_evidence'=>$rule['approval_evidence']];$sum+=$split['amount_minor'];
        }
        if($sum!==abs((int)$movement['amount_minor']))throw new AccountingLedgerException('Součet účetního členění se musí rovnat pohybu na haléř.');
        $q=$pdo->prepare('SELECT id FROM accounting_classifications WHERE movement_id=? ORDER BY id DESC LIMIT 1');$q->execute([$movementId]);$replaces=$q->fetchColumn();
        $pdo->prepare('INSERT INTO accounting_classifications(movement_id,splits_json,fingerprint,request_key,replaces_id,actor_trainer_id,reason) VALUES(?,?,?,?,?,?,?)')->execute([$movementId,json_encode($snapshot,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$fingerprint,$requestKey,$replaces===false?null:(int)$replaces,$actor,$reason]);
        $q=$pdo->prepare('SELECT * FROM accounting_classifications WHERE id=?');$q->execute([(int)$pdo->lastInsertId()]);return $q->fetch(PDO::FETCH_ASSOC);
    });
}
