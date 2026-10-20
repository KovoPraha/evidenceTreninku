<?php
declare(strict_types=1);
require_once __DIR__.'/kis_person_import.php';
require_once __DIR__.'/kis_roster.php';

const KIS_LEGACY_ROSTER_SEASON_CODE = 'LEGACY-KIS-2025-26';
const KIS_LEGACY_ROSTER_START = '2025-09-01';
const KIS_LEGACY_ROSTER_END = '2026-08-31';

/** The old export has no membership dates: these bounds identify the labelled season only. */
function kisLegacyRosterTokens(string $raw): array
{
    if (trim($raw)==='') return [];
    $tokens=preg_split('/(?<=\(2025\/2026\)),\s*/u',$raw);
    if (!is_array($tokens) || count($tokens)>100) throw new RuntimeException('Neplatné dělení soupisek v archivu.');
    $result=[];
    foreach($tokens as $token) {
        $token=trim($token);
        if (!str_ends_with($token,' (2025/2026)') || mb_strlen($token)>160 || str_contains($token,'<') || str_contains($token,'>'))
            throw new RuntimeException('Soupiska nemá jednoznačné označení sezony 2025/26.');
        if (isset($result[$token])) throw new RuntimeException('Duplicitní soupiska na jednom zdrojovém řádku.');
        $result[$token]=$token;
    }
    return array_values($result);
}

function kisLegacyRosterReason(array $claim, array $allSourceLabels, ?string $birth): string
{
    if (!$claim['player']) return 'pouze_trener';
    foreach($allSourceLabels as $labels) if ($labels && !in_array($claim['label'],$labels,true)) return 'rozpor_sloucenych_aliasu';
    $base=substr($claim['label'],0,-strlen(' (2025/2026)'));
    if (preg_match('/\((20\d{2})\/{1,2}(20\d{2})\)|\((20\d{2})-(20\d{2})\)/u',$base,$m)) {
        $lo=(int)(($m[1]??'')!==''?$m[1]:($m[3]??0));$hi=(int)(($m[2]??'')!==''?$m[2]:($m[4]??0));
        if ($birth===null || $birth==='') return 'nezname_narozeni';
        $year=(int)substr($birth,0,4);
        if ($year<$lo || $year>$hi) return 'rocnik_mimo_rozsah';
    }
    return '';
}

/** Pure read of the authenticated archive and imported alias map. */
function kisLegacyRosterPreview(PDO $pdo,?array $configOverride=null): array
{
    $batch=$pdo->query('SELECT fingerprint,payload_ciphertext,key_version FROM kis_person_batches ORDER BY created_at DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    if (!$batch) throw new RuntimeException('Nejprve je nutné dokončit import osob ze starého KIS.');
    $archive=json_decode(kisPersonDecryptArchive($batch,$configOverride),true,64,JSON_THROW_ON_ERROR);
    if (($archive['source_namespace']??'')!=='servis.kovopraha.cz/2026-10-20'
        || !is_array($archive['source_archive']['people']??null)) throw new RuntimeException('Archiv KIS nemá očekávaný původ.');
    $aliases=[];$people=[];
    foreach($pdo->query('SELECT a.source_key,a.sportovec_id,p.narozeni,p.jmeno,p.prijmeni FROM kis_person_aliases a JOIN sportovci p ON p.id=a.sportovec_id') as $row) {
        $aliases[$row['source_key']]=(int)$row['sportovec_id'];
        $people[(int)$row['sportovec_id']]=['id'=>(int)$row['sportovec_id'],'name'=>trim($row['jmeno'].' '.$row['prijmeni']),'birth'=>$row['narozeni']];
    }
    if(count($aliases)!==count($archive['source_archive']['people']))throw new RuntimeException('Počet aliasů neodpovídá původnímu archivu.');
    $claims=[];$labels=[];$allByPerson=[];
    foreach($archive['source_archive']['people'] as $row) {
        $source=(string)($row['id_hash']??'');
        if (!isset($aliases[$source])) throw new RuntimeException('Chybí vazba zdrojové osoby.');
        $personId=$aliases[$source];$tokens=kisLegacyRosterTokens((string)($row['Soupisky']??''));
        if(!$tokens)continue;
        $allByPerson[$personId][$source]=$tokens;
        foreach($tokens as $label) {
            $labels[$label]=true;$key=$personId.'|'.$label;
            if(!isset($claims[$key]))$claims[$key]=['sportovec_id'=>$personId,'label'=>$label,'source_keys'=>[],'player'=>false];
            $claims[$key]['source_keys'][]=$source;
            $claims[$key]['player']=$claims[$key]['player'] || (($row['je_hrac_dle_KIS']??'')==='ANO');
        }
    }
    if (!$claims) throw new RuntimeException('Archiv neobsahuje žádné soupisky.');
    ksort($labels,SORT_STRING);ksort($claims,SORT_STRING);
    $counts=['matched'=>0,'review'=>0];
    foreach($claims as &$claim) {
        sort($claim['source_keys'],SORT_STRING);
        $claim['name']=$people[$claim['sportovec_id']]['name'];
        $claim['birth']=$people[$claim['sportovec_id']]['birth'];
        $claim['reason']=kisLegacyRosterReason($claim,$allByPerson[$claim['sportovec_id']],$claim['birth']);
        $counts[$claim['reason']===''?'matched':'review']++;
    }
    unset($claim);
    $names=array_keys($labels);
    $planFingerprint=hash('sha256',kisPersonJson(['batch'=>$batch['fingerprint'],'names'=>$names,'claims'=>array_values($claims)]));
    return ['batch_fingerprint'=>$batch['fingerprint'],'fingerprint'=>$planFingerprint,'teams'=>$names,'claims'=>array_values($claims),'counts'=>$counts,
        'source_rows'=>count($archive['source_archive']['people']),'source_with_rosters'=>count(array_unique(array_merge(...array_map(static fn($c)=>$c['source_keys'],array_values($claims))))),
        'people_with_rosters'=>count($allByPerson)];
}

function kisLegacyRosterInsertMember(PDO $pdo,int $teamId,int $personId,int $actorId,string $note): int
{
    $existing=$pdo->prepare('SELECT id FROM club_roster_members WHERE team_id=? AND sportovec_id=?');
    $existing->execute([$teamId,$personId]);
    if($existing->fetchColumn())throw new RuntimeException('Členství již existuje mimo tento import.');
    $pdo->prepare("INSERT INTO club_roster_members(team_id,sportovec_id,status,source,kis_external_id_snapshot,valid_from,valid_to,created_by_trainer_id) VALUES (?,?,'removed','kis_shadow',NULL,?,?,?)")
        ->execute([$teamId,$personId,KIS_LEGACY_ROSTER_START,KIS_LEGACY_ROSTER_END,$actorId]);
    $id=(int)$pdo->lastInsertId();
    kisRosterEvent($pdo,$teamId,$id,$actorId,'import_legacy_member',null,
        ['id'=>$id,'team_id'=>$teamId,'sportovec_id'=>$personId,'source'=>'kis_shadow','status'=>'removed','season_only_dates'=>true],$note);
    return $id;
}

/** Atomic, idempotent import of a labelled historical snapshot. */
function kisLegacyRosterApply(PDO $pdo,int $actorId,string $expectedFingerprint,?array $configOverride=null): array
{
    if($actorId<1)throw new InvalidArgumentException('Chybí administrátor.');
    $plan=kisLegacyRosterPreview($pdo,$configOverride);
    if(!hash_equals($plan['fingerprint'],$expectedFingerprint))throw new RuntimeException('Náhled se změnil. Načtěte jej znovu.');
    $mysql=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
    if($mysql){$s=$pdo->query("SELECT GET_LOCK('kis_legacy_roster_2025_26',15)");if((int)$s->fetchColumn()!==1)throw new RuntimeException('Import soupisek právě probíhá.');}
    try {
        $pdo->beginTransaction();
        $done=$pdo->prepare('SELECT summary_json,plan_fingerprint FROM kis_legacy_roster_runs WHERE batch_fingerprint=?'.($mysql?' FOR UPDATE':''));
        $done->execute([$plan['batch_fingerprint']]);$row=$done->fetch(PDO::FETCH_ASSOC);
        if($row){if(!hash_equals($row['plan_fingerprint'],$expectedFingerprint))throw new RuntimeException('Tento archiv již byl zpracován podle jiného plánu.');$pdo->commit();return json_decode($row['summary_json'],true,32,JSON_THROW_ON_ERROR)+['idempotent'=>true];}
        $season=$pdo->prepare('SELECT id,name,starts_on,ends_on FROM club_seasons WHERE code=?'.($mysql?' FOR UPDATE':''));
        $season->execute([KIS_LEGACY_ROSTER_SEASON_CODE]);$seasonRow=$season->fetch(PDO::FETCH_ASSOC);
        if($seasonRow)throw new RuntimeException('Historická sezona již existuje bez protokolu importu.');
        $pdo->prepare("INSERT INTO club_seasons(code,name,season_type,starts_on,ends_on,status,created_by_trainer_id) VALUES (?,'Starý KIS 2025/26','school_year',?,?,'closed',?)")
            ->execute([KIS_LEGACY_ROSTER_SEASON_CODE,KIS_LEGACY_ROSTER_START,KIS_LEGACY_ROSTER_END,$actorId]);
        $seasonId=(int)$pdo->lastInsertId();$teamIds=[];
        $insertTeam=$pdo->prepare("INSERT INTO club_teams(season_id,series_id,code,name,discipline,age_label,status,created_by_trainer_id) VALUES (?,NULL,?,?,'Historický KIS','Dle zdrojové soupisky','closed',?)");
        foreach($plan['teams'] as $label) {
            $code='LEG25-'.strtoupper(substr(hash('sha256',$label),0,16));
            $insertTeam->execute([$seasonId,$code,$label,$actorId]);$teamIds[$label]=(int)$pdo->lastInsertId();
            kisRosterEvent($pdo,$teamIds[$label],null,$actorId,'import_legacy_team',null,['label'=>$label,'season_id'=>$seasonId,'status'=>'closed'],'Doslovná soupiska z exportu starého KIS 2025/26.');
        }
        $summary=['teams'=>count($teamIds),'matched'=>$plan['counts']['matched'],'review'=>$plan['counts']['review'],
            'claims'=>count($plan['claims']),'people_with_rosters'=>$plan['people_with_rosters'],'season_id'=>$seasonId];
        $pdo->prepare('INSERT INTO kis_legacy_roster_runs(batch_fingerprint,plan_fingerprint,season_id,summary_json,actor_trainer_id) VALUES (?,?,?,?,?)')
            ->execute([$plan['batch_fingerprint'],$plan['fingerprint'],$seasonId,kisPersonJson($summary),$actorId]);
        $insertClaim=$pdo->prepare('INSERT INTO kis_legacy_roster_claims(batch_fingerprint,sportovec_id,team_id,legacy_label,source_keys_json,reason_code,status,member_id) VALUES (?,?,?,?,?,?,?,?)');
        foreach($plan['claims'] as $claim) {
            $teamId=$teamIds[$claim['label']];$review=$claim['reason']!=='';
            $memberId=$review?null:kisLegacyRosterInsertMember($pdo,$teamId,$claim['sportovec_id'],$actorId,'Doslovný export starého KIS 2025/26; data platnosti jsou pouze hranice sezony, nikoli datum přihlášení.');
            $insertClaim->execute([$plan['batch_fingerprint'],$claim['sportovec_id'],$teamId,$claim['label'],kisPersonJson($claim['source_keys']),$claim['reason'],$review?'review':'matched',$memberId]);
        }
        $pdo->commit();return $summary+['idempotent'=>false];
    } catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    finally {if($mysql)$pdo->query("SELECT RELEASE_LOCK('kis_legacy_roster_2025_26')");}
}

function kisLegacyRosterDecide(PDO $pdo,int $claimId,int $actorId,string $decision,string $note): void
{
    if($claimId<1||$actorId<1||!in_array($decision,['approve','skip'],true)||trim($note)===''||mb_strlen($note)>1000)
        throw new InvalidArgumentException('Vyberte rozhodnutí a zapište důvod.');
    $mysql=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';$pdo->beginTransaction();
    try {
        $s=$pdo->prepare('SELECT * FROM kis_legacy_roster_claims WHERE id=?'.($mysql?' FOR UPDATE':''));$s->execute([$claimId]);$claim=$s->fetch(PDO::FETCH_ASSOC);
        if(!$claim||$claim['status']!=='review')throw new RuntimeException('Případ již byl rozhodnut nebo neexistuje.');
        $memberId=$decision==='approve'?kisLegacyRosterInsertMember($pdo,(int)$claim['team_id'],(int)$claim['sportovec_id'],$actorId,
            'Ruční potvrzení historické soupisky: '.trim($note)):null;
        $pdo->prepare('UPDATE kis_legacy_roster_claims SET status=?,member_id=?,decision_note=?,decided_by_trainer_id=?,decided_at=CURRENT_TIMESTAMP WHERE id=?')
            ->execute([$decision==='approve'?'approved':'skipped',$memberId,trim($note),$actorId,$claimId]);
        $pdo->commit();
    } catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
