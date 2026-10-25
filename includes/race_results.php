<?php
declare(strict_types=1);
require_once __DIR__.'/sports_measurement_contract.php';

function raceResultsJson(array $value): string { return json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR); }
/** Read-only legacy projection. Unknown discipline, heat and status require human review. */
function raceResultsLegacyPreview(PDO $pdo,int $raceId): array {
    $q=$pdo->prepare('SELECT z.*,s.jmeno,s.prijmeni FROM zavod_sportovec z JOIN sportovci s ON s.id=z.sportovec_id WHERE z.zavod_id=? ORDER BY s.id');
    $q->execute([$raceId]);$rows=[];
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $p)$rows[]=[
        'key'=>'person:'.$p['sportovec_id'],'person_id'=>(int)$p['sportovec_id'],
        'name'=>trim($p['prijmeni'].' '.$p['jmeno']),'club'=>'','discipline'=>'','heat'=>'',
        'status'=>sportsRaceResultStatus((string)($p['result_status']??''))??'entered',
        'time'=>(string)($p['cas']??''),'rank'=>$p['poradi']??'',
    ];
    return $rows;
}
function raceResultsText(mixed $value,int $max,string $label,bool $required=false): string {
    if(!is_scalar($value)&&$value!==null) throw new InvalidArgumentException($label.': neplatná hodnota.');
    $text=trim((string)$value);
    if(($required&&$text==='')||mb_strlen($text)>$max) throw new InvalidArgumentException($label.': vyplňte nejvýše '.$max.' znaků.');
    return $text;
}
/** Permission is checked again by the HTTP caller, using the active sports workspace. */
function raceResultsAssertAccess(PDO $pdo,int $raceId,int $actorId,bool $mayManage): void {
    if(!$mayManage||$actorId<1) throw new DomainException('Ke správě výsledků nemáte oprávnění.');
    $q=$pdo->prepare('SELECT id FROM zavody WHERE id=?');$q->execute([$raceId]);
    if(!$q->fetchColumn()) throw new DomainException('Závod není dostupný.');
}
/** Explicit stable participant keys; never match people by name. Full replacement of a draft only. */
function raceResultsNormalize(PDO $pdo,int $raceId,array $rows): array {
    if(!$rows||count($rows)>500) throw new InvalidArgumentException('Vložte 1 až 500 výsledků.');
    $result=[];$identities=[];$keys=[];
    foreach($rows as $index=>$row) {
        try {
            if(!is_array($row)) throw new InvalidArgumentException('Neplatný řádek.');
            // Keep stable keys identical under SQLite and MariaDB's case-insensitive collation.
            $key=strtolower(raceResultsText($row['key']??'',100,'Klíč účastníka',true));
            if(!preg_match('/^[a-zA-Z0-9_.:-]+$/D',$key)) throw new InvalidArgumentException('Klíč účastníka obsahuje nepovolené znaky.');
            $person=trim((string)($row['person_id']??''));
            if($person!==''&&(!ctype_digit($person)||(int)$person<1)) throw new InvalidArgumentException('Neplatné ID osoby.');
            $personId=$person===''?null:(int)$person;
            $name=raceResultsText($row['name']??'',200,'Jméno',true);
            $club=raceResultsText($row['club']??'',200,'Klub');
            if($personId!==null) {
                // Internal results must refer to an explicitly registered race participant.
                $q=$pdo->prepare('SELECT s.id FROM sportovci s JOIN zavod_sportovec z ON z.sportovec_id=s.id WHERE z.zavod_id=? AND s.id=?');
                $q->execute([$raceId,$personId]);
                if(!$q->fetchColumn()) throw new InvalidArgumentException('Osoba není přihlášená k tomuto závodu.');
                if($key!=='person:'.$personId) throw new InvalidArgumentException('Pro vlastní osobu použijte klíč person:ID.');
            } elseif(!str_starts_with($key,'ext:')) throw new InvalidArgumentException('Externí účastník potřebuje vlastní stabilní klíč ext:…');
            $identity=[$personId,$name,$club];
            if(isset($identities[$key])&&$identities[$key]!==$identity) throw new InvalidArgumentException('Stejný klíč má rozdílnou identitu.');
            $identities[$key]=$identity;
            $discipline=raceResultsText($row['discipline']??'',100,'Disciplína',true);
            $heat=raceResultsText($row['heat']??'',60,'Jízda',true);
            $unique=raceResultsJson([$key,$discipline,$heat]);
            if(isset($keys[$unique])) throw new InvalidArgumentException('Duplicitní účastník v disciplíně a jízdě.');
            $keys[$unique]=true;
            $status=sportsRaceResultStatus((string)($row['status']??''));
            if($status===null) throw new InvalidArgumentException('Vyberte stav výsledku.');
            $time=raceResultsText($row['time']??'',30,'Čas');
            $milliseconds=sportsMeasurementDurationMilliseconds($time);
            $rank=trim((string)($row['rank']??''));
            if($rank!==''&&(!ctype_digit($rank)||(int)$rank<1||(int)$rank>100000)) throw new InvalidArgumentException('Pořadí musí být kladné celé číslo.');
            if($status!=='finished'&&($rank!==''||$time!=='')) throw new InvalidArgumentException('DNS/DNF/DSQ a přihlášený účastník nemají čas ani pořadí.');
            if($status==='finished'&&$rank===''&&$time==='') throw new InvalidArgumentException('Dokončený výsledek potřebuje čas nebo pořadí.');
            $result[]=['key'=>$key,'person_id'=>$personId,'name'=>$name,'club'=>$club,'discipline'=>$discipline,'heat'=>$heat,'status'=>$status,'time'=>$time,'duration_ms'=>$milliseconds,'rank'=>$rank===''?null:(int)$rank];
        } catch(InvalidArgumentException $e) { throw new InvalidArgumentException('Řádek '.($index+1).': '.$e->getMessage()); }
    }
    return $result;
}
function raceResultsState(PDO $pdo,int $raceId): array {
    $q=$pdo->prepare('SELECT * FROM race_result_states WHERE race_id=?');$q->execute([$raceId]);
    return $q->fetch(PDO::FETCH_ASSOC)?:['race_id'=>$raceId,'version'=>0,'latest_revision_id'=>null,'published_revision_id'=>null];
}
function raceResultsRevision(PDO $pdo,int $raceId,?int $id): ?array {
    if(!$id)return null;
    $q=$pdo->prepare('SELECT * FROM race_result_revisions WHERE id=? AND race_id=?');$q->execute([$id,$raceId]);
    $r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)return null;$r['rows']=json_decode($r['rows_json'],true,32,JSON_THROW_ON_ERROR);return $r;
}
/** Atomic optimistic concurrency + request idempotency. Published revisions are immutable. */
function raceResultsWrite(PDO $pdo,int $raceId,int $actorId,bool $allowed,int $version,string $kind,array $rows,string $reason,string $request): array {
    raceResultsAssertAccess($pdo,$raceId,$actorId,$allowed);
    if(!in_array($kind,['draft','published'],true)||!preg_match('/^[a-f0-9]{32,64}$/D',$request)) throw new InvalidArgumentException('Neplatná akce nebo klíč požadavku.');
    $reason=raceResultsText($reason,500,'Důvod',true);
    if(mb_strlen($reason)<5) throw new InvalidArgumentException('Důvod musí mít alespoň 5 znaků.');
    if($pdo->inTransaction()) throw new LogicException('Výsledky vyžadují vlastní transakci.');
    $pdo->beginTransaction();
    try {
        $lock=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
        $q=$pdo->prepare('SELECT id FROM zavody WHERE id=?'.$lock);$q->execute([$raceId]);
        if(!$q->fetchColumn())throw new DomainException('Závod není dostupný.');
        $normalized=raceResultsNormalize($pdo,$raceId,$rows);
        $hash=hash('sha256',raceResultsJson([$raceId,$actorId,$kind,$normalized,$reason]));
        $q=$pdo->prepare('SELECT * FROM race_result_revisions WHERE request_key=?');$q->execute([$request]);$existing=$q->fetch(PDO::FETCH_ASSOC);
        if($existing){if(!hash_equals($existing['payload_hash'],$hash))throw new DomainException('Klíč požadavku už patří jinému zápisu.');$pdo->commit();return $existing+['idempotent'=>true];}
        $state=raceResultsState($pdo,$raceId);
        if((int)$state['version']!==$version) throw new DomainException('Výsledky mezitím změnil jiný uživatel. Obnovte náhled.');
        if($kind==='published') {
            $draft=raceResultsRevision($pdo,$raceId,(int)$state['latest_revision_id']);
            if(!$draft||$draft['kind']!=='draft'||raceResultsJson($draft['rows'])!==raceResultsJson($normalized)) throw new DomainException('Nejprve uložte a zkontrolujte tento koncept.');
        }
        foreach($normalized as $row) {
            $q=$pdo->prepare('SELECT * FROM race_participants WHERE race_id=? AND source_key=?');$q->execute([$raceId,$row['key']]);$p=$q->fetch(PDO::FETCH_ASSOC);
            if($p&&($p['sportovec_id']===null?null:(int)$p['sportovec_id'])!==$row['person_id'])throw new DomainException('Klíč účastníka nelze přepárovat na jinou osobu.');
            if(!$p)$pdo->prepare('INSERT INTO race_participants(race_id,source_key,sportovec_id,name_snapshot,club_snapshot) VALUES(?,?,?,?,?)')->execute([$raceId,$row['key'],$row['person_id'],$row['name'],$row['club']]);
        }
        $pdo->prepare('INSERT INTO race_result_revisions(race_id,version,kind,actor_id,reason,request_key,payload_hash,rows_json) VALUES(?,?,?,?,?,?,?,?)')->execute([$raceId,$version+1,$kind,$actorId,$reason,$request,$hash,raceResultsJson($normalized)]);
        $id=(int)$pdo->lastInsertId();
        if($version===0)$pdo->prepare('INSERT INTO race_result_states(race_id,version) VALUES(?,0)')->execute([$raceId]);
        $q=$pdo->prepare('UPDATE race_result_states SET version=?,latest_revision_id=?,published_revision_id=? WHERE race_id=? AND version=?');
        $q->execute([$version+1,$id,$kind==='published'?$id:$state['published_revision_id'],$raceId,$version]);
        if($q->rowCount()!==1)throw new DomainException('Konflikt verze výsledků.');
        $pdo->commit();return ['id'=>$id,'version'=>$version+1,'idempotent'=>false];
    } catch(Throwable $e) {if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function raceResultsParseCsv(string $csv): array {
    if(strlen($csv)>1000000)throw new InvalidArgumentException('CSV smí mít nejvýše 1 MB.');
    $stream=fopen('php://temp','r+');fwrite($stream,$csv);rewind($stream);
    try {
        $header=fgetcsv($stream,0,';', '"','');
        if($header!==['key','person_id','name','club','discipline','heat','status','time','rank']) throw new InvalidArgumentException('CSV potřebuje přesnou hlavičku uvedenou u formuláře.');
        $rows=[];
        while(($row=fgetcsv($stream,0,';','"',''))!==false) {
            if($row===[null])continue;
            if(count($row)!==count($header))throw new InvalidArgumentException('CSV řádek '.(count($rows)+2).' má jiný počet sloupců.');
            $rows[]=array_combine($header,$row);if(count($rows)>500)throw new InvalidArgumentException('Nejvýše 500 řádků.');
        }return $rows;
    } finally {fclose($stream);}
}
function raceResultsCsv(array $rows): string {
    $stream=fopen('php://temp','r+');$columns=['key','person_id','name','club','discipline','heat','status','time','rank'];fputcsv($stream,$columns,';','"','');
    foreach($rows as $row)fputcsv($stream,array_map(static fn($key)=>$row[$key]??'',$columns),';','"','');
    rewind($stream);$text=stream_get_contents($stream);fclose($stream);return $text;
}
