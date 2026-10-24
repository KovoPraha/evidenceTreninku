<?php
declare(strict_types=1);
require_once __DIR__.'/roster_hierarchy.php';
require_once __DIR__.'/operational_cases.php';
require_once __DIR__.'/staff_record_list.php';

function seasonPreparationActor(PDO $pdo):int {
    $actor=(int)($_SESSION['trener_id']??0);$position=staffActivePosition();
    if(!in_array($position,['registrar','sports_lead'],true))throw new DomainException('Příprava období patří registráři a vedoucímu sportu.');
    operationalCaseActor($pdo,$actor,$position);return $actor;
}
function seasonPreparationRead(PDO $pdo,int $teamId,array $input=[]):array {
    seasonPreparationActor($pdo);$on=kisRosterDate(staffListText($input,'on')?:date('Y-m-d'));$today=date('Y-m-d');
    $q=$pdo->prepare('SELECT t.*,s.name AS season_name FROM club_teams t LEFT JOIN club_seasons s ON s.id=t.season_id WHERE t.id=?');$q->execute([$teamId]);$team=$q->fetch(PDO::FETCH_ASSOC);
    if(!$team)throw new InvalidArgumentException('Skupina nebo soupiska nebyla nalezena.');
    $targetSeason=(int)staffListText($input,'target_season_id');$preview=null;
    if($targetSeason){if(!$team['season_id'])throw new InvalidArgumentException('Trvalá skupina nemá roční obnovu. Zvolte den kontroly intervalů.');$preview=kisRosterPreviewRollover($pdo,$teamId,$targetSeason);$on=$preview['target_starts_on'];}
    $current=kisRosterEffectiveMemberIds($pdo,$teamId,$today);$future=kisRosterEffectiveMemberIds($pdo,$teamId,$on);$ids=array_values(array_unique(array_merge($current,$future)));$rows=[];
    if($ids){$q=$pdo->prepare('SELECT id,jmeno,prijmeni FROM sportovci WHERE id IN ('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY prijmeni,jmeno,id');$q->execute($ids);$rows=$q->fetchAll(PDO::FETCH_ASSOC);}
    $counts=['continuing'=>0,'leaving'=>0,'joining'=>0];foreach($rows as &$row){$row['change']=in_array((int)$row['id'],$current,true)?(in_array((int)$row['id'],$future,true)?'continuing':'leaving'):'joining';$counts[$row['change']]++;}unset($row);
    $search=mb_substr(staffListText($input,'q'),0,100);$state=staffListText($input,'state');if($state!==''&&!isset($counts[$state]))throw new InvalidArgumentException('Vyberte stav zařazení.');
    $rows=array_values(array_filter($rows,static function($r)use($search,$state){if($state!==''&&$r['change']!==$state)return false;foreach(preg_split('/\s+/u',$search,-1,PREG_SPLIT_NO_EMPTY) as $word)if(mb_stripos($r['prijmeni'].' '.$r['jmeno'],$word)===false)return false;return true;}));
    $total=count($rows);$pages=max(1,(int)ceil($total/25));$page=max(1,min($pages,(int)staffListText($input,'page')));$rows=array_slice($rows,($page-1)*25,25);
    $teams=kisRosterDescendantIds($pdo,$teamId,$on);$marks=implode(',',array_fill(0,count($teams),'?'));
    $q=$pdo->prepare("SELECT g.id,g.valid_from,g.valid_to,t.jmeno,c.name FROM coach_scope_grants g JOIN treneri t ON t.id=g.trainer_id AND t.aktivni=1 JOIN staff_user_positions p ON p.trainer_id=t.id AND p.position_code='coach' JOIN club_teams c ON c.id=g.scope_id WHERE g.scope_kind='team' AND g.scope_id IN ($marks) AND g.revoked_at IS NULL ORDER BY c.name,t.jmeno,g.id");$q->execute($teams);$grants=$q->fetchAll(PDO::FETCH_ASSOC);$at=$on.' 00:00:00';
    foreach($grants as &$g)$g['active_on']=$g['valid_from']<=$at&&($g['valid_to']===null||$g['valid_to']>$at);unset($g);
    $until=(new DateTimeImmutable($on))->modify('+90 days')->format('Y-m-d');
    // A child may join or leave the hierarchy during this period. Resolve each lesson on its own date.
    $q=$pdo->prepare("SELECT p.id,p.nazev,p.datum,p.cas_od,p.cas_do,t.jmeno,l.team_id FROM planovane_treninky p JOIN training_roster_links l ON l.plan_id=p.id LEFT JOIN treneri t ON t.id=p.trener_id WHERE p.stav<>'zruseny' AND p.datum>=? AND p.datum<=? ORDER BY p.datum,p.cas_od,p.id");$q->execute([$on,$until]);$plans=[];$byDate=[];
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $plan){$date=$plan['datum'];$byDate[$date]??=kisRosterDescendantIds($pdo,$teamId,$date);if(in_array((int)$plan['team_id'],$byDate[$date],true)){$planId=(int)$plan['id'];unset($plan['team_id']);$plans[$planId]=$plan;}}$plans=array_values($plans);
    return compact('team','on','today','rows','counts','total','pages','page','search','state','grants','plans','until','preview','targetSeason');
}
