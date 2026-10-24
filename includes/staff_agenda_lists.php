<?php
declare(strict_types=1);
require_once __DIR__.'/staff_record_list.php';

function staffExpenseList(PDO $pdo, string $kind, array $input): array
{
    $definitions=[
        'case'=>['club_expense_cases c LEFT JOIN treneri t ON t.id=c.leader_trainer_id',['c.title','t.jmeno','c.id'],'c.due_on',['open'=>'Otevřený','closed'=>'Uzavřený']],
        'claim'=>['club_expense_claims c',['c.supplier','c.document_number','c.payer_label','c.creditor_label','c.purpose','c.id'],'c.document_on',['draft'=>'Rozepsáno','submitted'=>'Předloženo','approved'=>'Schváleno','returned'=>'Vráceno k opravě','rejected'=>'Zamítnuto']],
        'advance'=>['club_expense_advances c',['c.recipient_label','c.purpose','c.id'],'c.due_on',['requested'=>'Požadováno','approved'=>'Schváleno','closed'=>'Uzavřeno']],
    ];
    if(!isset($definitions[$kind]))throw new InvalidArgumentException('Neplatný přehled.');
    [$table,$columns,$date,$states]=$definitions[$kind];$f=staffListFilters($input,$kind.'_',$states);$params=[];
    $where=staffListWhere($f,$columns,$date,'c.state',$params);
    return ['filters'=>$f]+staffListPage($pdo,'c.*','FROM '.$table.' WHERE '.$where,'c.id DESC',$params,$f['page']);
}

function staffRewardList(PDO $pdo, array $input, string $position, int $actor): array
{
    if($actor<1||!in_array($position,['coach','sports_lead','finance_manager'],true))throw new DomainException('Přehled odměn není dostupný.');
    $f=staffListFilters($input,'reward_',['pending'=>'Čeká na posouzení','approved'=>'Schváleno','returned'=>'Vráceno k doplnění']);$params=[];
    $from="FROM club_reward_claims c LEFT JOIN club_reward_decisions d ON d.version_id=c.current_version_id
        LEFT JOIN sportovci s ON c.kind='athlete' AND s.id=c.subject_id LEFT JOIN treneri t ON c.kind='coach' AND t.id=c.subject_id";
    $where=staffListWhere($f,['s.jmeno','s.prijmeni','t.jmeno','c.id'],'c.date_from',"COALESCE(d.outcome,'pending')",$params);
    if($position==='coach'){$where.=" AND c.kind='coach' AND c.subject_id=?";$params[]=$actor;}
    return ['filters'=>$f]+staffListPage($pdo,'c.*,s.jmeno AS person_first_name,s.prijmeni AS person_last_name,t.jmeno AS trainer_name',$from.' WHERE '.$where,'c.id DESC',$params,$f['page']);
}
