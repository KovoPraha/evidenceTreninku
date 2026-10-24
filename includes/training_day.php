<?php
declare(strict_types=1);
require_once __DIR__.'/training_closure.php';
require_once __DIR__.'/staff_record_list.php';

function trainingDayPlan(PDO $pdo,int $actor,int $planId): array
{
    trainingClosureAssertActor($pdo,$actor);
    $q=$pdo->prepare('SELECT * FROM planovane_treninky WHERE id=?');$q->execute([$planId]);$plan=$q->fetch(PDO::FETCH_ASSOC);
    if(!$plan)throw new TrainingClosureException('Lekce nebyla nalezena.');
    coachScopeAssertPlan($pdo,$planId);
    return $plan;
}

function trainingDayChoices(PDO $pdo,int $actor,string $search,string $on): array
{
    trainingClosureAssertActor($pdo,$actor);
    $f=staffListFilters(['q'=>$search,'from'=>$on,'to'=>$on],'',[]);$params=[];
    $where=staffListWhere($f,['p.nazev','p.id'],'p.datum',null,$params);
    if(staffActivePositionIs('coach')){$where.=' AND p.trener_id=?';$params[]=$actor;}
    $q=$pdo->prepare('SELECT p.* FROM planovane_treninky p WHERE '.$where.' ORDER BY p.datum DESC,p.cas_od,p.id DESC LIMIT 50');$q->execute($params);$out=[];
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){try{coachScopeAssertPlan($pdo,(int)$row['id']);$out[]=$row;}catch(RuntimeException){/* An owned plan does not grant access to a foreign roster. */}}
    return $out;
}
