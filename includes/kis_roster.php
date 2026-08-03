<?php
declare(strict_types=1);

final class KisRosterException extends RuntimeException{}

function kisRosterDate(string $value):string{$date=DateTimeImmutable::createFromFormat('!Y-m-d',trim($value));if(!$date||$date->format('Y-m-d')!==trim($value))throw new InvalidArgumentException('Datum musí mít formát RRRR-MM-DD.');return$date->format('Y-m-d');}
function kisRosterCode(string $value,int $max=48):string{$value=strtoupper(trim($value));if(strlen($value)>$max||preg_match('/^[A-Z0-9][A-Z0-9_-]{2,}$/D',$value)!==1)throw new InvalidArgumentException('Kód musí mít alespoň 3 znaky A–Z, 0–9, pomlčku nebo podtržítko.');return$value;}
function kisRosterText(string $value,int $max,string $label):string{$value=trim($value);if($value===''||mb_strlen($value,'UTF-8')>$max||preg_match('/[<>]/u',$value)===1)throw new InvalidArgumentException($label.' musí být neprázdný prostý text.');return$value;}

/** @return array<string,mixed> */
function kisRosterCreateSeason(PDO $pdo,int $actorId,string $code,string $name,string $startsOn,string $endsOn):array
{
    $code=kisRosterCode($code,32);$name=kisRosterText($name,120,'Název sezóny');$startsOn=kisRosterDate($startsOn);$endsOn=kisRosterDate($endsOn);
    if($actorId<1||$startsOn>=$endsOn)throw new InvalidArgumentException('Sezóna vyžaduje administrátora a konec po začátku.');
    $existing=$pdo->prepare('SELECT * FROM club_seasons WHERE code=?');$existing->execute([$code]);$row=$existing->fetch(PDO::FETCH_ASSOC);
    if($row){if($row['name']!==$name||$row['starts_on']!==$startsOn||$row['ends_on']!==$endsOn)throw new KisRosterException('Kód sezóny už označuje jiné neměnné období.');return$row;}
    $statement=$pdo->prepare("INSERT INTO club_seasons(code,name,starts_on,ends_on,status,created_by_trainer_id) VALUES (?,?,?,?,'active',?)");$statement->execute([$code,$name,$startsOn,$endsOn,$actorId]);
    return['id'=>(int)$pdo->lastInsertId(),'code'=>$code,'name'=>$name,'starts_on'=>$startsOn,'ends_on'=>$endsOn,'status'=>'active'];
}

/** @return array<string,mixed> */
function kisRosterCreateTeam(PDO $pdo,int $seasonId,int $actorId,string $code,string $name,string $discipline,string $ageLabel,string $note):array
{
    $code=kisRosterCode($code);$name=kisRosterText($name,160,'Název týmu');$discipline=kisRosterText($discipline,120,'Disciplína');$ageLabel=kisRosterText($ageLabel,120,'Věková kategorie');$note=kisRosterText($note,1000,'Důvod');
    if($seasonId<1||$actorId<1)throw new InvalidArgumentException('Tým vyžaduje sezónu a administrátora.');
    $pdo->beginTransaction();try{
        $season=$pdo->prepare('SELECT * FROM club_seasons WHERE id=?');$season->execute([$seasonId]);if(!$season->fetch(PDO::FETCH_ASSOC))throw new KisRosterException('Sezóna nebyla nalezena.');
        $existing=$pdo->prepare('SELECT * FROM club_teams WHERE season_id=? AND code=?');$existing->execute([$seasonId,$code]);$team=$existing->fetch(PDO::FETCH_ASSOC);
        if($team){if($team['name']!==$name||$team['discipline']!==$discipline||$team['age_label']!==$ageLabel)throw new KisRosterException('Kód týmu už označuje jiný tým.');$pdo->commit();return$team;}
        $insert=$pdo->prepare("INSERT INTO club_teams(season_id,code,name,discipline,age_label,status,created_by_trainer_id) VALUES (?,?,?,?,?,'active',?)");$insert->execute([$seasonId,$code,$name,$discipline,$ageLabel,$actorId]);$id=(int)$pdo->lastInsertId();
        kisRosterEvent($pdo,$id,null,$actorId,'create_team',null,['id'=>$id,'code'=>$code,'name'=>$name,'discipline'=>$discipline,'age_label'=>$ageLabel,'status'=>'active'],$note);$pdo->commit();return['id'=>$id,'season_id'=>$seasonId,'code'=>$code,'name'=>$name,'discipline'=>$discipline,'age_label'=>$ageLabel,'status'=>'active'];
    }catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();if($e instanceof InvalidArgumentException||$e instanceof KisRosterException)throw$e;throw new KisRosterException('Tým se nepodařilo založit bez částečného zápisu.',0,$e);}
}

/** @return array{id:int,created:bool,status:string} */
function kisRosterAddMember(PDO $pdo,int $teamId,int $sportovecId,int $actorId,string $source,string $validFrom,string $note):array
{
    if(!in_array($source,['manual','kis_shadow'],true))throw new InvalidArgumentException('Nepodporovaný zdroj soupisky.');$validFrom=kisRosterDate($validFrom);$note=kisRosterText($note,1000,'Důvod');
    if($teamId<1||$sportovecId<1||$actorId<1)throw new InvalidArgumentException('Přidání vyžaduje tým, sportovce a administrátora.');
    $pdo->beginTransaction();try{
        $teamSql='SELECT * FROM club_teams WHERE id=?';if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$teamSql.=' FOR UPDATE';$team=$pdo->prepare($teamSql);$team->execute([$teamId]);$team=$team->fetch(PDO::FETCH_ASSOC);if(!$team||$team['status']!=='active')throw new KisRosterException('Aktivní tým nebyl nalezen.');
        $person=$pdo->prepare('SELECT id,uciid,stav_clenstvi FROM sportovci WHERE id=?');$person->execute([$sportovecId]);$person=$person->fetch(PDO::FETCH_ASSOC);if(!$person)throw new KisRosterException('Sportovec nebyl nalezen.');if($person['stav_clenstvi']==='archiv')throw new KisRosterException('Archivovaného sportovce nelze přidat na aktivní soupisku.');
        $sql='SELECT * FROM club_roster_members WHERE team_id=? AND sportovec_id=?';if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$sql.=' FOR UPDATE';$existing=$pdo->prepare($sql);$existing->execute([$teamId,$sportovecId]);$member=$existing->fetch(PDO::FETCH_ASSOC);
        if($member&&$member['status']==='active'&&$member['valid_to']===null){$pdo->commit();return['id'=>(int)$member['id'],'created'=>false,'status'=>'active'];}
        $snapshot=trim((string)$person['uciid'])?:null;$before=$member?:null;
        if($member){$pdo->prepare("UPDATE club_roster_members SET status='active',source=?,kis_external_id_snapshot=?,valid_from=?,valid_to=NULL,created_by_trainer_id=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$source,$snapshot,$validFrom,$actorId,(int)$member['id']]);$id=(int)$member['id'];$created=false;$action='reactivate_member';}
        else{$pdo->prepare("INSERT INTO club_roster_members(team_id,sportovec_id,status,source,kis_external_id_snapshot,valid_from,created_by_trainer_id) VALUES (?,?,'active',?,?,?,?)")->execute([$teamId,$sportovecId,$source,$snapshot,$validFrom,$actorId]);$id=(int)$pdo->lastInsertId();$created=true;$action='add_member';}
        $after=['id'=>$id,'team_id'=>$teamId,'sportovec_id'=>$sportovecId,'status'=>'active','source'=>$source,'kis_external_id_snapshot'=>$snapshot,'valid_from'=>$validFrom,'valid_to'=>null];kisRosterEvent($pdo,$teamId,$id,$actorId,$action,$before,$after,$note);$pdo->commit();return['id'=>$id,'created'=>$created,'status'=>'active'];
    }catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();if($e instanceof InvalidArgumentException||$e instanceof KisRosterException)throw$e;throw new KisRosterException('Sportovce se nepodařilo přidat bez částečného zápisu.',0,$e);}
}

/** @return array{id:int,changed:bool,status:string} */
function kisRosterRemoveMember(PDO $pdo,int $memberId,int $actorId,string $validTo,string $note):array
{
    $validTo=kisRosterDate($validTo);$note=kisRosterText($note,1000,'Důvod');if($memberId<1||$actorId<1)throw new InvalidArgumentException('Odebrání vyžaduje člena soupisky a administrátora.');
    $pdo->beginTransaction();try{$sql='SELECT * FROM club_roster_members WHERE id=?';if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$sql.=' FOR UPDATE';$s=$pdo->prepare($sql);$s->execute([$memberId]);$member=$s->fetch(PDO::FETCH_ASSOC);if(!$member)throw new KisRosterException('Člen soupisky nebyl nalezen.');if($member['status']==='removed'){$pdo->commit();return['id'=>$memberId,'changed'=>false,'status'=>'removed'];}if($validTo<$member['valid_from'])throw new InvalidArgumentException('Konec členství nesmí být před jeho začátkem.');$after=$member;$after['status']='removed';$after['valid_to']=$validTo;$pdo->prepare("UPDATE club_roster_members SET status='removed',valid_to=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$validTo,$memberId]);kisRosterEvent($pdo,(int)$member['team_id'],$memberId,$actorId,'remove_member',$member,$after,$note);$pdo->commit();return['id'=>$memberId,'changed'=>true,'status'=>'removed'];}catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();if($e instanceof InvalidArgumentException||$e instanceof KisRosterException)throw$e;throw new KisRosterException('Sportovce se nepodařilo odebrat bez částečného zápisu.',0,$e);}
}

function kisRosterEvent(PDO $pdo,int $teamId,?int $memberId,int $actorId,string $action,?array $before,array $after,string $note):void{$pdo->prepare('INSERT INTO club_roster_events(team_id,roster_member_id,actor_trainer_id,action,before_json,after_json,note) VALUES (?,?,?,?,?,?,?)')->execute([$teamId,$memberId,$actorId,$action,$before===null?null:json_encode($before,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),json_encode($after,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$note]);}
function kisRosterSeasons(PDO $pdo):array{return$pdo->query("SELECT s.*,COUNT(DISTINCT t.id) team_count,COUNT(DISTINCT CASE WHEN m.status='active' THEN m.id END) member_count FROM club_seasons s LEFT JOIN club_teams t ON t.season_id=s.id LEFT JOIN club_roster_members m ON m.team_id=t.id GROUP BY s.id ORDER BY s.starts_on DESC,s.id DESC")->fetchAll(PDO::FETCH_ASSOC);}
function kisRosterTeams(PDO $pdo,int $seasonId=0):array{$sql="SELECT t.*,s.name season_name,COUNT(CASE WHEN m.status='active' THEN 1 END) member_count FROM club_teams t JOIN club_seasons s ON s.id=t.season_id LEFT JOIN club_roster_members m ON m.team_id=t.id";$params=[];if($seasonId>0){$sql.=' WHERE t.season_id=?';$params[]=$seasonId;}$sql.=' GROUP BY t.id,s.name ORDER BY s.starts_on DESC,t.name,t.id';$s=$pdo->prepare($sql);$s->execute($params);return$s->fetchAll(PDO::FETCH_ASSOC);}
function kisRosterTeamDetail(PDO $pdo,int $teamId):array{$s=$pdo->prepare('SELECT t.*,s.name season_name,s.starts_on,s.ends_on FROM club_teams t JOIN club_seasons s ON s.id=t.season_id WHERE t.id=?');$s->execute([$teamId]);$team=$s->fetch(PDO::FETCH_ASSOC);if(!$team)return['team'=>null,'members'=>[],'events'=>[]];$m=$pdo->prepare('SELECT m.*,sp.jmeno,sp.prijmeni,sp.narozeni,sp.uciid,sp.stav_clenstvi FROM club_roster_members m JOIN sportovci sp ON sp.id=m.sportovec_id WHERE m.team_id=? ORDER BY CASE m.status WHEN \'active\' THEN 0 ELSE 1 END,sp.prijmeni,sp.jmeno,m.id');$m->execute([$teamId]);$e=$pdo->prepare('SELECT e.*,tr.jmeno actor_name FROM club_roster_events e LEFT JOIN treneri tr ON tr.id=e.actor_trainer_id WHERE e.team_id=? ORDER BY e.id DESC LIMIT 100');$e->execute([$teamId]);return['team'=>$team,'members'=>$m->fetchAll(PDO::FETCH_ASSOC),'events'=>$e->fetchAll(PDO::FETCH_ASSOC)];}
