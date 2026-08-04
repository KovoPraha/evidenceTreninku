<?php
declare(strict_types=1);

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

/** @return array{status:string,payment_id:?int,order_id:?int,reason:string} */
function fioProposePaymentMatch(PDO $pdo, int $amountMinor, string $currency, ?string $variableSymbol): array
{
    if ($amountMinor <= 0) return ['status'=>'ignored_non_credit','payment_id'=>null,'order_id'=>null,'reason'=>'Odchozi nebo nulovy pohyb se neparuje.'];
    if ($variableSymbol === null) return ['status'=>'review_missing_vs','payment_id'=>null,'order_id'=>null,'reason'=>'Prichozi platba nema platny variabilni symbol.'];
    $statement = $pdo->prepare("SELECT p.id AS payment_id,o.id AS order_id,p.method,p.status AS payment_status,p.amount_minor,p.currency,o.status AS order_status,o.payment_status AS order_payment_status FROM payments p LEFT JOIN shop_orders o ON o.id=p.payable_id WHERE p.payable_type='shop_order' AND p.variable_symbol=? LIMIT 2");
    $statement->execute([$variableSymbol]);
    $candidates = $statement->fetchAll(PDO::FETCH_ASSOC);
    if (count($candidates) !== 1 || $candidates[0]['order_id'] === null) return ['status'=>'review_unknown_vs','payment_id'=>null,'order_id'=>null,'reason'=>'Variabilni symbol neodpovida jedine objednavce.'];
    $candidate = $candidates[0]; $paymentId=(int)$candidate['payment_id']; $orderId=(int)$candidate['order_id'];
    if ((int)$candidate['amount_minor'] !== $amountMinor) return ['status'=>'review_amount','payment_id'=>$paymentId,'order_id'=>$orderId,'reason'=>'Castka neodpovida cenovemu snapshotu platby.'];
    if (strtoupper((string)$candidate['currency']) !== $currency) return ['status'=>'review_currency','payment_id'=>$paymentId,'order_id'=>$orderId,'reason'=>'Mena neodpovida cenovemu snapshotu platby.'];
    if ($candidate['method'] !== 'bank_transfer' || $candidate['payment_status'] !== 'pending' || $candidate['order_status'] !== 'placed' || $candidate['order_payment_status'] !== 'pending') {
        return ['status'=>'review_state','payment_id'=>$paymentId,'order_id'=>$orderId,'reason'=>'Platba nebo objednavka uz neni v cekajicim stavu.'];
    }
    return ['status'=>'proposed_exact','payment_id'=>$paymentId,'order_id'=>$orderId,'reason'=>'Presna shoda VS, castky a meny; pouze navrh bez zmeny objednavky.'];
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
            $existing=$pdo->prepare('SELECT raw_sha256 FROM fio_account_movements WHERE fio_movement_id=?');$existing->execute([$movementId]);$existingHash=$existing->fetchColumn();
            if($existingHash!==false){if(!hash_equals((string)$existingHash,$rawHash))throw new FioImportException('fio_movement_changed');$counts['duplicates']++;continue;}
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

function fioAdminMovements(PDO $pdo,int $limit=200):array{$limit=max(1,min(500,$limit));return $pdo->query('SELECT m.*,o.public_code,p.status AS payment_status FROM fio_account_movements m LEFT JOIN shop_orders o ON o.id=m.candidate_order_id LEFT JOIN payments p ON p.id=m.candidate_payment_id ORDER BY m.booked_on DESC,m.id DESC LIMIT '.$limit)->fetchAll(PDO::FETCH_ASSOC);}
function fioAdminRuns(PDO $pdo,int $limit=30):array{$limit=max(1,min(100,$limit));return $pdo->query('SELECT * FROM fio_import_runs ORDER BY id DESC LIMIT '.$limit)->fetchAll(PDO::FETCH_ASSOC);}
