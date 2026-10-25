<?php
declare(strict_types=1);
require_once __DIR__.'/person_workflow.php';
require_once __DIR__.'/staff_record_list.php';
require_once __DIR__.'/training_rsvp.php';

function personLifecycleAssertActor(PDO $pdo): int
{
    if (!staffActivePositionIs('registrar')) throw new DomainException('Vyřízení osoby patří registráři.');
    $actor=(int)($_SESSION['trener_id']??0);
    operationalCaseActor($pdo,$actor,'registrar');
    return $actor;
}

function personLifecycleIntents(): array
{
    return ['intake'=>'Přijetí osoby','pause'=>'Přerušení činnosti','departure'=>'Odchod osoby','return'=>'Návrat osoby'];
}

/** Current dependencies, not a historical membership reconstruction or a bulk mutation. */
function personLifecycleRead(PDO $pdo,int $id,array $input=[]): array
{
    personLifecycleAssertActor($pdo);
    $q=$pdo->prepare('SELECT id,jmeno,prijmeni,stav_clenstvi,stav_manualni,stav_duvod,stav_aktualizovan FROM sportovci WHERE id=?');$q->execute([$id]);
    $person=$q->fetch(PDO::FETCH_ASSOC);
    if(!$person)throw new InvalidArgumentException('Osoba nebyla nalezena.');
    $missing=[];
    $read=static function(array $tables,string $sql,array $params,string $label)use($pdo,&$missing):array{
        if(!adminOperationalHasTables($pdo,$tables)){$missing[]=$label;return [];}
        $q=$pdo->prepare($sql);$q->execute($params);return $q->fetchAll(PDO::FETCH_ASSOC);
    };
    $today=date('Y-m-d');
    $roles=$read(['account_person_roles','verejni_uzivatele'],
        'SELECT r.id,r.relation_role,r.status,r.valid_from,r.valid_to,u.aktivni,u.email_overeno,u.jmeno,u.prijmeni FROM account_person_roles r JOIN verejni_uzivatele u ON u.id=r.account_id WHERE r.sportovec_id=? ORDER BY r.id',[$id],'Rodinné přístupy');
    $rosters=$read(['club_roster_members','club_teams'],
        "SELECT m.id,m.team_id,m.status,m.valid_from,m.valid_to,t.name FROM club_roster_members m JOIN club_teams t ON t.id=m.team_id WHERE m.sportovec_id=? AND m.status<>'cancelled' AND (m.status<>'removed' OR m.valid_to IS NOT NULL) AND (m.valid_to IS NULL OR m.valid_to>=?) ORDER BY m.valid_from,t.name,m.id",[$id,$today],'Soupisky');
    $context=['roles'=>$roles,'rosters'=>['current'=>array_values(array_filter($rosters,static fn($r)=>$r['valid_from']<=$today)), 'planned'=>array_values(array_filter($rosters,static fn($r)=>$r['valid_from']>$today))]];
    $steps=personWorkflowSteps($pdo,$person,$context);
    $paper=$read(['club_paper_records','club_paper_record_events'],
        "SELECT p.id,p.kind,p.version_label,(SELECT e.event_type FROM club_paper_record_events e WHERE e.record_id=p.id AND e.event_type<>'file_viewed' ORDER BY e.id DESC LIMIT 1) AS state FROM club_paper_records p WHERE p.sportovec_id=? ORDER BY p.id DESC",[$id],'Papírové podklady');
    $charges=$read(['club_member_charges'],"SELECT id,title_snapshot,status,due_on FROM club_member_charges WHERE sportovec_id=? AND status='pending' ORDER BY due_on,id",[$id],'Členské předpisy');
    $until=(new DateTimeImmutable($today))->modify('+90 days')->format('Y-m-d');$trainings=[];
    if(adminOperationalHasTables($pdo,['training_rsvps','training_roster_links','club_roster_members','planovane_treninky']))$trainings=trainingRsvpUpcomingForPeople($pdo,[$id],$today,$until);
    else $missing[]='Plánované lekce';
    $activities=$read(['club_event_planned_participants','club_events','club_event_sessions'],
        "SELECT e.id,e.name,p.status,MIN(s.starts_at) AS starts_at FROM club_event_planned_participants p JOIN club_events e ON e.id=p.event_id JOIN club_event_sessions s ON s.event_id=e.id WHERE p.sportovec_id=? AND p.status<>'cancelled' AND e.planning_status<>'cancelled' AND s.status<>'cancelled' AND s.ends_at>=? GROUP BY e.id,e.name,p.status ORDER BY starts_at,e.id",[$id,$today.' 00:00:00'],'Plánované klubové akce');
    $caseRows=$read(['operational_cases'],"SELECT * FROM operational_cases WHERE source_type='person_followup' AND source_id=?",[$id],'Pracovní případ');
    $case=$caseRows[0]??null;
    $events=$case?$read(['operational_case_events','treneri'],'SELECT e.created_at,e.reason,e.after_json,t.jmeno FROM operational_case_events e JOIN treneri t ON t.id=e.actor_trainer_id WHERE e.case_id=? ORDER BY e.id DESC',[(int)$case['id']],'Historie vyřízení'):[];
    $history=['rows'=>[],'total'=>0,'page'=>1,'pages'=>1];
    if(adminOperationalHasTables($pdo,['sportovec_history','treneri'])){
        $history=staffListPage($pdo,'h.id,h.created_at,h.title,h.new_json,t.jmeno','FROM sportovec_history h LEFT JOIN treneri t ON t.id=h.created_by WHERE h.sportovec_id=? AND h.event_type=?','h.created_at DESC,h.id DESC',[$id,'status_manual'],max(1,(int)staffListText($input,'history_page')));
        foreach($history['rows'] as &$row){$v=json_decode((string)$row['new_json'],true);$row['membership']=is_array($v)?array_intersect_key($v,array_flip(['stav_clenstvi','stav_manualni','stav_duvod'])):[];unset($row['new_json']);}unset($row);
    }else $missing[]='Historie členství';
    return compact('person','steps','roles','rosters','paper','charges','trainings','until','activities','case','events','history','missing');
}

function personLifecycleSave(PDO $pdo,int $person,array $input): int
{
    $actor=personLifecycleAssertActor($pdo);
    if(staffListText($input,'confirmed')!=='1')throw new DomainException('Potvrďte samostatné vyřízení návazných agend.');
    // A single manual work item per person, using the existing audit and stale-write guard.
    return operationalCaseSave($pdo,$actor,'registrar',array_replace($input,['source_type'=>'person_followup','source_id'=>$person]));
}
