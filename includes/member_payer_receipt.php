<?php
declare(strict_types=1);

require_once __DIR__.'/club_finance.php';
require_once __DIR__.'/member_charge_admin.php';

final class MemberPayerReceiptException extends RuntimeException {}

function memberPayerReceiptLockSuffix(PDO $pdo): string
{
    return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
}

function memberPayerReceiptReason(string $reason): string
{
    return memberChargeAdminReason($reason);
}

/** @return array<int,int> charge ID => positive minor units */
function memberPayerReceiptAmounts(array $amounts): array
{
    $clean=[];
    foreach ($amounts as $chargeId=>$raw) {
        if ($raw === '' || $raw === null || $raw === '0' || $raw === 0) continue;
        if ((string)(int)$chargeId !== (string)$chargeId || (int)$chargeId < 1 || !is_scalar($raw) || preg_match('/^[1-9][0-9]{0,8}$/D', (string)$raw) !== 1) {
            throw new InvalidArgumentException('Rozdělení obsahuje neplatný předpis nebo částku v haléřích.');
        }
        $clean[(int)$chargeId]=(int)$raw;
    }
    if ($clean===[]) throw new InvalidArgumentException('Zadejte alespoň jednu kladnou částku pro předpis.');
    ksort($clean,SORT_NUMERIC);
    return $clean;
}

/** @return array<string,mixed> */
function memberPayerReceiptLock(PDO $pdo,int $receiptId): array
{
    $query=$pdo->prepare('SELECT * FROM member_payer_receipts WHERE id=?'.memberPayerReceiptLockSuffix($pdo));
    $query->execute([$receiptId]);
    $row=$query->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new MemberPayerReceiptException('Rodinná platba nebyla nalezena.');
    return $row;
}

/** @return array{receipt_id:int,allocated_minor:int,remaining_minor:int} */
function memberPayerReceiptCreate(PDO $pdo,int $payerAccountId,?int $fioMovementId,string $reference,int $amountMinor,string $paidOn,array $amounts,int $actorId,string $reason,bool $confirmed): array
{
    $reference=trim($reference);$reason=memberPayerReceiptReason($reason);$allocations=memberPayerReceiptAmounts($amounts);
    $paidOn=memberChargeAdminDate($paidOn,true);
    if (!$confirmed||$payerAccountId<1||$actorId<1||$amountMinor<1||$amountMinor>1000000000||preg_match('#^[A-Za-z0-9._:/-]{6,100}$#D',$reference)!==1||$paidOn>(new DateTimeImmutable('today',new DateTimeZone('Europe/Prague')))->format('Y-m-d')||($fioMovementId!==null&&$fioMovementId<1)) {
        throw new InvalidArgumentException('Rodinná platba vyžaduje ověřeného plátce, částku, bankovní referenci, datum a potvrzení.');
    }
    if (!clubFinanceTableExists($pdo,'member_payer_receipts')) throw new MemberPayerReceiptException('Evidence rodinných plateb ještě není migrována.');
    if (array_sum($allocations)>$amountMinor) throw new MemberPayerReceiptException('Součet přidělení přesahuje přijatou částku.');
    $pdo->beginTransaction();
    try {
        $payer=$pdo->prepare('SELECT id,aktivni,email_overeno FROM verejni_uzivatele WHERE id=?'.memberPayerReceiptLockSuffix($pdo));
        $payer->execute([$payerAccountId]);$account=$payer->fetch(PDO::FETCH_ASSOC);
        if (!$account||(int)$account['aktivni']!==1||(int)$account['email_overeno']!==1) throw new MemberPayerReceiptException('Aktivní ověřený účet plátce nebyl nalezen.');
        clubFinanceAssertBankReferenceUnused($pdo,$reference,$amountMinor,$paidOn);
        if ($fioMovementId!==null) {
            $fio=$pdo->prepare('SELECT * FROM fio_account_movements WHERE id=?'.memberPayerReceiptLockSuffix($pdo));$fio->execute([$fioMovementId]);$movement=$fio->fetch(PDO::FETCH_ASSOC);
            if (!$movement||$movement['fio_movement_id']!==$reference||(int)$movement['amount_minor']!==$amountMinor||(string)$movement['currency']!=='CZK'||(string)$movement['booked_on']!==$paidOn||(string)$movement['match_status']==='confirmed') throw new MemberPayerReceiptException('Pohyb Fio neodpovídá bankovní referenci, částce, datu nebo již byl potvrzen.');
            $vs=$pdo->prepare('SELECT variable_symbol FROM member_payer_variable_symbols WHERE payer_account_id=?');$vs->execute([$payerAccountId]);$payerVs=$vs->fetchColumn();
            if (!is_string($payerVs)||!hash_equals($payerVs,(string)$movement['variable_symbol'])) throw new MemberPayerReceiptException('Variabilní symbol pohybu nepatří zvolenému plátci.');
        } else {
            $fio=$pdo->prepare('SELECT id FROM fio_account_movements WHERE fio_movement_id=?'.memberPayerReceiptLockSuffix($pdo));$fio->execute([$reference]);
            if ($fio->fetchColumn()) throw new MemberPayerReceiptException('Reference je ve Fio importu. Vyberte odpovídající bankovní pohyb.');
        }
        $pdo->prepare("INSERT INTO member_payer_receipts(payer_account_id,reference,fio_movement_id,amount_minor,currency,paid_on,confirmed_by_trainer_id,reason) VALUES(?,?,?,?,'CZK',?,?,?)")
            ->execute([$payerAccountId,$reference,$fioMovementId,$amountMinor,$paidOn,$actorId,$reason]);
        $receiptId=(int)$pdo->lastInsertId();
        $result=memberPayerReceiptAllocateInTransaction($pdo,$receiptId,$allocations,$actorId,$reason);
        if ($fioMovementId!==null) {
            $update=$pdo->prepare("UPDATE fio_account_movements SET match_status='confirmed',candidate_payment_id=NULL,candidate_order_id=NULL,match_reason=?,confirmed_at=CURRENT_TIMESTAMP,confirmed_by_trainer_id=?,confirmation_note=? WHERE id=? AND match_status<>'confirmed'");
            $update->execute(['Rodinná platba #'.$receiptId.' rozdělena mezi členské předpisy.',$actorId,$reason,$fioMovementId]);
            if ($update->rowCount()!==1) throw new MemberPayerReceiptException('Pohyb Fio se nepodařilo vyhradit.');
        }
        memberPayerReceiptEvent($pdo,$receiptId,$actorId,'confirm_receipt',$reason,['reference'=>$reference,'fio_movement_id'=>$fioMovementId,'amount_minor'=>$amountMinor,'allocations'=>$allocations,'remaining_minor'=>$result['remaining_minor']]);
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($e instanceof InvalidArgumentException||$e instanceof MemberPayerReceiptException||$e instanceof ClubFinanceException||$e instanceof MemberChargeAdminException) throw $e;
        throw new MemberPayerReceiptException('Rodinnou platbu se nepodařilo uložit bez částečného zápisu.',0,$e);
    }
}

/** @return array{receipt_id:int,allocated_minor:int,remaining_minor:int} */
function memberPayerReceiptAllocateRemaining(PDO $pdo,int $receiptId,array $amounts,int $actorId,string $reason,bool $confirmed,?string $requestKey=null): array
{
    $reason=memberPayerReceiptReason($reason);$allocations=memberPayerReceiptAmounts($amounts);
    if (!$confirmed||$receiptId<1||$actorId<1) throw new InvalidArgumentException('Přidělení zbytku vyžaduje platbu, správce a potvrzení.');
    if ($requestKey!==null&&preg_match('/^[a-f0-9]{32}$/D',$requestKey)!==1) throw new InvalidArgumentException('Neplatný identifikátor odeslání formuláře.');
    $fingerprint=hash('sha256',json_encode([$receiptId,$allocations,$actorId,$reason],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
    $requestKey=$requestKey===null?$fingerprint:hash('sha256',$requestKey);
    $pdo->beginTransaction();
    try {
        memberPayerReceiptLock($pdo,$receiptId);
        $check=$pdo->prepare('SELECT snapshot_json FROM member_payer_receipt_events WHERE receipt_id=? AND request_key=?'.memberPayerReceiptLockSuffix($pdo));$check->execute([$receiptId,$requestKey]);
        $prior=$check->fetchColumn();
        if ($prior!==false) {
            $snapshot=json_decode((string)$prior,true,32,JSON_THROW_ON_ERROR);
            if (!is_array($snapshot)||($snapshot['request_fingerprint']??null)!==$fingerprint) throw new MemberPayerReceiptException('Tento formulář již byl použit pro jiné přidělení.');
            $pdo->commit();return ['receipt_id'=>$receiptId,'allocated_minor'=>(int)$snapshot['allocated_minor'],'remaining_minor'=>(int)$snapshot['remaining_minor']];
        }
        $result=memberPayerReceiptAllocateInTransaction($pdo,$receiptId,$allocations,$actorId,$reason);
        memberPayerReceiptEvent($pdo,$receiptId,$actorId,'allocate_remainder',$reason,['allocations'=>$allocations,'allocated_minor'=>$result['allocated_minor'],'remaining_minor'=>$result['remaining_minor'],'request_fingerprint'=>$fingerprint],$requestKey);
        $pdo->commit();return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($e instanceof InvalidArgumentException||$e instanceof MemberPayerReceiptException||$e instanceof ClubFinanceException||$e instanceof MemberChargeAdminException) throw $e;
        throw new MemberPayerReceiptException('Přidělení zbytku selhalo bez částečného zápisu.',0,$e);
    }
}

/** @return array{receipt_id:int,allocated_minor:int,remaining_minor:int} */
function memberPayerReceiptAllocateInTransaction(PDO $pdo,int $receiptId,array $allocations,int $actorId,string $reason): array
{
    if (!$pdo->inTransaction()) throw new LogicException('Přidělení vyžaduje transakci.');
    $receipt=memberPayerReceiptLock($pdo,$receiptId);
    $query=$pdo->prepare('SELECT COALESCE(SUM(amount_minor),0) FROM member_payer_receipt_allocations WHERE receipt_id=?');$query->execute([$receiptId]);
    $used=(int)$query->fetchColumn();$new=array_sum($allocations);$remaining=(int)$receipt['amount_minor']-$used;
    if ($remaining<0||$new>$remaining) throw new MemberPayerReceiptException('Přidělení přesahuje nevyužitou část rodinné platby.');
    foreach ($allocations as $chargeId=>$part) {
        $charge=memberChargeAdminLock($pdo,$chargeId);
        if (!$charge||(int)$charge['payer_account_id']!==(int)$receipt['payer_account_id']||(string)$charge['currency']!=='CZK'||(string)$charge['status']!=='pending') throw new MemberPayerReceiptException('Předpis #'.$chargeId.' není čekající předpis tohoto plátce v CZK.');
        $payment=$pdo->prepare("SELECT * FROM payments WHERE payable_type='member_charge' AND payable_id=?".memberPayerReceiptLockSuffix($pdo));$payment->execute([$chargeId]);$payment=$payment->fetch(PDO::FETCH_ASSOC);
        if (!$payment||(string)$payment['status']!=='pending'||(string)$payment['method']!=='bank_transfer'||(int)$payment['amount_minor']!==(int)$charge['amount_minor']) throw new MemberPayerReceiptException('Předpis #'.$chargeId.' nemá konzistentní čekající bankovní platbu.');
        $already=clubFinanceChargeAllocatedMinor($pdo,$chargeId);
        if ($part>(int)$charge['amount_minor']-$already) throw new MemberPayerReceiptException('Přidělení na předpis #'.$chargeId.' přesahuje jeho neuhrazený zbytek.');
        $pdo->prepare('INSERT INTO member_payer_receipt_allocations(receipt_id,charge_id,amount_minor) VALUES(?,?,?)')->execute([$receiptId,$chargeId,$part]);
        if ($already+$part===(int)$charge['amount_minor']) {
            $note='Uhrazeno přidělením rodinné platby #'.$receiptId.' ('.$receipt['reference'].').';
            $pdo->prepare("UPDATE payments SET status='paid',payment_source='bank_transfer',paid_at=?,confirmed_by_trainer_id=?,confirmation_note=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND status='pending'")
                ->execute([$receipt['paid_on'].' 12:00:00',$actorId,$note,(int)$payment['id']]);
            $pdo->prepare("UPDATE club_member_charges SET status='paid',updated_at=CURRENT_TIMESTAMP WHERE id=? AND status='pending'")->execute([$chargeId]);
            memberChargeAdminEvent($pdo,$chargeId,'payer_receipt_fully_allocated','pending','paid',$actorId,$reason,['receipt_id'=>$receiptId,'amount_minor'=>$part,'total_allocated_minor'=>$already+$part,'payment_id'=>(int)$payment['id']]);
        } else {
            memberChargeAdminEvent($pdo,$chargeId,'payer_receipt_part_allocated','pending','pending',$actorId,$reason,['receipt_id'=>$receiptId,'amount_minor'=>$part,'total_allocated_minor'=>$already+$part]);
        }
    }
    return ['receipt_id'=>$receiptId,'allocated_minor'=>$new,'remaining_minor'=>$remaining-$new];
}

function memberPayerReceiptEvent(PDO $pdo,int $receiptId,int $actorId,string $action,string $reason,array $snapshot,?string $requestKey=null): void
{
    $pdo->prepare('INSERT INTO member_payer_receipt_events(receipt_id,actor_trainer_id,action,request_key,reason,snapshot_json) VALUES(?,?,?,?,?,?)')->execute([$receiptId,$actorId,$action,$requestKey,$reason,json_encode($snapshot,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
}

/** @return list<array<string,mixed>> */
function memberPayerReceiptOpenCharges(PDO $pdo,int $payerId): array
{
    $sql="SELECT c.id,c.sportovec_id,s.jmeno,s.prijmeni,c.public_code,c.title_snapshot,c.due_on,c.amount_minor,c.currency,c.status FROM club_member_charges c JOIN sportovci s ON s.id=c.sportovec_id JOIN payments p ON p.payable_type='member_charge' AND p.payable_id=c.id WHERE c.payer_account_id=? AND c.status='pending' AND c.currency='CZK' AND p.status='pending' AND p.method='bank_transfer' ORDER BY c.due_on,c.id";
    $query=$pdo->prepare($sql);$query->execute([$payerId]);$rows=$query->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) $row['remaining_minor']=max(0,(int)$row['amount_minor']-clubFinanceChargeAllocatedMinor($pdo,(int)$row['id']));
    unset($row);
    return array_values(array_filter($rows,static fn(array $row):bool=>$row['remaining_minor']>0));
}

/** @return list<array<string,mixed>> */
function memberPayerReceiptRecent(PDO $pdo,int $payerId,int $limit=30): array
{
    $limit=max(1,min(100,$limit));
    $query=$pdo->prepare('SELECT r.*,COALESCE(a.allocated_minor,0) AS allocated_minor FROM member_payer_receipts r LEFT JOIN (SELECT receipt_id,SUM(amount_minor) AS allocated_minor FROM member_payer_receipt_allocations GROUP BY receipt_id) a ON a.receipt_id=r.id WHERE r.payer_account_id=? ORDER BY r.paid_on DESC,r.id DESC LIMIT '.$limit);
    $query->execute([$payerId]);return $query->fetchAll(PDO::FETCH_ASSOC);
}
