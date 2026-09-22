<?php
declare(strict_types=1);

require_once __DIR__.'/shop_checkout.php';
require_once __DIR__.'/member_charge_admin.php';

final class FioImportException extends RuntimeException {}

function fioNormalizeIban(string $value): string
{
    return strtoupper((string)preg_replace('/\s+/', '', trim($value)));
}

function fioNormalizeVariableSymbol(mixed $value): ?string
{
    $value = trim((string)$value);
    if ($value === '' || preg_match('/^[0-9]{1,10}$/D', $value) !== 1) return null;
    return str_pad((string)(int)$value, 10, '0', STR_PAD_LEFT);
}

function fioAmountToMinor(mixed $value): int
{
    if (is_float($value)) $value = rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
    $value = trim((string)$value);
    if (preg_match('/^(-?)([0-9]+)(?:\.([0-9]+))?$/D', $value, $match) !== 1) throw new FioImportException('fio_invalid_amount');
    $fraction = $match[3] ?? '';
    if (strlen(rtrim($fraction, '0')) > 2) throw new FioImportException('fio_amount_has_subminor_precision');
    $fraction = str_pad(substr($fraction, 0, 2), 2, '0');
    $major = (int)$match[2];
    if ($major > intdiv(PHP_INT_MAX - 99, 100)) throw new FioImportException('fio_amount_overflow');
    $minor = ($major * 100) + (int)$fraction;
    return ($match[1] ?? '') === '-' ? -$minor : $minor;
}

function fioTableExists(PDO $pdo,string $table):bool
{
    if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'){$statement=$pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');$statement->execute([$table]);return(bool)$statement->fetchColumn();}
    $statement=$pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1");$statement->execute([$table]);return(bool)$statement->fetchColumn();
}

/** @return array{status:string,payment_id:?int,order_id:?int,payable_type:?string,payable_id:?int,reason:string} */
function fioProposePaymentMatch(PDO $pdo, int $amountMinor, string $currency, ?string $variableSymbol): array
{
    $empty=static fn(string$status,string$reason):array=>['status'=>$status,'payment_id'=>null,'order_id'=>null,'payable_type'=>null,'payable_id'=>null,'reason'=>$reason];
    if ($amountMinor <= 0) return $empty('ignored_non_credit','Odchozí nebo nulový pohyb se nepáruje.');
    if ($variableSymbol === null) return $empty('review_missing_vs','Příchozí platba nemá platný variabilní symbol.');
    $hasPlans=fioTableExists($pdo,'club_member_charges')&&fioTableExists($pdo,'member_fee_plan_run_items')&&fioTableExists($pdo,'member_fee_plan_runs');
    $planJoins=$hasPlans?"LEFT JOIN club_member_charges c ON c.id=p.payable_id AND p.payable_type='member_charge' LEFT JOIN member_fee_plan_run_items ri ON ri.charge_id=c.id LEFT JOIN member_fee_plan_runs rr ON rr.id=ri.run_id ":'';
    $statement = $pdo->prepare('SELECT p.id AS payment_id,p.payable_type,p.payable_id,p.method,p.status AS payment_status,p.amount_minor,p.currency'.($hasPlans?',rr.plan_id AS charge_plan_id,c.sportovec_id,c.status AS charge_status,c.due_on':',NULL AS charge_plan_id,NULL AS sportovec_id,NULL AS charge_status,NULL AS due_on').' FROM payments p '.$planJoins.'WHERE p.variable_symbol=? ORDER BY '.($hasPlans?'c.due_on,':'').'p.id');
    $statement->execute([$variableSymbol]);
    $candidates = $statement->fetchAll(PDO::FETCH_ASSOC);
    if(count($candidates)>1){
        $planIds=[];$personIds=[];$validPlan=true;foreach($candidates as$row){if((string)$row['payable_type']!=='member_charge'||(int)($row['charge_plan_id']??0)<1||(int)($row['sportovec_id']??0)<1){$validPlan=false;break;}$planIds[(int)$row['charge_plan_id']]=true;$personIds[(int)$row['sportovec_id']]=true;}
        if(!$validPlan||count($planIds)!==1||count($personIds)!==1)return$empty('review_unknown_vs','Variabilní symbol neodpovídá jednomu členskému plánu a sportovci.');
        $eligible=array_values(array_filter($candidates,static fn(array$row):bool=>(string)$row['payment_status']==='pending'&&(string)$row['charge_status']==='pending'&&(int)$row['amount_minor']===$amountMinor&&strtoupper((string)$row['currency'])===$currency));
        if($eligible===[])return$empty('review_amount','Částka nebo stav neodpovídá žádnému neuhrazenému období trvalého příkazu.');
        $candidates=[$eligible[0]];
    }
    if (count($candidates) !== 1) return $empty('review_unknown_vs','Variabilní symbol neodpovídá právě jedné evidované platbě.');
    $candidate=$candidates[0];$paymentId=(int)$candidate['payment_id'];$payableType=(string)$candidate['payable_type'];$payableId=(int)$candidate['payable_id'];$orderId=null;$targetStatus=null;$orderPaymentStatus=null;
    if($payableType==='shop_order'){$target=$pdo->prepare('SELECT id,status,payment_status FROM shop_orders WHERE id=?');$target->execute([$payableId]);$row=$target->fetch(PDO::FETCH_ASSOC);if(!$row)return$empty('review_unknown_vs','Objednávka přiřazená k platbě nebyla nalezena.');$orderId=(int)$row['id'];$targetStatus=(string)$row['status'];$orderPaymentStatus=(string)$row['payment_status'];}
    elseif($payableType==='member_charge'&&fioTableExists($pdo,'club_member_charges')){$target=$pdo->prepare('SELECT id,status FROM club_member_charges WHERE id=?');$target->execute([$payableId]);$row=$target->fetch(PDO::FETCH_ASSOC);if(!$row)return$empty('review_unknown_vs','Členský předpis přiřazený k platbě nebyl nalezen.');$targetStatus=(string)$row['status'];}
    else return['status'=>'review_type','payment_id'=>$paymentId,'order_id'=>null,'payable_type'=>$payableType,'payable_id'=>$payableId,'reason'=>'Tento typ platby zatím nelze párovat.'];
    $base=['payment_id'=>$paymentId,'order_id'=>$orderId,'payable_type'=>$payableType,'payable_id'=>$payableId];
    if ((int)$candidate['amount_minor'] !== $amountMinor) return ['status'=>'review_amount','reason'=>'Částka neodpovídá uložené platbě.']+$base;
    if (strtoupper((string)$candidate['currency']) !== $currency) return ['status'=>'review_currency','reason'=>'Měna neodpovídá uložené platbě.']+$base;
    $targetPending=$payableType==='shop_order'?$targetStatus==='placed'&&$orderPaymentStatus==='pending':$targetStatus==='pending';
    if ($candidate['method'] !== 'bank_transfer'||$candidate['payment_status'] !== 'pending'||!$targetPending) return ['status'=>'review_state','reason'=>'Platba nebo její předpis už není v čekajícím stavu.']+$base;
    return ['status'=>'proposed_exact','reason'=>'Přesná shoda VS, částky a měny; čeká na výslovné potvrzení správce.']+$base;
}

/** @return array<string,mixed> */
function fioColumn(array $transaction, string $name): array
{
    $column = $transaction[$name] ?? null;
    return is_array($column) ? $column : [];
}

function fioBookedOn(mixed $value): string
{
    if (is_string($value)) {
        $value = trim($value);
        if (preg_match('/^(\d{4}-\d{2}-\d{2})(?:([+-])(\d{2}):(\d{2}))?$/D', $value, $match) === 1) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $match[1]);
            if (!$date || $date->format('Y-m-d') !== $match[1]) {
                throw new FioImportException('fio_invalid_booking_date');
            }
            if (isset($match[2]) && $match[2] !== '') {
                $hours = (int)$match[3];
                $minutes = (int)$match[4];
                if ($hours > 14 || $minutes > 59 || ($hours === 14 && $minutes !== 0)) {
                    throw new FioImportException('fio_invalid_booking_date');
                }
            }
            return $match[1];
        }
        if (preg_match('/^[0-9]{10,16}$/D', $value) !== 1) {
            throw new FioImportException('fio_invalid_booking_date');
        }
    } elseif (!is_int($value) && !is_float($value)) {
        throw new FioImportException('fio_invalid_booking_date');
    }
    $numeric = (float)$value;
    if (!is_finite($numeric) || $numeric <= 0) {
        throw new FioImportException('fio_invalid_booking_date');
    }
    $seconds = $numeric >= 100_000_000_000 ? (int)floor($numeric / 1000) : (int)floor($numeric);
    try {
        return (new DateTimeImmutable('@'.$seconds))->setTimezone(new DateTimeZone('Europe/Prague'))->format('Y-m-d');
    } catch (Throwable) {
        throw new FioImportException('fio_invalid_booking_date');
    }
}

/** @return array{run_id:int,fetched:int,inserted:int,duplicates:int,proposed:int,review:int,ignored:int} */
function fioImportJson(PDO $pdo, string $json, string $periodFrom, string $periodTo, string $expectedIban): array
{
    foreach ([$periodFrom,$periodTo] as $date) { $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date); if(!$parsed||$parsed->format('Y-m-d')!==$date) throw new InvalidArgumentException('Neplatne obdobi Fio importu.'); }
    if ($periodFrom > $periodTo) throw new InvalidArgumentException('Pocatecni datum je po koncovem.');
    $expectedIban=fioNormalizeIban($expectedIban); if($expectedIban==='') throw new InvalidArgumentException('Ocekavany IBAN nesmi byt prazdny.');
    $run=$pdo->prepare("INSERT INTO fio_import_runs(period_from,period_to,status) VALUES (?,?,'running')"); $run->execute([$periodFrom,$periodTo]); $runId=(int)$pdo->lastInsertId();
    try {
        if(strlen($json)>5_000_000) throw new FioImportException('fio_response_too_large');
        $document=json_decode($json,true,64,JSON_THROW_ON_ERROR|JSON_BIGINT_AS_STRING); $statement=$document['accountStatement']??null;
        if(!is_array($statement)) throw new FioImportException('fio_invalid_response_shape');
        $actualIban=fioNormalizeIban((string)($statement['info']['iban']??''));
        if($actualIban===''||!hash_equals($expectedIban,$actualIban)) throw new FioImportException('fio_unexpected_account');
        $transactions=$statement['transactionList']['transaction']??[]; if($transactions===null)$transactions=[];
        if(is_array($transactions)&&$transactions!==[]&&!array_is_list($transactions))$transactions=[$transactions];
        if(!is_array($transactions)||count($transactions)>2000) throw new FioImportException('fio_invalid_transaction_list');
        $counts=['fetched'=>count($transactions),'inserted'=>0,'duplicates'=>0,'proposed'=>0,'review'=>0,'ignored'=>0];
        $pdo->beginTransaction();
        foreach($transactions as $transaction){
            if(!is_array($transaction))throw new FioImportException('fio_invalid_transaction');
            $movementId=trim((string)(fioColumn($transaction,'column22')['value']??''));
            if($movementId===''||strlen($movementId)>80||preg_match('/^[A-Za-z0-9._:-]+$/D',$movementId)!==1)throw new FioImportException('fio_invalid_movement_id');
            $rawHash=hash('sha256',(string)json_encode($transaction,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION|JSON_THROW_ON_ERROR));
            $existing=$pdo->prepare('SELECT id,raw_sha256,amount_minor,currency,variable_symbol,match_status FROM fio_account_movements WHERE fio_movement_id=?');$existing->execute([$movementId]);$existingRow=$existing->fetch(PDO::FETCH_ASSOC);
            if($existingRow){
                if(!hash_equals((string)$existingRow['raw_sha256'],$rawHash))throw new FioImportException('fio_movement_changed');
                if((string)$existingRow['match_status']!=='confirmed'){
                    $refreshed=fioProposePaymentMatch($pdo,(int)$existingRow['amount_minor'],(string)$existingRow['currency'],$existingRow['variable_symbol']!==null?(string)$existingRow['variable_symbol']:null);
                    $pdo->prepare('UPDATE fio_account_movements SET match_status=?,candidate_payment_id=?,candidate_order_id=?,match_reason=? WHERE id=?')->execute([$refreshed['status'],$refreshed['payment_id'],$refreshed['order_id'],$refreshed['reason'],(int)$existingRow['id']]);
                }
                $counts['duplicates']++;continue;
            }
            $amountMinor=fioAmountToMinor(fioColumn($transaction,'column1')['value']??null);
            $currency=strtoupper(trim((string)(fioColumn($transaction,'column14')['value']??'')));if(preg_match('/^[A-Z]{3}$/D',$currency)!==1)throw new FioImportException('fio_invalid_currency');
            $variableSymbol=fioNormalizeVariableSymbol(fioColumn($transaction,'column5')['value']??null);$bookedOn=fioBookedOn(fioColumn($transaction,'column0')['value']??null);
            $movementType=trim((string)(fioColumn($transaction,'column8')['value']??'neznamy'));if($movementType==='')$movementType='neznamy';$movementType=mb_substr($movementType,0,64,'UTF-8');
            $proposal=fioProposePaymentMatch($pdo,$amountMinor,$currency,$variableSymbol);
            $insert=$pdo->prepare('INSERT INTO fio_account_movements(fio_movement_id,booked_on,amount_minor,currency,variable_symbol,movement_type,raw_sha256,match_status,candidate_payment_id,candidate_order_id,match_reason,import_run_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
            $insert->execute([$movementId,$bookedOn,$amountMinor,$currency,$variableSymbol,$movementType,$rawHash,$proposal['status'],$proposal['payment_id'],$proposal['order_id'],$proposal['reason'],$runId]);
            $counts['inserted']++;if($proposal['status']==='proposed_exact')$counts['proposed']++;elseif(str_starts_with($proposal['status'],'review_'))$counts['review']++;else$counts['ignored']++;
        }
        $pdo->commit();
        $finish=$pdo->prepare("UPDATE fio_import_runs SET source_account_iban=?,status='completed',fetched_count=?,inserted_count=?,duplicate_count=?,proposed_count=?,review_count=?,ignored_count=?,finished_at=CURRENT_TIMESTAMP WHERE id=?");
        $finish->execute([$actualIban,$counts['fetched'],$counts['inserted'],$counts['duplicates'],$counts['proposed'],$counts['review'],$counts['ignored'],$runId]);
        return ['run_id'=>$runId]+$counts;
    }catch(Throwable $exception){
        if($pdo->inTransaction())$pdo->rollBack();
        if($exception instanceof JsonException)$code='fio_invalid_json';
        elseif($exception instanceof FioImportException||$exception instanceof InvalidArgumentException)$code=substr((string)preg_replace('/[^a-z0-9_:-]/i','_',$exception->getMessage()),0,64);
        else $code='fio_import_failed';
        $failed=$pdo->prepare("UPDATE fio_import_runs SET status='failed',error_code=?,finished_at=CURRENT_TIMESTAMP WHERE id=?");$failed->execute([$code,$runId]);
        if($exception instanceof InvalidArgumentException||$exception instanceof FioImportException)throw $exception;throw new FioImportException('fio_import_failed',0,$exception);
    }
}

function fioFetchPeriodJson(string $token, string $periodFrom, string $periodTo): string
{
    if(preg_match('/^[A-Za-z0-9_-]{20,128}$/D',$token)!==1)throw new InvalidArgumentException('FIO_API_TOKEN nema bezpecny format.');
    if(!function_exists('curl_init'))throw new FioImportException('fio_curl_extension_missing');
    $url='https://fioapi.fio.cz/v1/rest/periods/'.rawurlencode($token).'/'.$periodFrom.'/'.$periodTo.'/transactions.json';$handle=curl_init($url);if($handle===false)throw new FioImportException('fio_http_init_failed');
    curl_setopt_array($handle,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>20,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_HTTPHEADER=>['Accept: application/json','User-Agent: KovoPrahaEvidence/1.0']]);
    $body=curl_exec($handle);$status=(int)curl_getinfo($handle,CURLINFO_RESPONSE_CODE);$error=curl_errno($handle);curl_close($handle);
    if($body===false||$error!==0||$status!==200)throw new FioImportException('fio_http_failed_'.$status);if(strlen($body)>5_000_000)throw new FioImportException('fio_response_too_large');return $body;
}

/** @return array{movement_id:int,payment_id:int,payable_type:string,payable_id:int,changed:bool} */
function fioAdminConfirmExactMovement(PDO $pdo,int $movementId,int $actorTrainerId,string $reason,bool $confirmed):array
{
    $reason=trim((string)preg_replace('/\s+/u',' ',$reason));
    if($movementId<1||$actorTrainerId<1||!$confirmed||mb_strlen($reason,'UTF-8')<5||mb_strlen($reason,'UTF-8')>1000)throw new InvalidArgumentException('Potvrzení vyžaduje správce, důvod (5 až 1000 znaků) a výslovný souhlas.');
    $pdo->beginTransaction();
    try{
        $sql='SELECT * FROM fio_account_movements WHERE id=?';if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$sql.=' FOR UPDATE';$statement=$pdo->prepare($sql);$statement->execute([$movementId]);$movement=$statement->fetch(PDO::FETCH_ASSOC);
        if(!$movement)throw new FioImportException('Bankovní pohyb nebyl nalezen.');
        if((string)$movement['match_status']==='confirmed'){
            $paymentId=(int)($movement['candidate_payment_id']??0);$check=$pdo->prepare('SELECT payable_type,payable_id,status,bank_movement_id FROM payments WHERE id=?');$check->execute([$paymentId]);$payment=$check->fetch(PDO::FETCH_ASSOC);
            if(!$payment||$payment['status']!=='paid'||(string)$payment['bank_movement_id']!==(string)$movement['fio_movement_id'])throw new FioImportException('Potvrzený bankovní pohyb není konzistentní s platbou.');
            $pdo->commit();return['movement_id'=>$movementId,'payment_id'=>$paymentId,'payable_type'=>(string)$payment['payable_type'],'payable_id'=>(int)$payment['payable_id'],'changed'=>false];
        }
        $proposal=fioProposePaymentMatch($pdo,(int)$movement['amount_minor'],(string)$movement['currency'],$movement['variable_symbol']!==null?(string)$movement['variable_symbol']:null);
        if($proposal['status']!=='proposed_exact'||!$proposal['payment_id']||!$proposal['payable_type']||!$proposal['payable_id'])throw new FioImportException('Pohyb už nesplňuje přesnou shodu: '.$proposal['reason']);
        $paymentId=(int)$proposal['payment_id'];$auditReason=$reason.' Fio pohyb '.(string)$movement['fio_movement_id'].'.';
        if($proposal['payable_type']==='shop_order')shopOrderConfirmPaymentInTransaction($pdo,$paymentId,'bank_transfer','trainer',$actorTrainerId,$auditReason);
        elseif($proposal['payable_type']==='member_charge')memberChargeConfirmBankPaymentInTransaction($pdo,$paymentId,(string)$movement['booked_on'],'trainer',$actorTrainerId,$auditReason);
        else throw new FioImportException('Typ platby nelze potvrdit z Fio pohybu.');
        $bind=$pdo->prepare('UPDATE payments SET bank_movement_id=?,bank_booked_on=?,paid_at=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND (bank_movement_id IS NULL OR bank_movement_id=?)');
        $bind->execute([(string)$movement['fio_movement_id'],(string)$movement['booked_on'],(string)$movement['booked_on'].' 12:00:00',$paymentId,(string)$movement['fio_movement_id']]);
        if($bind->rowCount()!==1)throw new FioImportException('Platba už je svázána s jiným bankovním pohybem.');
        $update=$pdo->prepare("UPDATE fio_account_movements SET match_status='confirmed',candidate_payment_id=?,candidate_order_id=?,match_reason=?,confirmed_at=CURRENT_TIMESTAMP,confirmed_by_trainer_id=?,confirmation_note=? WHERE id=? AND match_status<>'confirmed'");
        $update->execute([$paymentId,$proposal['order_id'],'Přesná shoda potvrzena správcem.',$actorTrainerId,$reason,$movementId]);if($update->rowCount()!==1)throw new FioImportException('Bankovní pohyb se nepodařilo potvrdit.');
        $pdo->commit();return['movement_id'=>$movementId,'payment_id'=>$paymentId,'payable_type'=>(string)$proposal['payable_type'],'payable_id'=>(int)$proposal['payable_id'],'changed'=>true];
    }catch(Throwable$exception){if($pdo->inTransaction())$pdo->rollBack();if($exception instanceof InvalidArgumentException||$exception instanceof FioImportException||$exception instanceof ShopCheckoutException||$exception instanceof MemberChargeAdminException)throw$exception;throw new FioImportException('Potvrzení bankovního pohybu selhalo bez částečného zápisu.',0,$exception);}
}

function fioAdminMovements(PDO $pdo,int $limit=200):array
{
    $limit=max(1,min(500,$limit));$memberJoin=fioTableExists($pdo,'club_member_charges')?' LEFT JOIN club_member_charges c ON c.id=p.payable_id AND p.payable_type=\'member_charge\'':'';$memberSelect=fioTableExists($pdo,'club_member_charges')?',c.public_code AS member_charge_code,c.title_snapshot AS member_charge_title':',NULL AS member_charge_code,NULL AS member_charge_title';
    return$pdo->query('SELECT m.*,o.public_code,p.status AS payment_status,p.payable_type,p.payable_id'.$memberSelect.' FROM fio_account_movements m LEFT JOIN shop_orders o ON o.id=m.candidate_order_id LEFT JOIN payments p ON p.id=m.candidate_payment_id'.$memberJoin.' ORDER BY m.booked_on DESC,m.id DESC LIMIT '.$limit)->fetchAll(PDO::FETCH_ASSOC);
}
function fioAdminRuns(PDO $pdo,int $limit=30):array{$limit=max(1,min(100,$limit));return $pdo->query('SELECT * FROM fio_import_runs ORDER BY id DESC LIMIT '.$limit)->fetchAll(PDO::FETCH_ASSOC);}
