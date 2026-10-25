<?php
declare(strict_types=1);
require_once __DIR__.'/sports_activity.php';
require_once __DIR__.'/staff_record_list.php';

function sportsActivityEventPredicate(int $actor): string
{
    return staffActivePosition()==='sports_lead'?'1=1':"EXISTS(SELECT 1 FROM club_event_people ep WHERE ep.event_id=e.id AND ep.trainer_id=".(int)$actor." AND ep.person_role IN ('lead_coach','coach','organizer'))";
}

function sportsActivityEventChoices(PDO $pdo,int $actor,int $person,string $search): array
{
    sportsActivityAssertActor($pdo,$actor,$person);
    $f=staffListFilters(['q'=>$search],'',[]);$params=[];
    $where=staffListWhere($f,['e.name','e.id'],'e.created_at',null,$params);
    $q=$pdo->prepare('SELECT e.id,e.name,(SELECT MIN(es.starts_at) FROM club_event_sessions es WHERE es.event_id=e.id) AS starts_at FROM club_events e WHERE '.sportsActivityEventPredicate($actor).' AND '.$where.' ORDER BY e.id DESC LIMIT 50');$q->execute($params);
    return $q->fetchAll(PDO::FETCH_ASSOC);
}

function sportsActivityFormContext(PDO $pdo,int $actor,int $person,int $eventId,?array $failedPost=null): array
{
    sportsActivityAssertActor($pdo,$actor,$person);
    $q=$pdo->prepare('SELECT e.id,e.name FROM club_events e WHERE e.id=? AND '.sportsActivityEventPredicate($actor));$q->execute([$eventId]);$event=$q->fetch(PDO::FETCH_ASSOC);
    if(!$event)throw new RuntimeException('Akce neexistuje nebo k její evidenci nemáte oprávnění.');
    $q=$pdo->prepare('SELECT r.* FROM sports_event_participation p JOIN sports_event_revisions r ON r.id=p.current_revision_id WHERE p.event_id=? AND p.sportovec_id=?');$q->execute([$eventId,$person]);$current=$q->fetch(PDO::FETCH_ASSOC)?:null;
    $snapshot=$current?json_decode($current['snapshot_json'],true,512,JSON_THROW_ON_ERROR):[];
    $form=['expected_version'=>(int)($current['version']??0),'stage'=>$snapshot['stage']??'nominated',
        'actual_date'=>$snapshot['actual_date']??'','result_status'=>$snapshot['result_status']??'',
        'result_value'=>$snapshot['result_value']??'','result_unit'=>$snapshot['result_unit']??'',
        'source_reference'=>$snapshot['source_reference']??'','reason'=>'','confirmed'=>'',
        'request_key'=>'sport:'.bin2hex(random_bytes(20))];
    // Preserve the submitted revision on conflict. Reload must be an explicit GET.
    if($failedPost!==null){foreach(array_keys($form) as $key)$form[$key]=staffListText($failedPost,$key);$form['expected_version']=(int)$form['expected_version'];}
    return ['event'=>$event,'current'=>$current,'form'=>$form];
}
