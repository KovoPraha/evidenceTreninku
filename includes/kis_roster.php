<?php
declare(strict_types=1);

final class KisRosterException extends RuntimeException {}

function kisRosterDate(string $value): string
{
    $date=DateTimeImmutable::createFromFormat('!Y-m-d',trim($value));
    if(!$date||$date->format('Y-m-d')!==trim($value))throw new InvalidArgumentException('Datum musi mit format RRRR-MM-DD.');
    return$date->format('Y-m-d');
}
function kisRosterCode(string$value,int$max=48):string{$value=strtoupper(trim($value));if(strlen($value)>$max||preg_match('/^[A-Z0-9][A-Z0-9_-]{2,}$/D',$value)!==1)throw new InvalidArgumentException('Kod musi mit alespon 3 znaky A-Z, 0-9, pomlcku nebo podtrzitko.');return$value;}
function kisRosterText(string$value,int$max,string$label):string{$value=trim($value);if($value===''||mb_strlen($value,'UTF-8')>$max||preg_match('/[<>]/u',$value)===1)throw new InvalidArgumentException($label.' musi byt neprazdny prosty text.');return$value;}
function kisRosterEnum(string$value,array$allowed,string$label):string{$value=trim($value);if(!in_array($value,$allowed,true))throw new InvalidArgumentException($label.' nema podporovanou hodnotu.');return$value;}
function kisRosterSeasonType(string$value):string{return kisRosterEnum($value,['school_year','calendar_year'],'Typ sezony');}
function kisRosterInferSeasonType(string$startsOn,string$endsOn):string{return substr($startsOn,0,4)===substr($endsOn,0,4)?'calendar_year':'school_year';}

/** @return array<string,mixed> */
function kisRosterCreateSeries(PDO$pdo,int$actorId,string$code,string$name,string$seriesType,string$seasonType,string$rolloverPolicy,?int$nextSeriesId=null,?int$ageFrom=null,?int$ageTo=null,?int$birthYearFrom=null,?int$birthYearTo=null):array
{
    $code=kisRosterCode($code);$name=kisRosterText($name,160,'Nazev serie');
    $seriesType=kisRosterEnum($seriesType,['hobby','age','discipline','special'],'Typ serie');
    $seasonType=kisRosterSeasonType($seasonType);
    $rolloverPolicy=kisRosterEnum($rolloverPolicy,['renewal_required','age_progression','carry_forward','manual'],'Politika obnovy');
    if($actorId<1)throw new InvalidArgumentException('Serie vyzaduje administratora.');
    $allowed=['hobby'=>['school_year'=>['renewal_required','manual']],'age'=>['calendar_year'=>['age_progression','manual']],'discipline'=>['calendar_year'=>['carry_forward','manual']],'special'=>['school_year'=>['manual'],'calendar_year'=>['manual']]];
    if(!isset($allowed[$seriesType][$seasonType])||!in_array($rolloverPolicy,$allowed[$seriesType][$seasonType],true))throw new InvalidArgumentException('Typ serie, kalendar a politika obnovy nejsou kompatibilni.');
    foreach([$ageFrom,$ageTo]as$value)if($value!==null&&($value<0||$value>120))throw new InvalidArgumentException('Vekove pravidlo musi byt mezi 0 a 120 lety.');
    foreach([$birthYearFrom,$birthYearTo]as$value)if($value!==null&&($value<1900||$value>2200))throw new InvalidArgumentException('Rocnikove pravidlo musi byt platny rok.');
    if(($ageFrom===null)!==($ageTo===null)||($ageFrom!==null&&$ageFrom>$ageTo)||($birthYearFrom===null)!==($birthYearTo===null)||($birthYearFrom!==null&&$birthYearFrom>$birthYearTo)||($ageFrom!==null&&$birthYearFrom!==null))throw new InvalidArgumentException('Pouzijte jednu uplnou a serazenou dvojici vekovych nebo rocnikovych mezi.');
    if($seriesType!=='age'&&($ageFrom!==null||$birthYearFrom!==null||$nextSeriesId!==null))throw new InvalidArgumentException('Vekova pravidla a nasledna serie patri pouze vekove rade.');
    if($nextSeriesId!==null){if($nextSeriesId<1)throw new InvalidArgumentException('Nasledna serie neni platna.');$next=$pdo->prepare('SELECT id,series_type,season_type FROM club_team_series WHERE id=?');$next->execute([$nextSeriesId]);$next=$next->fetch(PDO::FETCH_ASSOC);if(!$next||$next['series_type']!=='age'||$next['season_type']!=='calendar_year')throw new KisRosterException('Nasledna vekova serie nebyla nalezena.');}
    $existing=$pdo->prepare('SELECT * FROM club_team_series WHERE code=?');$existing->execute([$code]);$row=$existing->fetch(PDO::FETCH_ASSOC);
    $values=[$name,$seriesType,$seasonType,$rolloverPolicy,$nextSeriesId,$ageFrom,$ageTo,$birthYearFrom,$birthYearTo];
    if($row){$actual=[(string)$row['name'],(string)$row['series_type'],(string)$row['season_type'],(string)$row['rollover_policy'],$row['next_series_id']===null?null:(int)$row['next_series_id'],$row['age_from_years']===null?null:(int)$row['age_from_years'],$row['age_to_years']===null?null:(int)$row['age_to_years'],$row['birth_year_from']===null?null:(int)$row['birth_year_from'],$row['birth_year_to']===null?null:(int)$row['birth_year_to']];if($actual!==$values)throw new KisRosterException('Kod serie uz oznacuje jinou stabilni skupinu.');return$row;}
    $pdo->prepare("INSERT INTO club_team_series(code,name,series_type,season_type,rollover_policy,next_series_id,age_from_years,age_to_years,birth_year_from,birth_year_to,status,created_by_trainer_id) VALUES (?,?,?,?,?,?,?,?,?,?,'active',?)")->execute([$code,...$values,$actorId]);
    return['id'=>(int)$pdo->lastInsertId(),'code'=>$code,'name'=>$name,'series_type'=>$seriesType,'season_type'=>$seasonType,'rollover_policy'=>$rolloverPolicy,'next_series_id'=>$nextSeriesId,'age_from_years'=>$ageFrom,'age_to_years'=>$ageTo,'birth_year_from'=>$birthYearFrom,'birth_year_to'=>$birthYearTo,'status'=>'active'];
}

/** @return array<string,mixed> */
function kisRosterCreateSeason(PDO$pdo,int$actorId,string$code,string$name,string$startsOn,string$endsOn,?string$seasonType=null):array
{
    $code=kisRosterCode($code,32);$name=kisRosterText($name,120,'Nazev sezony');$startsOn=kisRosterDate($startsOn);$endsOn=kisRosterDate($endsOn);$seasonType=kisRosterSeasonType($seasonType??kisRosterInferSeasonType($startsOn,$endsOn));
    if($actorId<1||$startsOn>=$endsOn)throw new InvalidArgumentException('Sezona vyzaduje administratora a konec po zacatku.');
    $startYear=(int)substr($startsOn,0,4);$endYear=(int)substr($endsOn,0,4);
    if($seasonType==='calendar_year'&&$startYear!==$endYear)throw new InvalidArgumentException('Kalendarni sezona musi zacinat i koncit ve stejnem roce.');
    if($seasonType==='school_year'&&$endYear!==$startYear+1)throw new InvalidArgumentException('Skolni sezona musi koncit v nasledujicim roce.');
    $existing=$pdo->prepare('SELECT * FROM club_seasons WHERE code=?');$existing->execute([$code]);$row=$existing->fetch(PDO::FETCH_ASSOC);
    if($row){if($row['name']!==$name||$row['starts_on']!==$startsOn||$row['ends_on']!==$endsOn||($row['season_type']??kisRosterInferSeasonType($startsOn,$endsOn))!==$seasonType)throw new KisRosterException('Kod sezony uz oznacuje jine nemenne obdobi.');return$row;}
    $pdo->prepare("INSERT INTO club_seasons(code,name,season_type,starts_on,ends_on,status,created_by_trainer_id) VALUES (?,?,?,?,?,'active',?)")->execute([$code,$name,$seasonType,$startsOn,$endsOn,$actorId]);
    return['id'=>(int)$pdo->lastInsertId(),'code'=>$code,'name'=>$name,'season_type'=>$seasonType,'starts_on'=>$startsOn,'ends_on'=>$endsOn,'status'=>'active'];
}

/** @return array<string,mixed> */
function kisRosterCreateTeam(PDO$pdo,int$seasonId,int$actorId,string$code,string$name,string$discipline,string$ageLabel,string$note,?int$seriesId=null):array
{
    $code=kisRosterCode($code);$name=kisRosterText($name,160,'Nazev tymu');$discipline=kisRosterText($discipline,120,'Disciplina');$ageLabel=kisRosterText($ageLabel,120,'Vekova kategorie');$note=kisRosterText($note,1000,'Duvod');
    if($seasonId<1||$actorId<1)throw new InvalidArgumentException('Tym vyzaduje sezonu a administratora.');
    $pdo->beginTransaction();
    try{
        $s=$pdo->prepare('SELECT * FROM club_seasons WHERE id=?');$s->execute([$seasonId]);$season=$s->fetch(PDO::FETCH_ASSOC);if(!$season)throw new KisRosterException('Sezona nebyla nalezena.');
        if($seriesId!==null){$s=$pdo->prepare("SELECT * FROM club_team_series WHERE id=? AND status='active'");$s->execute([$seriesId]);$series=$s->fetch(PDO::FETCH_ASSOC);if(!$series)throw new KisRosterException('Aktivni serie nebyla nalezena.');if($series['season_type']!==$season['season_type'])throw new InvalidArgumentException('Serie a sezona pouzivaji jiny kalendar.');}
        $s=$pdo->prepare('SELECT * FROM club_teams WHERE season_id=? AND code=?');$s->execute([$seasonId,$code]);$team=$s->fetch(PDO::FETCH_ASSOC);
        if($team){if($team['name']!==$name||$team['discipline']!==$discipline||$team['age_label']!==$ageLabel||($team['series_id']===null?null:(int)$team['series_id'])!==$seriesId)throw new KisRosterException('Kod tymu uz oznacuje jiny tym.');$pdo->commit();return$team;}
        $pdo->prepare("INSERT INTO club_teams(season_id,series_id,code,name,discipline,age_label,status,created_by_trainer_id) VALUES (?,?,?,?,?,?,'active',?)")->execute([$seasonId,$seriesId,$code,$name,$discipline,$ageLabel,$actorId]);$id=(int)$pdo->lastInsertId();
        $after=['id'=>$id,'season_id'=>$seasonId,'series_id'=>$seriesId,'code'=>$code,'name'=>$name,'discipline'=>$discipline,'age_label'=>$ageLabel,'status'=>'active'];kisRosterEvent($pdo,$id,null,$actorId,'create_team',null,$after,$note);$pdo->commit();return$after;
    }catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();if($e instanceof InvalidArgumentException||$e instanceof KisRosterException)throw$e;throw new KisRosterException('Tym se nepodarilo zalozit bez castecneho zapisu.',0,$e);}
}
function kisRosterCreateSeriesTeam(PDO$pdo,int$seriesId,int$seasonId,int$actorId,string$code,string$name,string$discipline,string$ageLabel,string$note):array{return kisRosterCreateTeam($pdo,$seasonId,$actorId,$code,$name,$discipline,$ageLabel,$note,$seriesId);}

/** @return array{id:int,created:bool,status:string} */
function kisRosterAddMember(PDO$pdo,int$teamId,int$sportovecId,int$actorId,string$source,string$validFrom,string$note):array
{
    if(!in_array($source,['manual','kis_shadow'],true))throw new InvalidArgumentException('Nepodporovany zdroj soupisky.');$validFrom=kisRosterDate($validFrom);$note=kisRosterText($note,1000,'Duvod');if($teamId<1||$sportovecId<1||$actorId<1)throw new InvalidArgumentException('Pridani vyzaduje tym, sportovce a administratora.');
    $pdo->beginTransaction();try{
        $sql='SELECT * FROM club_teams WHERE id=?';if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$sql.=' FOR UPDATE';$s=$pdo->prepare($sql);$s->execute([$teamId]);$team=$s->fetch(PDO::FETCH_ASSOC);if(!$team||$team['status']!=='active')throw new KisRosterException('Aktivni tym nebyl nalezen.');
        $s=$pdo->prepare('SELECT id,uciid,stav_clenstvi FROM sportovci WHERE id=?');$s->execute([$sportovecId]);$person=$s->fetch(PDO::FETCH_ASSOC);if(!$person)throw new KisRosterException('Sportovec nebyl nalezen.');if($person['stav_clenstvi']==='archiv')throw new KisRosterException('Archivovaného sportovce nelze přidat na aktivní soupisku.');
        $sql='SELECT * FROM club_roster_members WHERE team_id=? AND sportovec_id=?';if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$sql.=' FOR UPDATE';$s=$pdo->prepare($sql);$s->execute([$teamId,$sportovecId]);$member=$s->fetch(PDO::FETCH_ASSOC);if($member&&$member['status']==='active'&&$member['valid_to']===null){$pdo->commit();return['id'=>(int)$member['id'],'created'=>false,'status'=>'active'];}
        $snapshot=trim((string)$person['uciid'])?:null;$before=$member?:null;
        if($member){$pdo->prepare("UPDATE club_roster_members SET status='active',source=?,kis_external_id_snapshot=?,valid_from=?,valid_to=NULL,created_by_trainer_id=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$source,$snapshot,$validFrom,$actorId,(int)$member['id']]);$id=(int)$member['id'];$created=false;$action='reactivate_member';}
        else{$pdo->prepare("INSERT INTO club_roster_members(team_id,sportovec_id,status,source,kis_external_id_snapshot,valid_from,created_by_trainer_id) VALUES (?,?,'active',?,?,?,?)")->execute([$teamId,$sportovecId,$source,$snapshot,$validFrom,$actorId]);$id=(int)$pdo->lastInsertId();$created=true;$action='add_member';}
        $after=['id'=>$id,'team_id'=>$teamId,'sportovec_id'=>$sportovecId,'status'=>'active','source'=>$source,'kis_external_id_snapshot'=>$snapshot,'valid_from'=>$validFrom,'valid_to'=>null];kisRosterEvent($pdo,$teamId,$id,$actorId,$action,$before,$after,$note);$pdo->commit();return['id'=>$id,'created'=>$created,'status'=>'active'];
    }catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();if($e instanceof InvalidArgumentException||$e instanceof KisRosterException)throw$e;throw new KisRosterException('Sportovce se nepodarilo pridat bez castecneho zapisu.',0,$e);}
}

/** @return array{id:int,changed:bool,status:string} */
function kisRosterRemoveMember(PDO$pdo,int$memberId,int$actorId,string$validTo,string$note):array
{
    $validTo=kisRosterDate($validTo);$note=kisRosterText($note,1000,'Duvod');if($memberId<1||$actorId<1)throw new InvalidArgumentException('Odebrani vyzaduje clena soupisky a administratora.');
    $pdo->beginTransaction();try{$sql='SELECT * FROM club_roster_members WHERE id=?';if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$sql.=' FOR UPDATE';$s=$pdo->prepare($sql);$s->execute([$memberId]);$member=$s->fetch(PDO::FETCH_ASSOC);if(!$member)throw new KisRosterException('Clen soupisky nebyl nalezen.');if($member['status']==='removed'){$pdo->commit();return['id'=>$memberId,'changed'=>false,'status'=>'removed'];}if($validTo<$member['valid_from'])throw new InvalidArgumentException('Konec clenstvi nesmi byt pred jeho zacatkem.');$after=$member;$after['status']='removed';$after['valid_to']=$validTo;$pdo->prepare("UPDATE club_roster_members SET status='removed',valid_to=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$validTo,$memberId]);kisRosterEvent($pdo,(int)$member['team_id'],$memberId,$actorId,'remove_member',$member,$after,$note);$pdo->commit();return['id'=>$memberId,'changed'=>true,'status'=>'removed'];}catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();if($e instanceof InvalidArgumentException||$e instanceof KisRosterException)throw$e;throw new KisRosterException('Sportovce se nepodarilo odebrat bez castecneho zapisu.',0,$e);}
}

/** @return array<string,mixed> */
function kisRosterPreviewRollover(PDO$pdo,int$teamId,int$targetSeasonId):array
{
    if($teamId<1||$targetSeasonId<1)throw new InvalidArgumentException('Nahled vyzaduje zdrojovou soupisku a cilovou sezonu.');
    $s=$pdo->prepare('SELECT t.*,cs.season_type,ser.name series_name,ser.series_type,ser.rollover_policy,ser.next_series_id FROM club_teams t JOIN club_seasons cs ON cs.id=t.season_id LEFT JOIN club_team_series ser ON ser.id=t.series_id WHERE t.id=?');$s->execute([$teamId]);$team=$s->fetch(PDO::FETCH_ASSOC);if(!$team)throw new KisRosterException('Zdrojova soupiska nebyla nalezena.');
    $s=$pdo->prepare('SELECT * FROM club_seasons WHERE id=?');$s->execute([$targetSeasonId]);$season=$s->fetch(PDO::FETCH_ASSOC);if(!$season)throw new KisRosterException('Cilova sezona nebyla nalezena.');
    $policy=$team['series_id']===null?'manual':(string)$team['rollover_policy'];if($team['series_id']!==null&&$team['season_type']!==$season['season_type'])throw new InvalidArgumentException('Cilova sezona pouziva jiny kalendar nez serie.');
    $targetSeriesId=$policy==='age_progression'?($team['next_series_id']===null?null:(int)$team['next_series_id']):($team['series_id']===null?null:(int)$team['series_id']);$targetTeam=null;
    if($targetSeriesId!==null){$s=$pdo->prepare('SELECT id,code,name FROM club_teams WHERE season_id=? AND series_id=?');$s->execute([$targetSeasonId,$targetSeriesId]);$targetTeam=$s->fetch(PDO::FETCH_ASSOC)?:null;}
    $s=$pdo->prepare("SELECT m.id member_id,m.sportovec_id,sp.jmeno,sp.prijmeni,sp.narozeni FROM club_roster_members m JOIN sportovci sp ON sp.id=m.sportovec_id WHERE m.team_id=? AND m.status='active' AND m.valid_to IS NULL ORDER BY sp.prijmeni,sp.jmeno,m.id");$s->execute([$teamId]);$members=$s->fetchAll(PDO::FETCH_ASSOC);$proposals=[];
    foreach($members as$member){$action=$policy;if($policy==='renewal_required')$action='await_renewal';elseif($policy==='manual')$action='manual_review';elseif($targetSeriesId===null)$action='configuration_required';elseif($targetTeam===null)$action='target_team_required';$proposals[]=['sportovec_id'=>(int)$member['sportovec_id'],'name'=>trim($member['prijmeni'].' '.$member['jmeno']),'action'=>$action,'target_series_id'=>$targetSeriesId,'target_team_id'=>$targetTeam===null?null:(int)$targetTeam['id']];}
    return['source_team_id'=>$teamId,'target_season_id'=>$targetSeasonId,'policy'=>$policy,'target_series_id'=>$targetSeriesId,'target_team'=>$targetTeam,'proposals'=>$proposals,'mutation_count'=>0];
}

function kisRosterEvent(PDO$pdo,int$teamId,?int$memberId,int$actorId,string$action,?array$before,array$after,string$note):void{$pdo->prepare('INSERT INTO club_roster_events(team_id,roster_member_id,actor_trainer_id,action,before_json,after_json,note) VALUES (?,?,?,?,?,?,?)')->execute([$teamId,$memberId,$actorId,$action,$before===null?null:json_encode($before,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),json_encode($after,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$note]);}
function kisRosterSeries(PDO$pdo):array{return$pdo->query('SELECT s.*,n.name next_series_name,COUNT(t.id) team_count FROM club_team_series s LEFT JOIN club_team_series n ON n.id=s.next_series_id LEFT JOIN club_teams t ON t.series_id=s.id GROUP BY s.id,n.name ORDER BY s.name,s.id')->fetchAll(PDO::FETCH_ASSOC);}
function kisRosterSeasons(PDO$pdo):array{return$pdo->query("SELECT s.*,COUNT(DISTINCT t.id) team_count,COUNT(DISTINCT CASE WHEN m.status='active' THEN m.id END) member_count FROM club_seasons s LEFT JOIN club_teams t ON t.season_id=s.id LEFT JOIN club_roster_members m ON m.team_id=t.id GROUP BY s.id ORDER BY s.starts_on DESC,s.id DESC")->fetchAll(PDO::FETCH_ASSOC);}
function kisRosterTeams(PDO$pdo,int$seasonId=0):array{$sql="SELECT t.*,s.name season_name,s.season_type,ser.name series_name,ser.rollover_policy,COUNT(CASE WHEN m.status='active' THEN 1 END) member_count FROM club_teams t JOIN club_seasons s ON s.id=t.season_id LEFT JOIN club_team_series ser ON ser.id=t.series_id LEFT JOIN club_roster_members m ON m.team_id=t.id";$params=[];if($seasonId>0){$sql.=' WHERE t.season_id=?';$params[]=$seasonId;}$sql.=' GROUP BY t.id,s.name,s.season_type,ser.name,ser.rollover_policy ORDER BY s.starts_on DESC,t.name,t.id';$s=$pdo->prepare($sql);$s->execute($params);return$s->fetchAll(PDO::FETCH_ASSOC);}
function kisRosterTeamDetail(PDO$pdo,int$teamId):array{$s=$pdo->prepare('SELECT t.*,s.name season_name,s.season_type,s.starts_on,s.ends_on,ser.name series_name,ser.rollover_policy FROM club_teams t JOIN club_seasons s ON s.id=t.season_id LEFT JOIN club_team_series ser ON ser.id=t.series_id WHERE t.id=?');$s->execute([$teamId]);$team=$s->fetch(PDO::FETCH_ASSOC);if(!$team)return['team'=>null,'members'=>[],'events'=>[]];$m=$pdo->prepare("SELECT m.*,sp.jmeno,sp.prijmeni,sp.narozeni,sp.uciid,sp.stav_clenstvi FROM club_roster_members m JOIN sportovci sp ON sp.id=m.sportovec_id WHERE m.team_id=? ORDER BY CASE m.status WHEN 'active' THEN 0 ELSE 1 END,sp.prijmeni,sp.jmeno,m.id");$m->execute([$teamId]);$e=$pdo->prepare('SELECT e.*,tr.jmeno actor_name FROM club_roster_events e LEFT JOIN treneri tr ON tr.id=e.actor_trainer_id WHERE e.team_id=? ORDER BY e.id DESC LIMIT 100');$e->execute([$teamId]);return['team'=>$team,'members'=>$m->fetchAll(PDO::FETCH_ASSOC),'events'=>$e->fetchAll(PDO::FETCH_ASSOC)];}
