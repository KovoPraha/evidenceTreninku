<?php
declare(strict_types=1);
require_once __DIR__ . '/roster_hierarchy.php';

final class TrainingRosterBridgeException extends RuntimeException {}

/** @return list<array<string,mixed>> */
function trainingRosterBridgeEligibleTeams(PDO $pdo, string $date): array
{
    trainingRosterBridgeAssertDate($date);
    $statement = $pdo->query(
        "SELECT t.id,t.code,t.name,t.discipline,t.age_label,s.name season_name "
        . "FROM club_teams t JOIN club_seasons s ON s.id=t.season_id "
        . "WHERE t.status='active' ORDER BY t.name,t.id"
    );
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

/** @return list<int> */
function trainingRosterBridgePlanTeamIds(PDO $pdo, int $planId): array
{
    $statement = $pdo->prepare('SELECT team_id FROM training_roster_links WHERE plan_id=? ORDER BY team_id');
    $statement->execute([$planId]);
    return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
}

/** @return list<int> */
function trainingRosterBridgeTrainingTeamIds(PDO $pdo, int $trainingId): array
{
    $statement = $pdo->prepare('SELECT team_id FROM training_roster_links WHERE trenink_id=? ORDER BY team_id');
    $statement->execute([$trainingId]);
    return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Filter a fetched training list by a canonical group or roster at each
 * training's own date. A group-targeted training also matches its child roster.
 * Legacy rows with no canonical target remain visible in the unfiltered list.
 *
 * @param list<array<string,mixed>> $rows
 * @return list<array<string,mixed>>
 */
function trainingRosterBridgeFilterTrainingRows(PDO $pdo, array $rows, int $filterTeamId): array
{
    if ($filterTeamId < 1 || $rows === []) return $rows;
    $ids = array_values(array_unique(array_map(static fn(array $row): int => (int)$row['id'], $rows)));
    $links = [];
    foreach (array_chunk($ids, 500) as $chunk) {
        $marks = implode(',', array_fill(0, count($chunk), '?'));
        $stmt = $pdo->prepare("SELECT trenink_id,team_id FROM training_roster_links WHERE trenink_id IN ($marks)");
        $stmt->execute($chunk);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $link) $links[(int)$link['trenink_id']][] = (int)$link['team_id'];
    }
    $cache = [];
    return array_values(array_filter($rows, static function (array $row) use ($pdo, $filterTeamId, $links, &$cache): bool {
        $date = (string)$row['datum'];
        $filterDescendants = $cache[$filterTeamId][$date] ??= kisRosterDescendantIds($pdo, $filterTeamId, $date);
        foreach ($links[(int)$row['id']] ?? [] as $targetId) {
            $targetDescendants = $cache[$targetId][$date] ??= kisRosterDescendantIds($pdo, $targetId, $date);
            if (in_array($targetId, $filterDescendants, true) || in_array($filterTeamId, $targetDescendants, true)) return true;
        }
        return false;
    }));
}

/** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
function trainingRosterBridgeFilterPlanRows(PDO $pdo, array $rows, int $filterTeamId): array
{
    if ($filterTeamId < 1 || $rows === []) return $rows;
    $ids = array_values(array_unique(array_map(static fn(array $row): int => (int)$row['id'], $rows)));
    $links = [];
    foreach (array_chunk($ids, 500) as $chunk) {
        $marks = implode(',', array_fill(0, count($chunk), '?'));
        $stmt = $pdo->prepare("SELECT plan_id,team_id FROM training_roster_links WHERE plan_id IN ($marks)");
        $stmt->execute($chunk);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $link) $links[(int)$link['plan_id']][] = (int)$link['team_id'];
    }
    $cache = [];
    return array_values(array_filter($rows, static function (array $row) use ($pdo, $filterTeamId, $links, &$cache): bool {
        $date = (string)$row['datum'];
        $filterDescendants = $cache[$filterTeamId][$date] ??= kisRosterDescendantIds($pdo, $filterTeamId, $date);
        foreach ($links[(int)$row['id']] ?? [] as $targetId) {
            $targetDescendants = $cache[$targetId][$date] ??= kisRosterDescendantIds($pdo, $targetId, $date);
            if (in_array($targetId, $filterDescendants, true) || in_array($filterTeamId, $targetDescendants, true)) return true;
        }
        return false;
    }));
}

/** @param list<array<string,mixed>> $rows @return array<int,string> */
function trainingRosterBridgeTrainingLabels(PDO $pdo, array $rows): array
{
    if ($rows === []) return [];
    $ids = array_values(array_unique(array_map(static fn(array $row): int => (int)$row['id'], $rows)));
    $names = [];
    foreach (array_chunk($ids, 500) as $chunk) {
        $marks = implode(',', array_fill(0, count($chunk), '?'));
        $stmt = $pdo->prepare("SELECT trenink_id,team_name_snapshot FROM training_roster_links WHERE trenink_id IN ($marks) ORDER BY team_name_snapshot");
        $stmt->execute($chunk);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $names[(int)$row['trenink_id']][] = (string)$row['team_name_snapshot'];
    }
    return array_map(static fn(array $labels): string => implode(', ', array_values(array_unique($labels))), $names);
}

/** @param list<array<string,mixed>> $rows @return array<int,string> */
function trainingRosterBridgePlanLabels(PDO $pdo, array $rows): array
{
    if ($rows === []) return [];
    $ids = array_values(array_unique(array_map(static fn(array $row): int => (int)$row['id'], $rows)));
    $names = [];
    foreach (array_chunk($ids, 500) as $chunk) {
        $marks = implode(',', array_fill(0, count($chunk), '?'));
        $stmt = $pdo->prepare("SELECT plan_id,team_name_snapshot FROM training_roster_links WHERE plan_id IN ($marks) ORDER BY team_name_snapshot");
        $stmt->execute($chunk);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $names[(int)$row['plan_id']][] = (string)$row['team_name_snapshot'];
    }
    return array_map(static fn(array $labels): string => implode(', ', array_values(array_unique($labels))), $names);
}

/**
 * Replaces the plan-to-team bindings and rebuilds their historical expectation snapshot atomically.
 * This function deliberately never writes to trenink_sportovec.
 *
 * @param list<int|string> $teamIds
 * @return array{link_count:int,expected_count:int}
 */
function trainingRosterBridgeReplacePlanTeams(PDO $pdo, int $planId, array $teamIds, int $actorId): array
{
    return trainingRosterBridgeReplace($pdo, 'plan', $planId, $teamIds, $actorId);
}

/** @param list<int|string> $teamIds @return array{link_count:int,expected_count:int} */
function trainingRosterBridgeReplaceTrainingTeams(PDO $pdo, int $trainingId, array $teamIds, int $actorId): array
{
    return trainingRosterBridgeReplace($pdo, 'training', $trainingId, $teamIds, $actorId);
}

/** @return list<array{id:int,label:string}> */
function trainingRosterBridgeExpectedForPlan(PDO $pdo, int $planId): array
{
    $statement = $pdo->prepare(
        "SELECT e.sportovec_id id,TRIM(COALESCE(sp.prijmeni,'') || ' ' || COALESCE(sp.jmeno,'')) label "
        . 'FROM training_roster_expected e '
        . 'JOIN training_roster_links l ON l.id=e.link_id '
        . 'JOIN sportovci sp ON sp.id=e.sportovec_id '
        . 'WHERE l.plan_id=? GROUP BY e.sportovec_id,sp.prijmeni,sp.jmeno ORDER BY sp.prijmeni,sp.jmeno,e.sportovec_id'
    );
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare(
            "SELECT e.sportovec_id id,TRIM(CONCAT(COALESCE(sp.prijmeni,''),' ',COALESCE(sp.jmeno,''))) label "
            . 'FROM training_roster_expected e JOIN training_roster_links l ON l.id=e.link_id '
            . 'JOIN sportovci sp ON sp.id=e.sportovec_id WHERE l.plan_id=? '
            . 'GROUP BY e.sportovec_id,sp.prijmeni,sp.jmeno ORDER BY sp.prijmeni,sp.jmeno,e.sportovec_id'
        );
    }
    $statement->execute([$planId]);
    return array_map(static fn(array $row): array => ['id' => (int)$row['id'], 'label' => (string)$row['label']], $statement->fetchAll(PDO::FETCH_ASSOC));
}

/**
 * Copies the immutable planning expectation to the actual Evidence training.
 * Attendance itself is deliberately not copied and remains a manual trainer decision.
 *
 * @return array{link_count:int,expected_count:int}
 */
function trainingRosterBridgeCopyPlanToTraining(PDO $pdo,int $planId,int $trainingId,int $actorId):array
{
    if($planId<1||$trainingId<1||$actorId<1)throw new InvalidArgumentException('Neplatný plán, trénink nebo aktér.');
    $ownsTransaction=!$pdo->inTransaction();if($ownsTransaction)$pdo->beginTransaction();
    try{
        $plan=$pdo->prepare('SELECT id FROM planovane_treninky WHERE id=?');$plan->execute([$planId]);
        $training=$pdo->prepare('SELECT id FROM treninky WHERE id=?');$training->execute([$trainingId]);
        if(!$plan->fetchColumn()||!$training->fetchColumn())throw new TrainingRosterBridgeException('Plán nebo skutečný trénink nebyl nalezen.');
        $old=$pdo->prepare('SELECT id FROM training_roster_links WHERE trenink_id=?');$old->execute([$trainingId]);$oldIds=array_map('intval',$old->fetchAll(PDO::FETCH_COLUMN));
        if($oldIds!==[])$pdo->prepare('DELETE FROM training_roster_expected WHERE link_id IN ('.implode(',',array_fill(0,count($oldIds),'?')).')')->execute($oldIds);
        $pdo->prepare('DELETE FROM training_roster_links WHERE trenink_id=?')->execute([$trainingId]);
        $links=$pdo->prepare('SELECT id,team_id,target_date,team_code_snapshot,team_name_snapshot FROM training_roster_links WHERE plan_id=? ORDER BY id');$links->execute([$planId]);$links=$links->fetchAll(PDO::FETCH_ASSOC);
        $insertLink=$pdo->prepare('INSERT INTO training_roster_links(plan_id,trenink_id,team_id,target_date,team_code_snapshot,team_name_snapshot,created_by_trainer_id) VALUES(NULL,?,?,?,?,?,?)');
        $expected=$pdo->prepare('SELECT sportovec_id,roster_member_id,member_valid_from_snapshot,member_valid_to_snapshot FROM training_roster_expected WHERE link_id=? ORDER BY id');
        $insertExpected=$pdo->prepare('INSERT INTO training_roster_expected(link_id,sportovec_id,roster_member_id,member_valid_from_snapshot,member_valid_to_snapshot) VALUES(?,?,?,?,?)');
        $people=[];
        foreach($links as$link){
            $insertLink->execute([$trainingId,(int)$link['team_id'],(string)$link['target_date'],(string)$link['team_code_snapshot'],(string)$link['team_name_snapshot'],$actorId]);$newLinkId=(int)$pdo->lastInsertId();
            $expected->execute([(int)$link['id']]);foreach($expected->fetchAll(PDO::FETCH_ASSOC)as$row){$insertExpected->execute([$newLinkId,(int)$row['sportovec_id'],$row['roster_member_id']!==null?(int)$row['roster_member_id']:null,(string)$row['member_valid_from_snapshot'],$row['member_valid_to_snapshot']]);$people[(int)$row['sportovec_id']]=true;}
        }
        if($ownsTransaction)$pdo->commit();return['link_count'=>count($links),'expected_count'=>count($people)];
    }catch(Throwable$exception){if($ownsTransaction&&$pdo->inTransaction())$pdo->rollBack();if($exception instanceof InvalidArgumentException||$exception instanceof TrainingRosterBridgeException)throw$exception;throw new TrainingRosterBridgeException('Očekávání soupisek se nepodařilo přenést bez částečného zápisu.',0,$exception);}
}

/** @return array<string,mixed> */
function trainingRosterBridgePlanAttendanceComparison(PDO $pdo,int $planId):array
{
    if($planId<1)throw new InvalidArgumentException('Neplatný plán tréninku.');
    $statement=$pdo->prepare('SELECT id,nazev,datum,cas_od,cas_do,stav,trenink_id,trener_id FROM planovane_treninky WHERE id=?');$statement->execute([$planId]);$plan=$statement->fetch(PDO::FETCH_ASSOC);
    if(!$plan)throw new TrainingRosterBridgeException('Plánovaný trénink nebyl nalezen.');
    $teams=$pdo->prepare('SELECT team_id,team_code_snapshot,team_name_snapshot,target_date FROM training_roster_links WHERE plan_id=? ORDER BY team_name_snapshot,team_id');$teams->execute([$planId]);$teams=$teams->fetchAll(PDO::FETCH_ASSOC);
    $expected=trainingRosterBridgeExpectedForPlan($pdo,$planId);$expectedById=[];foreach($expected as$row)$expectedById[(int)$row['id']]=$row;
    $actual=[];$trainingId=(int)($plan['trenink_id']??0);
    if($trainingId>0){$attendance=$pdo->prepare("SELECT sp.id,sp.jmeno,sp.prijmeni FROM trenink_sportovec ts JOIN sportovci sp ON sp.id=ts.sportovec_id WHERE ts.trenink_id=? ORDER BY sp.prijmeni,sp.jmeno,sp.id");$attendance->execute([$trainingId]);foreach($attendance->fetchAll(PDO::FETCH_ASSOC)as$row)$actual[(int)$row['id']]=['id'=>(int)$row['id'],'label'=>trim((string)$row['prijmeni'].' '.(string)$row['jmeno'])];}
    $attendedExpected=[];$missing=[];foreach($expectedById as$id=>$row){if(isset($actual[$id]))$attendedExpected[]=$row;else$missing[]=$row;}
    $unexpected=[];foreach($actual as$id=>$row)if(!isset($expectedById[$id]))$unexpected[]=$row;
    return['plan'=>$plan,'teams'=>$teams,'expected'=>array_values($expectedById),'actual'=>array_values($actual),'attended_expected'=>$attendedExpected,'missing'=>$missing,'unexpected'=>$unexpected];
}

/** @param list<int|string> $teamIds @return array{link_count:int,expected_count:int} */
function trainingRosterBridgeReplace(PDO $pdo, string $kind, int $entityId, array $teamIds, int $actorId): array
{
    if (!in_array($kind, ['plan', 'training'], true) || $entityId < 1 || $actorId < 1) {
        throw new InvalidArgumentException('Neplatná vazba tréninku nebo aktér.');
    }
    $ids = array_values(array_unique(array_filter(array_map('intval', $teamIds), static fn(int $id): bool => $id > 0)));
    $ownerColumn = $kind === 'plan' ? 'plan_id' : 'trenink_id';
    $ownerTable = $kind === 'plan' ? 'planovane_treninky' : 'treninky';
    $dateColumn = 'datum';
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();
    try {
        $owner = $pdo->prepare("SELECT {$dateColumn} target_date FROM {$ownerTable} WHERE id=?");
        $owner->execute([$entityId]);
        $date = $owner->fetchColumn();
        if (!is_string($date) || $date === '') throw new TrainingRosterBridgeException('Trénink nebyl nalezen.');
        trainingRosterBridgeAssertDate($date);

        $teams = [];
        if ($ids !== []) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $query = $pdo->prepare(
                "SELECT t.id,t.code,t.name FROM club_teams t WHERE t.id IN ({$placeholders}) AND t.status='active'"
            );
            $query->execute($ids);
            foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $team) $teams[(int)$team['id']] = $team;
            if (count($teams) !== count($ids)) {
                throw new TrainingRosterBridgeException('Některá soupiska neexistuje, není aktivní nebo neplatí v den tréninku.');
            }
        }

        $oldLinks = $pdo->prepare("SELECT id FROM training_roster_links WHERE {$ownerColumn}=?");
        $oldLinks->execute([$entityId]);
        $oldIds = array_map('intval', $oldLinks->fetchAll(PDO::FETCH_COLUMN));
        if ($oldIds !== []) {
            $deleteExpected = $pdo->prepare('DELETE FROM training_roster_expected WHERE link_id IN (' . implode(',', array_fill(0, count($oldIds), '?')) . ')');
            $deleteExpected->execute($oldIds);
        }
        $pdo->prepare("DELETE FROM training_roster_links WHERE {$ownerColumn}=?")->execute([$entityId]);

        $linkInsert = $pdo->prepare(
            'INSERT INTO training_roster_links(plan_id,trenink_id,team_id,target_date,team_code_snapshot,team_name_snapshot,created_by_trainer_id) '
            . 'VALUES(?,?,?,?,?,?,?)'
        );
        $expectedInsert = $pdo->prepare(
            'INSERT INTO training_roster_expected(link_id,sportovec_id,roster_member_id,member_valid_from_snapshot,member_valid_to_snapshot) VALUES(?,?,?,?,?)'
        );
        $expectedPeople = [];
        foreach ($ids as $teamId) {
            $team = $teams[$teamId];
            $linkInsert->execute([
                $kind === 'plan' ? $entityId : null,
                $kind === 'training' ? $entityId : null,
                $teamId,
                $date,
                $team['code'],
                $team['name'],
                $actorId,
            ]);
            $linkId = (int)$pdo->lastInsertId();
            $sourceByPerson = [];
            $descendants = kisRosterDescendantIds($pdo, $teamId, $date);
            $marks = implode(',', array_fill(0, count($descendants), '?'));
            $sourceStmt = $pdo->prepare("SELECT id,sportovec_id,valid_from,valid_to FROM club_roster_members WHERE team_id IN ($marks) AND valid_from<=? AND (valid_to IS NULL OR valid_to>=?) AND status<>'cancelled' AND (status<>'removed' OR valid_to IS NOT NULL) ORDER BY id");
            $sourceStmt->execute([...$descendants, $date, $date]);
            foreach ($sourceStmt->fetchAll(PDO::FETCH_ASSOC) as $source) $sourceByPerson[(int)$source['sportovec_id']] ??= $source;
            foreach (kisRosterEffectiveMemberIds($pdo, $teamId, $date) as $personId) {
                // An expectation is an immutable snapshot; attendance is entered separately.
                // The source interval can be in any descendant and may later be closed.
                $source = $sourceByPerson[(int)$personId] ?? null;
                $expectedInsert->execute([$linkId, $personId, $source['id'] ?? null, $source['valid_from'] ?? $date, $source['valid_to'] ?? null]);
                $expectedPeople[(int)$personId] = true;
            }
        }
        if ($ownsTransaction) $pdo->commit();
        return ['link_count' => count($ids), 'expected_count' => count($expectedPeople)];
    } catch (Throwable $exception) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof InvalidArgumentException || $exception instanceof TrainingRosterBridgeException) throw $exception;
        throw new TrainingRosterBridgeException('Vazby soupisek se nepodařilo uložit bez částečného zápisu.', 0, $exception);
    }
}

function trainingRosterBridgeAssertDate(string $date): void
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) throw new InvalidArgumentException('Neplatné datum tréninku.');
}
