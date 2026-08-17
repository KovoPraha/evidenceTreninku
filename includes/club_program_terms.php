<?php
declare(strict_types=1);

const CLUB_PROGRAM_TERM_PURPOSES=['program_cancellation','program_consent'];
const CLUB_PROGRAM_TERM_DEFAULTS=[
    'program_cancellation'=>'Při zrušení účasti před začátkem období klub vrátí uhrazenou cenu po odečtení již vzniklých nevratných nákladů. Po zahájení období posoudí případné vrácení individuálně podle důvodu a již čerpané části programu.',
    'program_consent'=>'Souhlasím s přihlášením vybraného dítěte nebo účastníka do uvedeného kroužku, s jeho účastí na programu a s organizační komunikací klubu k této účasti.',
];

final class ClubProgramTermsException extends RuntimeException{}

function clubProgramTermsColumnExists(PDO $pdo,string $table,string $column):bool
{
    if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'){$statement=$pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');$statement->execute([$table,$column]);return(bool)$statement->fetchColumn();}
    foreach($pdo->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC)as$row)if((string)$row['name']===$column)return true;return false;
}

function clubProgramTermsRegistryAvailable(PDO $pdo):bool{return clubProgramTermsColumnExists($pdo,'club_event_term_versions','status');}
function clubProgramTermsAcceptanceAvailable(PDO $pdo):bool{return clubProgramTermsColumnExists($pdo,'shop_order_items','program_terms_snapshot_json')&&clubProgramTermsColumnExists($pdo,'club_program_enrollments','terms_snapshot_json');}

function clubProgramTermsScope(string $targetType,int $targetId):array
{
    if($targetId<1||!in_array($targetType,['program','offer'],true))throw new InvalidArgumentException('Podmínky vyžadují program nebo nabídku.');
    return$targetType==='program'?['club_program','program:'.$targetId]:['club_program_offer','offer:'.$targetId];
}

/** @return array<string,mixed>|false */
function clubProgramTermsCurrent(PDO $pdo,string $targetType,int $targetId,string $purpose,bool $lock=false):array|false
{
    if(!in_array($purpose,CLUB_PROGRAM_TERM_PURPOSES,true))throw new InvalidArgumentException('Typ dokumentu není podporován.');
    if(!clubProgramTermsRegistryAvailable($pdo))return false;
    [$scopeType,$scopeKey]=clubProgramTermsScope($targetType,$targetId);
    $sql="SELECT * FROM club_event_term_versions WHERE scope_type=? AND scope_key=? AND consent_purpose=? AND status='active' ORDER BY id DESC LIMIT 1";
    if($lock&&(string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$sql.=' FOR UPDATE';
    $statement=$pdo->prepare($sql);$statement->execute([$scopeType,$scopeKey,$purpose]);return$statement->fetch(PDO::FETCH_ASSOC);
}

/** @return array{program_cancellation:array<string,mixed>|null,program_consent:array<string,mixed>|null} */
function clubProgramTermsEffective(PDO $pdo,int $programId,int $offerId,bool $lock=false):array
{
    $result=[];
    foreach(CLUB_PROGRAM_TERM_PURPOSES as$purpose){
        $term=clubProgramTermsCurrent($pdo,'offer',$offerId,$purpose,$lock);
        if(!$term)$term=clubProgramTermsCurrent($pdo,'program',$programId,$purpose,$lock);
        $result[$purpose]=$term?:null;
    }
    return$result;
}

/** @param array<string,array<string,mixed>|null> $terms */
function clubProgramTermsComplete(array $terms):bool
{
    foreach(CLUB_PROGRAM_TERM_PURPOSES as$purpose)if(empty($terms[$purpose])||trim((string)($terms[$purpose]['consent_text_plain']??''))==='')return false;
    return true;
}

/** @return array{id:int,terms_version:string,changed:bool} */
function clubProgramTermsConfigure(PDO $pdo,int $actorId,string $targetType,int $targetId,string $purpose,string $text,bool $confirmed):array
{
    $pdo->beginTransaction();
    try{$result=clubProgramTermsConfigureInTransaction($pdo,$actorId,$targetType,$targetId,$purpose,$text,$confirmed);$pdo->commit();return$result;}
    catch(Throwable$exception){if($pdo->inTransaction())$pdo->rollBack();if($exception instanceof InvalidArgumentException||$exception instanceof ClubProgramTermsException)throw$exception;throw new ClubProgramTermsException('Podmínky se nepodařilo uložit bez částečného zápisu.',0,$exception);}
}

/** @return array{id:int,terms_version:string,changed:bool} */
function clubProgramTermsConfigureInTransaction(PDO $pdo,int $actorId,string $targetType,int $targetId,string $purpose,string $text,bool $confirmed):array
{
    $text=trim($text);
    if(!$pdo->inTransaction())throw new LogicException('Uložení podmínek vyžaduje transakci.');
    if($actorId<1||!$confirmed||!in_array($purpose,CLUB_PROGRAM_TERM_PURPOSES,true)||$text===''||mb_strlen($text,'UTF-8')>4000)throw new InvalidArgumentException('Nová verze vyžaduje správce, dokument do 4000 znaků a výslovné potvrzení.');
    [$scopeType,$scopeKey]=clubProgramTermsScope($targetType,$targetId);
    $table=$targetType==='program'?'club_programs':'club_program_offers';
    $sql='SELECT id FROM '.$table.' WHERE id=?';if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$sql.=' FOR UPDATE';
    $target=$pdo->prepare($sql);$target->execute([$targetId]);if($target->fetchColumn()===false)throw new ClubProgramTermsException('Program nebo nabídka nebyly nalezeny.');
    $current=clubProgramTermsCurrent($pdo,$targetType,$targetId,$purpose,true);
    if($current&&hash_equals(hash('sha256',(string)$current['consent_text_plain']),hash('sha256',$text)))return['id'=>(int)$current['id'],'terms_version'=>(string)$current['terms_version'],'changed'=>false];
    $versionQuery=$pdo->prepare('SELECT terms_version FROM club_event_term_versions WHERE scope_type=? AND scope_key=? AND consent_purpose=?');$versionQuery->execute([$scopeType,$scopeKey,$purpose]);
    $number=0;foreach($versionQuery->fetchAll(PDO::FETCH_COLUMN)as$version)if(preg_match('/^v([1-9][0-9]*)$/D',(string)$version,$match)===1)$number=max($number,(int)$match[1]);
    $version='v'.($number+1);
    if($current)$pdo->prepare("UPDATE club_event_term_versions SET status='archived',archived_at=CURRENT_TIMESTAMP,archived_by_trainer_id=? WHERE id=? AND status='active'")->execute([$actorId,(int)$current['id']]);
    $insert=$pdo->prepare('INSERT INTO club_event_term_versions(scope_type,scope_key,consent_purpose,event_id,terms_version,consent_text_plain,cancellation_policy_plain,cancellation_deadline_at,actor_trainer_id,actor_type,actor_id,status) VALUES(?,?,?,NULL,?,?,NULL,NULL,?,\'trainer\',?,\'active\')');
    $insert->execute([$scopeType,$scopeKey,$purpose,$version,$text,$actorId,$actorId]);
    return['id'=>(int)$pdo->lastInsertId(),'terms_version'=>$version,'changed'=>true];
}

/** @param array<string,array<string,mixed>|null> $terms */
function clubProgramTermsSnapshot(array $terms):array
{
    if(!clubProgramTermsComplete($terms))throw new ClubProgramTermsException('Kroužek nemá zveřejněné platné storno podmínky a souhlas.');
    $snapshot=['schema'=>'club-program-terms-v1','documents'=>[]];
    foreach(CLUB_PROGRAM_TERM_PURPOSES as$purpose){$term=$terms[$purpose];$text=(string)$term['consent_text_plain'];$snapshot['documents'][$purpose]=['term_version_id'=>(int)$term['id'],'version'=>(string)$term['terms_version'],'text'=>$text,'sha256'=>hash('sha256',$text)];}
    return$snapshot;
}

function clubProgramTermsSnapshotJson(array $terms):string
{
    return json_encode(clubProgramTermsSnapshot($terms),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
}

function clubProgramTermsSnapshotValid(string $json):bool
{
    try{$snapshot=json_decode($json,true,512,JSON_THROW_ON_ERROR);}catch(Throwable){return false;}
    if(($snapshot['schema']??null)!=='club-program-terms-v1')return false;
    foreach(CLUB_PROGRAM_TERM_PURPOSES as$purpose){$document=$snapshot['documents'][$purpose]??null;if(!is_array($document)||(int)($document['term_version_id']??0)<1||trim((string)($document['version']??''))===''||trim((string)($document['text']??''))===''||!hash_equals((string)($document['sha256']??''),hash('sha256',(string)$document['text'])))return false;}
    return true;
}
