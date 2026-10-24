<?php
declare(strict_types=1);
require_once __DIR__.'/operational_cases.php';
require_once __DIR__.'/medical_clearance.php';
require_once __DIR__.'/sportovec_status_lib.php';

/** Registrar-only read model. Missing optional/online records never create membership or access. */
function personWorkflowSteps(PDO $pdo,array $person,array $context): array
{
    if(!staffActivePositionIs('registrar'))throw new DomainException('Přehled přijetí patří registráři.');
    operationalCaseActor($pdo,(int)($_SESSION['trener_id']??0),'registrar');
    $id=(int)$person['id'];$steps=[];
    $add=static function(string $title,string $detail,string $href,bool $attention=false)use(&$steps):void{$steps[]=compact('title','detail','href','attention');};
    $identity=[];
    if(adminOperationalHasTables($pdo,['person_identity_reviews'])){
        foreach($pdo->query("SELECT id,sportovec_id,candidate_ids_json FROM person_identity_reviews WHERE status IN ('review','deferred')")->fetchAll(PDO::FETCH_ASSOC) as $row){
            $candidates=json_decode($row['candidate_ids_json'],true);
            if((int)$row['sportovec_id']===$id || (is_array($candidates)&&in_array($id,array_map('intval',$candidates),true)))$identity[]=(int)$row['id'];
        }
        $add('Identita', $identity?'Otevřené neshody: '.count($identity).'. Ověřte podklad před další změnou.':'Není evidovaná otevřená neshoda. Toto samo nepotvrzuje ověření totožnosti.', $identity?'identity_reviews_admin.php?id='.$identity[0]:'person_audit_admin.php?sportovec_id='.$id, (bool)$identity);
    }else $add('Identita','Zdroj ověřování identity není dostupný.','identity_reviews_admin.php',true);
    $active=0;$today=date('Y-m-d');$now=(string)$pdo->query('SELECT CURRENT_TIMESTAMP')->fetchColumn();
    foreach($context['roles']??[] as $role){if(($role['status']??'')==='approved'&&(int)($role['aktivni']??0)===1&&(int)($role['email_overeno']??0)===1&&($role['valid_from']??'')<=$now&&(($role['valid_to']??null)===null||$role['valid_to']>$now))$active++;}
    $add('Rodiče a přístupy',$active?'Platné schválené online vazby: '.$active.'.':'Není platný schválený online přístup. Osobní vyřízení bez účtu je možné; kontakt není přístup.', 'sprava_sportovcu.php?edit_id='.$id.'#parents-title');
    if(adminOperationalHasTables($pdo,['club_paper_records','club_paper_record_events'])){
        $q=$pdo->prepare("SELECT (SELECT e.event_type FROM club_paper_record_events e WHERE e.record_id=p.id AND e.event_type<>'file_viewed' ORDER BY e.id DESC LIMIT 1) AS state FROM club_paper_records p WHERE p.sportovec_id=?");$q->execute([$id]);$states=$q->fetchAll(PDO::FETCH_COLUMN);
        $pending=count(array_filter($states,static fn($s)=>$s==='recorded'));$accepted=count(array_filter($states,static fn($s)=>$s==='accepted'));
        $add('Přihlášky a souhlasy','K posouzení: '.$pending.', ověřené podklady: '.$accepted.'. Úplnost se posuzuje pro konkrétní účel a aktuální znění.', 'paper_registrations_admin.php?person_id='.$id.'#record',$pending>0);
    }else $add('Přihlášky a souhlasy','Zdroj papírových podkladů není dostupný.','paper_registrations_admin.php?person_id='.$id,true);
    $recorded=(int)($person['stav_manualni']??0)===1;
    $status=sportovecStatusCompute($person);
    $add('Členství',($status['label']??'Neověřeno').($recorded?' — evidované rozhodnutí.':' — chybí doložené rozhodnutí registráře.'),'sportovec_karta.php?sportovec_id='.$id.'&tab=prehled',!$recorded);
    $rosters=$context['rosters']??[];$count=count($rosters['current']??[]);$planned=count($rosters['planned']??[]);
    $add('Skupina a soupiska','Aktuální zařazení: '.$count.', budoucí: '.$planned.'. Zařazení proveďte podle dohodnuté činnosti.','sprava_sportovcu.php?edit_id='.$id.'#rosters-title');
    if(adminOperationalHasTables($pdo,['medical_clearance_watches','medical_clearance_records'])){
        $q=$pdo->prepare('SELECT purpose FROM medical_clearance_watches WHERE sportovec_id=?');$q->execute([$id]);$purposes=$q->fetchAll(PDO::FETCH_COLUMN);$pending=0;
        foreach($purposes as $purpose)if(medicalClearanceStatus($pdo,$id,$purpose,$today)['status']!=='valid')$pending++;
        $add('Zdravotní podklady',$purposes?'Sledované účely: '.count($purposes).', bez platného podkladu: '.$pending.'.':'Není nastavené sledování. Případnou povinnost určuje konkrétní činnost a pravidla oddílu.','medical_clearance_admin.php?person_id='.$id,$pending>0);
    }else $add('Zdravotní podklady','Zdroj zdravotních stavů není dostupný.','medical_clearance_admin.php?person_id='.$id,true);
    $add('Osobní přijetí do programu','Pokud se osoba hlásí do programu, pokračujte s vybranou osobou. Plátce, podklady a nabídku potvrďte samostatně.','club_program_admissions_admin.php?person_id='.$id);
    return $steps;
}
