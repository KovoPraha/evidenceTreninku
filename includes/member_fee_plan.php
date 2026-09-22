<?php
declare(strict_types=1);

require_once __DIR__ . '/member_charge_admin.php';

final class MemberFeePlanException extends RuntimeException {}

function memberFeePlanMonth(string $period): array
{
    if (preg_match('/^(20[0-9]{2})-(0[1-9]|1[0-2])$/D', $period) !== 1) throw new InvalidArgumentException('Období musí mít formát RRRR-MM.');
    $from = new DateTimeImmutable($period . '-01');
    return [$from->format('Y-m-d'), $from->modify('last day of this month')->format('Y-m-d')];
}

function memberFeePlanText(string $value, int $max, string $label): string
{
    $value = trim($value);
    if ($value === '' || mb_strlen($value, 'UTF-8') > $max || preg_match('/[<>]/u', $value) === 1) throw new InvalidArgumentException($label . ' není platný prostý text.');
    return $value;
}

/** @return array{id:int} */
function memberFeePlanCreate(PDO $pdo, int $actorId, array $input, string $reason, bool $confirmed): array
{
    $teamId = (int)($input['team_id'] ?? 0);
    $name = memberFeePlanText((string)($input['name'] ?? ''), 160, 'Název plánu');
    $title = memberFeePlanText((string)($input['charge_title'] ?? ''), 255, 'Název předpisu');
    $amount = (int)($input['amount_minor'] ?? 0);
    $dueDay = (int)($input['due_day'] ?? 0);
    $startsOn = memberChargeAdminDate((string)($input['starts_on'] ?? ''), true);
    $endsOn = memberChargeAdminDate((string)($input['ends_on'] ?? ''));
    $reason = memberChargeAdminReason($reason);
    if (!$confirmed || $actorId < 1 || $teamId < 1 || $amount < 1 || $amount > 100000000 || $dueDay < 1 || $dueDay > 28 || ($endsOn !== null && $endsOn < $startsOn)) throw new InvalidArgumentException('Plán vyžaduje soupisku, částku, den splatnosti 1–28, platnost a potvrzení.');
    $team = $pdo->prepare("SELECT 1 FROM club_teams WHERE id=? AND status='active'");$team->execute([$teamId]);
    if (!$team->fetchColumn()) throw new MemberFeePlanException('Aktivní soupiska nebyla nalezena.');
    $pdo->beginTransaction();
    try {
        $pdo->prepare("INSERT INTO member_fee_plans(team_id,name,charge_title,amount_minor,currency,due_day,starts_on,ends_on,status,created_by_trainer_id) VALUES(?,?,?,?, 'CZK',?,?,?,'active',?)")
            ->execute([$teamId,$name,$title,$amount,$dueDay,$startsOn,$endsOn,$actorId]);
        $id = (int)$pdo->lastInsertId();
        $pdo->commit();
        return ['id'=>$id];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof InvalidArgumentException || $exception instanceof MemberFeePlanException) throw $exception;
        throw new MemberFeePlanException('Plán se nepodařilo založit bez částečného zápisu.', 0, $exception);
    }
}

function memberFeePlanAddExclusion(PDO $pdo, int $planId, int $sportovecId, int $actorId, string $from, ?string $to, string $reason, bool $confirmed): void
{
    $from = memberChargeAdminDate($from, true);$to = memberChargeAdminDate((string)$to);$reason = memberChargeAdminReason($reason);
    if (!$confirmed || min($planId,$sportovecId,$actorId) < 1 || ($to !== null && $to < $from)) throw new InvalidArgumentException('Výjimka vyžaduje osobu, platnost, důvod a potvrzení.');
    $pdo->prepare('INSERT INTO member_fee_plan_exclusions(plan_id,sportovec_id,valid_from,valid_to,reason,created_by_trainer_id) VALUES(?,?,?,?,?,?)')->execute([$planId,$sportovecId,$from,$to,$reason,$actorId]);
}

/** @return array<string,mixed>|false */
function memberFeePlanRow(PDO $pdo, int $planId, bool $lock = false): array|false
{
    $sql = 'SELECT p.*,t.name AS team_name FROM member_fee_plans p JOIN club_teams t ON t.id=p.team_id WHERE p.id=?';
    if ($lock && (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $sql .= ' FOR UPDATE';
    $statement=$pdo->prepare($sql);$statement->execute([$planId]);return $statement->fetch(PDO::FETCH_ASSOC);
}

function memberFeePlanPayer(PDO $pdo, int $sportovecId, string $periodFrom, string $periodTo): ?int
{
    $statement=$pdo->prepare("SELECT a.id FROM account_person_roles r JOIN verejni_uzivatele a ON a.id=r.account_id WHERE r.sportovec_id=? AND r.status='approved' AND r.relation_role IN ('self','guardian') AND a.aktivni=1 AND a.email_overeno=1 AND r.valid_from<=? AND (r.valid_to IS NULL OR r.valid_to>?) ORDER BY CASE r.relation_role WHEN 'self' THEN 0 ELSE 1 END,r.id LIMIT 1");
    $statement->execute([$sportovecId,$periodTo.' 23:59:59',$periodFrom.' 00:00:00']);$value=$statement->fetchColumn();return$value===false?null:(int)$value;
}

/** @return array{plan:array<string,mixed>,period:string,period_from:string,period_to:string,due_on:string,rows:list<array<string,mixed>>,fingerprint:string,ready_count:int,skipped_count:int} */
function memberFeePlanPreview(PDO $pdo, int $planId, string $period): array
{
    [$periodFrom,$periodTo]=memberFeePlanMonth($period);$plan=memberFeePlanRow($pdo,$planId);
    if(!$plan||$plan['status']!=='active')throw new MemberFeePlanException('Aktivní plán nebyl nalezen.');
    if((string)$plan['starts_on']>$periodTo||(!empty($plan['ends_on'])&&(string)$plan['ends_on']<$periodFrom))throw new MemberFeePlanException('Plán v tomto měsíci není platný.');
    $dueOn=$period.'-'.str_pad((string)(int)$plan['due_day'],2,'0',STR_PAD_LEFT);
    $members=$pdo->prepare("SELECT m.sportovec_id,s.jmeno,s.prijmeni FROM club_roster_members m JOIN sportovci s ON s.id=m.sportovec_id WHERE m.team_id=? AND m.status='active' AND m.valid_from<=? AND (m.valid_to IS NULL OR m.valid_to>=?) ORDER BY s.prijmeni,s.jmeno,s.id");
    $members->execute([(int)$plan['team_id'],$periodTo,$periodFrom]);$rows=[];$ready=0;$skipped=0;
    foreach($members->fetchAll(PDO::FETCH_ASSOC)as$member){$personId=(int)$member['sportovec_id'];$exclusion=$pdo->prepare('SELECT reason FROM member_fee_plan_exclusions WHERE plan_id=? AND sportovec_id=? AND valid_from<=? AND (valid_to IS NULL OR valid_to>=?) ORDER BY id DESC LIMIT 1');$exclusion->execute([$planId,$personId,$periodTo,$periodFrom]);$excluded=$exclusion->fetchColumn();$payer=memberFeePlanPayer($pdo,$personId,$periodFrom,$periodTo);$external='feeplan:'.$planId.':'.$period.':'.$personId;$existing=$pdo->prepare("SELECT id FROM club_member_charges WHERE source_system='member_fee_plan' AND source_external_id=?");$existing->execute([$external]);$charge=$existing->fetchColumn();
        $result='ready';$detail='Připraveno k vytvoření.';if($excluded!==false){$result='excluded';$detail='Výjimka: '.$excluded;}elseif($charge!==false){$result='existing';$detail='Předpis již existuje.';}elseif($payer===null){$result='missing_payer';$detail='Chybí aktivní ověřený účet plátce.';}
        if($result==='ready')$ready++;else$skipped++;$rows[]=['sportovec_id'=>$personId,'name'=>(string)$member['prijmeni'].' '.(string)$member['jmeno'],'payer_account_id'=>$payer,'external_id'=>$external,'result'=>$result,'detail'=>$detail,'existing_charge_id'=>$charge===false?null:(int)$charge];}
    $fingerprint=hash('sha256',json_encode(['plan_id'=>$planId,'period'=>$period,'plan_updated_at'=>$plan['updated_at'],'rows'=>$rows],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    return['plan'=>$plan,'period'=>$period,'period_from'=>$periodFrom,'period_to'=>$periodTo,'due_on'=>$dueOn,'rows'=>$rows,'fingerprint'=>$fingerprint,'ready_count'=>$ready,'skipped_count'=>$skipped];
}

/** @return array{id:int,generated_count:int,skipped_count:int,idempotent:bool} */
function memberFeePlanGenerate(PDO $pdo,int $planId,string $period,int $actorId,string $reason,string $fingerprint,bool $confirmed):array
{
    $reason=memberChargeAdminReason($reason);if(!$confirmed||min($planId,$actorId)<1||preg_match('/^[a-f0-9]{64}$/D',$fingerprint)!==1)throw new InvalidArgumentException('Vytvoření vyžaduje náhled, hospodáře, důvod a potvrzení.');
    $pdo->beginTransaction();try{$existing=$pdo->prepare('SELECT id,generated_count,skipped_count FROM member_fee_plan_runs WHERE plan_id=? AND period_key=?');$existing->execute([$planId,$period]);$run=$existing->fetch(PDO::FETCH_ASSOC);if($run){$pdo->commit();return['id'=>(int)$run['id'],'generated_count'=>(int)$run['generated_count'],'skipped_count'=>(int)$run['skipped_count'],'idempotent'=>true];}
        if(!memberFeePlanRow($pdo,$planId,true))throw new MemberFeePlanException('Plán nebyl nalezen.');$preview=memberFeePlanPreview($pdo,$planId,$period);if(!hash_equals($preview['fingerprint'],$fingerprint))throw new MemberFeePlanException('Soupiska nebo plátci se od náhledu změnili. Obnovte náhled.');$settings=shopBankSettingsEffective($pdo);$created=[];
        foreach($preview['rows']as&$row){if($row['result']!=='ready')continue;do{$code=memberChargeAdminPublicCode();$check=$pdo->prepare('SELECT 1 FROM club_member_charges WHERE public_code=?');$check->execute([$code]);}while($check->fetchColumn());$values=['amount_minor'=>(int)$preview['plan']['amount_minor'],'currency'=>(string)$preview['plan']['currency'],'due_on'=>$preview['due_on']];$pdo->prepare("INSERT INTO club_member_charges(sportovec_id,payer_account_id,public_code,charge_type,title_snapshot,period_from,period_to,amount_minor,currency,due_on,status,source_system,source_external_id,source_import_run_id) VALUES(?,?,?,'membership',?,?,?,?,?,?,'pending','member_fee_plan',?,NULL)")->execute([$row['sportovec_id'],$row['payer_account_id'],$code,$preview['plan']['charge_title'],$preview['period_from'],$preview['period_to'],$values['amount_minor'],$values['currency'],$values['due_on'],$row['external_id']]);$chargeId=(int)$pdo->lastInsertId();$payment=memberChargeAdminInsertPayment($pdo,$chargeId,$values,$settings,$row['external_id'],$actorId);memberChargeAdminEvent($pdo,$chargeId,'fee_plan_generate',null,'pending',$actorId,$reason,['plan_id'=>$planId,'period'=>$period,'payment_id'=>$payment['id']]);$row['charge_id']=$chargeId;$row['result']='created';$row['detail']='Předpis a platební údaje vytvořeny.';$created[]=$chargeId;}unset($row);
        $pdo->prepare('INSERT INTO member_fee_plan_runs(plan_id,period_key,preview_fingerprint,generated_count,skipped_count,actor_trainer_id,reason) VALUES(?,?,?,?,?,?,?)')->execute([$planId,$period,$fingerprint,count($created),(int)$preview['skipped_count'],$actorId,$reason]);$runId=(int)$pdo->lastInsertId();foreach($preview['rows']as$row)$pdo->prepare('INSERT INTO member_fee_plan_run_items(run_id,sportovec_id,payer_account_id,charge_id,result,detail) VALUES(?,?,?,?,?,?)')->execute([$runId,$row['sportovec_id'],$row['payer_account_id'],$row['charge_id']??$row['existing_charge_id'],$row['result'],$row['detail']]);$pdo->commit();return['id'=>$runId,'generated_count'=>count($created),'skipped_count'=>(int)$preview['skipped_count'],'idempotent'=>false];
    }catch(Throwable$exception){if($pdo->inTransaction())$pdo->rollBack();if($exception instanceof InvalidArgumentException||$exception instanceof MemberFeePlanException||$exception instanceof MemberChargeAdminException||$exception instanceof ShopCheckoutException)throw$exception;throw new MemberFeePlanException('Měsíční předpisy se nepodařilo vytvořit bez částečného zápisu.',0,$exception);}
}
