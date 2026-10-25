<?php
declare(strict_types=1);
require_once __DIR__.'/medical_clearance.php';
require_once __DIR__.'/training_day.php';
require_once __DIR__.'/training_rsvp.php';

function medicalActivityLabels(): array
{
    return ['missing'=>'Nedoloženo','pending'=>'Čeká na ověření','valid'=>'Platné','expiring'=>'Končí platnost','expired'=>'Platnost skončila','not_yet_valid'=>'Dosud neplatné','revoked'=>'Odvoláno','untracked'=>'Sledování neurčeno'];
}

/** Only the canonical status and purpose; no scan location, notes or medical contents. */
function medicalActivityPersonRows(PDO $pdo,int $person,string $on,?int $account=null): array
{
    $rows=medicalClearanceView($pdo,$person,$on,$account);
    $q=$pdo->prepare('SELECT w.purpose,p.reminder_days FROM medical_clearance_watches w JOIN medical_clearance_policies p ON p.id=w.policy_id WHERE w.sportovec_id=?');$q->execute([$person]);
    $days=$q->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach($rows as &$r){
        $horizon=(new DateTimeImmutable($on))->modify('+'.max(0,min(365,(int)($days[$r['purpose']]??0))).' days')->format('Y-m-d');
        $r['attention_status']=$r['status']==='valid'&&$r['valid_until']<=$horizon?'expiring':$r['status'];
        if($r['attention_status']==='expiring')$r['next_step']='Domluvte s registrářem včasné doložení navazujícího podkladu. K vybranému dni je stávající podklad platný.';
    }unset($r);
    return $rows;
}

function medicalActivityActor(PDO $pdo): int
{
    $position=staffActivePosition();$actor=(int)($_SESSION['trener_id']??0);
    if(!in_array($position,['coach','sports_lead','registrar'],true))throw new DomainException('Přehled není v této pracovní pozici.');
    operationalCaseActor($pdo,$actor,$position);
    return $actor;
}

function medicalActivityRead(PDO $pdo,array $input): array
{
    $actor=medicalActivityActor($pdo);$planId=max(0,(int)staffListText($input,'plan_id'));$teamId=max(0,(int)staffListText($input,'team_id'));
    $on=staffListText($input,'on')?:date('Y-m-d');
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$on))throw new InvalidArgumentException('Zadejte platný den aktivity.');
    medicalClearanceDate($on);
    $people=[];$context='';$ids=[];
    if($planId){
        // Registrar may inspect groups, not acquire access to trainer-only plan administration.
        $plan=trainingDayPlan($pdo,$actor,$planId);$on=medicalClearanceDate($plan['datum']);$context=$plan['nazev'];
        $ids=trainingRsvpEligibleMemberIds($pdo,$planId,$on);
        foreach(clubMakeupReservationsForPlan($pdo,$planId) as $r)$ids[]=(int)$r['sportovec_id'];
        if((int)$plan['trenink_id']>0){$q=$pdo->prepare('SELECT sportovec_id FROM trenink_sportovec WHERE trenink_id=?');$q->execute([(int)$plan['trenink_id']]);$ids=array_merge($ids,$q->fetchAll(PDO::FETCH_COLUMN));}
    }elseif($teamId){
        coachScopeAssertTeams($pdo,[$teamId],$on);
        $q=$pdo->prepare('SELECT name FROM club_teams WHERE id=?');$q->execute([$teamId]);$context=(string)$q->fetchColumn();
        if($context==='')throw new InvalidArgumentException('Skupina nebyla nalezena.');
        $ids=kisRosterEffectiveMemberIds($pdo,$teamId,$on);
    }
    $ids=array_values(array_unique(array_map('intval',$ids)));coachScopeAssertPeople($pdo,$ids);
    if($ids){$q=$pdo->prepare('SELECT id,jmeno,prijmeni FROM sportovci WHERE id IN ('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY prijmeni,jmeno,id');$q->execute($ids);$people=$q->fetchAll(PDO::FETCH_ASSOC);}
    $state=staffListText($input,'state');$search=mb_substr(staffListText($input,'q'),0,100);
    if($state!==''&&!isset(medicalActivityLabels()[$state]))throw new InvalidArgumentException('Vyberte stav z nabídky.');
    $rows=[];
    foreach($people as $person){
        $name=$person['prijmeni'].' '.$person['jmeno'];$words=preg_split('/\s+/u',$search,-1,PREG_SPLIT_NO_EMPTY);
        foreach($words as $word)if(mb_stripos($name,$word)===false)continue 2;
        $statuses=medicalActivityPersonRows($pdo,(int)$person['id'],$on);
        if(!$statuses)$statuses=[['purpose'=>'Sledování neurčeno','status'=>'untracked','attention_status'=>'untracked','valid_until'=>null,'next_step'=>'Ověřte s registrářem, zda se pro tuto činnost podklad vyžaduje.']];
        foreach($statuses as $status)if($state===''||$state===$status['attention_status'])$rows[]=['person_id'=>(int)$person['id'],'name'=>$name]+$status;
    }
    $total=count($rows);$pages=max(1,(int)ceil($total/25));$page=max(1,min($pages,(int)(staffListText($input,'page')?:1)));$rows=array_slice($rows,($page-1)*25,25);
    return compact('rows','total','pages','page','on','context','planId','teamId','state','search');
}
