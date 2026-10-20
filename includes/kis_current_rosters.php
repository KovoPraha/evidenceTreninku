<?php
declare(strict_types=1);
require_once __DIR__.'/kis_legacy_rosters.php';

const KIS_CURRENT_ROSTER_SEASON_CODE = 'SCHOOL-2026-27';

/** Only literal, unique group continuations. Split groups and general club labels stay in review. */
function kisCurrentRosterTargetCode(string $label): ?string
{
    if (!str_ends_with($label, ' (2025/2026)')) return null;
    $names = [
        'Aligátoři'=>'KR-2627-ALIG-TO-I', 'Kamzíci'=>'KR-2627-KAMZ-CI',
        'Papuchálci'=>'KR-2627-PAPUCH-LCI', 'Svišti'=>'KR-2627-SVI-TI',
        'Jezevci'=>'KR-2627-JEZEVCI', 'Sumci'=>'KR-2627-SUMCI',
        'Zubříci'=>'KR-2627-ZUB-CI', 'Kapybary'=>'KR-2627-KAPYBARY',
        'Lenochodi'=>'KR-2627-LENOCHODI', 'Lamy'=>'KR-2627-LAMY',
        'Pásovci'=>'KR-2627-P-SOVCI', 'Mývalové'=>'KR-2627-M-VALOV',
        'Lvíčci'=>'KR-2627-LV-CI', 'Lemuři'=>'KR-2627-LEMU-I',
        'Sloni'=>'KR-2627-SLONI', 'Surikaty'=>'KR-2627-SURIKATY',
        'Žirafy'=>'KR-2627-IRAFY', 'Pumptrack'=>'KR-2627-PUMPTRACK',
        'Sportovní přípravka'=>'KR-2627-SPORTOVN-P-PRAVKA',
        'Cyklistická přípravka'=>'KR-2627-CYKLISTICK-P-PRAVKA',
        'Točňáci 2x týdně'=>'KR-2627-TO-CI-2X-T-DN',
    ];
    $matched=[];
    foreach ($names as $name=>$code) if (mb_strpos($label,$name)!==false) $matched[$code]=true;
    return count($matched)===1 ? (string)array_key_first($matched) : null;
}

/** Club and racing rosters missing from the new-year catalogue, explicitly approved for creation. */
function kisCurrentRosterNewTeamSpec(string $label): ?array
{
    $teams=[
        'BIKROS (2025/2026)'=>['LEG26-BIKROS','Bikros 2026/27'],
        'KDC/individual (2025/2026)'=>['LEG26-KDC-INDIVIDUAL','KDC / individuální 2026/27'],
        'Rexonix Kovo Praha - oddíl (2025/2026)'=>['LEG26-REXONIX-ODDIL','Rexonix Kovo Praha – oddíl 2026/27'],
        'Rexonix Kovo Praha - Ž/K/J ( závodní - všichni) (2025/2026)'=>['LEG26-REXONIX-ZAVODNI','Rexonix Kovo Praha – závodní Ž/K/J 2026/27'],
        'Rexonix Kovo Praha I. - Ž/K/J MTB (2x Třebešín, Točná - Čert, Ondra) 1700Kč/měsíc (2025/2026)'=>['LEG26-REXONIX-I-MTB','Rexonix Kovo Praha I – MTB 2026/27'],
        'Rexonix Kovo Praha III. - CX (Ondra) (2025/2026)'=>['LEG26-REXONIX-III-CX','Rexonix Kovo Praha III – CX 2026/27'],
        'Rexonix Kovo Praha III. - MTB (Ž/K/J - Ondra)  (2025/2026)'=>['LEG26-REXONIX-III-MTB','Rexonix Kovo Praha III – MTB 2026/27'],
        'Rexonix Kovo Praha III. - dráha/silnice (Vašek) (2025/2026)'=>['LEG26-REXONIX-III-SILNICE','Rexonix Kovo Praha III – dráha/silnice 2026/27'],
    ];
    if (!isset($teams[$label])) return null;
    return ['code'=>$teams[$label][0],'name'=>$teams[$label][1],'source_label'=>$label];
}

/** @return array<string,mixed> */
function kisCurrentRosterPreview(PDO $pdo): array
{
    $run=$pdo->query('SELECT batch_fingerprint FROM kis_legacy_roster_runs ORDER BY created_at DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    if (!$run) throw new RuntimeException('Nejprve dokončete historický import soupisek.');
    $season=$pdo->prepare('SELECT id,starts_on,ends_on,status FROM club_seasons WHERE code=?');
    $season->execute([KIS_CURRENT_ROSTER_SEASON_CODE]);$season=$season->fetch(PDO::FETCH_ASSOC);
    if (!$season || $season['status']!=='active') throw new RuntimeException('Aktivní školní rok 2026/27 nebyl nalezen.');
    $teams=$pdo->prepare('SELECT id,code,name,status FROM club_teams WHERE season_id=?');
    $teams->execute([(int)$season['id']]);$teamByCode=[];
    foreach ($teams->fetchAll(PDO::FETCH_ASSOC) as $team) $teamByCode[$team['code']]=$team;
    $offers=$pdo->prepare("SELECT team_id,birth_year_from,birth_year_to FROM club_program_offers WHERE season_id=? AND status='active'");
    $offers->execute([(int)$season['id']]);$rangeByTeam=[];
    foreach ($offers->fetchAll(PDO::FETCH_ASSOC) as $offer) {
        $id=(int)$offer['team_id'];$range=[$offer['birth_year_from'],$offer['birth_year_to']];
        $rangeByTeam[$id][$range[0].':'.$range[1]]=$range;
    }
    $members=$pdo->prepare('SELECT m.id,m.team_id,m.sportovec_id,m.status,m.valid_to FROM club_roster_members m JOIN club_teams t ON t.id=m.team_id WHERE t.season_id=?');
    $members->execute([(int)$season['id']]);$memberByPair=[];
    foreach ($members->fetchAll(PDO::FETCH_ASSOC) as $member) $memberByPair[$member['team_id'].':'.$member['sportovec_id']]=$member;
    $claims=$pdo->prepare('SELECT c.id,c.sportovec_id,c.legacy_label,c.status AS legacy_status,c.member_id AS legacy_member_id,c.reason_code AS legacy_reason,p.jmeno,p.prijmeni,p.narozeni,p.stav_clenstvi FROM kis_legacy_roster_claims c JOIN sportovci p ON p.id=c.sportovec_id WHERE c.batch_fingerprint=? ORDER BY c.id');
    $claims->execute([$run['batch_fingerprint']]);$rows=[];$counts=['matched'=>0,'already_present'=>0,'review'=>0];
    foreach ($claims->fetchAll(PDO::FETCH_ASSOC) as $claim) {
        $newSpec=kisCurrentRosterNewTeamSpec((string)$claim['legacy_label']);
        $code=$newSpec['code']??kisCurrentRosterTargetCode((string)$claim['legacy_label']);
        $target=$code===null?null:($teamByCode[$code]??null);
        $teamId=$target===null?null:(int)$target['id'];
        $member=$teamId===null?null:($memberByPair[$teamId.':'.$claim['sportovec_id']]??null);
        $reason='';$status='matched';
        if (!in_array($claim['legacy_status'],['matched','approved'],true)) $reason='historical_claim_review';
        elseif ($claim['legacy_member_id']===null) $reason='historical_member_missing';
        elseif ($code===null) $reason='no_unique_target';
        elseif (($target===null && $newSpec===null) || ($target!==null && $target['status']!=='active')) $reason='target_unavailable';
        elseif ($newSpec!==null && $target!==null && $target['name']!==$newSpec['name']) $reason='target_conflict';
        elseif ($claim['stav_clenstvi']==='archiv') $reason='person_archived';
        elseif ($newSpec===null && empty($rangeByTeam[$teamId])) $reason='no_active_offer';
        elseif ($newSpec===null && count($rangeByTeam[$teamId])!==1) $reason='offer_age_conflict';
        elseif ($newSpec===null && ($claim['narozeni']===null || $claim['narozeni']==='')) $reason='birth_missing';
        elseif ($newSpec===null) {
            [$from,$to]=array_values($rangeByTeam[$teamId])[0];
            $birthYear=(int)substr((string)$claim['narozeni'],0,4);
            if ($from===null || $to===null || $birthYear<(int)$from || $birthYear>(int)$to) $reason='birth_out_of_range';
        }
        if ($reason==='' && $member!==null && ($member['status']!=='active' || $member['valid_to']!==null)) $reason='existing_inactive_member';
        elseif ($reason==='' && $member!==null) $status='already_present';
        if ($reason!=='') $status='review';
        $counts[$status]++;
        $rows[]=[
            'legacy_claim_id'=>(int)$claim['id'],'sportovec_id'=>(int)$claim['sportovec_id'],
            'name'=>trim($claim['prijmeni'].' '.$claim['jmeno']),'birth'=>$claim['narozeni'],
            'legacy_label'=>$claim['legacy_label'],'legacy_status'=>$claim['legacy_status'],
            'suggested_team_id'=>$teamId,'suggested_team_name'=>$target['name']??($newSpec['name']??null),
            'target_code'=>$code,'new_team_spec'=>$newSpec,
            'existing_member_id'=>$member===null?null:(int)$member['id'],
            'status'=>$status,'reason'=>$reason,
        ];
    }
    $today=(new DateTimeImmutable('today'))->format('Y-m-d');
    $fingerprint=hash('sha256',kisPersonJson(['batch'=>$run['batch_fingerprint'],'season'=>$season,'today'=>$today,'claims'=>$rows]));
    return ['legacy_batch_fingerprint'=>$run['batch_fingerprint'],'season_id'=>(int)$season['id'],
        'valid_from'=>$today,'fingerprint'=>$fingerprint,'counts'=>$counts,'claims'=>$rows];
}

/** Caller holds a transaction and has validated the target team. */
function kisCurrentRosterInsertMember(PDO $pdo,int $teamId,int $personId,int $actorId,string $validFrom,string $note): int
{
    $existing=$pdo->prepare('SELECT id FROM club_roster_members WHERE team_id=? AND sportovec_id=?');
    $existing->execute([$teamId,$personId]);
    if ($existing->fetchColumn()) throw new RuntimeException('Členství se změnilo; obnovte náhled.');
    $pdo->prepare("INSERT INTO club_roster_members(team_id,sportovec_id,status,source,kis_external_id_snapshot,valid_from,valid_to,created_by_trainer_id) VALUES (?,?,'active','kis_shadow',NULL,?,NULL,?)")
        ->execute([$teamId,$personId,$validFrom,$actorId]);
    $id=(int)$pdo->lastInsertId();
    kisRosterEvent($pdo,$teamId,$id,$actorId,'import_current_member',null,
        ['id'=>$id,'team_id'=>$teamId,'sportovec_id'=>$personId,'status'=>'active','source'=>'kis_shadow','valid_from'=>$validFrom],$note);
    return $id;
}

/** Atomic and idempotent current-roster import from reviewed historical claims. */
function kisCurrentRosterApply(PDO $pdo,int $actorId,string $expectedFingerprint): array
{
    if ($actorId<1) throw new InvalidArgumentException('Chybí administrátor.');
    $mysql=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
    if ($mysql && (int)$pdo->query("SELECT GET_LOCK('kis_current_roster_2026_27',15)")->fetchColumn()!==1)
        throw new RuntimeException('Převod soupisek právě probíhá.');
    try {
        $existingRun=$pdo->query('SELECT plan_fingerprint,summary_json FROM kis_current_roster_runs ORDER BY created_at DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        if ($existingRun) {
            if (!hash_equals($existingRun['plan_fingerprint'],$expectedFingerprint)) throw new RuntimeException('Tento archiv už byl převeden podle jiného náhledu.');
            return json_decode($existingRun['summary_json'],true,32,JSON_THROW_ON_ERROR)+['idempotent'=>true];
        }
        $plan=kisCurrentRosterPreview($pdo);
        if (!hash_equals($plan['fingerprint'],$expectedFingerprint)) throw new RuntimeException('Náhled se změnil. Připravte nový.');
        $pdo->beginTransaction();
        $done=$pdo->prepare('SELECT summary_json,plan_fingerprint FROM kis_current_roster_runs WHERE legacy_batch_fingerprint=?'.($mysql?' FOR UPDATE':''));
        $done->execute([$plan['legacy_batch_fingerprint']]);$row=$done->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            if (!hash_equals($row['plan_fingerprint'],$expectedFingerprint)) throw new RuntimeException('Tento archiv už byl převeden podle jiného náhledu.');
            $pdo->commit();return json_decode($row['summary_json'],true,32,JSON_THROW_ON_ERROR)+['idempotent'=>true];
        }
        $newTeamCodes=[];
        foreach ($plan['claims'] as $claim) if ($claim['status']==='matched' && $claim['new_team_spec']!==null && $claim['suggested_team_id']===null)
            $newTeamCodes[$claim['new_team_spec']['code']]=true;
        $summary=['matched'=>$plan['counts']['matched'],'already_present'=>$plan['counts']['already_present'],
            'review'=>$plan['counts']['review'],'created_teams'=>count($newTeamCodes),
            'claims'=>count($plan['claims']),'season_id'=>$plan['season_id']];
        $pdo->prepare('INSERT INTO kis_current_roster_runs(legacy_batch_fingerprint,plan_fingerprint,season_id,summary_json,actor_trainer_id) VALUES (?,?,?,?,?)')
            ->execute([$plan['legacy_batch_fingerprint'],$plan['fingerprint'],$plan['season_id'],kisPersonJson($summary),$actorId]);
        $runId=(int)$pdo->lastInsertId();
        $createdTeams=[];
        $createTeam=$pdo->prepare("INSERT INTO club_teams(season_id,series_id,code,name,discipline,age_label,status,created_by_trainer_id) VALUES (?,NULL,?,?,'Cyklistika','Dle starého KIS','active',?)");
        foreach ($plan['claims'] as $claim) {
            $spec=$claim['new_team_spec'];
            if ($claim['status']!=='matched' || $spec===null || $claim['suggested_team_id']!==null || isset($createdTeams[$spec['code']])) continue;
            $createTeam->execute([$plan['season_id'],$spec['code'],$spec['name'],$actorId]);
            $createdTeams[$spec['code']]=(int)$pdo->lastInsertId();
            kisRosterEvent($pdo,$createdTeams[$spec['code']],null,$actorId,'import_current_team',null,
                ['code'=>$spec['code'],'name'=>$spec['name'],'season_id'=>$plan['season_id'],'source_label'=>$spec['source_label']],
                'Založení chybějící oddílové nebo závodní soupisky pro převod jednoznačných vazeb ze starého KIS.');
        }
        $insert=$pdo->prepare('INSERT INTO kis_current_roster_claims(run_id,legacy_claim_id,sportovec_id,suggested_team_id,target_team_id,member_id,reason_code,status) VALUES (?,?,?,?,?,?,?,?)');
        foreach ($plan['claims'] as $claim) {
            $suggestedId=$claim['suggested_team_id']??($createdTeams[$claim['target_code']]??null);
            $targetId=$claim['status']==='review'?null:$suggestedId;
            $memberId=$claim['existing_member_id'];
            if ($claim['status']==='matched') $memberId=kisCurrentRosterInsertMember($pdo,$targetId,$claim['sportovec_id'],$actorId,$plan['valid_from'],
                'Převod jednoznačné historické vazby #'.$claim['legacy_claim_id'].' do aktuální soupisky 2026/27; datum označuje den převodu.');
            $insert->execute([$runId,$claim['legacy_claim_id'],$claim['sportovec_id'],$suggestedId,$targetId,
                $claim['status']==='review'?null:$memberId,$claim['reason'],$claim['status']]);
        }
        $pdo->commit();return $summary+['idempotent'=>false];
    } catch (Throwable $e) {if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    finally {if($mysql)$pdo->query("SELECT RELEASE_LOCK('kis_current_roster_2026_27')");}
}

function kisCurrentRosterDecide(PDO $pdo,int $claimId,int $actorId,string $decision,?int $targetTeamId,string $note): void
{
    $note=trim($note);
    if ($claimId<1 || $actorId<1 || !in_array($decision,['approve','skip'],true) || $note==='' || mb_strlen($note)>1000)
        throw new InvalidArgumentException('Vyberte rozhodnutí a zapište důvod.');
    $mysql=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
    $pdo->beginTransaction();
    try {
        $s=$pdo->prepare('SELECT c.*,l.status AS legacy_status,p.stav_clenstvi FROM kis_current_roster_claims c JOIN kis_legacy_roster_claims l ON l.id=c.legacy_claim_id JOIN sportovci p ON p.id=c.sportovec_id WHERE c.id=?'.($mysql?' FOR UPDATE':''));
        $s->execute([$claimId]);$claim=$s->fetch(PDO::FETCH_ASSOC);
        if (!$claim || $claim['status']!=='review') throw new RuntimeException('Případ již byl rozhodnut nebo neexistuje.');
        $memberId=null;
        if ($decision==='approve') {
            if (!in_array($claim['legacy_status'],['matched','approved'],true)) throw new RuntimeException('Nejprve rozhodněte historickou vazbu.');
            if ($claim['stav_clenstvi']==='archiv') throw new RuntimeException('Archivovaného sportovce nelze přidat.');
            if ($targetTeamId===null || $targetTeamId<1) throw new InvalidArgumentException('Vyberte cílovou soupisku.');
            $s=$pdo->prepare('SELECT t.id,s.starts_on,s.ends_on FROM club_teams t JOIN club_seasons s ON s.id=t.season_id WHERE t.id=? AND t.status=\'active\' AND s.status=\'active\' AND s.code=?'.($mysql?' FOR UPDATE':''));
            $s->execute([$targetTeamId,KIS_CURRENT_ROSTER_SEASON_CODE]);$team=$s->fetch(PDO::FETCH_ASSOC);
            if (!$team) throw new RuntimeException('Cílová soupiska není aktivní v roce 2026/27.');
            $s=$pdo->prepare('SELECT id,status,valid_to FROM club_roster_members WHERE team_id=? AND sportovec_id=?'.($mysql?' FOR UPDATE':''));
            $s->execute([$targetTeamId,$claim['sportovec_id']]);$existing=$s->fetch(PDO::FETCH_ASSOC);
            if ($existing && ($existing['status']!=='active' || $existing['valid_to']!==null)) throw new RuntimeException('Existující ukončené členství vyžaduje samostatné obnovení.');
            $memberId=$existing?(int)$existing['id']:kisCurrentRosterInsertMember($pdo,$targetTeamId,(int)$claim['sportovec_id'],$actorId,
                (new DateTimeImmutable('today'))->format('Y-m-d'),'Ruční rozhodnutí o převodu historické vazby #'.$claim['legacy_claim_id'].': '.$note);
        }
        $pdo->prepare('UPDATE kis_current_roster_claims SET status=?,target_team_id=?,member_id=?,decision_note=?,decided_by_trainer_id=?,decided_at=CURRENT_TIMESTAMP WHERE id=?')
            ->execute([$decision==='approve'?'approved':'skipped',$decision==='approve'?$targetTeamId:null,$memberId,$note,$actorId,$claimId]);
        $pdo->commit();
    } catch (Throwable $e) {if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
