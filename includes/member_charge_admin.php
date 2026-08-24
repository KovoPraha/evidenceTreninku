<?php
declare(strict_types=1);

require_once __DIR__ . '/shop_checkout.php';
require_once __DIR__ . '/shop_bank_settings.php';

final class MemberChargeAdminException extends RuntimeException {}

function memberChargeAdminReason(string $reason): string
{
    $reason=trim((string)preg_replace('/\s+/u',' ',$reason));$length=mb_strlen($reason,'UTF-8');
    if($length<5||$length>1000)throw new InvalidArgumentException('Důvod musí mít 5 až 1000 znaků.');return$reason;
}

function memberChargeAdminDate(string $value,bool $required=false):?string
{
    $value=trim($value);if($value===''&&!$required)return null;$date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);if(!$date||$date->format('Y-m-d')!==$value)throw new InvalidArgumentException('Datum musí mít formát RRRR-MM-DD.');return$value;
}

/** @return array<string,mixed> */
function memberChargeAdminValues(array $input):array
{
    $sportovecId=(int)($input['sportovec_id']??0);$payer=trim((string)($input['payer_account_id']??''));$payerId=$payer===''?null:(int)$payer;
    $title=trim((string)($input['title_snapshot']??''));$amount=(int)($input['amount_minor']??0);$currency=strtoupper(trim((string)($input['currency']??'CZK')));
    $from=memberChargeAdminDate((string)($input['period_from']??''));$to=memberChargeAdminDate((string)($input['period_to']??''));$due=memberChargeAdminDate((string)($input['due_on']??''),true);
    if($sportovecId<1||($payerId!==null&&$payerId<1))throw new InvalidArgumentException('Vyberte sportovce a platný účet plátce.');
    if($title===''||mb_strlen($title,'UTF-8')>255||preg_match('/[<>]/u',$title)===1)throw new InvalidArgumentException('Název předpisu musí být platný prostý text.');
    if($amount<1||$amount>100000000||$currency!=='CZK')throw new InvalidArgumentException('Částka musí být kladná a v CZK.');
    if($from!==null&&$to!==null&&$from>$to)throw new InvalidArgumentException('Konec období nesmí být před začátkem.');
    return['sportovec_id'=>$sportovecId,'payer_account_id'=>$payerId,'title_snapshot'=>$title,'period_from'=>$from,'period_to'=>$to,'amount_minor'=>$amount,'currency'=>$currency,'due_on'=>$due];
}

function memberChargeAdminPublicCode():string{return'MC-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(4)));}
function memberChargeAdminVariableSymbol(string $seed,int $attempt=0):string{$number=hexdec(substr(hash('sha256',$seed.':'.$attempt),0,8))%1000000000;return'9'.str_pad((string)$number,9,'0',STR_PAD_LEFT);}

/** @param array<string,mixed> $settings @return array{id:int,variable_symbol:string} */
function memberChargeAdminInsertPayment(PDO$pdo,int$chargeId,array$values,array$settings,string$seed,int$actorId,?string$paidAt=null,string$note=''):array
{
    for($attempt=0;$attempt<20;$attempt++){$vs=memberChargeAdminVariableSymbol($seed,$attempt);$check=$pdo->prepare('SELECT 1 FROM payments WHERE variable_symbol=?');$check->execute([$vs]);if(!$check->fetchColumn())break;if($attempt===19)throw new MemberChargeAdminException('Nepodařilo se přidělit volný variabilní symbol.');}
    $status=$paidAt===null?'pending':'paid';$message='Členský předpis '.$chargeId;$spd=shopPaymentSpdPayload((string)$settings['iban'],(int)$values['amount_minor'],(string)$values['currency'],$vs,$message);
    $pdo->prepare('INSERT INTO payments(payable_type,payable_id,method,status,amount_minor,currency,variable_symbol,iban_snapshot,bic_snapshot,account_label_snapshot,spd_payload,due_at,paid_at,confirmed_by_trainer_id,confirmation_note) VALUES (\'member_charge\',?,\'bank_transfer\',?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$chargeId,$status,$values['amount_minor'],$values['currency'],$vs,$settings['iban'],$settings['bic']!==''?$settings['bic']:null,$settings['account_label'],$spd,$values['due_on'].' 23:59:59',$paidAt,$paidAt===null?null:$actorId,$paidAt===null?null:$note]);
    return['id'=>(int)$pdo->lastInsertId(),'variable_symbol'=>$vs];
}

function memberChargeAdminEvent(PDO$pdo,int$chargeId,string$action,?string$from,string$to,int$actorId,string$reason,array$snapshot):void
{
    $pdo->prepare('INSERT INTO club_member_charge_events(charge_id,action,from_status,to_status,actor_type,actor_id,reason,snapshot_json) VALUES (?,?,?,?,\'trainer\',?,?,?)')->execute([$chargeId,$action,$from,$to,$actorId,$reason,json_encode($snapshot,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
}

/** @return array{id:int,public_code:string,variable_symbol:string} */
function memberChargeAdminCreate(PDO$pdo,int$actorId,array$input,string$reason,bool$confirmed):array
{
    if($actorId<1||!$confirmed)throw new InvalidArgumentException('Založení předpisu vyžaduje správce a potvrzení.');$reason=memberChargeAdminReason($reason);$values=memberChargeAdminValues($input);$settings=shopBankSettingsEffective($pdo);
    $pdo->beginTransaction();try{$person=$pdo->prepare('SELECT 1 FROM sportovci WHERE id=?');$person->execute([$values['sportovec_id']]);if(!$person->fetchColumn())throw new MemberChargeAdminException('Sportovec nebyl nalezen.');if($values['payer_account_id']!==null){$payer=$pdo->prepare('SELECT 1 FROM verejni_uzivatele WHERE id=? AND aktivni=1');$payer->execute([$values['payer_account_id']]);if(!$payer->fetchColumn())throw new MemberChargeAdminException('Aktivní účet plátce nebyl nalezen.');}
        do{$code=memberChargeAdminPublicCode();$check=$pdo->prepare('SELECT 1 FROM club_member_charges WHERE public_code=?');$check->execute([$code]);}while($check->fetchColumn());
        $pdo->prepare("INSERT INTO club_member_charges(sportovec_id,payer_account_id,public_code,charge_type,title_snapshot,period_from,period_to,amount_minor,currency,due_on,status,source_system,source_external_id,source_import_run_id) VALUES (?,?,?,'membership',?,?,?,?,?,?,'pending','manual',?,NULL)")->execute([$values['sportovec_id'],$values['payer_account_id'],$code,$values['title_snapshot'],$values['period_from'],$values['period_to'],$values['amount_minor'],$values['currency'],$values['due_on'],$code]);$id=(int)$pdo->lastInsertId();$payment=memberChargeAdminInsertPayment($pdo,$id,$values,$settings,$code,$actorId);memberChargeAdminEvent($pdo,$id,'manual_create',null,'pending',$actorId,$reason,$values+['public_code'=>$code,'payment_id'=>$payment['id']]);$pdo->commit();return['id'=>$id,'public_code'=>$code,'variable_symbol'=>$payment['variable_symbol']];
    }catch(Throwable$exception){if($pdo->inTransaction())$pdo->rollBack();if($exception instanceof InvalidArgumentException||$exception instanceof MemberChargeAdminException||$exception instanceof ShopCheckoutException)throw$exception;throw new MemberChargeAdminException('Předpis se nepodařilo založit bez částečného zápisu.',0,$exception);}
}

/** @return array{id:int,changed:bool,status:string} */
function memberChargeAdminCorrect(PDO$pdo,int$chargeId,int$actorId,array$input,string$reason,bool$confirmed):array
{
    if($chargeId<1||$actorId<1||!$confirmed)throw new InvalidArgumentException('Oprava předpisu vyžaduje správce a potvrzení.');$reason=memberChargeAdminReason($reason);$values=memberChargeAdminValues($input);$settings=shopBankSettingsEffective($pdo);
    $pdo->beginTransaction();try{$before=memberChargeAdminLock($pdo,$chargeId);if(!$before)throw new MemberChargeAdminException('Předpis nebyl nalezen.');if($before['status']!=='pending')throw new MemberChargeAdminException('Opravit lze pouze neuhrazený předpis.');$changed=false;foreach($values as$key=>$value)if((string)($before[$key]??'')!==(string)($value??'')){$changed=true;break;}if($changed){$pdo->prepare('UPDATE club_member_charges SET sportovec_id=?,payer_account_id=?,title_snapshot=?,period_from=?,period_to=?,amount_minor=?,currency=?,due_on=? WHERE id=?')->execute([$values['sportovec_id'],$values['payer_account_id'],$values['title_snapshot'],$values['period_from'],$values['period_to'],$values['amount_minor'],$values['currency'],$values['due_on'],$chargeId]);$payment=$pdo->prepare("SELECT * FROM payments WHERE payable_type='member_charge' AND payable_id=?");$payment->execute([$chargeId]);$row=$payment->fetch(PDO::FETCH_ASSOC);if($row){$spd=shopPaymentSpdPayload((string)$settings['iban'],$values['amount_minor'],$values['currency'],(string)$row['variable_symbol'],'Členský předpis '.$chargeId);$pdo->prepare('UPDATE payments SET amount_minor=?,currency=?,iban_snapshot=?,bic_snapshot=?,account_label_snapshot=?,spd_payload=?,due_at=? WHERE id=?')->execute([$values['amount_minor'],$values['currency'],$settings['iban'],$settings['bic']!==''?$settings['bic']:null,$settings['account_label'],$spd,$values['due_on'].' 23:59:59',$row['id']]);}else{memberChargeAdminInsertPayment($pdo,$chargeId,$values,$settings,(string)$before['public_code'],$actorId);}memberChargeAdminEvent($pdo,$chargeId,'manual_correct','pending','pending',$actorId,$reason,['before'=>$before,'after'=>$values]);}$pdo->commit();return['id'=>$chargeId,'changed'=>$changed,'status'=>'pending'];
    }catch(Throwable$exception){if($pdo->inTransaction())$pdo->rollBack();if($exception instanceof InvalidArgumentException||$exception instanceof MemberChargeAdminException||$exception instanceof ShopCheckoutException)throw$exception;throw new MemberChargeAdminException('Předpis se nepodařilo opravit bez částečného zápisu.',0,$exception);}
}

/** @return array{id:int,changed:bool,status:string} */
function memberChargeAdminCancel(PDO$pdo,int$chargeId,int$actorId,string$reason,bool$confirmed):array
{
    if($chargeId<1||$actorId<1||!$confirmed)throw new InvalidArgumentException('Zrušení předpisu vyžaduje správce a potvrzení.');$reason=memberChargeAdminReason($reason);$pdo->beginTransaction();try{$before=memberChargeAdminLock($pdo,$chargeId);if(!$before)throw new MemberChargeAdminException('Předpis nebyl nalezen.');if($before['status']==='cancelled'){$pdo->commit();return['id'=>$chargeId,'changed'=>false,'status'=>'cancelled'];}if($before['status']!=='pending')throw new MemberChargeAdminException('Uhrazený předpis nelze zrušit; proveďte samostatné vrácení platby.');$pdo->prepare("UPDATE club_member_charges SET status='cancelled' WHERE id=?")->execute([$chargeId]);$pdo->prepare("UPDATE payments SET status='cancelled',confirmation_note=? WHERE payable_type='member_charge' AND payable_id=? AND status='pending'")->execute([$reason,$chargeId]);memberChargeAdminEvent($pdo,$chargeId,'manual_cancel','pending','cancelled',$actorId,$reason,$before);$pdo->commit();return['id'=>$chargeId,'changed'=>true,'status'=>'cancelled'];}catch(Throwable$exception){if($pdo->inTransaction())$pdo->rollBack();if($exception instanceof InvalidArgumentException||$exception instanceof MemberChargeAdminException)throw$exception;throw new MemberChargeAdminException('Předpis se nepodařilo zrušit bez částečného zápisu.',0,$exception);}
}

/** @return array{id:int,changed:bool,status:string} */
function memberChargeAdminConfirmPaid(PDO$pdo,int$chargeId,int$actorId,string$paidOn,string$reason,bool$confirmed):array
{
    if($chargeId<1||$actorId<1||!$confirmed)throw new InvalidArgumentException('Potvrzení úhrady vyžaduje správce a potvrzení.');$reason=memberChargeAdminReason($reason);$paidOn=memberChargeAdminDate($paidOn,true);$settings=shopBankSettingsEffective($pdo);$paidAt=$paidOn.' 12:00:00';$pdo->beginTransaction();try{$before=memberChargeAdminLock($pdo,$chargeId);if(!$before)throw new MemberChargeAdminException('Předpis nebyl nalezen.');if($before['status']==='paid'){$pdo->commit();return['id'=>$chargeId,'changed'=>false,'status'=>'paid'];}if($before['status']!=='pending')throw new MemberChargeAdminException('Uhradit lze pouze aktivní neuhrazený předpis.');$values=['amount_minor'=>(int)$before['amount_minor'],'currency'=>(string)$before['currency'],'due_on'=>(string)($before['due_on']?:$paidOn)];$payment=$pdo->prepare("SELECT * FROM payments WHERE payable_type='member_charge' AND payable_id=?");$payment->execute([$chargeId]);$row=$payment->fetch(PDO::FETCH_ASSOC);if($row){$pdo->prepare("UPDATE payments SET status='paid',paid_at=?,confirmed_by_trainer_id=?,confirmation_note=? WHERE id=? AND status='pending'")->execute([$paidAt,$actorId,$reason,$row['id']]);}else{memberChargeAdminInsertPayment($pdo,$chargeId,$values,$settings,(string)$before['public_code'],$actorId,$paidAt,$reason);}$pdo->prepare("UPDATE club_member_charges SET status='paid' WHERE id=?")->execute([$chargeId]);memberChargeAdminEvent($pdo,$chargeId,'manual_confirm_paid','pending','paid',$actorId,$reason,['paid_at'=>$paidAt,'before'=>$before]);$pdo->commit();return['id'=>$chargeId,'changed'=>true,'status'=>'paid'];}catch(Throwable$exception){if($pdo->inTransaction())$pdo->rollBack();if($exception instanceof InvalidArgumentException||$exception instanceof MemberChargeAdminException||$exception instanceof ShopCheckoutException)throw$exception;throw new MemberChargeAdminException('Úhradu se nepodařilo potvrdit bez částečného zápisu.',0,$exception);}
}

/** @return array{id:int,changed:bool,status:string} */
function memberChargeAdminConfirmRefund(PDO $pdo,int $chargeId,int $actorId,string $reference,string $reason,bool $confirmed):array
{
    $reference=trim($reference);
    if($chargeId<1||$actorId<1||!$confirmed||$reference===''||mb_strlen($reference,'UTF-8')>255)throw new InvalidArgumentException('Potvrzení vratky vyžaduje hospodáře, bankovní referenci a výslovné potvrzení.');
    $reason=memberChargeAdminReason($reason);$pdo->beginTransaction();
    try{$before=memberChargeAdminLock($pdo,$chargeId);if(!$before)throw new MemberChargeAdminException('Předpis nebyl nalezen.');
        if($before['status']==='refunded'){$pdo->commit();return['id'=>$chargeId,'changed'=>false,'status'=>'refunded'];}
        if($before['status']!=='refund_required')throw new MemberChargeAdminException('Vratku lze potvrdit pouze u předpisu označeného k vrácení.');
        $payment=$pdo->prepare("SELECT * FROM payments WHERE payable_type='member_charge' AND payable_id=? ORDER BY id DESC LIMIT 1");$payment->execute([$chargeId]);$row=$payment->fetch(PDO::FETCH_ASSOC);
        if(!$row||!in_array((string)$row['status'],['paid','refund_required'],true))throw new MemberChargeAdminException('K předpisu chybí uhrazená platba pro vratku.');
        $pdo->prepare("UPDATE payments SET status='refunded',refund_sent_at=CURRENT_TIMESTAMP,refund_reference=?,refund_confirmed_by_trainer_id=?,refund_confirmation_note=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$reference,$actorId,$reason,(int)$row['id']]);
        $pdo->prepare("UPDATE club_member_charges SET status='refunded',updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$chargeId]);
        memberChargeAdminEvent($pdo,$chargeId,'manual_confirm_refund','refund_required','refunded',$actorId,$reason,['reference'=>$reference,'payment_id'=>(int)$row['id'],'before'=>$before]);
        $pdo->commit();return['id'=>$chargeId,'changed'=>true,'status'=>'refunded'];
    }catch(Throwable$exception){if($pdo->inTransaction())$pdo->rollBack();if($exception instanceof InvalidArgumentException||$exception instanceof MemberChargeAdminException)throw$exception;throw new MemberChargeAdminException('Vratku se nepodařilo potvrdit bez částečného zápisu.',0,$exception);}
}

/** @return array<string,mixed>|false */
function memberChargeAdminLock(PDO$pdo,int$chargeId):array|false{$sql='SELECT * FROM club_member_charges WHERE id=?';if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$sql.=' FOR UPDATE';$statement=$pdo->prepare($sql);$statement->execute([$chargeId]);return$statement->fetch(PDO::FETCH_ASSOC);}
