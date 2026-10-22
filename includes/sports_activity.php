<?php
declare(strict_types=1);
require_once __DIR__.'/coach_scope.php';
require_once __DIR__.'/club_event.php';

function sportsActivityStages():array {return ['nominated'=>'Nominován','accepted'=>'Potvrdil účast','registered'=>'Přihlášen pořadateli','attended'=>'Skutečně se účastnil','started'=>'Skutečně odstartoval','cancelled'=>'Účast zrušena'];}
function sportsActivityDate(string $value):string {
    $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
    if(!$date||$date->format('Y-m-d')!==$value)throw new InvalidArgumentException('Neplatné datum.');return $value;
}
/** Rechecks live assignment, not merely a cached role or event ownership. */
function sportsActivityAssertActor(PDO $pdo,int $actor,int $personId):void {
    $position=staffActivePosition();
    if($actor<1||$actor!==(int)($_SESSION['trener_id']??0)||!in_array($position,['coach','sports_lead'],true))throw new RuntimeException('Sportovní evidence není v této pracovní pozici dostupná.');
    $lock=$pdo->inTransaction()&&$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
    $s=$pdo->prepare('SELECT id FROM treneri WHERE id=? AND aktivni=1'.$lock);$s->execute([$actor]);if(!$s->fetchColumn())throw new RuntimeException('Pracovní oprávnění již neplatí.');
    $s=$pdo->prepare('SELECT 1 FROM staff_user_positions WHERE trainer_id=? AND position_code=?');$s->execute([$actor,$position]);
    if(!$s->fetchColumn())throw new RuntimeException('Pracovní oprávnění již neplatí.');
    if($personId>0&&!coachScopeCanViewPerson($pdo,$personId))throw new RuntimeException('Sportovec není ve vašem pracovním rozsahu.');
}
function sportsEventValidate(array $input):array {
    $stage=(string)($input['stage']??'');if(!isset(sportsActivityStages()[$stage]))throw new InvalidArgumentException('Vyberte stav účasti.');
    $date=trim((string)($input['actual_date']??''));$actual=in_array($stage,['attended','started'],true);
    if($actual){sportsActivityDate($date);if($date>date('Y-m-d'))throw new InvalidArgumentException('Skutečnou účast nelze potvrdit do budoucna.');}
    elseif($date!=='')throw new InvalidArgumentException('Datum skutečné účasti patří jen k doložené účasti nebo startu.');
    $result=strtoupper(trim((string)($input['result_status']??'')));
    if(!in_array($result,['','FINISHED','DNS','DNF','DSQ','UNKNOWN'],true))throw new InvalidArgumentException('Neplatný stav výsledku.');
    if(($stage==='started'&&$result==='DNS')||($stage!=='started'&&$result!==''&&$result!=='DNS')||($result==='DNS'&&!in_array($stage,['registered','cancelled'],true)))throw new InvalidArgumentException('Stav výsledku odporuje skutečné účasti.');
    $raw=trim((string)($input['result_value']??''));$unit=trim((string)($input['result_unit']??''));
    if(mb_strlen($raw)>255||mb_strlen($unit)>40)throw new InvalidArgumentException('Výsledek nebo jednotka jsou příliš dlouhé.');
    if($raw!==''&&$stage!=='started')throw new InvalidArgumentException('Výsledek vyžaduje doložený start.');
    $source=trim((string)($input['source_reference']??''));$reason=trim((string)($input['reason']??''));
    if(mb_strlen($source)<5||mb_strlen($source)>1000||mb_strlen($reason)<10||mb_strlen($reason)>1000)throw new InvalidArgumentException('Uveďte dohledatelný podklad (5–1000 znaků) a důvod (10–1000 znaků).');
    if(($input['confirmed']??false)!==true)throw new InvalidArgumentException('Zápis vyžaduje výslovné potvrzení.');
    return ['stage'=>$stage,'actual_date'=>$actual?$date:null,'result_status'=>$result===''?null:$result,'result_value'=>$raw===''?null:$raw,'result_unit'=>$unit===''?null:$unit,'source_reference'=>$source,'reason'=>$reason];
}
/** One serialized event/person history. Corrections append; neither enrollment nor payments are mutated. */
function sportsEventRecord(PDO $pdo,int $eventId,int $personId,int $actor,array $input,int $expectedVersion,string $requestKey):array {
    $data=sportsEventValidate($input);
    if(min($eventId,$personId)<1||$expectedVersion<0||preg_match('/^[a-zA-Z0-9:_.-]{12,100}$/D',$requestKey)!==1)throw new InvalidArgumentException('Neplatná akce, osoba, verze nebo klíč požadavku.');
    $hash=hash('sha256',json_encode([$eventId,$personId,$actor,$expectedVersion,$data],JSON_THROW_ON_ERROR));
    $pdo->beginTransaction();try{
        sportsActivityAssertActor($pdo,$actor,$personId);
        $lock=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
        $q=$pdo->prepare('SELECT * FROM club_events WHERE id=?'.$lock);$q->execute([$eventId]);$event=$q->fetch(PDO::FETCH_ASSOC);if(!$event)throw new InvalidArgumentException('Akce neexistuje.');
        if(staffActivePosition()==='coach'){
            $q=$pdo->prepare("SELECT 1 FROM club_event_people WHERE event_id=? AND trainer_id=? AND person_role IN ('lead_coach','coach','organizer')");$q->execute([$eventId,$actor]);
            if(!$q->fetchColumn())throw new RuntimeException('Nejste pověřen evidencí této akce.');
        }
        $q=$pdo->prepare('SELECT * FROM sports_event_revisions WHERE request_key=?');$q->execute([$requestKey]);$retry=$q->fetch(PDO::FETCH_ASSOC);
        if($retry){if(!hash_equals($retry['request_hash'],$hash))throw new RuntimeException('Klíč požadavku již byl použit pro jiný zápis.');$pdo->commit();return ['id'=>(int)$retry['id'],'version'=>(int)$retry['version'],'changed'=>false];}
        if($data['stage']==='started'&&($event['activity_kind']??'')!=='race')throw new InvalidArgumentException('Start a závodní výsledek patří pouze k závodu.');
        if(in_array($data['stage'],['attended','started'],true)&&($event['planning_status']??'')==='cancelled')throw new InvalidArgumentException('Zrušená akce vyžaduje nejprve opravu kalendáře.');
        $q=$pdo->prepare('SELECT p.id,r.version FROM sports_event_participation p LEFT JOIN sports_event_revisions r ON r.id=p.current_revision_id WHERE p.event_id=? AND p.sportovec_id=?');$q->execute([$eventId,$personId]);$current=$q->fetch(PDO::FETCH_ASSOC);
        $version=(int)($current['version']??0);if($expectedVersion!==$version)throw new RuntimeException('Účast mezitím změnil jiný pracovník. Načtěte aktuální verzi.');
        if(!$current){$pdo->prepare('INSERT INTO sports_event_participation(event_id,sportovec_id) VALUES(?,?)')->execute([$eventId,$personId]);$participationId=(int)$pdo->lastInsertId();}else $participationId=(int)$current['id'];
        $snapshot=$data+['event_id'=>$eventId,'person_id'=>$personId,'event_name'=>$event['name'],'activity_kind'=>$event['activity_kind']??null,'legacy_race_id'=>$event['legacy_race_id']??null,'version'=>$version+1];
        $pdo->prepare('INSERT INTO sports_event_revisions(participation_id,version,stage,actual_date,result_status,snapshot_json,reason,source_reference,actor_trainer_id,request_key,request_hash) VALUES(?,?,?,?,?,?,?,?,?,?,?)')->execute([$participationId,$version+1,$data['stage'],$data['actual_date'],$data['result_status'],json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$data['reason'],$data['source_reference'],$actor,$requestKey,$hash]);
        $id=(int)$pdo->lastInsertId();$pdo->prepare('UPDATE sports_event_participation SET current_revision_id=? WHERE id=?')->execute([$id,$participationId]);
        $pdo->commit();return ['id'=>$id,'version'=>$version+1,'changed'=>true];
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function sportsEventCurrent(PDO $pdo,int $personId):array {
    $q=$pdo->prepare('SELECT r.*,p.event_id FROM sports_event_participation p JOIN sports_event_revisions r ON r.id=p.current_revision_id WHERE p.sportovec_id=? ORDER BY r.recorded_at DESC,r.id DESC');$q->execute([$personId]);return $q->fetchAll(PDO::FETCH_ASSOC);
}
/** Keep original text and explicit units; an unparsed historical value is never zero. */
function sportsActivityMeasurements(PDO $pdo,int $personId,string $from,string $to):array {
    foreach(['mereni_zaznamy','trenink_mereni','treninky'] as $table)if(!memberChargeReadTableExists($pdo,$table))return [];
    if(!clubEventColumnExists($pdo,'mereni_zaznamy','duration_ms'))return [];
    $q=$pdo->prepare('SELECT DISTINCT m.id,m.typ,m.vzdalenost,m.distance_unit,m.distance_meters,m.cas,m.duration_ms,m.rpe,m.rpe_value,m.contract_version,t.id AS training_id,t.datum AS activity_date FROM mereni_zaznamy m JOIN trenink_mereni tm ON tm.mereni_id=m.id JOIN treninky t ON t.id=tm.trenink_id WHERE m.sportovec_id=? AND t.datum BETWEEN ? AND ? ORDER BY t.datum,m.id,t.id');$q->execute([$personId,$from,$to]);
    return $q->fetchAll(PDO::FETCH_ASSOC);
}
/** Canonical actual activities: plans/RSVP/nomination and unknown legacy evidence never become attendance. */
function sportsActivitySummary(PDO $pdo,int $personId,string $from,string $to):array {
    sportsActivityDate($from);sportsActivityDate($to);if($from>$to||$personId<1)throw new InvalidArgumentException('Neplatné období nebo osoba.');
    $activities=[];
    if(is_file(__DIR__.'/training_closure.php'))require_once __DIR__.'/training_closure.php';
    if(function_exists('trainingClosedActivitiesForPerson')&&memberChargeReadTableExists($pdo,'training_closures')){
        foreach(trainingClosedActivitiesForPerson($pdo,$personId,$from,$to) as $row){
            $activities[]=['kind'=>'training','activity_id'=>(int)$row['training_id'],'revision_id'=>(int)$row['revision_id'],'version'=>(int)$row['version'],'date'=>$row['actual_date'],'duration_minutes'=>$row['duration_minutes']===null?null:(int)$row['duration_minutes'],'role'=>'athlete','source'=>'training_closure_revisions','result_status'=>null,'result_value'=>null,'result_unit'=>null,'coach_ids'=>$row['coach_ids']];
        }
    }
    if(memberChargeReadTableExists($pdo,'sports_event_participation'))foreach(sportsEventCurrent($pdo,$personId) as $row){
        if(!in_array($row['stage'],['attended','started'],true)||$row['actual_date']<$from||$row['actual_date']>$to)continue;
        $snapshot=json_decode($row['snapshot_json'],true,512,JSON_THROW_ON_ERROR);
        $activities[]=['kind'=>$row['stage']==='started'?'race':'event','activity_id'=>(int)$row['event_id'],'revision_id'=>(int)$row['id'],'version'=>(int)$row['version'],'date'=>$row['actual_date'],'duration_minutes'=>null,'role'=>'athlete','source'=>'sports_event_revisions','result_status'=>$snapshot['result_status'],'result_value'=>$snapshot['result_value'],'result_unit'=>$snapshot['result_unit'],'coach_ids'=>[]];
    }
    usort($activities,static fn($a,$b)=>[$a['date'],$a['kind'],$a['activity_id']]<=>[$b['date'],$b['kind'],$b['activity_id']]);
    $training=array_values(array_filter($activities,static fn($a)=>$a['kind']==='training'));
    return ['contract'=>'sports-actual.v1','person_id'=>$personId,'from'=>$from,'to'=>$to,'training_count'=>count($training),'training_minutes'=>array_sum(array_column($training,'duration_minutes')),'unknown_duration_count'=>count(array_filter($training,static fn($a)=>$a['duration_minutes']===null)),'race_count'=>count(array_filter($activities,static fn($a)=>$a['kind']==='race')),'activities'=>$activities,'measurements'=>sportsActivityMeasurements($pdo,$personId,$from,$to)];
}
function sportsActivityHash(array $summary):string {return hash('sha256',json_encode($summary,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));}
function sportsActivityStatement(PDO $pdo,int $personId,string $from,string $to,int $actor,bool $confirmed):int {
    if(!$confirmed)throw new InvalidArgumentException('Předání podkladu vyžaduje výslovné potvrzení.');
    $pdo->beginTransaction();try{
        sportsActivityAssertActor($pdo,$actor,$personId);
        $lock=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';$q=$pdo->prepare('SELECT id FROM sportovci WHERE id=?'.$lock);$q->execute([$personId]);if(!$q->fetchColumn())throw new InvalidArgumentException('Osoba neexistuje.');
        $summary=sportsActivitySummary($pdo,$personId,$from,$to);$hash=sportsActivityHash($summary);
        $q=$pdo->prepare('SELECT id FROM sports_activity_statements WHERE sportovec_id=? AND date_from=? AND date_to=? AND snapshot_hash=?');$q->execute([$personId,$from,$to,$hash]);$id=(int)$q->fetchColumn();
        if(!$id){$pdo->prepare('INSERT INTO sports_activity_statements(sportovec_id,date_from,date_to,snapshot_json,snapshot_hash,actor_trainer_id) VALUES(?,?,?,?,?,?)')->execute([$personId,$from,$to,json_encode($summary,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$hash,$actor]);$id=(int)$pdo->lastInsertId();}
        $pdo->commit();return $id;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function sportsActivityCsv(array $summary):string {
    $s=fopen('php://temp','w+b');fwrite($s,"\xEF\xBB\xBF");fputcsv($s,['osoba','od','do','druh','aktivita','revize','verze','datum','role','minuty','stav_vysledku','hodnota','jednotka','zdroj','treneri'],';','"','');
    foreach($summary['activities'] as $a){$values=[$summary['person_id'],$summary['from'],$summary['to'],$a['kind'],$a['activity_id'],$a['revision_id'],$a['version'],$a['date'],$a['role'],$a['duration_minutes'],$a['result_status'],$a['result_value'],$a['result_unit'],$a['source'],implode(',', $a['coach_ids'])];$values=array_map(static fn($v)=>is_string($v)&&preg_match('/^[=+@\-\t\r]/',$v)?"'".$v:$v,$values);fputcsv($s,$values,';','"','');}rewind($s);$csv=stream_get_contents($s);fclose($s);return $csv;
}
function sportsActivityMeasurementsCsv(array $summary):string {
    $s=fopen('php://temp','w+b');fwrite($s,"\xEF\xBB\xBF");fputcsv($s,['osoba','datum','trenink','mereni','typ','puvodni_vzdalenost','jednotka','metry','puvodni_cas','cas_ms','puvodni_rpe','rpe','kontrakt','zdroj'],';','"','');
    foreach($summary['measurements']??[] as $m){$values=[$summary['person_id'],$m['activity_date'],$m['training_id'],$m['id'],$m['typ'],$m['vzdalenost'],$m['distance_unit'],$m['distance_meters'],$m['cas'],$m['duration_ms'],$m['rpe'],$m['rpe_value'],$m['contract_version'],'mereni_zaznamy'];$values=array_map(static fn($v)=>is_string($v)&&preg_match('/^[=+@\-\t\r]/',$v)?"'".$v:$v,$values);fputcsv($s,$values,';','"','');}rewind($s);$csv=stream_get_contents($s);fclose($s);return $csv;
}
