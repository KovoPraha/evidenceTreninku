<?php
declare(strict_types=1);
require_once __DIR__.'/sports_activity_form.php';
require_once __DIR__.'/operational_cases.php';
require_once __DIR__.'/club_calendar.php';

function clubEventWorkActor(PDO $pdo): int {
    $actor=(int)($_SESSION['trener_id']??0);$position=staffActivePosition();
    if(!in_array($position,['coach','sports_lead','finance_manager'],true))throw new DomainException('Zajištění akce není v této pracovní pozici.');
    operationalCaseActor($pdo,$actor,$position);return $actor;
}
function clubEventWorkPredicate(int $actor):string {return staffActivePosition()==='coach'?sportsActivityEventPredicate($actor):'1=1';}
function clubEventWorkList(PDO $pdo,array $input):array {
    $actor=clubEventWorkActor($pdo);$f=staffListFilters($input,'event_',clubCalendarPlanningStatuses());$params=[];
    $where=staffListWhere($f,['e.name','e.id'],"(SELECT MIN(s.starts_at) FROM club_event_sessions s WHERE s.event_id=e.id)",'e.planning_status',$params);
    // The upper date includes the whole selected day for datetime sessions.
    if($f['to']!==''&&$f['error']===''){$index=count($params)-($f['state']!==''?2:1);$params[$index]=$f['to'].' 23:59:59';}
    return ['filters'=>$f]+staffListPage($pdo,'e.id,e.name,e.planning_status,(SELECT MIN(s.starts_at) FROM club_event_sessions s WHERE s.event_id=e.id) AS starts_at','FROM club_events e WHERE '.clubEventWorkPredicate($actor).' AND '.$where,'e.id DESC',$params,$f['page']);
}
function clubEventWorkRead(PDO $pdo,int $id,array $input=[]):array {
    $actor=clubEventWorkActor($pdo);$q=$pdo->prepare('SELECT e.id,e.name,e.planning_status,e.activity_kind FROM club_events e WHERE e.id=? AND '.clubEventWorkPredicate($actor));$q->execute([$id]);$event=$q->fetch(PDO::FETCH_ASSOC);
    if(!$event)throw new DomainException('Akce neexistuje nebo k ní nemáte pracovní přístup.');
    $q=$pdo->prepare('SELECT starts_at,ends_at,location,status FROM club_event_sessions WHERE event_id=? ORDER BY starts_at');$q->execute([$id]);$sessions=$q->fetchAll(PDO::FETCH_ASSOC);
    $q=$pdo->prepare('SELECT p.person_role,p.external_name,t.jmeno FROM club_event_people p LEFT JOIN treneri t ON t.id=p.trainer_id WHERE p.event_id=? ORDER BY p.id');$q->execute([$id]);$people=$q->fetchAll(PDO::FETCH_ASSOC);
    $q=$pdo->prepare('SELECT v.vehicle_id,v.starts_at,v.ends_at,v.status,v.driver_name,t.jmeno,car.znacka_model,car.spz FROM club_event_vehicle_reservations v JOIN ucto_vozidla car ON car.id=v.vehicle_id LEFT JOIN treneri t ON t.id=v.driver_trainer_id WHERE v.event_id=? ORDER BY v.starts_at');$q->execute([$id]);$vehicles=$q->fetchAll(PDO::FETCH_ASSOC);
    $f=staffListFilters($input,'participant_',['planned'=>'Plánuje se','confirmed'=>'Potvrzeno','waitlisted'=>'Náhradník','cancelled'=>'Zrušeno']);$params=[$id,$id];
    $f['from']='';$f['to']=''; // Participant filter has no independent date range.
    $where=staffListWhere($f,['s.jmeno','s.prijmeni','s.id'],'s.id','p.status',$params);
    $scope=staffActivePosition()==='coach'?coachScopePersonPredicate($pdo):'1=1';
    $from='FROM sportovci s LEFT JOIN club_event_planned_participants p ON p.sportovec_id=s.id AND p.event_id=? LEFT JOIN sports_event_participation a ON a.sportovec_id=s.id AND a.event_id=? LEFT JOIN sports_event_revisions r ON r.id=a.current_revision_id WHERE (p.id IS NOT NULL OR a.id IS NOT NULL) AND '.$scope.' AND '.$where;
    $participants=['filters'=>$f]+staffListPage($pdo,'s.id,s.jmeno,s.prijmeni,p.status AS planned_status,r.stage,r.version,r.actual_date',$from,'s.prijmeni,s.jmeno,s.id',$params,$f['page']);
    // Only operational status crosses the finance boundary, never amounts, receipt contents or payees.
    $q=$pdo->prepare("SELECT c.id,c.title,c.state,c.due_on,t.jmeno AS leader,(SELECT COUNT(*) FROM club_expense_claims x WHERE x.case_id=c.id AND x.state IN ('draft','submitted')) AS pending_claims,(SELECT COUNT(*) FROM club_expense_advances a WHERE a.case_id=c.id AND a.state<>'closed') AS open_advances FROM club_expense_cases c LEFT JOIN treneri t ON t.id=c.leader_trainer_id WHERE c.club_event_id=? ORDER BY c.id DESC");$q->execute([$id]);$cases=$q->fetchAll(PDO::FETCH_ASSOC);
    return compact('event','sessions','people','vehicles','participants','cases');
}
