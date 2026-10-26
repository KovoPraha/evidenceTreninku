<?php
declare(strict_types=1);
require_once __DIR__.'/kis_import_run_lib.php';
require_once __DIR__.'/roster_hierarchy.php';
require_once __DIR__.'/sportovec_history_lib.php';
require_once __DIR__.'/public_profile_token.php';

const KIS_REVIEW_CONTRACT='kis-reviewed-incremental-v1';
function kisReviewHash(array $data):string{return hash('sha256',json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));}
function kisReviewRows(PDO $pdo,string $table,string $where='',array $args=[]):array{
    if(!preg_match('/^[a-z_]+$/D',$table))throw new LogicException('Invalid table.');
    $lock=$pdo->inTransaction()&&$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
    $q=$pdo->prepare('SELECT * FROM '.$table.($where?' WHERE '.$where:'').' ORDER BY id'.$lock);$q->execute($args);return $q->fetchAll(PDO::FETCH_ASSOC);
}
/** Includes all staged values, not only the classification fingerprint of the older preview. */
function kisReviewSource(PDO $pdo,int $runId):array{
    $lock=$pdo->inTransaction()&&$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
    $q=$pdo->prepare('SELECT * FROM kis_import_runs WHERE id=?'.$lock);$q->execute([$runId]);$run=$q->fetch(PDO::FETCH_ASSOC);
    if(!$run||$run['status']!=='preview')throw new DomainException('Importní běh není platný náhled.');
    $manifest=json_decode((string)$run['source_manifest_json'],true,32,JSON_THROW_ON_ERROR);
    $ids=[];foreach(kisSourceKinds() as $kind){if(empty($manifest['sources'][$kind]['artifact_id']))throw new DomainException('Chybí zdroj '.$kind.'.');$ids[$kind]=(int)$manifest['sources'][$kind]['artifact_id'];}
    $fresh=kisSourceManifest($pdo,$ids);
    if(!hash_equals(kisReviewHash($manifest),kisReviewHash($fresh)))throw new DomainException('Manifest byl změněn.');
    $root=kisSourceArchiveRoot(kisSourceConfiguredArchiveDirectory());
    foreach($fresh['sources'] as $source){
        $path=$root.DIRECTORY_SEPARATOR.$source['storage_key'];$real=realpath($path);
        if(!$real||!kisSourcePathWithin($real,$root)||is_link($path))throw new DomainException('Zdrojový archiv není bezpečně dostupný.');
        kisSourceAssertArchivedFile($real,$source['sha256'],(int)$source['byte_size']);
    }
    $rows=kisReviewRows($pdo,'kis_import_rows','run_id=?',[$runId]);
    if(!$rows||count($rows)>5000)throw new DomainException('Běh musí mít 1 až 5000 osob.');
    $seen=[];foreach($rows as $row){
        $id=kisFieldNormalizeExternalId($row['kis_external_id']??'');
        if($id===''||isset($seen[$id]))throw new DomainException('Chybějící nebo duplicitní stabilní KIS ID.');
        $seen[$id]=true;
    }
    $canonical=['contract'=>KIS_REVIEW_CONTRACT,'run_id'=>$runId,'manifest'=>$fresh,'rows'=>$rows,
        'payments'=>kisReviewRows($pdo,'kis_import_payment_rows','run_id=?',[$runId])];
    return $canonical+['fingerprint'=>kisReviewHash($canonical)];
}
function kisReviewDatabaseHash(PDO $pdo):string{
    // Conservative drift detection: a new person or grant requires a fresh review.
    return kisReviewHash([kisReviewRows($pdo,'sportovci'),kisReviewRows($pdo,'club_teams'),kisReviewRows($pdo,'club_roster_members')]);
}
function kisReviewReason(string $reason):string{
    $reason=trim($reason);if(mb_strlen($reason)<5||mb_strlen($reason)>500||preg_match('/[<>]/u',$reason))throw new InvalidArgumentException('Důvod musí mít 5–500 znaků prostého textu.');return $reason;
}
function kisReviewAllowed(int $actor,bool $allowed,bool $confirmed=true):void{
    if(!$allowed||$actor<1)throw new DomainException('Tento převod je zatím povolen pouze správci v izolovaném prostředí.');
    if(!$confirmed)throw new DomainException('Potvrďte konkrétní náhled a rozsah.');
}
function kisReviewUpdatePerson(PDO $pdo,int $id,array $fields):void{
    $columns=array_values(array_intersect(['jmeno','prijmeni','narozeni','kis_external_id','first_name_norm','last_name_norm'],array_keys($fields)));
    if(!$columns)throw new DomainException('Chybí povolená pole osoby.');
    $pdo->prepare('UPDATE sportovci SET '.implode(',',array_map(static fn($c)=>$c.'=?',$columns)).' WHERE id=?')
        ->execute([...array_map(static fn($c)=>$fields[$c],$columns),$id]);
}
/** All mappings are explicit. Absent people, accounts and financial records are untouched. */
function kisReviewPreview(PDO $pdo,int $runId,string $sourceFingerprint,array $choices,string $validFrom,int $actor,bool $allowed):array{
    kisReviewAllowed($actor,$allowed);$validFrom=kisRosterDate($validFrom);
    if($pdo->inTransaction())throw new LogicException('Náhled vyžaduje vlastní transakci.');
    if($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $pdo->beginTransaction();
    try {
    $lock=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
    if((int)$pdo->query('SELECT id FROM kis_review_mutex WHERE id=1'.$lock)->fetchColumn()!==1)throw new DomainException('Chybí zámek řízeného převodu.');
    $source=kisReviewSource($pdo,$runId);
    // Capture and lock before-images and their fingerprint in the same snapshot.
    $databaseFingerprint=kisReviewDatabaseHash($pdo);
    if(!hash_equals($source['fingerprint'],$sourceFingerprint))throw new DomainException('Zdroj se změnil. Obnovte předání.');
    $items=[];$targets=[];
    foreach($source['rows'] as $row){
        $choice=$choices[(int)$row['id']]??null;if(!is_array($choice))throw new DomainException('Rozhodněte každý zdrojový řádek.');
        $target=(string)($choice['target']??'');$external=(string)$row['kis_external_id'];
        $q=$pdo->prepare('SELECT * FROM sportovci WHERE kis_external_id=? ORDER BY id');$q->execute([$external]);$existing=$q->fetchAll(PDO::FETCH_ASSOC);
        $before=null;
        if($target==='new'){if($existing)throw new DomainException('KIS ID '.$external.' už patří existující osobě.');}
        else{
            if(!ctype_digit($target)||(int)$target<1||isset($targets[$target]))throw new DomainException('Neplatné nebo opakované přiřazení osoby.');
            $q=$pdo->prepare('SELECT * FROM sportovci WHERE id=?');$q->execute([(int)$target]);$before=$q->fetch(PDO::FETCH_ASSOC);
            if(!$before||$before['stav_clenstvi']==='archiv')throw new DomainException('Cílová osoba neexistuje nebo je archivovaná.');
            if(trim((string)$before['kis_external_id'])!==''&&(string)$before['kis_external_id']!==$external)throw new DomainException('Cílová osoba má jiné KIS ID.');
            foreach($existing as $p)if((int)$p['id']!==(int)$target)throw new DomainException('KIS ID je přiřazeno jiné osobě.');
            $targets[$target]=true;
        }
        $fields=['jmeno'=>trim((string)$row['jmeno']),'prijmeni'=>trim((string)$row['prijmeni']),'narozeni'=>$row['narozeni']?:null,'kis_external_id'=>$external];
        $fields['first_name_norm']=kisMatchNormalizeText($fields['jmeno']);$fields['last_name_norm']=kisMatchNormalizeText($fields['prijmeni']);
        foreach(['jmeno','prijmeni'] as $field)if($fields[$field]===''||mb_strlen($fields[$field])>100)throw new DomainException('Neplatné jméno ve zdroji.');
        if($fields['narozeni']===null)throw new DomainException('Osoba '.$external.' nemá datum narození. Doplňte ověřený zdroj; datum se neodhaduje.');
        kisRosterDate($fields['narozeni']);
        $teams=[];foreach((array)($choice['teams']??[])as $teamId){
            if(!ctype_digit((string)$teamId)||(int)$teamId<1)throw new DomainException('Neplatná soupiska.');
            $q=$pdo->prepare("SELECT * FROM club_teams WHERE id=? AND status='active' AND team_kind='roster'");$q->execute([(int)$teamId]);$team=$q->fetch(PDO::FETCH_ASSOC);
            if(!$team)throw new DomainException('Vybraná soupiska není aktivní.');$teams[(int)$teamId]=$team['name'];
        }
        $items[]=['source_row'=>(int)$row['id'],'external_id'=>$external,'target'=>$target,'fields'=>$fields,
            'before'=>$before?array_intersect_key($before,$fields):null,'teams'=>$teams];
    }
    $plan=['contract'=>KIS_REVIEW_CONTRACT,'run_id'=>$runId,'source_fingerprint'=>$sourceFingerprint,'database_fingerprint'=>$databaseFingerprint,'valid_from'=>$validFrom,'items'=>$items,'finance'=>'deferred_separate_parity','accounts'=>'unchanged'];
    $fingerprint=kisReviewHash($plan);
    $pdo->prepare('INSERT INTO kis_review_plans(run_id,fingerprint,plan_json,created_by) VALUES(?,?,?,?)')->execute([$runId,$fingerprint,kisImportJson($plan),$actor]);
    $id=(int)$pdo->lastInsertId();$pdo->commit();
    return ['id'=>$id,'fingerprint'=>$fingerprint,'plan'=>$plan,'status'=>'preview'];
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function kisReviewPlan(PDO $pdo,int $id):array{
    $lock=$pdo->inTransaction()&&$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
    $q=$pdo->prepare('SELECT * FROM kis_review_plans WHERE id=?'.$lock);$q->execute([$id]);$row=$q->fetch(PDO::FETCH_ASSOC);
    if(!$row)throw new DomainException('Náhled neexistuje.');
    $row['plan']=json_decode($row['plan_json'],true,64,JSON_THROW_ON_ERROR);
    if(!hash_equals($row['fingerprint'],kisReviewHash($row['plan'])))throw new DomainException('Náhled neprošel kontrolou otisku.');
    return $row;
}
/** All direct references are hashed without storing their private contents. */
function kisReviewPersonDependencies(PDO $pdo,int $id):string{
    $mysql=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';$columns=[];
    if($mysql){$rows=$pdo->query("SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND (COLUMN_NAME='sportovec_id' OR COLUMN_NAME LIKE '%!_sportovec!_id' ESCAPE '!') ORDER BY TABLE_NAME,COLUMN_NAME")->fetchAll(PDO::FETCH_NUM);foreach($rows as [$t,$c])$columns[$t][]=$c;}
    else{foreach($pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN) as $t){if(!preg_match('/^[a-z_]+$/D',$t))continue;foreach($pdo->query('PRAGMA table_info('.$t.')')->fetchAll(PDO::FETCH_ASSOC) as $c)if($c['name']==='sportovec_id'||str_ends_with($c['name'],'_sportovec_id'))$columns[$t][]=$c['name'];}}
    $hashes=[];foreach($columns as $table=>$names){
        if(in_array($table,['sportovec_history','kis_import_matches'],true))continue;
        if(!preg_match('/^[a-z_]+$/D',$table))throw new DomainException('Neznámý závislý objekt vyžaduje kontrolu.');
        foreach($names as $name)if(!preg_match('/^[a-z_]+$/D',$name))throw new DomainException('Neznámá vazba.');
        $lock=$mysql&&$pdo->inTransaction()?' FOR UPDATE':'';
        $q=$pdo->prepare('SELECT * FROM '.$table.' WHERE '.implode(' OR ',array_map(static fn($c)=>$c.'=?',$names)).$lock);$q->execute(array_fill(0,count($names),$id));
        $digests=array_map(static fn($row)=>kisReviewHash($row),$q->fetchAll(PDO::FETCH_ASSOC));sort($digests);$hashes[$table]=$digests;
    }return kisReviewHash($hashes);
}
function kisReviewExecute(PDO $pdo,int $planId,string $fingerprint,int $actor,bool $allowed,bool $confirmed,string $reason,bool $rollback=false):array{
    kisReviewAllowed($actor,$allowed,$confirmed);$reason=kisReviewReason($reason);
    if($pdo->inTransaction())throw new LogicException('Převod vyžaduje vlastní transakci.');
    if($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $pdo->beginTransaction();
    try{
        $lock=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
        if((int)$pdo->query('SELECT id FROM kis_review_mutex WHERE id=1'.$lock)->fetchColumn()!==1)throw new DomainException('Chybí zámek řízeného převodu.');
        $record=kisReviewPlan($pdo,$planId);$plan=$record['plan'];
        if(!hash_equals($record['fingerprint'],$fingerprint))throw new DomainException('Potvrzení patří jinému náhledu.');
        if(($rollback&&$record['status']==='rolled_back')||(!$rollback&&$record['status']==='applied')){$pdo->commit();return ['id'=>$planId,'idempotent'=>true];}
        if($rollback){
            if($record['status']!=='applied')throw new DomainException('Není co vracet.');
            $q=$pdo->prepare("SELECT changes_json FROM kis_review_events WHERE plan_id=? AND action='apply' ORDER BY id DESC LIMIT 1");$q->execute([$planId]);$changes=json_decode((string)$q->fetchColumn(),true,64,JSON_THROW_ON_ERROR);
            foreach($changes as $change){
                $q=$pdo->prepare('SELECT * FROM sportovci WHERE id=?'.$lock);$q->execute([$change['person_id']]);$current=$q->fetch(PDO::FETCH_ASSOC);
                if(!$current||!hash_equals($change['person_hash'],kisReviewHash($current))||!hash_equals($change['dependency_hash'],kisReviewPersonDependencies($pdo,$change['person_id'])))throw new DomainException('Osoba #'.$change['person_id'].' nebo její vazby se změnily. Návrat vyžaduje ruční kontrolu.');
            }
            foreach($changes as $change){
                foreach($change['members'] as $memberId){
                    $q=$pdo->prepare('SELECT * FROM club_roster_members WHERE id=?');$q->execute([$memberId]);$beforeMember=$q->fetch(PDO::FETCH_ASSOC);
                    $pdo->prepare("UPDATE club_roster_members SET status='cancelled',updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$memberId]);
                    kisRosterEvent($pdo,(int)$beforeMember['team_id'],$memberId,$actor,'cancel_period',$beforeMember,['status'=>'cancelled'],$reason);
                }
                if($change['before']===null){
                    $pdo->prepare("UPDATE sportovci SET stav_clenstvi='archiv',stav_duvod=? WHERE id=?")->execute(['Vrácení izolovaného KIS převodu #'.$planId,$change['person_id']]);
                    $after=['stav_clenstvi'=>'archiv'];
                }else{
                    $after=$change['before'];kisReviewUpdatePerson($pdo,$change['person_id'],$after);
                }
                sportovecLogEvent($pdo,$change['person_id'],'kis_review_rollback','Vrácení řízeného převodu',[],$after,'kis_import',$actor,$reason,'kis_review_plans',$planId);
            }
        }else{
            if($record['status']!=='preview')throw new DomainException('Vrácený plán nelze znovu použít; vytvořte nový náhled.');
            $source=kisReviewSource($pdo,(int)$plan['run_id']);
            // Lock canonical source of drift before checking the saved database fingerprint.
            $pdo->query('SELECT id FROM sportovci ORDER BY id'.$lock)->fetchAll();$pdo->query('SELECT id FROM club_teams ORDER BY id'.$lock)->fetchAll();$pdo->query('SELECT id FROM club_roster_members ORDER BY id'.$lock)->fetchAll();
            if(!hash_equals($plan['source_fingerprint'],$source['fingerprint'])||!hash_equals($plan['database_fingerprint'],kisReviewDatabaseHash($pdo)))throw new DomainException('Zdroj nebo cílová evidence se změnily. Vytvořte nový náhled.');
            $changes=[];
            foreach($plan['items'] as $item){
                $f=$item['fields'];
                if($item['target']==='new'){
                    $pdo->prepare("INSERT INTO sportovci(jmeno,prijmeni,narozeni,kis_external_id,hash,uci,email,stav_clenstvi,first_name_norm,last_name_norm) VALUES(?,?,?,?,?,0,'','aktivni',?,?)")->execute([$f['jmeno'],$f['prijmeni'],$f['narozeni'],$f['kis_external_id'],public_profile_token_generate(),$f['first_name_norm']??kisMatchNormalizeText($f['jmeno']),$f['last_name_norm']??kisMatchNormalizeText($f['prijmeni'])]);
                    $person=(int)$pdo->lastInsertId();
                }else{
                    $person=(int)$item['target'];kisReviewUpdatePerson($pdo,$person,$f);
                }
                sportovecLogEvent($pdo,$person,'kis_review_apply','Řízený převod osoby',$item['before']??[],$f,'kis_import',$actor,$reason,'kis_review_plans',$planId);
                $members=[];foreach($item['teams'] as $team=>$unused){
                    $member=kisRosterGrantPeriod($pdo,(int)$team,$person,$actor,'kis_review',$plan['valid_from'],null,$reason,'kis-review:'.$planId.':'.$item['source_row']);
                    if(!$member['created'])throw new DomainException('Zdrojové členství již existuje.');$members[]=(int)$member['id'];
                }
                $changes[]=['person_id'=>$person,'before'=>$item['before'],'members'=>$members];
            }
            foreach($changes as &$change){$q=$pdo->prepare('SELECT * FROM sportovci WHERE id=?');$q->execute([$change['person_id']]);$change['person_hash']=kisReviewHash($q->fetch(PDO::FETCH_ASSOC));$change['dependency_hash']=kisReviewPersonDependencies($pdo,$change['person_id']);}unset($change);
        }
        $action=$rollback?'rollback':'apply';
        $pdo->prepare('INSERT INTO kis_review_events(plan_id,action,actor_id,reason,changes_json) VALUES(?,?,?,?,?)')->execute([$planId,$action,$actor,$reason,kisImportJson($changes)]);
        $pdo->prepare('UPDATE kis_review_plans SET status=? WHERE id=?')->execute([$rollback?'rolled_back':'applied',$planId]);
        $pdo->commit();return ['id'=>$planId,'idempotent'=>false,'people'=>count($changes)];
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
