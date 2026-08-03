<?php
declare(strict_types=1);

final class ClubEventRosterTargetException extends RuntimeException
{
}

/** @return list<array<string,mixed>> */
function clubEventRosterTargets(PDO $pdo, int $eventId): array
{
    if ($eventId < 1) {
        return [];
    }
    $statement = $pdo->prepare(
        'SELECT rt.event_id,rt.team_id,rt.actor_trainer_id,rt.decision_note,rt.created_at, '
        . 't.code AS team_code,t.name AS team_name,t.status AS team_status, '
        . 's.name AS season_name,s.starts_on,s.ends_on,s.status AS season_status '
        . 'FROM club_event_roster_targets rt JOIN club_teams t ON t.id=rt.team_id '
        . 'JOIN club_seasons s ON s.id=t.season_id WHERE rt.event_id=? ORDER BY t.name,t.id'
    );
    $statement->execute([$eventId]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

/** @return list<array<string,mixed>> */
function clubEventRosterAvailableTeams(PDO $pdo): array
{
    return $pdo->query(
        "SELECT t.id,t.code,t.name,t.status,s.name AS season_name,s.starts_on,s.ends_on "
        . "FROM club_teams t JOIN club_seasons s ON s.id=t.season_id "
        . "WHERE t.status='active' AND s.status='active' ORDER BY s.starts_on DESC,t.name,t.id"
    )->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Replaces all roster targets atomically. An empty list means an explicitly public event.
 * @param list<int|string> $teamIds
 * @return array{event_id:int,team_ids:list<int>,changed:bool}
 */
function clubEventRosterReplaceTargets(
    PDO $pdo,
    int $eventId,
    array $teamIds,
    int $actorTrainerId,
    string $note,
    bool $confirmed
): array {
    $note = trim($note);
    $normalized = [];
    foreach ($teamIds as $teamId) {
        $teamId = (int)$teamId;
        if ($teamId > 0) {
            $normalized[$teamId] = $teamId;
        }
    }
    $normalized = array_values($normalized);
    sort($normalized, SORT_NUMERIC);
    if ($eventId < 1 || $actorTrainerId < 1 || $note === '' || !$confirmed) {
        throw new InvalidArgumentException('Cílení vyžaduje událost, správce, důvod a výslovné potvrzení.');
    }
    if (mb_strlen($note, 'UTF-8') > 1000) {
        throw new InvalidArgumentException('Důvod cílení smí mít nejvýše 1000 znaků.');
    }

    $pdo->beginTransaction();
    try {
        $event = clubEventLock($pdo, $eventId);
        if (!$event || (string)$event['status'] !== 'draft') {
            throw new ClubEventRosterTargetException('Cílové soupisky lze měnit pouze u události ve stavu draft.');
        }
        if ($normalized !== []) {
            $placeholders = implode(',', array_fill(0, count($normalized), '?'));
            $statement = $pdo->prepare(
                "SELECT id FROM club_teams WHERE status='active' AND id IN ($placeholders) ORDER BY id"
            );
            $statement->execute($normalized);
            $found = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
            if ($found !== $normalized) {
                throw new ClubEventRosterTargetException('Jedna nebo více cílových soupisek nejsou aktivní.');
            }
        }
        $beforeStatement = $pdo->prepare(
            'SELECT team_id FROM club_event_roster_targets WHERE event_id=? ORDER BY team_id'
        );
        $beforeStatement->execute([$eventId]);
        $before = array_map('intval', $beforeStatement->fetchAll(PDO::FETCH_COLUMN));
        if ($before === $normalized) {
            $pdo->commit();
            return ['event_id' => $eventId, 'team_ids' => $normalized, 'changed' => false];
        }
        $pdo->prepare('DELETE FROM club_event_roster_targets WHERE event_id=?')->execute([$eventId]);
        $insert = $pdo->prepare(
            'INSERT INTO club_event_roster_targets '
            . '(event_id,team_id,actor_trainer_id,decision_note) VALUES (?,?,?,?)'
        );
        foreach ($normalized as $teamId) {
            $insert->execute([$eventId, $teamId, $actorTrainerId, $note]);
        }
        clubEventAudit($pdo, $eventId, $actorTrainerId, 'set_roster_targets', 'event', $eventId, $note, [
            'before_team_ids' => $before,
            'after_team_ids' => $normalized,
            'audience_mode' => $normalized === [] ? 'public' : 'roster_targeted',
        ]);
        $pdo->commit();
        return ['event_id' => $eventId, 'team_ids' => $normalized, 'changed' => true];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception instanceof InvalidArgumentException
            || $exception instanceof ClubEventRosterTargetException
        ) {
            throw $exception;
        }
        throw new ClubEventRosterTargetException(
            'Cílové soupisky se nepodařilo uložit bez částečné změny.',
            0,
            $exception
        );
    }
}

/**
 * @return array{mode:string,team_ids:list<int>,reason:string}|false
 */
function clubEventRosterEligibility(PDO $pdo, int $eventId, int $sportovecId): array|false
{
    $targets = $pdo->prepare('SELECT team_id FROM club_event_roster_targets WHERE event_id=? ORDER BY team_id');
    $targets->execute([$eventId]);
    $targetIds = array_map('intval', $targets->fetchAll(PDO::FETCH_COLUMN));
    if ($targetIds === []) {
        return [
            'mode' => 'public',
            'team_ids' => [],
            'reason' => 'Událost byla zveřejněna bez omezení na soupisku.',
        ];
    }
    $session = $pdo->prepare(
        "SELECT starts_at FROM club_event_sessions WHERE event_id=? AND status='scheduled' "
        . 'ORDER BY starts_at,id LIMIT 1'
    );
    $session->execute([$eventId]);
    $startsAt = $session->fetchColumn();
    if (!$startsAt) {
        return false;
    }
    $eventDate = substr((string)$startsAt, 0, 10);
    $placeholders = implode(',', array_fill(0, count($targetIds), '?'));
    $statement = $pdo->prepare(
        'SELECT DISTINCT m.team_id FROM club_roster_members m '
        . 'JOIN club_teams t ON t.id=m.team_id JOIN club_seasons s ON s.id=t.season_id '
        . "WHERE m.sportovec_id=? AND m.team_id IN ($placeholders) "
        . "AND m.status='active' AND t.status='active' AND s.status='active' "
        . 'AND m.valid_from<=? AND (m.valid_to IS NULL OR m.valid_to>=?) '
        . 'AND s.starts_on<=? AND s.ends_on>=? ORDER BY m.team_id'
    );
    $statement->execute([$sportovecId, ...$targetIds, $eventDate, $eventDate, $eventDate, $eventDate]);
    $matching = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    if ($matching === []) {
        return false;
    }
    return [
        'mode' => 'roster_targeted',
        'team_ids' => $matching,
        'reason' => 'Aktivní členství v cílové soupisce k datu první části události ' . $eventDate . '.',
    ];
}

/** @param array{mode:string,team_ids:list<int>,reason:string} $eligibility */
function clubEventRosterEligibilityJson(array $eligibility): ?string
{
    if ($eligibility['mode'] === 'public') {
        return null;
    }
    return json_encode(array_values($eligibility['team_ids']), JSON_THROW_ON_ERROR);
}
