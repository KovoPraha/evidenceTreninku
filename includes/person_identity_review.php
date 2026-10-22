<?php
declare(strict_types=1);

require_once __DIR__.'/person_match.php';
require_once __DIR__.'/member_charge_read.php';

/** Additive review only: no merge, import, account grant or membership change. */
function personIdentityReviewCreate(PDO $pdo, array $input, int $actor): int
{
    if ($actor < 1) throw new InvalidArgumentException('Chybí autor případu.');
    $namespace = trim((string)($input['source_namespace'] ?? ''));
    $sourceId = trim((string)($input['source_id'] ?? ''));
    if (!preg_match('~^[a-zA-Z0-9][a-zA-Z0-9._:/-]{1,79}$~D', $namespace)
        || !preg_match('~^[a-zA-Z0-9][a-zA-Z0-9._:/-]{0,99}$~D', $sourceId)) {
        throw new InvalidArgumentException('Uveďte technický název zdroje a stabilní zdrojové ID; nikoli e-mail nebo jméno.');
    }
    $first = trim((string)($input['jmeno'] ?? ''));
    $last = trim((string)($input['prijmeni'] ?? ''));
    $rawBirth = trim((string)($input['narozeni'] ?? ''));
    if ($first === '' || $last === '' || mb_strlen($first)>100 || mb_strlen($last)>255
        || ($rawBirth !== '' && personMatchV1Date($rawBirth) === null)) {
        throw new InvalidArgumentException('Zkontrolujte jméno, příjmení a datum narození. Neznámé datum ponechte prázdné.');
    }
    $birth = personMatchV1BirthDate($rawBirth);
    $evidence = trim((string)($input['evidence_ref'] ?? ''));
    if (mb_strlen($evidence)<3 || mb_strlen($evidence)>255) throw new InvalidArgumentException('Uveďte odkaz nebo označení podkladu (3–255 znaků), bez citlivých údajů.');
    $fingerprint = hash('sha256', json_encode([$namespace,$sourceId,$first,$last,$rawBirth,$evidence], JSON_THROW_ON_ERROR));
    $pdo->beginTransaction();
    try {
        $existing = $pdo->prepare('SELECT id,source_fingerprint FROM person_identity_reviews WHERE source_namespace=? AND source_id=?');
        $existing->execute([$namespace,$sourceId]);
        if ($row = $existing->fetch(PDO::FETCH_ASSOC)) {
            if (!hash_equals($row['source_fingerprint'],$fingerprint)) throw new RuntimeException('Zdrojové ID již má případ s jinými podklady. Otevřete existující případ; nic nebylo přepsáno.');
            $pdo->commit();
            return (int)$row['id'];
        }
        $match = personMatchV1($pdo,['jmeno'=>$first,'prijmeni'=>$last,'narozeni'=>$birth]);
        $candidateIds = array_column($match['candidates'],'id');
        $insert = $pdo->prepare('INSERT INTO person_identity_reviews (source_namespace,source_id,source_fingerprint,first_name,last_name,birth_date,candidate_ids_json,reason,evidence_ref,created_by_trainer_id) VALUES(?,?,?,?,?,?,?,?,?,?)');
        $insert->execute([$namespace,$sourceId,$fingerprint,$first,$last,$birth,json_encode($candidateIds,JSON_THROW_ON_ERROR),'Čeká na výslovné ověření identity.',$evidence,$actor]);
        $id = (int)$pdo->lastInsertId();
        personIdentityReviewEvent($pdo,$id,$actor,1,[],['status'=>'review','sportovec_id'=>null,'candidate_ids'=>$candidateIds,'evidence_ref'=>$evidence]);
        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function personIdentityReviewGet(PDO $pdo, int $id, bool $lock=false): array
{
    $stmt=$pdo->prepare('SELECT * FROM person_identity_reviews WHERE id=?'.($lock && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''));
    $stmt->execute([$id]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new InvalidArgumentException('Případ identity nebyl nalezen.');
    return $row;
}

function personIdentityReviewDecide(PDO $pdo,int $id,int $revision,string $decision,?int $personId,string $reason,string $evidence,int $actor): void
{
    $reason=trim($reason); $evidence=trim($evidence);
    if ($actor<1 || !in_array($decision,['verified','deferred','excluded'],true)
        || mb_strlen($reason)<10 || mb_strlen($reason)>2000 || mb_strlen($evidence)<3 || mb_strlen($evidence)>255) {
        throw new InvalidArgumentException('Vyberte rozhodnutí a uveďte důvod (10–2000 znaků) i označení podkladu (3–255 znaků).');
    }
    if ($decision==='verified' && ($personId??0)<1) throw new InvalidArgumentException('Potvrzení vyžaduje výslovný výběr osoby.');
    if ($decision!=='verified') $personId=null;
    $pdo->beginTransaction();
    try {
        $old=personIdentityReviewGet($pdo,$id,true);
        if ((int)$old['revision']!==$revision) throw new RuntimeException('Případ mezitím změnil jiný pracovník. Obnovte stránku.');
        if ($personId!==null) {
            $p=$pdo->prepare('SELECT id FROM sportovci WHERE id=?'.($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''));
            $p->execute([$personId]);
            if (!$p->fetchColumn()) throw new InvalidArgumentException('Cílová osoba neexistuje.');
            // Existing imported aliases remain authoritative until a separate,
            // explicitly reviewed migration. This ledger cannot replace them.
            if (memberChargeReadTableExists($pdo,'kis_person_aliases') && memberChargeReadTableExists($pdo,'kis_person_profiles') && memberChargeReadTableExists($pdo,'kis_person_batches')) {
                $a=$pdo->prepare('SELECT a.sportovec_id FROM kis_person_aliases a JOIN kis_person_profiles p ON p.sportovec_id=a.sportovec_id JOIN kis_person_batches b ON b.fingerprint=p.batch_fingerprint WHERE a.source_key=? AND b.source_namespace=?');
                $a->execute([$old['source_id'],$old['source_namespace']]);
                $existingId=$a->fetchColumn();
                if ($existingId!==false && (int)$existingId!==$personId) throw new RuntimeException('Zdroj je již navázán na jinou osobu v importním archivu. Odložte případ k řešení; tato akce vazbu nepřepíše.');
            }
        }
        $new=['status'=>$decision,'sportovec_id'=>$personId,'reason'=>$reason,'evidence_ref'=>$evidence];
        $pdo->prepare('UPDATE person_identity_reviews SET status=?,sportovec_id=?,reason=?,evidence_ref=?,revision=revision+1,decided_by_trainer_id=?,decided_at=CURRENT_TIMESTAMP WHERE id=? AND revision=?')
            ->execute([$decision,$personId,$reason,$evidence,$actor,$id,$revision]);
        personIdentityReviewEvent($pdo,$id,$actor,$revision+1,array_intersect_key($old,$new),$new);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function personIdentityReviewEvent(PDO $pdo,int $id,int $actor,int $revision,array $before,array $after): void
{
    $pdo->prepare('INSERT INTO person_identity_review_events(review_id,actor_trainer_id,revision,before_json,after_json) VALUES(?,?,?,?,?)')
        ->execute([$id,$actor,$revision,json_encode($before,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),json_encode($after,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
}
