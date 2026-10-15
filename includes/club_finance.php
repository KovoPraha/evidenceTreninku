<?php
declare(strict_types=1);

final class ClubFinanceException extends RuntimeException {}

function clubFinanceTableExists(PDO $pdo, string $table): bool
{
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $s=$pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $s->execute([$table]);
        return (bool)$s->fetchColumn();
    }
    $s=$pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
    $s->execute([$table]);
    return (bool)$s->fetchColumn();
}

function clubFinanceColumnExists(PDO $pdo, string $table, string $column): bool
{
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $s=$pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $s->execute([$table,$column]);
        return (bool)$s->fetchColumn();
    }
    foreach ($pdo->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC) as $row) if ($row['name']===$column) return true;
    return false;
}

/** The rollout starts in observation mode. A missing migration never activates restrictions. */
function clubFinanceSettings(PDO $pdo): array
{
    $defaults=['enforcement_enabled'=>0,'grace_days'=>14,'registration_limit_minor'=>200000,'training_notice_minor'=>500000];
    if (!clubFinanceTableExists($pdo,'club_finance_settings')) return $defaults;
    $row=$pdo->query('SELECT * FROM club_finance_settings WHERE id=1')->fetch(PDO::FETCH_ASSOC);
    if (!$row) return $defaults;
    foreach ($defaults as $key=>$value) $defaults[$key]=(int)$row[$key];
    return $defaults;
}

function clubFinanceSaveSettings(PDO $pdo,int $actorId,array $input,string $reason,bool $confirmed):array
{
    $reason=trim($reason);
    $days=(int)($input['grace_days']??-1);
    $block=(int)($input['registration_limit_minor']??-1);
    $notice=(int)($input['training_notice_minor']??-1);
    $enabled=!empty($input['enforcement_enabled'])?1:0;
    if (!$confirmed||$actorId<1||$days<0||$days>365||$block<0||$block>1000000000||$notice<0||$notice>1000000000||mb_strlen($reason,'UTF-8')<5||mb_strlen($reason,'UTF-8')>1000) {
        throw new InvalidArgumentException('Zadejte platné limity, lhůtu 0–365 dní, důvod a potvrzení.');
    }
    if (!clubFinanceTableExists($pdo,'club_finance_settings')) throw new ClubFinanceException('Finanční nastavení ještě není migrováno.');
    $pdo->beginTransaction();
    try {
        $sql='SELECT * FROM club_finance_settings WHERE id=1'.((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'');
        $before=$pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
        if (!$before) throw new ClubFinanceException('Finanční nastavení chybí.');
        $after=['enforcement_enabled'=>$enabled,'grace_days'=>$days,'registration_limit_minor'=>$block,'training_notice_minor'=>$notice];
        $pdo->prepare('UPDATE club_finance_settings SET enforcement_enabled=?,grace_days=?,registration_limit_minor=?,training_notice_minor=?,updated_by_trainer_id=?,updated_at=CURRENT_TIMESTAMP WHERE id=1')
            ->execute([$enabled,$days,$block,$notice,$actorId]);
        $pdo->prepare('INSERT INTO club_finance_setting_events(actor_trainer_id,before_json,after_json,reason) VALUES(?,?,?,?)')
            ->execute([$actorId,json_encode($before,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),json_encode($after,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$reason]);
        $pdo->commit();
        return $after;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/** A purchase without a beneficiary belongs to the only approved athlete on the buying account. */
function clubFinanceOnlyAthleteForAccount(PDO $pdo,int $accountId):?int
{
    if ($accountId<1||!clubFinanceTableExists($pdo,'account_person_roles')) return null;
    $s=$pdo->prepare("SELECT DISTINCT sportovec_id FROM account_person_roles WHERE account_id=? AND status='approved' AND relation_role IN ('self','guardian') AND valid_from<=CURRENT_TIMESTAMP AND (valid_to IS NULL OR valid_to>CURRENT_TIMESTAMP)");
    $s->execute([$accountId]);
    $ids=array_values(array_unique(array_map('intval',$s->fetchAll(PDO::FETCH_COLUMN))));
    return count($ids)===1?$ids[0]:null;
}

/** A multi-athlete checkout must name one financial owner; guest-only accounts remain unassigned. */
function clubFinanceOrderAthlete(PDO $pdo,int $accountId,?int $requested):?int
{
    if (!clubFinanceColumnExists($pdo,'shop_orders','finance_sportovec_id')||!clubFinanceTableExists($pdo,'account_person_roles')) return null;
    $s=$pdo->prepare("SELECT DISTINCT sportovec_id FROM account_person_roles WHERE account_id=? AND status='approved' AND relation_role IN ('self','guardian') AND valid_from<=CURRENT_TIMESTAMP AND (valid_to IS NULL OR valid_to>CURRENT_TIMESTAMP)");
    $s->execute([$accountId]);$ids=array_values(array_unique(array_map('intval',$s->fetchAll(PDO::FETCH_COLUMN))));
    if ($ids===[]) {
        if ($requested!==null) throw new ClubFinanceException('Zvolený sportovec není propojený s účtem.');
        return null;
    }
    if (count($ids)===1) {
        if ($requested!==null&&$requested!==$ids[0]) throw new ClubFinanceException('Zvolený sportovec není propojený s účtem.');
        return $ids[0];
    }
    if ($requested===null||!in_array($requested,$ids,true)) throw new ClubFinanceException('U společného účtu vyberte sportovce, ke kterému se objednávka přiřadí.');
    return $requested;
}

/** Allocations to an unpaid item cancelled later become reusable credit; refund-required items do not. */
function clubFinanceAvailableReceipts(PDO $pdo,int $sportovecId,?DateTimeImmutable $asOf=null,bool $lock=false):array
{
    if (!clubFinanceTableExists($pdo,'club_finance_receipts')||!clubFinanceTableExists($pdo,'club_finance_allocations')) return [];
    $asOf=$asOf??new DateTimeImmutable('now',new DateTimeZone('Europe/Prague'));
    $sql="SELECT id,amount_minor FROM club_finance_receipts WHERE sportovec_id=? AND currency='CZK' ORDER BY paid_on,id";
    if ($lock&&(string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql') $sql.=' FOR UPDATE';
    $s=$pdo->prepare($sql);$s->execute([$sportovecId]);$receipts=$s->fetchAll(PDO::FETCH_ASSOC);
    $alloc=$pdo->prepare('SELECT a.amount_minor,c.status AS charge_status,o.payment_status AS order_payment_status,o.payment_expires_at FROM club_finance_allocations a LEFT JOIN club_member_charges c ON c.id=a.charge_id LEFT JOIN shop_orders o ON o.id=a.order_id WHERE a.receipt_id=?');
    $available=[];
    foreach ($receipts as $receipt) {
        $used=0;$alloc->execute([(int)$receipt['id']]);
        foreach ($alloc->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($row['charge_status']==='cancelled'||$row['order_payment_status']==='cancelled') continue;
            if ($row['order_payment_status']==='pending'&&!empty($row['payment_expires_at'])&&(string)$row['payment_expires_at']<=$asOf->format('Y-m-d H:i:s')) continue;
            $used+=(int)$row['amount_minor'];
        }
        $rest=max(0,(int)$receipt['amount_minor']-$used);
        if ($rest>0) $available[]=['id'=>(int)$receipt['id'],'amount_minor'=>$rest];
    }
    return $available;
}

/** @return array{open_minor:int,overdue_minor:int,credit_minor:int,items:list<array<string,mixed>>,as_of:string} */
function clubFinanceBalanceForAthlete(PDO $pdo,int $sportovecId,?DateTimeImmutable $asOf=null):array
{
    if ($sportovecId<1) throw new InvalidArgumentException('Neplatný sportovec.');
    $asOf=$asOf??new DateTimeImmutable('now',new DateTimeZone('Europe/Prague'));
    $today=$asOf->format('Y-m-d');$settings=clubFinanceSettings($pdo);
    $items=[];$open=0;$overdue=0;
    if (clubFinanceTableExists($pdo,'club_member_charges')) {
        $allocationJoin=clubFinanceTableExists($pdo,'club_finance_allocations')?' LEFT JOIN (SELECT charge_id,SUM(amount_minor) AS allocated_minor FROM club_finance_allocations WHERE charge_id IS NOT NULL GROUP BY charge_id) a ON a.charge_id=c.id':' ';
        $allocationSelect=clubFinanceTableExists($pdo,'club_finance_allocations')?'COALESCE(a.allocated_minor,0)':'0';
        $s=$pdo->prepare("SELECT c.id,c.title_snapshot,c.amount_minor,c.currency,c.due_on,$allocationSelect AS allocated_minor FROM club_member_charges c $allocationJoin WHERE c.sportovec_id=? AND c.status='pending' ORDER BY c.due_on,c.id");
        $s->execute([$sportovecId]);
        foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($row['currency']!=='CZK') continue;
            $minor=max(0,(int)$row['amount_minor']-(int)$row['allocated_minor']);$open+=$minor;
            $isOverdue=clubFinancePastGrace((string)($row['due_on']??''),$today,$settings['grace_days']);
            if ($isOverdue) $overdue+=$minor;
            $items[]=['kind'=>'member_charge','id'=>(int)$row['id'],'title'=>(string)$row['title_snapshot'],'amount_minor'=>$minor,'due_on'=>$row['due_on'],'past_grace'=>$isOverdue];
        }
    }
    if (clubFinanceTableExists($pdo,'shop_orders')&&clubFinanceTableExists($pdo,'payments')) {
        $hasAttribution=clubFinanceColumnExists($pdo,'shop_orders','finance_sportovec_id');
        $selected=$hasAttribution?'o.finance_sportovec_id':'NULL AS finance_sportovec_id';
        $expiration=clubFinanceColumnExists($pdo,'shop_orders','payment_expires_at')?'o.payment_expires_at':'NULL AS payment_expires_at';
        $allocationJoin=clubFinanceTableExists($pdo,'club_finance_allocations')?' LEFT JOIN (SELECT order_id,SUM(amount_minor) AS allocated_minor FROM club_finance_allocations WHERE order_id IS NOT NULL GROUP BY order_id) a ON a.order_id=o.id':' ';
        $allocationSelect=clubFinanceTableExists($pdo,'club_finance_allocations')?'COALESCE(a.allocated_minor,0)':'0';
        $s=$pdo->query("SELECT o.id,o.account_id,o.public_code,o.total_minor,o.currency,o.status,o.payment_status,$expiration,$selected,p.due_at,$allocationSelect AS allocated_minor FROM shop_orders o JOIN payments p ON p.payable_type='shop_order' AND p.payable_id=o.id $allocationJoin WHERE o.status='placed' AND o.payment_status='pending' AND p.status='pending'");
        foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($row['currency']!=='CZK') continue;
            $owner=$row['finance_sportovec_id']===null?clubFinanceOnlyAthleteForAccount($pdo,(int)$row['account_id']):(int)$row['finance_sportovec_id'];
            if ($owner!==$sportovecId) continue;
            // An expired order is cancelled, not an enforceable debt, even if the expiry worker is late.
            if (!empty($row['payment_expires_at'])&&(string)$row['payment_expires_at']<=$asOf->format('Y-m-d H:i:s')) continue;
            $minor=max(0,(int)$row['total_minor']-(int)$row['allocated_minor']);$open+=$minor;
            $isOverdue=clubFinancePastGrace(substr((string)$row['due_at'],0,10),$today,$settings['grace_days']);
            if ($isOverdue) $overdue+=$minor;
            $items[]=['kind'=>'shop_order','id'=>(int)$row['id'],'title'=>'Objednávka '.(string)$row['public_code'],'amount_minor'=>$minor,'due_on'=>substr((string)$row['due_at'],0,10),'past_grace'=>$isOverdue];
        }
    }
    $credit=0;
    foreach (clubFinanceAvailableReceipts($pdo,$sportovecId,$asOf) as $receipt) $credit+=$receipt['amount_minor'];
    return ['open_minor'=>max(0,$open-$credit),'overdue_minor'=>max(0,$overdue-$credit),'credit_minor'=>max(0,$credit-$open),'items'=>$items,'as_of'=>$asOf->format('Y-m-d H:i:s')];
}

function clubFinancePastGrace(string $dueOn,string $today,int $days):bool
{
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/D',$dueOn)!==1) return false;
    try { return $today>(new DateTimeImmutable($dueOn))->modify('+'.$days.' days')->format('Y-m-d'); }
    catch (Throwable) { return false; }
}

/** @return array{allowed:bool,overdue_minor:int,limit_minor:int,reason:string} */
function clubFinanceRegistrationDecision(PDO $pdo,int $sportovecId):array
{
    $settings=clubFinanceSettings($pdo);
    if (!$settings['enforcement_enabled']) return ['allowed'=>true,'overdue_minor'=>0,'limit_minor'=>$settings['registration_limit_minor'],'reason'=>'Finanční omezení není zapnuté.'];
    $balance=clubFinanceBalanceForAthlete($pdo,$sportovecId);
    $allowed=$balance['overdue_minor']<=$settings['registration_limit_minor'];
    return ['allowed'=>$allowed,'overdue_minor'=>$balance['overdue_minor'],'limit_minor'=>$settings['registration_limit_minor'],
        'reason'=>$allowed?'Finanční podmínka je splněna.':'Přihlášení nyní není dostupné: neuhrazená částka po lhůtě překračuje limit. Přehled a pomoc najdete ve svém sportovním profilu.'];
}

function clubFinanceAssertCanRegister(PDO $pdo,int $sportovecId):void
{
    $decision=clubFinanceRegistrationDecision($pdo,$sportovecId);
    if (!$decision['allowed']) throw new ClubFinanceException($decision['reason']);
}

/** Apply existing overpayment to a new monthly charge in the caller's transaction. */
function clubFinanceAllocateCreditToCharge(PDO $pdo,int $sportovecId,int $chargeId,int $actorId,string $reason):int
{
    if (!$pdo->inTransaction()||$sportovecId<1||$chargeId<1) throw new InvalidArgumentException('Přidělení přeplatku vyžaduje otevřenou transakci a předpis.');
    if (!clubFinanceTableExists($pdo,'club_finance_receipts')) return 0;
    require_once __DIR__.'/member_charge_admin.php';
    $s=$pdo->prepare("SELECT amount_minor,status FROM club_member_charges WHERE id=? AND sportovec_id=? AND currency='CZK'");$s->execute([$chargeId,$sportovecId]);$charge=$s->fetch(PDO::FETCH_ASSOC);
    if (!$charge||$charge['status']!=='pending') return 0;
    $s=$pdo->prepare("SELECT id,status FROM payments WHERE payable_type='member_charge' AND payable_id=?");$s->execute([$chargeId]);$payment=$s->fetch(PDO::FETCH_ASSOC);
    if (!$payment||$payment['status']!=='pending') throw new ClubFinanceException('Předpis nemá konzistentní čekající platbu.');
    $s=$pdo->prepare('SELECT COALESCE(SUM(amount_minor),0) FROM club_finance_allocations WHERE charge_id=?');$s->execute([$chargeId]);
    $need=max(0,(int)$charge['amount_minor']-(int)$s->fetchColumn());$applied=0;
    foreach (clubFinanceAvailableReceipts($pdo,$sportovecId,null,true) as $receipt) {
        if ($need<1) break;
        $part=min($need,$receipt['amount_minor']);
        $pdo->prepare('INSERT INTO club_finance_allocations(receipt_id,charge_id,amount_minor) VALUES(?,?,?)')->execute([$receipt['id'],$chargeId,$part]);
        $need-=$part;$applied+=$part;
    }
    if ($need===0) {
        $pdo->prepare("UPDATE payments SET status='paid',paid_at=CURRENT_TIMESTAMP,confirmed_by_trainer_id=?,confirmation_note=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND status='pending'")
            ->execute([$actorId,'Uhrazeno z dřívějšího ověřeného přeplatku.',(int)$payment['id']]);
        $pdo->prepare("UPDATE club_member_charges SET status='paid',updated_at=CURRENT_TIMESTAMP WHERE id=? AND status='pending'")->execute([$chargeId]);
        memberChargeAdminEvent($pdo,$chargeId,'credit_fully_allocated','pending','paid',$actorId,$reason,['allocated_minor'=>$applied]);
    }
    return $applied;
}

function clubFinanceChargeAllocatedMinor(PDO $pdo,int $chargeId):int
{
    if (!clubFinanceTableExists($pdo,'club_finance_allocations')) return 0;
    $s=$pdo->prepare('SELECT COALESCE(SUM(amount_minor),0) FROM club_finance_allocations WHERE charge_id=?');$s->execute([$chargeId]);
    return (int)$s->fetchColumn();
}

function clubFinanceAssertBankReferenceUnused(PDO $pdo,string $reference,int $amountMinor,string $paidOn):void
{
    $duplicate=$pdo->prepare('SELECT 1 FROM club_finance_receipts WHERE reference=?');$duplicate->execute([$reference]);
    if ($duplicate->fetchColumn()) throw new ClubFinanceException('Tato bankovní reference již byla zapsána. Platbu nelze zaúčtovat znovu.');
    if (clubFinanceColumnExists($pdo,'payments','bank_movement_id')) {
        $matched=$pdo->prepare('SELECT 1 FROM payments WHERE bank_movement_id=?');$matched->execute([$reference]);
        if ($matched->fetchColumn()) throw new ClubFinanceException('Tato bankovní platba již byla přiřazena jiné úhradě.');
    }
    if (clubFinanceTableExists($pdo,'fio_account_movements')) {
        $movement=$pdo->prepare('SELECT amount_minor,currency,booked_on FROM fio_account_movements WHERE fio_movement_id=?');$movement->execute([$reference]);$row=$movement->fetch(PDO::FETCH_ASSOC);
        if ($row&&((int)$row['amount_minor']!==$amountMinor||(string)$row['currency']!=='CZK'||(string)$row['booked_on']!==$paidOn)) throw new ClubFinanceException('Bankovní reference existuje, ale datum nebo částka nesouhlasí s Fio výpisem.');
    }
}

/**
 * An administrator-confirmed bank receipt is allocated to oldest open items.
 * Residual value remains a visible overpayment. The bank import itself remains
 * review-first and does not call this method automatically.
 * @return array{receipt_id:int,allocated_minor:int,credit_minor:int}
 */
function clubFinanceRecordBankReceipt(PDO $pdo,int $sportovecId,int $amountMinor,string $reference,string $paidOn,int $actorId,string $reason,bool $confirmed):array
{
    $reference=trim($reference);$reason=trim($reason);
    $date=DateTimeImmutable::createFromFormat('!Y-m-d',$paidOn,new DateTimeZone('Europe/Prague'));
    if (!$confirmed||$sportovecId<1||$amountMinor<1||$actorId<1||preg_match('/^[A-Za-z0-9._:\/-]{6,100}$/D',$reference)!==1
        ||!$date||$date->format('Y-m-d')!==$paidOn||$paidOn>(new DateTimeImmutable('today',new DateTimeZone('Europe/Prague')))->format('Y-m-d')
        ||mb_strlen($reason,'UTF-8')<5||mb_strlen($reason,'UTF-8')>1000) {
        throw new InvalidArgumentException('Přijatá platba vyžaduje sportovce, kladnou částku, platné datum, jedinečnou bankovní referenci, důvod a potvrzení.');
    }
    if (!clubFinanceTableExists($pdo,'club_finance_receipts')) throw new ClubFinanceException('Evidence hromadných plateb ještě není migrována.');
    require_once __DIR__.'/member_charge_admin.php';
    require_once __DIR__.'/shop_checkout.php';
    $pdo->beginTransaction();
    try {
        $sql='SELECT id FROM sportovci WHERE id=?'.((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'');
        $person=$pdo->prepare($sql);$person->execute([$sportovecId]);
        if (!$person->fetchColumn()) throw new ClubFinanceException('Sportovec nebyl nalezen.');
        clubFinanceAssertBankReferenceUnused($pdo,$reference,$amountMinor,$paidOn);
        $pdo->prepare("INSERT INTO club_finance_receipts(sportovec_id,plan_id,period_start,kind,reference,amount_minor,currency,paid_on,confirmed_by_trainer_id,reason) VALUES(?,NULL,NULL,'manual_bank',? ,?,'CZK',?,?,?)")
            ->execute([$sportovecId,$reference,$amountMinor,$paidOn,$actorId,$reason]);
        $receiptId=(int)$pdo->lastInsertId();
        $items=clubFinanceBalanceForAthlete($pdo,$sportovecId)['items'];
        usort($items,static fn(array $a,array $b):int=>[(string)$a['due_on'],$a['kind'],$a['id']]<=>[(string)$b['due_on'],$b['kind'],$b['id']]);
        foreach ($items as $item) {
            $need=(int)$item['amount_minor'];
            if ($need<1) continue;
            if ($item['kind']==='member_charge') {
                $chargeId=(int)$item['id'];
                $applied=clubFinanceAllocateCreditToCharge($pdo,$sportovecId,$chargeId,$actorId,$reason);
                if ($applied===$need) {
                    // The helper already completed the canonical charge transition.
                }
            } else {
                $orderId=(int)$item['id'];
                $payment=$pdo->prepare("SELECT id,method,status FROM payments WHERE payable_type='shop_order' AND payable_id=?");$payment->execute([$orderId]);$payment=$payment->fetch(PDO::FETCH_ASSOC);
                if (!$payment||$payment['status']!=='pending'||$payment['method']!=='bank_transfer') continue;
                $applied=0;
                foreach (clubFinanceAvailableReceipts($pdo,$sportovecId,null,true) as $available) {
                    if ($applied===$need) break;
                    $part=min($need-$applied,$available['amount_minor']);
                    $pdo->prepare('INSERT INTO club_finance_allocations(receipt_id,order_id,amount_minor) VALUES(?,?,?)')->execute([$available['id'],$orderId,$part]);
                    $applied+=$part;
                }
                if ($applied===$need) shopOrderConfirmPaymentInTransaction($pdo,(int)$payment['id'],'bank_transfer','trainer',$actorId,'Uhrazeno z ověřených příjmů včetně #'.$receiptId.'. '.$reason);
            }
        }
        $remaining=0;
        foreach (clubFinanceAvailableReceipts($pdo,$sportovecId) as $available) if ($available['id']===$receiptId) $remaining=$available['amount_minor'];
        $pdo->commit();
        return ['receipt_id'=>$receiptId,'allocated_minor'=>$amountMinor-$remaining,'credit_minor'=>$remaining];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($e instanceof InvalidArgumentException||$e instanceof ClubFinanceException) throw $e;
        error_log('club finance receipt: '.$e->getMessage());
        throw new ClubFinanceException('Přijatou platbu se nepodařilo uložit bez částečného zápisu.',0,$e);
    }
}
