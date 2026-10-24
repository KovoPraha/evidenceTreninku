<?php
declare(strict_types=1);
require_once __DIR__.'/operational_cases.php';
require_once __DIR__.'/staff_record_list.php';

function clubCommunicationActor(PDO $pdo):string {
    $position=staffActivePosition();if(!in_array($position,['registrar','order_operator','system_admin'],true))throw new DomainException('Přehled komunikace není v této pracovní pozici.');
    operationalCaseActor($pdo,(int)($_SESSION['trener_id']??0),$position);return $position;
}
function clubCommunicationNotice(?string $disabled=null):string {
    $disabled=array_map('trim',explode(',',strtolower($disabled??(string)ini_get('disable_functions'))));
    $prefix=in_array('mail',$disabled,true)?'PHP e-mailové odesílání je v tomto prostředí blokované. ':'Dostupnost PHP mail sama nepotvrzuje aktivní odesílání. ';
    return $prefix.'Stav fronty dokládá zpracování aplikací, nikoli doručení do schránky. Aktivní plánovač, zvolený transport a skutečné doručení nejsou tímto přehledem ověřeny; stav „sent“ může pocházet i z místního outboxu.';
}
function clubCommunicationQueues(PDO $pdo):array {
    clubCommunicationActor($pdo);$result=[];$now=new DateTimeImmutable();$stale=$now->modify('-15 minutes')->format('Y-m-d H:i:s');
    foreach(['roster_message_recipients'=>'Zprávy skupinám','family_weekly_summaries'=>'Rodinné souhrny'] as $table=>$label){
        if(!adminOperationalHasTables($pdo,[$table])){$result[]=['label'=>$label,'ready'=>false];continue;}
        $counts=[];foreach($pdo->query('SELECT status,COUNT(*) AS n FROM '.$table.' GROUP BY status')->fetchAll(PDO::FETCH_ASSOC) as $r)$counts[$r['status']]=(int)$r['n'];
        $q=$pdo->prepare("SELECT COUNT(*) FROM $table WHERE status='processing' AND (claimed_at IS NULL OR claimed_at<?)");$q->execute([$stale]);
        $result[]=['label'=>$label,'ready'=>true,'counts'=>$counts,'stale'=>(int)$q->fetchColumn(),'oldest'=>$pdo->query("SELECT MIN(created_at) FROM $table WHERE status='pending'")->fetchColumn()?:null,'last_sent'=>$pdo->query("SELECT MAX(sent_at) FROM $table WHERE status='sent'")->fetchColumn()?:null];
    }return $result;
}
function clubCommunicationList(PDO $pdo,string $kind,array $input):array {
    $position=clubCommunicationActor($pdo);$allowed=($kind==='roster'&&$position==='registrar')||($kind==='family'&&$position==='order_operator');if(!$allowed)throw new DomainException('Obsah této fronty vyžaduje její vlastní pracovní pozici.');
    $statusColumn='m.status';
    if($kind==='roster'){
        // Held recipients may leave the old parent status at queued. Show actual recipient states without running a worker.
        $table="roster_messages m JOIN club_teams t ON t.id=m.team_id LEFT JOIN (SELECT message_id,COUNT(*) AS total,SUM(CASE WHEN status='sent' THEN 1 ELSE 0 END) AS sent_count,SUM(CASE WHEN status='failed' THEN 1 ELSE 0 END) AS failed_count,SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) AS pending_count,SUM(CASE WHEN status='processing' THEN 1 ELSE 0 END) AS processing_count FROM roster_message_recipients GROUP BY message_id) d ON d.message_id=m.id";
        $statusColumn="CASE WHEN COALESCE(d.total,0)<>m.recipient_count OR COALESCE(d.total,0)=0 THEN 'incomplete' WHEN d.processing_count>0 THEN 'processing' WHEN d.pending_count>0 THEN 'queued' WHEN d.failed_count>0 THEN CASE WHEN d.sent_count>0 THEN 'partial' ELSE 'failed' END WHEN d.sent_count=d.total THEN 'sent' ELSE 'incomplete' END";
        $columns=['m.subject_plain','t.name','m.id'];$states=['queued'=>'Čeká','processing'=>'Zpracovává se','sent'=>'Zpracováno','failed'=>'Selhalo / zadrženo','partial'=>'Částečně zpracováno','incomplete'=>'Nesouhlasí evidence příjemců'];
        $select="m.*,m.status AS stored_status,($statusColumn) AS status,t.name AS team_name,COALESCE(d.sent_count,0) AS sent_count,COALESCE(d.failed_count,0) AS failed_count";
    }
    else{$table='family_weekly_summaries m';$columns=['m.recipient_name','m.recipient_email','m.subject_plain','m.id'];$states=['pending'=>'Čeká','processing'=>'Zpracovává se','sent'=>'Zpracováno','failed'=>'Selhalo','cancelled'=>'Zrušeno'];$select='m.*';}
    $f=staffListFilters($input,'queue_',$states);$params=[];$where=staffListWhere($f,$columns,'m.created_at','('.$statusColumn.')',$params);
    if($f['to']!==''&&$f['error']==='')$params[count($params)-($f['state']!==''?2:1)]=$f['to'].' 23:59:59';
    return ['filters'=>$f]+staffListPage($pdo,$select,'FROM '.$table.' WHERE '.$where,'m.id DESC',$params,$f['page']);
}
