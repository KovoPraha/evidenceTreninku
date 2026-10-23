<?php
declare(strict_types=1);
require_once __DIR__.'/accounting_ledger.php';
require_once __DIR__.'/sports_activity.php';
require_once __DIR__.'/club_payment_returns.php';

/** Remuneration evidence, not payroll: no implicit tax regime, rate, bank send or historical paid flag. */
function clubRewardActor(PDO $pdo,int $actor,array $positions):void {
    $position=staffActivePosition();
    if($actor<1||$actor!==(int)($_SESSION['trener_id']??0)||!in_array($position,$positions,true))throw new DomainException('Úkon není povolen ve vaší pracovní pozici.');
    $q=$pdo->prepare('SELECT t.id FROM treneri t JOIN staff_user_positions p ON p.trainer_id=t.id WHERE t.id=? AND t.aktivni=1 AND p.position_code=?'.($pdo->inTransaction()?accountingLedgerLock($pdo):''));$q->execute([$actor,$position]);
    if(!$q->fetchColumn())throw new DomainException('Pracovní oprávnění již neplatí.');
}
function clubRewardJson(array $data):string {return json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);}
function clubRewardHash(array $data):string {return hash('sha256',clubRewardJson($data));}
function clubRewardAmount(string $value):int {
    $value=str_replace(',','.',trim($value));if(!preg_match('/^[0-9]{1,8}(?:\.[0-9]{1,2})?$/D',$value))throw new DomainException('Částka musí být v Kč, nejvýše se dvěma desetinnými místy.');
    [$whole,$fraction]=array_pad(explode('.',$value),2,'');return (int)$whole*100+(int)str_pad($fraction,2,'0');
}
function clubRewardRule(PDO $pdo,array $input,int $actor,string $reason,bool $confirmed):int {
    accountingLedgerAudit($actor,$reason,$confirmed);$kind=(string)($input['kind']??'');$unit=(string)($input['unit']??'');$rate=$input['rate_minor']??null;
    if(!in_array($kind,['coach','athlete'],true)||!in_array($unit,['training','hour'],true)||($kind==='athlete'&&$unit!=='training')||!is_int($rate)||$rate<0||$rate>100000000)throw new DomainException('Vyberte druh odměny, jednotku a výslovnou nezápornou sazbu.');
    $from=sportsActivityDate((string)($input['valid_from']??''));$to=sportsActivityDate((string)($input['valid_to']??''));if($from>$to)throw new DomainException('Neplatná účinnost sazby.');
    $title=accountingLedgerText((string)($input['title']??''));$proof=accountingLedgerText((string)($input['approval_evidence']??''),2000);$method=accountingLedgerText((string)($input['accounting_method']??''),2000);
    return accountingLedgerTransaction($pdo,function()use($pdo,$actor,$kind,$unit,$rate,$from,$to,$title,$proof,$method){clubRewardActor($pdo,$actor,['sports_lead']);
        $pdo->prepare('INSERT INTO club_reward_rules(kind,unit,rate_minor,valid_from,valid_to,title,approval_evidence,accounting_method,approved_by) VALUES(?,?,?,?,?,?,?,?,?)')->execute([$kind,$unit,$rate,$from,$to,$title,$proof,$method,$actor]);return (int)$pdo->lastInsertId();});
}
/** All training inputs are explicit closed versions. Other work remains proposed until separate approval. */
function clubRewardSource(PDO $pdo,string $kind,int $subject,string $from,string $to):array {
    $rows=[];
    if(accountingLedgerTable($pdo,'training_closures')){
        $q=$pdo->prepare("SELECT r.* FROM training_closures c JOIN training_closure_revisions r ON r.id=c.current_revision_id WHERE c.status='closed' AND r.actual_date BETWEEN ? AND ? ORDER BY r.actual_date,r.training_id".($pdo->inTransaction()?accountingLedgerLock($pdo):''));$q->execute([$from,$to]);
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r){$s=json_decode($r['snapshot_json'],true,512,JSON_THROW_ON_ERROR);if(!in_array($subject,$s[$kind==='coach'?'coach_ids':'participant_ids']??[],true))continue;$rows[]=['source'=>'training_closure_revisions','id'=>(int)$r['id'],'activity_id'=>(int)$r['training_id'],'date'=>$r['actual_date'],'minutes'=>(int)$r['duration_minutes'],'label'=>(string)($s['description']??'Trénink'),'snapshot_hash'=>hash('sha256',$r['snapshot_json'])];}
    }
    if($kind==='coach'&&accountingLedgerTable($pdo,'dalsi_cinnosti')){$q=$pdo->prepare('SELECT * FROM dalsi_cinnosti WHERE trener_id=? AND datum BETWEEN ? AND ? ORDER BY datum,id'.($pdo->inTransaction()?accountingLedgerLock($pdo):''));$q->execute([$subject,$from,$to]);foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r){$minutes=(float)$r['delka']*60;if(!is_finite($minutes)||$minutes<=0||$minutes>1440||abs(round($minutes)-$minutes)>0.0001)throw new DomainException('Další činnost má neplatnou délku; nejprve opravte zdrojový výkaz.');$rows[]=['source'=>'dalsi_cinnosti','id'=>(int)$r['id'],'activity_id'=>(int)$r['id'],'date'=>$r['datum'],'minutes'=>(int)round($minutes),'label'=>$r['nazev'],'note'=>$r['poznamka']??null];}}
    return ['kind'=>$kind,'subject_id'=>$subject,'from'=>$from,'to'=>$to,'activities'=>$rows];
}
function clubRewardClaim(PDO $pdo,int $id):array {$q=$pdo->prepare('SELECT * FROM club_reward_claims WHERE id=?');$q->execute([$id]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)throw new DomainException('Nárok nebyl nalezen.');return $r;}
function clubRewardVersion(PDO $pdo,int $id):array {$q=$pdo->prepare('SELECT * FROM club_reward_versions WHERE id=?');$q->execute([$id]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)throw new DomainException('Verze nebyla nalezena.');return $r;}
function clubRewardPaid(PDO $pdo,int $id):int {$q=$pdo->prepare('SELECT COALESCE(SUM(amount_minor),0) FROM club_reward_payouts WHERE claim_id=?');$q->execute([$id]);return (int)$q->fetchColumn();}
function clubRewardAssertRecipient(PDO $pdo,string $kind,int $id):void {
    $table=match($kind){'trainer'=>'treneri','person'=>'sportovci',default=>throw new DomainException('Vyberte konkrétního příjemce.')};$q=$pdo->prepare('SELECT id FROM '.$table.' WHERE id=?');$q->execute([$id]);if(!$q->fetchColumn())throw new DomainException('Příjemce nebyl nalezen.');
}
function clubRewardSubmit(PDO $pdo,array $input,int $actor,string $reason,bool $confirmed):array {
    $reason=accountingLedgerAudit($actor,$reason,$confirmed);$kind=(string)($input['kind']??'');$subject=(int)($input['subject_id']??0);$claimId=(int)($input['claim_id']??0);$expected=(int)($input['expected_version']??0);$ruleId=(int)($input['rule_id']??0);
    if(!in_array($kind,['coach','athlete'],true)||$subject<1||$expected<0)throw new DomainException('Neplatný druh, osoba nebo verze výkazu.');
    $from=accountingLedgerDate((string)($input['date_from']??''));$to=accountingLedgerDate((string)($input['date_to']??''));if($from>$to)throw new DomainException('Neplatné období.');
    $recipientKind=(string)($input['recipient_kind']??'');$recipient=(int)($input['recipient_id']??0);$evidence=accountingLedgerText((string)($input['recipient_evidence']??''),2000);$key=accountingLedgerText((string)($input['request_key']??''),100);$hash=clubRewardHash([$kind,$subject,$claimId,$expected,$ruleId,$from,$to,$recipientKind,$recipient,$evidence,$reason,$actor]);
    return accountingLedgerTransaction($pdo,function()use($pdo,$actor,$reason,$kind,$subject,$claimId,$expected,$ruleId,$from,$to,$recipientKind,$recipient,$evidence,$key,$hash){
        clubRewardActor($pdo,$actor,['coach','sports_lead']);if(staffActivePosition()==='coach'&&($kind!=='coach'||$subject!==$actor||$recipientKind!=='trainer'||$recipient!==$actor))throw new DomainException('Trenér předkládá pouze vlastní výkaz a vlastní příjem.');
        clubRewardAssertRecipient($pdo,$kind==='coach'?'trainer':'person',$subject);clubRewardAssertRecipient($pdo,$recipientKind,$recipient);
        $q=$pdo->prepare('SELECT * FROM club_reward_versions WHERE request_key=?');$q->execute([$key]);$retry=$q->fetch(PDO::FETCH_ASSOC);if($retry){if(!hash_equals($retry['request_hash'],$hash))throw new DomainException('Klíč požadavku patří jinému výkazu.');return $retry;}
        $q=$pdo->prepare('SELECT * FROM club_reward_rules WHERE id=?');$q->execute([$ruleId]);$rule=$q->fetch(PDO::FETCH_ASSOC);if(!$rule||$rule['kind']!==$kind||$rule['valid_from']>$from||$rule['valid_to']<$to)throw new DomainException('Chybí výslovně schválené pravidlo platné pro celé období. Při změně sazby rozdělte období.');
        if($claimId){$claim=clubRewardClaim($pdo,$claimId);if($claim['kind']!==$kind||(int)$claim['subject_id']!==$subject||$claim['date_from']!==$from||$claim['date_to']!==$to)throw new DomainException('Oprava nesmí změnit osobu ani období původního nároku.');$old=clubRewardVersion($pdo,(int)$claim['current_version_id']);if((int)$old['version']!==$expected)throw new DomainException('Výkaz mezitím změnil jiný pracovník.');if(clubRewardPaid($pdo,$claimId)!==0&&($old['recipient_kind']!==$recipientKind||(int)$old['recipient_id']!==$recipient))throw new DomainException('U vypořádávaného nároku nelze přepsat příjemce.');}
        else {
            if($expected!==0)throw new DomainException('Nový nárok musí začínat první verzí.');
            $q=$pdo->prepare('SELECT id FROM club_reward_claims WHERE kind=? AND subject_id=? AND date_from<=? AND date_to>=?');$q->execute([$kind,$subject,$to,$from]);if($q->fetchColumn())throw new DomainException('Období se překrývá s již předloženým nárokem. Opravte jeho verzi.');
            if($kind==='athlete'&&accountingLedgerTable($pdo,'sportovec_obdobi')){$q=$pdo->prepare("SELECT id FROM sportovec_obdobi WHERE sportovec_id=? AND datum_od<=? AND (datum_do>=? OR vyplaceno<>0) AND (vyplaceno<>0 OR (datum_do IS NOT NULL AND datum_do<>'' AND datum_do<>'0000-00-00'))");$q->execute([$subject,$to,$from]);if($q->fetchColumn())throw new DomainException('Období zasahuje do uzavřené nebo vyplacené historie. Vyžaduje samostatné ověření převodu, nikoli nový nárok.');}
            $pdo->prepare('INSERT INTO club_reward_claims(kind,subject_id,date_from,date_to,created_by) VALUES(?,?,?,?,?)')->execute([$kind,$subject,$from,$to,$actor]);$claimId=(int)$pdo->lastInsertId();
        }
        $source=clubRewardSource($pdo,$kind,$subject,$from,$to);$total=0;$lines=[];foreach($source['activities'] as $a){if($rule['unit']==='training'&&$a['source']!=='training_closure_revisions')continue;$amount=$rule['unit']==='hour'?intdiv((int)$a['minutes']*(int)$rule['rate_minor']+30,60):(int)$rule['rate_minor'];$total+=$amount;$lines[]=$a+['amount_minor'=>$amount];}
        if($total>1000000000000)throw new DomainException('Součet překračuje povolenou částku.');
        $snapshot=['contract'=>'club-reward.v1','source'=>$source,'lines'=>$lines,'rule'=>$rule,'currency'=>'CZK','amount_minor'=>$total,'recipient'=>['kind'=>$recipientKind,'id'=>$recipient,'evidence'=>$evidence]];
        $pdo->prepare('INSERT INTO club_reward_versions(claim_id,version,rule_id,amount_minor,recipient_kind,recipient_id,recipient_evidence,source_hash,snapshot_json,reason,submitted_by,request_key,request_hash) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$claimId,$expected+1,$ruleId,$total,$recipientKind,$recipient,$evidence,clubRewardHash($source),clubRewardJson($snapshot),$reason,$actor,$key,$hash]);$id=(int)$pdo->lastInsertId();$pdo->prepare('UPDATE club_reward_claims SET current_version_id=? WHERE id=?')->execute([$id,$claimId]);return clubRewardVersion($pdo,$id);
    });
}
function clubRewardChanged(PDO $pdo,array $claim,array $version):bool {return !hash_equals($version['source_hash'],clubRewardHash(clubRewardSource($pdo,$claim['kind'],(int)$claim['subject_id'],$claim['date_from'],$claim['date_to'])));}
function clubRewardDecide(PDO $pdo,int $versionId,string $outcome,int $actor,string $reason,bool $confirmed):int {
    $reason=accountingLedgerAudit($actor,$reason,$confirmed);if(!in_array($outcome,['approved','returned'],true))throw new DomainException('Vyberte schválení nebo vrácení k doplnění.');
    return accountingLedgerTransaction($pdo,function()use($pdo,$versionId,$outcome,$actor,$reason){clubRewardActor($pdo,$actor,['sports_lead']);$v=clubRewardVersion($pdo,$versionId);$c=clubRewardClaim($pdo,(int)$v['claim_id']);
        if($actor===(int)$v['submitted_by']||($c['kind']==='coach'&&$actor===(int)$c['subject_id'])||($v['recipient_kind']==='trainer'&&$actor===(int)$v['recipient_id']))throw new DomainException('Vlastní výkaz, odměnu ani předložený nárok nelze schválit. Vyžaduje jiného pověřeného vedoucího.');
        foreach(array_unique(array_filter([$c['kind']==='athlete'?(int)$c['subject_id']:0,$v['recipient_kind']==='person'?(int)$v['recipient_id']:0])) as $person){$q=$pdo->prepare('SELECT t.*,s.jmeno AS person_first,s.prijmeni AS person_last,s.email AS person_email FROM treneri t JOIN sportovci s ON s.id=? WHERE t.id=?');$q->execute([$person,$actor]);$identity=$q->fetch(PDO::FETCH_ASSOC);if($identity&&((trim((string)($identity['email']??''))!==''&&mb_strtolower(trim($identity['email']))===mb_strtolower(trim((string)$identity['person_email'])))||(trim((string)$identity['jmeno'])!==''&&mb_strtolower(trim($identity['jmeno']))===mb_strtolower(trim($identity['person_first']).' '.trim($identity['person_last'])))))throw new DomainException('Možná shoda schvalovatele s příjemcem nebo sportovcem. Nárok musí posoudit jiný vedoucí; identity se automaticky neslučují.');}
        $q=$pdo->prepare('SELECT * FROM club_reward_decisions WHERE version_id=?');$q->execute([$versionId]);$retry=$q->fetch(PDO::FETCH_ASSOC);if($retry){if($retry['outcome']!==$outcome||$retry['reason']!==$reason||(int)$retry['actor_trainer_id']!==$actor)throw new DomainException('Tato verze již má jiné rozhodnutí. Připravte opravu.');return (int)$retry['id'];}
        if((int)$c['current_version_id']!==$versionId)throw new DomainException('Schvalovat lze jen aktuální předloženou verzi.');if($outcome==='approved'&&clubRewardChanged($pdo,$c,$v))throw new DomainException('Podklady se změnily. Předložte novou verzi se skutečnou činností.');
        $pdo->prepare('INSERT INTO club_reward_decisions(version_id,outcome,reason,actor_trainer_id,decided_on) VALUES(?,?,?,?,?)')->execute([$versionId,$outcome,$reason,$actor,date('Y-m-d')]);$id=(int)$pdo->lastInsertId();if($outcome==='approved')$pdo->prepare('UPDATE club_reward_claims SET approved_version_id=? WHERE id=?')->execute([$versionId,$c['id']]);return $id;
    });
}
/** Called by the shared ledger too: arbitrary allocation cannot manufacture a reward payment. */
function clubRewardValidateAllocation(PDO $pdo,array $movement,string $caseType,int $caseId,int $amountMinor,?int $reversesId):void {
    if(($GLOBALS['club_reward_allocation_context']??null)!==[$caseId,$amountMinor,(int)$movement['id']]||$reversesId!==null||$amountMinor<1)throw new DomainException('Výplatu odměny přiřaďte ve schváleném nároku, včetně příjemce a podkladu.');
    if(isset($GLOBALS['club_reward_return_original'])){clubPaymentReturnOriginal($pdo,'reward',$caseId,(int)$GLOBALS['club_reward_return_original'],$movement,$amountMinor,$movement['effective_on']);return;}
    $c=clubRewardClaim($pdo,$caseId);if(!$c['approved_version_id']||(int)$c['approved_version_id']!==(int)$c['current_version_id'])throw new DomainException('Výplata vyžaduje schválenou aktuální verzi.');$v=clubRewardVersion($pdo,(int)$c['approved_version_id']);if(clubRewardChanged($pdo,$c,$v))throw new DomainException('Podklady se změnily; nejprve opravte a znovu schvalte výkaz.');
    $balance=(int)$v['amount_minor']-clubRewardPaid($pdo,$caseId);$expense=(int)$movement['amount_minor']<0;
    if(($expense&&!in_array($movement['kind'],['expense'],true))||(!$expense&&!in_array($movement['kind'],['income','reversal'],true))||($expense&&($balance<$amountMinor))||(!$expense&&(-$balance<$amountMinor)))throw new DomainException('Pohyb neodpovídá schválenému doplatku nebo přeplatku odměny.');
}
function clubRewardPay(PDO $pdo,int $claimId,array $input,int $actor,string $reason,bool $confirmed):array {
    $reason=accountingLedgerAudit($actor,$reason,$confirmed);$movement=(int)($input['movement_id']??0);$amount=$input['amount_minor']??null;if(!is_int($amount)||$amount<1)throw new DomainException('Uveďte kladnou skutečně vypořádanou částku.');
    $received=accountingLedgerText((string)($input['received_by']??''),2000);$key=accountingLedgerText((string)($input['request_key']??''),100);$date=accountingLedgerDate((string)($input['paid_on']??''));$originalPayout=(int)($input['return_of_payout_id']??0);$hash=clubRewardHash($originalPayout>0?[$claimId,$movement,$amount,$received,$date,$reason,$actor,$originalPayout]:[$claimId,$movement,$amount,$received,$date,$reason,$actor]);
    return accountingLedgerTransaction($pdo,function()use($pdo,$claimId,$movement,$amount,$received,$key,$date,$hash,$actor,$reason,$originalPayout){clubRewardActor($pdo,$actor,['finance_manager']);accountingLedgerActor($pdo,$actor);
        $q=$pdo->prepare('SELECT * FROM club_reward_payouts WHERE request_key=?');$q->execute([$key]);$retry=$q->fetch(PDO::FETCH_ASSOC);if($retry){if(!hash_equals($retry['request_hash'],$hash))throw new DomainException('Klíč výplaty patří jinému úkonu.');return $retry;}
        $c=clubRewardClaim($pdo,$claimId);$q=$pdo->prepare('SELECT * FROM accounting_movements WHERE id=?');$q->execute([$movement]);$m=$q->fetch(PDO::FETCH_ASSOC);if(!$m)throw new DomainException('Skutečný peněžní pohyb nebyl nalezen.');
        if($date!==$m['effective_on'])throw new DomainException('Datum výplaty musí odpovídat doloženému pohybu.');
        $original=null;if($originalPayout>0){$q=$pdo->prepare('SELECT * FROM club_reward_payouts WHERE id=? AND claim_id=? AND amount_minor>0');$q->execute([$originalPayout,$claimId]);$original=$q->fetch(PDO::FETCH_ASSOC);if(!$original)throw new DomainException('Původní výplata nebyla nalezena v tomto nároku.');$GLOBALS['club_reward_return_original']=(int)$original['allocation_id'];}
        $GLOBALS['club_reward_allocation_context']=[$claimId,$amount,$movement];try{$allocation=accountingLedgerAllocate($pdo,$movement,'reward_claim',$claimId,$amount,'reward-'.$key,$actor,$reason,true,null,'','',$date);}finally{unset($GLOBALS['club_reward_allocation_context'],$GLOBALS['club_reward_return_original']);}
        if($original)clubPaymentReturnLink($pdo,'reward',$claimId,(int)$original['allocation_id'],(int)$allocation['id'],$amount);
        $signed=(int)$m['amount_minor']<0?$amount:-$amount;
        $pdo->prepare('INSERT INTO club_reward_payouts(claim_id,version_id,movement_id,allocation_id,amount_minor,received_by,reason,actor_trainer_id,request_key,request_hash,paid_on) VALUES(?,?,?,?,?,?,?,?,?,?,?)')->execute([$claimId,$original?$original['version_id']:$c['approved_version_id'],$movement,$allocation['id'],$signed,$received,$reason,$actor,$key,$hash,$date]);$id=(int)$pdo->lastInsertId();$q=$pdo->prepare('SELECT * FROM club_reward_payouts WHERE id=?');$q->execute([$id]);return $q->fetch(PDO::FETCH_ASSOC);
    });
}
/** Guard legacy other-work insertion/deletion after submission; corrections use a new remuneration version. */
function clubRewardAssertOtherWorkMutable(PDO $pdo,int $trainer,string $date):void {
    if(!accountingLedgerTable($pdo,'club_reward_claims'))return;
    $q=$pdo->prepare("SELECT c.id FROM club_reward_claims c LEFT JOIN club_reward_decisions d ON d.version_id=c.current_version_id WHERE c.kind='coach' AND c.subject_id=? AND c.date_from<=? AND c.date_to>=? AND (d.outcome IS NULL OR d.outcome<>'returned')");$q->execute([$trainer,$date,$date]);if($q->fetchColumn())throw new DomainException('Činnost spadá do předloženého výkazu. Nejprve ji vraťte k doplnění a připravte opravnou verzi.');
}
function clubRewardExportRows(PDO $pdo,string $from,string $to):array {
    $out=['claims'=>[],'versions'=>[],'decisions'=>[],'payouts'=>[],'rules'=>[]];if(!accountingLedgerTable($pdo,'club_reward_claims'))return $out;
    $q=$pdo->prepare('SELECT * FROM club_reward_claims WHERE date_from<=? OR id IN (SELECT claim_id FROM club_reward_payouts WHERE paid_on<=?) ORDER BY id');$q->execute([$to,$to]);$out['claims']=$q->fetchAll(PDO::FETCH_ASSOC);
    foreach($out['claims'] as &$c){
        $q=$pdo->prepare('SELECT v.id FROM club_reward_versions v JOIN club_reward_decisions d ON d.version_id=v.id WHERE v.claim_id=? AND d.outcome=\'approved\' AND d.decided_on<=? ORDER BY v.version DESC LIMIT 1');$q->execute([$c['id'],$to]);$c['approved_version_id']=$q->fetchColumn()?:null;
        $q=$pdo->prepare('SELECT id FROM club_reward_versions WHERE claim_id=? AND submitted_at<=? ORDER BY version DESC LIMIT 1');$q->execute([$c['id'],$to.' 23:59:59']);$c['current_version_id']=$q->fetchColumn()?:null;
        $v=$c['approved_version_id']?clubRewardVersion($pdo,(int)$c['approved_version_id']):null;$c['approved_minor']=$v?(int)$v['amount_minor']:0;$q=$pdo->prepare('SELECT COALESCE(SUM(amount_minor),0) FROM club_reward_payouts WHERE claim_id=? AND paid_on<=?');$q->execute([$c['id'],$to]);$c['paid_minor']=(int)$q->fetchColumn();$c['balance_minor']=$c['approved_minor']-$c['paid_minor'];$c['review_required']=$v&&(clubRewardChanged($pdo,$c,$v)||(int)$c['current_version_id']!==(int)$c['approved_version_id'])?1:0;$c['state_as_of']=$to;
    }
    unset($c);$ids=array_map('intval',array_column($out['claims'],'id'));if(!$ids)return $out;$in=implode(',',$ids);
    $end=(new DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d');$q=$pdo->prepare('SELECT * FROM club_reward_versions WHERE claim_id IN ('.$in.') AND (submitted_at<? OR id IN (SELECT version_id FROM club_reward_payouts WHERE paid_on<=?)) ORDER BY id');$q->execute([$end,$to]);$out['versions']=$q->fetchAll(PDO::FETCH_ASSOC);foreach($out['versions'] as &$version)$version['late_recorded']=$version['submitted_at']>=$end;unset($version);
    $q=$pdo->prepare('SELECT d.* FROM club_reward_decisions d JOIN club_reward_versions v ON v.id=d.version_id WHERE v.claim_id IN ('.$in.') AND d.decided_on<=? ORDER BY d.id');$q->execute([$to]);$out['decisions']=$q->fetchAll(PDO::FETCH_ASSOC);
    $q=$pdo->prepare('SELECT * FROM club_reward_payouts WHERE claim_id IN ('.$in.') AND paid_on<=? ORDER BY id');$q->execute([$to]);$out['payouts']=$q->fetchAll(PDO::FETCH_ASSOC);
    $q=$pdo->prepare('SELECT * FROM club_reward_rules WHERE created_at<=? ORDER BY id');$q->execute([$to.' 23:59:59']);$out['rules']=$q->fetchAll(PDO::FETCH_ASSOC);return $out;
}
