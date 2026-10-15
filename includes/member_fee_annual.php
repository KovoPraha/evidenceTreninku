<?php
declare(strict_types=1);

require_once __DIR__.'/member_fee_plan.php';
require_once __DIR__.'/club_finance.php';

/** @return array{monthly_minor:int,total_minor:int,discount_minor:int,periods:list<string>} */
function memberFeeAnnualQuote(PDO $pdo,int $planId,int $sportovecId,string $firstPeriod,int $discountBasisPoints):array
{
    if ($planId<1||$sportovecId<1||$discountBasisPoints<0||$discountBasisPoints>2000) throw new InvalidArgumentException('Neplatný plán, sportovec nebo sleva.');
    [$from]=memberFeePlanMonth($firstPeriod);
    $plan=memberFeePlanRow($pdo,$planId);
    if (!$plan||$plan['status']!=='active'||(string)$plan['currency']!=='CZK') throw new MemberFeePlanException('Aktivní korunový plán nebyl nalezen.');
    $base=(int)$plan['amount_minor'];
    $monthly=intdiv($base*(10000-$discountBasisPoints),10000);
    if ($monthly<1) throw new MemberFeePlanException('Cena po slevě musí zůstat kladná.');
    $periods=[];
    $start=new DateTimeImmutable($from);
    for ($i=0;$i<12;$i++) {
        $period=$start->modify('+'.$i.' months')->format('Y-m');
        $preview=memberFeePlanPreview($pdo,$planId,$period);
        $matching=array_values(array_filter($preview['rows'],static fn(array $row):bool=>(int)$row['sportovec_id']===$sportovecId));
        if (count($matching)!==1||$matching[0]['result']!=='ready') {
            $detail=$matching[0]['detail']??'Sportovec není v soupisce plánu.';
            throw new MemberFeePlanException('Roční úhradu nelze založit pro '.$period.': '.$detail);
        }
        $periods[]=$period;
    }
    return ['monthly_minor'=>$monthly,'total_minor'=>$monthly*12,'discount_minor'=>($base-$monthly)*12,'periods'=>$periods];
}

/**
 * One verified bank receipt funds twelve separate monthly charges. The source
 * payment stays traceable through immutable allocation rows and the existing
 * monthly payment records; no Fio movement is auto-confirmed by this method.
 * @return array{receipt_id:int,charge_ids:list<int>,amount_minor:int}
 */
function memberFeeAnnualConfirm(PDO $pdo,int $planId,int $sportovecId,string $firstPeriod,int $discountBasisPoints,int $receivedMinor,string $bankReference,string $paidOn,int $actorId,string $reason,bool $confirmed):array
{
    $bankReference=trim($bankReference);$reason=trim($reason);
    if (!$confirmed||$actorId<1||$receivedMinor<1||preg_match('/^[A-Za-z0-9._:\/-]{6,100}$/D',$bankReference)!==1||mb_strlen($reason,'UTF-8')<5||mb_strlen($reason,'UTF-8')>1000) {
        throw new InvalidArgumentException('Potvrzení roční platby vyžaduje správce, bankovní referenci, důvod a souhlas.');
    }
    $paidOn=memberChargeAdminDate($paidOn,true);
    if ($paidOn>(new DateTimeImmutable('today',new DateTimeZone('Europe/Prague')))->format('Y-m-d')) throw new InvalidArgumentException('Budoucí platbu nelze potvrdit.');
    if (!clubFinanceTableExists($pdo,'club_finance_receipts')) throw new MemberFeePlanException('Roční platba ještě není migrována.');
    $pdo->beginTransaction();
    try {
        $plan=memberFeePlanRow($pdo,$planId,true);
        if (!$plan) throw new MemberFeePlanException('Plán nebyl nalezen.');
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql') {
            $lock=$pdo->prepare('SELECT id FROM sportovci WHERE id=? FOR UPDATE');$lock->execute([$sportovecId]);
            if (!$lock->fetchColumn()) throw new MemberFeePlanException('Sportovec nebyl nalezen.');
        }
        $quote=memberFeeAnnualQuote($pdo,$planId,$sportovecId,$firstPeriod,$discountBasisPoints);
        if ($receivedMinor!==$quote['total_minor']) throw new MemberFeePlanException('Přijatá částka se neshoduje s aktuálním náhledem roční platby. Nic nebylo uloženo.');
        clubFinanceAssertBankReferenceUnused($pdo,$bankReference,$receivedMinor,$paidOn);
        $firstPreview=memberFeePlanPreview($pdo,$planId,$firstPeriod);
        $payer=null;
        foreach ($firstPreview['rows'] as $row) if ((int)$row['sportovec_id']===$sportovecId) $payer=(int)$row['payer_account_id'];
        if (!$payer) throw new MemberFeePlanException('Chybí ověřený účet plátce.');
        $pdo->prepare("INSERT INTO club_finance_receipts(sportovec_id,plan_id,period_start,kind,reference,amount_minor,currency,paid_on,confirmed_by_trainer_id,reason) VALUES(?,?,?,'annual_fee',?,?,'CZK',?,?,?)")
            ->execute([$sportovecId,$planId,$firstPeriod,$bankReference,$quote['total_minor'],$paidOn,$actorId,$reason]);
        $receiptId=(int)$pdo->lastInsertId();$chargeIds=[];$bank=shopBankSettingsEffective($pdo);
        foreach ($quote['periods'] as $period) {
            [$periodFrom,$periodTo]=memberFeePlanMonth($period);
            $dueOn=$period.'-'.str_pad((string)(int)$plan['due_day'],2,'0',STR_PAD_LEFT);
            $external='feeplan:'.$planId.':'.$period.':'.$sportovecId;
            $code=memberChargeAdminPublicCode();
            $pdo->prepare("INSERT INTO club_member_charges(sportovec_id,payer_account_id,public_code,charge_type,title_snapshot,period_from,period_to,amount_minor,currency,due_on,status,source_system,source_external_id,source_import_run_id) VALUES(?,?,?,'membership',?,?,?,?,'CZK',?,'paid','member_fee_plan',?,NULL)")
                ->execute([$sportovecId,$payer,$code,(string)$plan['charge_title'],$periodFrom,$periodTo,$quote['monthly_minor'],$dueOn,$external]);
            $chargeId=(int)$pdo->lastInsertId();$chargeIds[]=$chargeId;
            $pdo->prepare('INSERT INTO club_finance_allocations(receipt_id,charge_id,amount_minor) VALUES(?,?,?)')
                ->execute([$receiptId,$chargeId,$quote['monthly_minor']]);
            $standingVs=memberFeePlanStandingVariableSymbol($pdo,$planId,$sportovecId);
            $payment=memberChargeAdminInsertPayment($pdo,$chargeId,['amount_minor'=>$quote['monthly_minor'],'currency'=>'CZK','due_on'=>$dueOn],$bank,$external,$actorId,$paidOn.' 12:00:00','Podíl roční platby '.$bankReference.'.',$standingVs);
            memberChargeAdminEvent($pdo,$chargeId,'annual_bulk_paid',null,'paid',$actorId,$reason,
                ['receipt_id'=>$receiptId,'payment_id'=>$payment['id'],'period'=>$period,'standard_minor'=>(int)$plan['amount_minor'],'discount_minor'=>(int)$plan['amount_minor']-$quote['monthly_minor']]);
        }
        $pdo->commit();
        return ['receipt_id'=>$receiptId,'charge_ids'=>$chargeIds,'amount_minor'=>$quote['total_minor']];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($e instanceof InvalidArgumentException||$e instanceof MemberFeePlanException||$e instanceof MemberChargeAdminException||$e instanceof ClubFinanceException||$e instanceof PDOException) throw $e;
        throw new MemberFeePlanException('Roční platbu se nepodařilo potvrdit bez částečného zápisu.',0,$e);
    }
}
