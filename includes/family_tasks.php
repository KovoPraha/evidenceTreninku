<?php
declare(strict_types=1);
require_once __DIR__.'/medical_activity.php';

/** Re-evaluate current family authorization on every read; no internal notes or other accounts. */
function familyTasksRead(PDO $pdo,int $account,?string $today=null): array
{
    $today=medicalClearanceDate($today??date('Y-m-d'));
    $q=$pdo->prepare('SELECT 1 FROM verejni_uzivatele WHERE id=? AND aktivni=1 AND email_overeno=1');$q->execute([$account]);
    if(!$q->fetchColumn())throw new FamilyPortalAccessDenied('Účet není aktivní a ověřený.');
    $tasks=[];$unavailable=[];$pending=null;
    $add=static function(string $kind,string $title,string $detail,string $href)use(&$tasks):void{$tasks[]=compact('kind','title','detail','href');};
    if(familyPortalTableExists($pdo,'account_person_claim_requests')){
        $q=$pdo->prepare("SELECT COUNT(*) FROM account_person_claim_requests WHERE account_id=? AND status='pending'");$q->execute([$account]);$pending=(int)$q->fetchColumn();
        if($pending)$add('club','Propojení osob čeká na klub','Žádostí k ověření: '.$pending.'. Žádost nepodávejte znovu; podklad můžete doložit osobně registráři.','moje_osoby.php');
    }else $unavailable[]='Žádosti o propojení';
    $people=familyPortalAuthorizedPeople($pdo,$account);
    if(!adminOperationalHasTables($pdo,['account_person_roles','sportovci']))$unavailable[]='Schválené profily';
    if(!$people&&$pending===0)$add('family','Propojit osobu','Pokud už jste vy nebo vaše dítě v evidenci, použijte žádost o propojení původního profilu. Pro osobní vyřízení kontaktujte registráře.','moje_osoby.php#propojeni');
    $healthReady=adminOperationalHasTables($pdo,['medical_clearance_watches','medical_clearance_records','medical_clearance_policies']);
    if(!$healthReady)$unavailable[]='Stav zdravotních podkladů';
    foreach($people as $person){
        $id=(int)$person['sportovec_id'];$name=$person['prijmeni'].' '.$person['jmeno'];
        if((int)($person['stav_manualni']??0)!==1)$add('club',$name.' — členství k ověření','Evidovaný stav ještě vyžaduje doložení klubem. Platba ani online přístup nejsou rozhodnutím o členství.','#family-profile-'.$id);
        if(!$healthReady)continue;
        foreach(medicalActivityPersonRows($pdo,$id,$today,$account) as $row){
            if($row['attention_status']==='valid')continue;
            $waiting=$row['status']==='pending';
            $detail=medicalActivityLabels()[$row['attention_status']].($row['valid_until']?' do '.$row['valid_until']:'').'. ';
            $detail.=$waiting?'Podklad čeká na ověření registrářem. Nový podklad nepředávejte jen kvůli čekání.':$row['next_step'].' Podklady lze vyřídit osobně s registrářem.';
            $add($waiting?'club':'family',$name.' — '.$row['purpose'],$detail,'#family-profile-'.$id);
        }
    }
    $until=(new DateTimeImmutable($today))->modify('+30 days')->format('Y-m-d');
    if(adminOperationalHasTables($pdo,['training_rsvps','training_roster_links','club_roster_members','planovane_treninky'])){
        foreach(trainingRsvpUpcomingForAccount($pdo,$account,$today,$until) as $training){
            if(trim((string)($training['response']??''))!=='')continue;
            $add('family',$training['prijmeni'].' '.$training['jmeno'].' — potvrdit trénink',$training['nazev'].' · '.$training['datum'].'. Potvrzení pomůže trenérovi s přípravou; skutečnou docházku zapisuje trenér.',
                '?mesic='.substr($training['datum'],0,7).'#training-rsvp-'.(int)$training['plan_id'].'-'.(int)$training['sportovec_id']);
        }
    }else $unavailable[]='Odpovědi k tréninkům';
    return compact('tasks','unavailable');
}

/** CLI chooses transport per run; subscription or queue status is not proof of delivery. */
function familyTasksDeliveryNotice(?string $disabledFunctions=null): string
{
    $disabled=array_map('trim',explode(',',strtolower($disabledFunctions??(string)ini_get('disable_functions'))));
    if(in_array('mail',$disabled,true))return 'E-mailové odesílání je v tomto prostředí blokované. Zapnutý odběr sám o sobě nezpůsobí doručení zprávy.';
    return 'Zapnutý odběr je vaše preference, nikoli potvrzení doručení. Přehled ukazuje zpracování zpráv; doručení do vaší schránky z něj nelze ověřit.';
}
