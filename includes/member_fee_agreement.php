<?php
declare(strict_types=1);

function memberFeeAgreementAvailable(PDO $pdo):bool {return clubProgramRuntimeTableExists($pdo,'member_fee_agreements');}

/** Temporal payer agreements are explicit; an authorized parent is not implicitly the payer. */
function memberFeeAgreementPayer(PDO $pdo,int $planId,int $person,string $from,string $to):?int {
 $q=$pdo->prepare('SELECT * FROM member_fee_agreements WHERE plan_id=? AND sportovec_id=? AND valid_from<=? AND valid_to>=? ORDER BY id DESC');$q->execute([$planId,$person,$to,$from]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
 if($rows===[])return null;
 // A later agreement may replace an interval, but a mid-period payer change needs a split decision.
 $selected=null;
 for($day=new DateTimeImmutable($from),$last=new DateTimeImmutable($to);$day<=$last;$day=$day->modify('+1 day')) {
  $payer=null;foreach($rows as$r)if($r['valid_from']<=$day->format('Y-m-d')&&$r['valid_to']>=$day->format('Y-m-d')){$payer=(int)$r['payer_account_id'];break;}
  if($payer===null||($selected!==null&&$selected!==$payer))return null;$selected=$payer;
 }
 $q=$pdo->prepare("SELECT DISTINCT a.id FROM verejni_uzivatele a JOIN account_person_roles r ON r.account_id=a.id WHERE a.id=? AND r.sportovec_id=? AND a.aktivni=1 AND a.email_overeno=1 AND r.status='approved' AND r.relation_role IN ('self','guardian') AND r.valid_from<=? AND (r.valid_to IS NULL OR r.valid_to>?)".($pdo->inTransaction()?shopInventoryLock($pdo):''));
 $q->execute([$selected,$person,date('Y-m-d H:i:s'),date('Y-m-d H:i:s')]);return$q->fetchColumn()===false?null:$selected;
}
function memberFeeAgreementSave(PDO $pdo,int $actor,int $plan,int $person,int $payer,string $from,string $to,int $expectedId,string $reason,bool $confirmed):int {
 $from=memberChargeAdminDate($from,true);$to=memberChargeAdminDate($to,true);$reason=memberChargeAdminReason($reason);
 if(!$confirmed||min($plan,$person,$payer,$actor)<1||$from>$to)throw new InvalidArgumentException('Dohoda vyžaduje plátce, platnost, důvod a potvrzení.');
 return shopInventoryTransaction($pdo,static function()use($pdo,$actor,$plan,$person,$payer,$from,$to,$expectedId,$reason):int {
  shopInventoryActor($pdo,$actor,'finance_manager');if(!memberFeePlanRow($pdo,$plan,true))throw new MemberFeePlanException('Plán neexistuje.');
  $q=$pdo->prepare('SELECT COALESCE(MAX(id),0) FROM member_fee_agreements WHERE plan_id=? AND sportovec_id=?');$q->execute([$plan,$person]);if((int)$q->fetchColumn()!==$expectedId)throw new MemberFeePlanException('Dohoda plátce se změnila. Obnovte formulář.');
  shopBeneficiaryAssertAccessible($pdo,$payer,$person,true);
  $q=$pdo->prepare('SELECT id FROM verejni_uzivatele WHERE id=? AND aktivni=1 AND email_overeno=1');$q->execute([$payer]);if(!$q->fetchColumn())throw new MemberFeePlanException('Plátce nemá ověřený aktivní účet.');
  $pdo->prepare('INSERT INTO member_fee_agreements(plan_id,sportovec_id,payer_account_id,valid_from,valid_to,reason,actor_id) VALUES(?,?,?,?,?,?,?)')->execute([$plan,$person,$payer,$from,$to,$reason,$actor]);return(int)$pdo->lastInsertId();
 });
}
function memberFeePeriodDecisionSave(PDO $pdo,int $actor,int $plan,int $person,string $period,int $amount,bool $overlap,int $expectedId,string $reason,bool $confirmed):int {
 [$from,$to]=memberFeePlanMonth($period);$reason=memberChargeAdminReason($reason);
 if(!$confirmed||$amount<0||$amount>100000000||min($actor,$plan,$person)<1)throw new InvalidArgumentException('Schválení vyžaduje přesnou částku, důvod a potvrzení.');
 return shopInventoryTransaction($pdo,static function()use($pdo,$actor,$plan,$person,$period,$amount,$overlap,$expectedId,$reason,$from,$to):int {
  shopInventoryActor($pdo,$actor,'finance_manager');if(!memberFeePlanRow($pdo,$plan,true))throw new MemberFeePlanException('Plán neexistuje.');
  $q=$pdo->prepare('SELECT COALESCE(MAX(id),0) FROM member_fee_period_decisions WHERE plan_id=? AND sportovec_id=? AND period_key=?');$q->execute([$plan,$person,$period]);if((int)$q->fetchColumn()!==$expectedId)throw new MemberFeePlanException('Rozhodnutí se změnilo. Obnovte formulář.');
  $q=$pdo->prepare("SELECT id FROM club_member_charges WHERE source_system='member_fee_plan' AND source_external_id=?");$q->execute(['feeplan:'.$plan.':'.$period.':'.$person]);if($q->fetchColumn())throw new MemberFeePlanException('Vystavený předpis nelze přepsat. Použijte účetní opravu a doložené vyrovnání.');
  if(!memberFeePlanMemberEligibleForPeriod($pdo,$plan,$person,$from,$to))throw new MemberFeePlanException('Osoba není v období zařazena v soupisce.');
  $pdo->prepare('INSERT INTO member_fee_period_decisions(plan_id,sportovec_id,period_key,amount_minor,allow_overlap,reason,actor_id) VALUES(?,?,?,?,?,?,?)')->execute([$plan,$person,$period,$amount,$overlap?1:0,$reason,$actor]);return(int)$pdo->lastInsertId();
 });
}
/** No guessed prorating or duplicate plan fees: every amount has an approved period decision. */
function memberFeePeriodQuote(PDO $pdo,int $plan,int $person,string $period):array {
 $q=$pdo->prepare('SELECT * FROM member_fee_period_decisions WHERE plan_id=? AND sportovec_id=? AND period_key=? ORDER BY id DESC LIMIT 1');$q->execute([$plan,$person,$period]);$decision=$q->fetch(PDO::FETCH_ASSOC);
 if(!$decision)return['result'=>'missing_rule','amount_minor'=>null,'decision_id'=>null,'detail'=>'Chybí schválení částky pro toto období; poměrné krácení se neodhaduje.'];
 [$from,$to]=memberFeePlanMonth($period);$overlapping=[];
 foreach($pdo->query("SELECT id FROM member_fee_plans WHERE status='active'")->fetchAll(PDO::FETCH_COLUMN)as$other)if((int)$other!==$plan&&memberFeePlanMemberEligibleForPeriod($pdo,(int)$other,$person,$from,$to))$overlapping[]=(int)$other;
 $blocked=$overlapping!==[]&&!(bool)$decision['allow_overlap'];
 return['result'=>$blocked?'overlap_review':((int)$decision['amount_minor']===0?'waived':'ready'),'amount_minor'=>(int)$decision['amount_minor'],'decision_id'=>(int)$decision['id'],'detail'=>($blocked?'Souběh plánů vyžaduje výslovné schválení. ':'').$decision['reason'],'overlapping_plan_ids'=>$overlapping];
}
