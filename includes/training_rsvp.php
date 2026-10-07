<?php
declare(strict_types=1);

require_once __DIR__ . '/family_portal.php';
require_once __DIR__ . '/child_access.php';

final class TrainingRsvpException extends RuntimeException {}

/** @param list<string> $tables */
function trainingRsvpHasTables(PDO $pdo, array $tables): bool
{
    foreach ($tables as $table) if (!familyPortalTableExists($pdo, $table)) return false;
    return true;
}

/** @param list<int> $personIds @return list<array<string,mixed>> */
function trainingRsvpUpcomingForPeople(PDO $pdo, array $personIds, string $from, string $to): array
{
    $start = DateTimeImmutable::createFromFormat('!Y-m-d', $from);
    $end = DateTimeImmutable::createFromFormat('!Y-m-d', $to);
    $personIds = array_values(array_unique(array_filter(array_map('intval', $personIds), static fn(int $id): bool => $id > 0)));
    if (!$start || !$end || $start->format('Y-m-d') !== $from || $end->format('Y-m-d') !== $to || $end < $start) {
        throw new InvalidArgumentException('Neplatné období potvrzení tréninků.');
    }
    if ($personIds === [] || !trainingRsvpHasTables($pdo, ['training_rsvps','training_roster_links','club_roster_members','planovane_treninky','sportovci'])) return [];
    $placeholders = implode(',', array_fill(0, count($personIds), '?'));
    $statement = $pdo->prepare(
        'SELECT p.id AS plan_id,p.nazev,p.datum,p.cas_od,p.cas_do,p.popis,p.stav,'
        . 'rm.sportovec_id,sp.jmeno,sp.prijmeni,MIN(l.team_name_snapshot) AS team_name_snapshot,'
        . 'r.id AS rsvp_id,r.response,r.responded_at '
        . 'FROM training_roster_links l JOIN planovane_treninky p ON p.id=l.plan_id '
        . 'JOIN club_roster_members rm ON rm.team_id=l.team_id JOIN sportovci sp ON sp.id=rm.sportovec_id '
        . 'LEFT JOIN training_rsvps r ON r.plan_id=p.id AND r.sportovec_id=rm.sportovec_id '
        . 'WHERE rm.sportovec_id IN (' . $placeholders . ") AND p.stav='planovany' "
        . "AND rm.status='active' AND rm.valid_from<=p.datum AND (rm.valid_to IS NULL OR rm.valid_to>=p.datum) "
        . 'AND p.datum BETWEEN ? AND ? '
        . 'GROUP BY p.id,p.nazev,p.datum,p.cas_od,p.cas_do,p.popis,p.stav,'
        . 'rm.sportovec_id,sp.jmeno,sp.prijmeni,r.id,r.response,r.responded_at '
        . 'ORDER BY p.datum,p.cas_od,p.id,sp.prijmeni,sp.jmeno'
    );
    $statement->execute([...$personIds, $from, $to]);
    $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Prague'));
    return array_values(array_filter(
        $statement->fetchAll(PDO::FETCH_ASSOC),
        static function (array $row) use ($now): bool {
            $start = new DateTimeImmutable(
                (string)$row['datum'] . ' ' . ((string)($row['cas_od'] ?? '') !== '' ? (string)$row['cas_od'] : '23:59:59'),
                new DateTimeZone('Europe/Prague')
            );
            return $start > $now;
        }
    ));
}

/** @return list<array<string,mixed>> */
function trainingRsvpUpcomingForAccount(PDO $pdo, int $accountId, string $from, string $to): array
{
    $ids = array_map(static fn(array $person): int => (int)$person['sportovec_id'], familyPortalAuthorizedPeople($pdo, $accountId));
    return trainingRsvpUpcomingForPeople($pdo, $ids, $from, $to);
}

/** @return list<array<string,mixed>> */
function trainingRsvpUpcomingForChild(PDO $pdo, int $accessAccountId, string $from, string $to): array
{
    $identity = childAccessIdentity($pdo, $accessAccountId);
    if ($identity === null) throw new TrainingRsvpException('Přístup sportovce není aktivní.');
    return trainingRsvpUpcomingForPeople($pdo, [(int)$identity['sportovec_id']], $from, $to);
}

/** @return array{id:int,changed:bool,response:string} */
function trainingRsvpSave(PDO $pdo, int $planId, int $sportovecId, string $response, string $actorType, int $actorId): array
{
    if ($planId < 1 || $sportovecId < 1 || $actorId < 1 || !in_array($response, ['going','not_going'], true) || !in_array($actorType, ['account','athlete'], true)) {
        throw new InvalidArgumentException('Neplatná odpověď k tréninku.');
    }
    if (!trainingRsvpHasTables($pdo, ['training_rsvps','training_rsvp_events'])) throw new TrainingRsvpException('Potvrzování tréninků zatím není dostupné.');
    if ($actorType === 'account') {
        if (!familyPortalCanViewPerson($pdo, $actorId, $sportovecId)) throw new TrainingRsvpException('K tomuto sportovnímu profilu nemáte oprávnění.');
        $accountId = $actorId;
        $accessAccountId = null;
    } else {
        $identity = childAccessIdentity($pdo, $actorId);
        if ($identity === null || (int)$identity['sportovec_id'] !== $sportovecId) throw new TrainingRsvpException('K tomuto sportovnímu profilu nemáte oprávnění.');
        $accountId = null;
        $accessAccountId = $actorId;
    }
    $eligible = $pdo->prepare(
        'SELECT p.id,p.datum,p.cas_od FROM planovane_treninky p JOIN training_roster_links l ON l.plan_id=p.id '
        . 'JOIN club_roster_members rm ON rm.team_id=l.team_id '
        . "WHERE p.id=? AND rm.sportovec_id=? AND p.stav='planovany' AND p.datum>=CURRENT_DATE "
        . "AND rm.status='active' AND rm.valid_from<=p.datum AND (rm.valid_to IS NULL OR rm.valid_to>=p.datum) LIMIT 1"
    );
    $eligible->execute([$planId, $sportovecId]);
    $eligibleTraining = $eligible->fetch(PDO::FETCH_ASSOC);
    if (!$eligibleTraining) throw new TrainingRsvpException('Trénink už není dostupný nebo sportovec není v jeho soupisce.');
    $trainingStart = new DateTimeImmutable(
        (string)$eligibleTraining['datum'] . ' ' . ((string)($eligibleTraining['cas_od'] ?? '') !== '' ? (string)$eligibleTraining['cas_od'] : '23:59:59'),
        new DateTimeZone('Europe/Prague')
    );
    if ($trainingStart <= new DateTimeImmutable('now', new DateTimeZone('Europe/Prague'))) {
        throw new TrainingRsvpException('Odpověď už nelze změnit, protože trénink začal nebo proběhl.');
    }

    $pdo->beginTransaction();
    try {
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            // A single plan-row lock serializes a guardian and athlete answering at the same moment.
            $lock = $pdo->prepare('SELECT id FROM planovane_treninky WHERE id=? FOR UPDATE');
            $lock->execute([$planId]);
        }
        $selectSql = 'SELECT * FROM training_rsvps WHERE plan_id=? AND sportovec_id=?';
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $selectSql .= ' FOR UPDATE';
        $select = $pdo->prepare($selectSql);
        $select->execute([$planId, $sportovecId]);
        $before = $select->fetch(PDO::FETCH_ASSOC);
        $from = $before ? (string)$before['response'] : null;
        if ($before) {
            $pdo->prepare('UPDATE training_rsvps SET response=?,account_id=?,access_account_id=?,responded_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE id=?')
                ->execute([$response, $accountId, $accessAccountId, (int)$before['id']]);
            $rsvpId = (int)$before['id'];
        } else {
            $pdo->prepare('INSERT INTO training_rsvps(plan_id,sportovec_id,account_id,access_account_id,response) VALUES(?,?,?,?,?)')
                ->execute([$planId, $sportovecId, $accountId, $accessAccountId, $response]);
            $rsvpId = (int)$pdo->lastInsertId();
        }
        $changed = $from !== $response;
        if ($changed) {
            $pdo->prepare('INSERT INTO training_rsvp_events(rsvp_id,actor_type,actor_id,from_response,to_response,note) VALUES(?,?,?,?,?,?)')
                ->execute([$rsvpId, $actorType, $actorId, $from, $response, 'Potvrzení účasti na plánovaném tréninku.']);
        }
        $pdo->commit();
        return ['id' => $rsvpId, 'changed' => $changed, 'response' => $response];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof InvalidArgumentException || $exception instanceof TrainingRsvpException) throw $exception;
        throw new TrainingRsvpException('Odpověď k tréninku se nepodařilo bezpečně uložit.', 0, $exception);
    }
}

/**
 * Returns one currently eligible roster member per athlete, enriched with the latest answer.
 *
 * @return list<array<string,mixed>>
 */
function trainingRsvpPlanOverview(PDO $pdo, int $planId): array
{
    if ($planId < 1) throw new InvalidArgumentException('Neplatný plán tréninku.');
    if (!trainingRsvpHasTables($pdo, ['training_rsvps','training_roster_links','club_roster_members','planovane_treninky','sportovci'])) return [];
    $statement = $pdo->prepare(
        'SELECT rm.sportovec_id,sp.jmeno,sp.prijmeni,'
        . 'MIN(l.team_name_snapshot) AS team_name,r.response,r.responded_at '
        . 'FROM training_roster_links l JOIN planovane_treninky p ON p.id=l.plan_id '
        . 'JOIN club_roster_members rm ON rm.team_id=l.team_id JOIN sportovci sp ON sp.id=rm.sportovec_id '
        . 'LEFT JOIN training_rsvps r ON r.plan_id=l.plan_id AND r.sportovec_id=rm.sportovec_id '
        . "WHERE l.plan_id=? AND rm.status='active' AND rm.valid_from<=p.datum "
        . 'AND (rm.valid_to IS NULL OR rm.valid_to>=p.datum) '
        . 'GROUP BY rm.sportovec_id,sp.jmeno,sp.prijmeni,r.response,r.responded_at '
        . 'ORDER BY sp.prijmeni,sp.jmeno,rm.sportovec_id'
    );
    $statement->execute([$planId]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

/** @param list<int> $planIds @return array<int,array{expected:int,going:int,not_going:int,pending:int}> */
function trainingRsvpPlanSummaries(PDO $pdo, array $planIds): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $planIds), static fn(int $id): bool => $id > 0)));
    if ($ids === [] || !trainingRsvpHasTables($pdo, ['training_rsvps','training_roster_links','club_roster_members','planovane_treninky'])) return [];
    $statement = $pdo->prepare(
        'SELECT l.plan_id,COUNT(DISTINCT rm.sportovec_id) AS expected_count,'
        . "COUNT(DISTINCT CASE WHEN r.response='going' THEN rm.sportovec_id END) AS going_count,"
        . "COUNT(DISTINCT CASE WHEN r.response='not_going' THEN rm.sportovec_id END) AS not_going_count "
        . 'FROM training_roster_links l JOIN planovane_treninky p ON p.id=l.plan_id '
        . 'JOIN club_roster_members rm ON rm.team_id=l.team_id '
        . 'LEFT JOIN training_rsvps r ON r.plan_id=l.plan_id AND r.sportovec_id=rm.sportovec_id '
        . 'WHERE l.plan_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ") AND rm.status='active' "
        . 'AND rm.valid_from<=p.datum AND (rm.valid_to IS NULL OR rm.valid_to>=p.datum) GROUP BY l.plan_id'
    );
    $statement->execute($ids);
    $result = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $expected = (int)$row['expected_count'];
        $going = (int)$row['going_count'];
        $notGoing = (int)$row['not_going_count'];
        $result[(int)$row['plan_id']] = [
            'expected' => $expected,
            'going' => $going,
            'not_going' => $notGoing,
            'pending' => max(0, $expected - $going - $notGoing),
        ];
    }
    return $result;
}

function trainingRsvpLabel(?string $response): string
{
    return match ($response) {
        'going' => 'Zúčastním se',
        'not_going' => 'Nezúčastním se',
        default => 'Bez odpovědi',
    };
}
