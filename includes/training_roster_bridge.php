<?php
declare(strict_types=1);

final class TrainingRosterBridgeException extends RuntimeException {}

/** @return list<array<string,mixed>> */
function trainingRosterBridgeEligibleTeams(PDO $pdo, string $date): array
{
    trainingRosterBridgeAssertDate($date);
    $statement = $pdo->prepare(
        "SELECT t.id,t.code,t.name,t.discipline,t.age_label,s.name season_name "
        . "FROM club_teams t JOIN club_seasons s ON s.id=t.season_id "
        . "WHERE t.status='active' AND s.status='active' AND s.starts_on<=? AND s.ends_on>=? "
        . 'ORDER BY t.name,t.id'
    );
    $statement->execute([$date, $date]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

/** @return list<int> */
function trainingRosterBridgePlanTeamIds(PDO $pdo, int $planId): array
{
    $statement = $pdo->prepare('SELECT team_id FROM training_roster_links WHERE plan_id=? ORDER BY team_id');
    $statement->execute([$planId]);
    return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
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
                "SELECT t.id,t.code,t.name FROM club_teams t JOIN club_seasons s ON s.id=t.season_id "
                . "WHERE t.id IN ({$placeholders}) AND t.status='active' AND s.status='active' AND s.starts_on<=? AND s.ends_on>=?"
            );
            $query->execute([...$ids, $date, $date]);
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
        $members = $pdo->prepare(
            "SELECT id,sportovec_id,valid_from,valid_to FROM club_roster_members "
            . "WHERE team_id=? AND status='active' AND valid_from<=? AND (valid_to IS NULL OR valid_to>=?) ORDER BY id"
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
            $members->execute([$teamId, $date, $date]);
            foreach ($members->fetchAll(PDO::FETCH_ASSOC) as $member) {
                $expectedInsert->execute([$linkId, $member['sportovec_id'], $member['id'], $member['valid_from'], $member['valid_to']]);
                $expectedPeople[(int)$member['sportovec_id']] = true;
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
