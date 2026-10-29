<?php

declare(strict_types=1);

require_once __DIR__ . '/family_portal.php';
require_once __DIR__ . '/child_access.php';
require_once __DIR__ . '/club_makeup.php';
require_once __DIR__ . '/club_finance.php';
require_once __DIR__ . '/roster_hierarchy.php';
require_once __DIR__ . '/participant_write_authorization.php';

final class TrainingRsvpException extends RuntimeException
{
}

/** @param list<string> $tables */
function trainingRsvpHasTables(PDO $pdo, array $tables): bool
{
    foreach ($tables as $table) {
        if (!familyPortalTableExists($pdo, $table)) {
            return false;
        }
    }
    return true;
}

/** @return list<int> */
function trainingRsvpTargetIds(PDO $pdo, int $planId): array
{
    $stmt = $pdo->prepare('SELECT team_id FROM training_roster_links WHERE plan_id=? ORDER BY team_id');
    $stmt->execute([$planId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/** @return list<int> */
function trainingRsvpEligibleMemberIds(PDO $pdo, int $planId, string $date): array
{
    $people = [];
    foreach (trainingRsvpTargetIds($pdo, $planId) as $teamId) {
        foreach (kisRosterEffectiveMemberIds($pdo, $teamId, $date) as $personId) {
            $people[(int)$personId] = true;
        }
    }
    $ids = array_keys($people);
    sort($ids, SORT_NUMERIC);
    return $ids;
}

/** @param list<int> $personIds @return list<array<string,mixed>> */
function trainingRsvpUpcomingForPeople(PDO $pdo, array $personIds, string $from, string $to): array
{
    $start = DateTimeImmutable::createFromFormat('!Y-m-d', $from);
    $end = DateTimeImmutable::createFromFormat('!Y-m-d', $to);
    $personIds = array_values(array_unique(array_filter(array_map('intval', $personIds), static fn (int $id): bool => $id > 0)));
    if (!$start || !$end || $start->format('Y-m-d') !== $from || $end->format('Y-m-d') !== $to || $end < $start) {
        throw new InvalidArgumentException('Neplatné období potvrzení tréninků.');
    }
    if ($personIds === [] || !trainingRsvpHasTables($pdo, ['training_rsvps','training_roster_links','club_roster_members','planovane_treninky','sportovci'])) {
        return [];
    }
    $statement = $pdo->prepare(
        'SELECT p.id AS plan_id,p.nazev,p.datum,p.cas_od,p.cas_do,p.popis,p.stav,'
        . 'l.team_id,l.team_name_snapshot FROM training_roster_links l '
        . 'JOIN planovane_treninky p ON p.id=l.plan_id '
        . "WHERE p.stav='planovany' AND p.datum BETWEEN ? AND ? "
        . 'ORDER BY p.datum,p.cas_od,p.id,l.team_id'
    );
    $statement->execute([$from, $to]);
    $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Prague'));
    $plans = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $id = (int)$row['plan_id'];
        $plans[$id] ??= ['plan' => $row, 'targets' => [], 'names' => []];
        $plans[$id]['targets'][] = (int)$row['team_id'];
        $plans[$id]['names'][] = (string)$row['team_name_snapshot'];
    }
    if ($plans === []) {
        return [];
    }
    $personMarks = implode(',', array_fill(0, count($personIds), '?'));
    $personStmt = $pdo->prepare("SELECT id,jmeno,prijmeni FROM sportovci WHERE id IN ($personMarks)");
    $personStmt->execute($personIds);
    $people = [];
    foreach ($personStmt->fetchAll(PDO::FETCH_ASSOC) as $person) {
        $people[(int)$person['id']] = $person;
    }
    $planIds = array_keys($plans);
    $planMarks = implode(',', array_fill(0, count($planIds), '?'));
    $rsvpStmt = $pdo->prepare("SELECT id,plan_id,sportovec_id,response,member_note,responded_at FROM training_rsvps WHERE plan_id IN ($planMarks) AND sportovec_id IN ($personMarks)");
    $rsvpStmt->execute([...$planIds, ...$personIds]);
    $rsvps = [];
    foreach ($rsvpStmt->fetchAll(PDO::FETCH_ASSOC) as $rsvp) {
        $rsvps[(int)$rsvp['plan_id']][(int)$rsvp['sportovec_id']] = $rsvp;
    }
    $result = [];
    foreach ($plans as $planId => $entry) {
        $plan = $entry['plan'];
        $trainingStart = new DateTimeImmutable(
            (string)$plan['datum'] . ' ' . ((string)($plan['cas_od'] ?? '') !== '' ? (string)$plan['cas_od'] : '23:59:59'),
            new DateTimeZone('Europe/Prague')
        );
        if ($trainingStart <= $now) {
            continue;
        }
        foreach ($people as $personId => $person) {
            if (kisRosterPersonTargetIds($pdo, $personId, $entry['targets'], (string)$plan['datum']) === []) {
                continue;
            }
            $rsvp = $rsvps[$planId][$personId] ?? null;
            $result[] = [
                'plan_id' => $planId,'nazev' => $plan['nazev'],'datum' => $plan['datum'],
                'cas_od' => $plan['cas_od'],'cas_do' => $plan['cas_do'],'popis' => $plan['popis'],'stav' => $plan['stav'],
                'sportovec_id' => $personId,'jmeno' => $person['jmeno'],'prijmeni' => $person['prijmeni'],
                'team_name_snapshot' => min($entry['names']),
                'rsvp_id' => $rsvp['id'] ?? null,'response' => $rsvp['response'] ?? null,
                'member_note' => $rsvp['member_note'] ?? null,'responded_at' => $rsvp['responded_at'] ?? null,
            ];
        }
    }
    usort($result, static fn (array $a, array $b): int => [$a['datum'],$a['cas_od'],$a['plan_id'],$a['prijmeni'],$a['jmeno']] <=> [$b['datum'],$b['cas_od'],$b['plan_id'],$b['prijmeni'],$b['jmeno']]);
    return $result;
}

/** @return list<array<string,mixed>> */
function trainingRsvpUpcomingForAccount(PDO $pdo, int $accountId, string $from, string $to): array
{
    $ids = array_map(static fn (array $person): int => (int)$person['sportovec_id'], familyPortalAuthorizedPeople($pdo, $accountId));
    return trainingRsvpUpcomingForPeople($pdo, $ids, $from, $to);
}

/** @return list<array<string,mixed>> */
function trainingRsvpUpcomingForChild(PDO $pdo, int $accessAccountId, string $from, string $to): array
{
    $identity = childAccessIdentity($pdo, $accessAccountId);
    if ($identity === null) {
        throw new TrainingRsvpException('Přístup sportovce není aktivní.');
    }
    return trainingRsvpUpcomingForPeople($pdo, [(int)$identity['sportovec_id']], $from, $to);
}

function trainingRsvpCalendarMonth(string $value): DateTimeImmutable
{
    $zone = new DateTimeZone('Europe/Prague');
    $current = new DateTimeImmutable('first day of this month 00:00:00', $zone);
    if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $value) !== 1) {
        return $current;
    }
    $month = DateTimeImmutable::createFromFormat('!Y-m-d', $value . '-01', $zone);
    if (!$month) {
        return $current;
    }
    if ($month < $current) {
        return $current;
    }
    $last = $current->modify('+12 months');
    return $month > $last ? $last : $month;
}

/** @return array{from:string,to:string} */
function trainingRsvpCalendarRange(DateTimeImmutable $month): array
{
    $today = new DateTimeImmutable('today', new DateTimeZone('Europe/Prague'));
    $from = $month < $today ? $today : $month;
    return ['from' => $from->format('Y-m-d'), 'to' => $month->modify('last day of this month')->format('Y-m-d')];
}

/** @return array{id:int,changed:bool,response:string,makeup_eligible:bool|null,makeup_reason:string|null} */
function trainingRsvpSave(PDO $pdo, int $planId, int $sportovecId, string $response, string $actorType, int $actorId, string $note = ''): array
{
    if ($planId < 1 || $sportovecId < 1 || $actorId < 1 || !in_array($response, ['going','not_going'], true) || !in_array($actorType, ['account','athlete'], true)) {
        throw new InvalidArgumentException('Neplatná odpověď k tréninku.');
    }
    if (!trainingRsvpHasTables($pdo, ['training_rsvps','training_rsvp_events'])) {
        throw new TrainingRsvpException('Potvrzování tréninků zatím není dostupné.');
    }
    $note = trim($note);
    if (mb_strlen($note, 'UTF-8') > 500) {
        throw new InvalidArgumentException('Poznámka pro trenéra může mít nejvýše 500 znaků.');
    }
    if ($actorType === 'account') {
        if (!familyPortalCanViewPerson($pdo, $actorId, $sportovecId)) {
            throw new TrainingRsvpException('K tomuto sportovnímu profilu nemáte oprávnění.');
        }
        $accountId = $actorId;
        $accessAccountId = null;
    } else {
        $identity = childAccessIdentity($pdo, $actorId);
        if ($identity === null || (int)$identity['sportovec_id'] !== $sportovecId) {
            throw new TrainingRsvpException('K tomuto sportovnímu profilu nemáte oprávnění.');
        }
        $accountId = null;
        $accessAccountId = $actorId;
    }
    $pdo->beginTransaction();
    try {
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            // A single plan-row lock serializes a guardian and athlete answering at the same moment.
            $lock = $pdo->prepare('SELECT id FROM planovane_treninky WHERE id=? FOR UPDATE');
            $lock->execute([$planId]);
        }
        // Validate after the plan-row lock: a concurrent reschedule or roster
        // edit must not allow an answer to the former occurrence or team.
        $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Prague'));
        $eligible = $pdo->prepare("SELECT id,datum,cas_od FROM planovane_treninky WHERE id=? AND stav='planovany' AND datum>=?");
        // Database CURRENT_DATE may be UTC while the club uses Prague time.
        $eligible->execute([$planId, $now->format('Y-m-d')]);
        $eligibleTraining = $eligible->fetch(PDO::FETCH_ASSOC);
        if (!$eligibleTraining || kisRosterPersonTargetIds($pdo, $sportovecId, trainingRsvpTargetIds($pdo, $planId), (string)($eligibleTraining['datum'] ?? $now->format('Y-m-d'))) === []) {
            throw new TrainingRsvpException('Trénink už není dostupný nebo sportovec není v jeho soupisce.');
        }
        $trainingStart = new DateTimeImmutable(
            (string)$eligibleTraining['datum'] . ' ' . ((string)($eligibleTraining['cas_od'] ?? '') !== '' ? (string)$eligibleTraining['cas_od'] : '23:59:59'),
            new DateTimeZone('Europe/Prague')
        );
        if ($trainingStart <= $now) {
            throw new TrainingRsvpException('Odpověď už nelze změnit, protože trénink začal nebo proběhl.');
        }
        $selectSql = 'SELECT * FROM training_rsvps WHERE plan_id=? AND sportovec_id=?';
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $selectSql .= ' FOR UPDATE';
        }
        $select = $pdo->prepare($selectSql);
        $select->execute([$planId, $sportovecId]);
        $before = $select->fetch(PDO::FETCH_ASSOC);
        $from = $before ? (string)$before['response'] : null;
        if ($response === 'going' && $from !== 'going') {
            try {
                clubFinanceAssertCanRegister($pdo, $sportovecId);
            } catch (ClubFinanceException $exception) {
                throw new TrainingRsvpException($exception->getMessage(), 0, $exception);
            }
        }
        if (!participantWriteCanActFor($pdo, $actorType, $actorId, $sportovecId)) {
            throw new TrainingRsvpException('K tomuto sportovnímu profilu nemáte oprávnění.');
        }
        $changed = $from !== $response || ($before && (string)$before['member_note'] !== $note);
        if ($before) {
            if ($changed) {
                $pdo->prepare('UPDATE training_rsvps SET '
                    . 'response=?,member_note=?,account_id=?,access_account_id=?,responded_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP '
                    . 'WHERE id=?')
                    ->execute([$response, $note, $accountId, $accessAccountId, (int)$before['id']]);
            }
            $rsvpId = (int)$before['id'];
        } else {
            $pdo->prepare('INSERT INTO training_rsvps(plan_id,sportovec_id,account_id,access_account_id,response,member_note) VALUES(?,?,?,?,?,?)')
                ->execute([$planId, $sportovecId, $accountId, $accessAccountId, $response, $note]);
            $rsvpId = (int)$pdo->lastInsertId();
        }
        if ($from !== $response && clubMakeupReady($pdo)) {
            $entitlement = $response === 'not_going' ? clubMakeupExcuseEntitlement($pdo, $planId, $sportovecId, $now) : null;
            $denial = $response === 'not_going' && !$entitlement ? clubMakeupExcuseFailureReason($pdo, $planId, $sportovecId, $now) : null;
            $pdo->prepare('UPDATE training_rsvps SET makeup_excused_at=?,makeup_source_enrollment_id=?,makeup_cutoff_hours=?,makeup_denial_reason=? WHERE id=?')
                ->execute([$entitlement ? $now->format('Y-m-d H:i:s') : null,$entitlement['enrollment_id'] ?? null,$entitlement['hours'] ?? null,$denial,$rsvpId]);
        }
        if ($changed) {
            $pdo->prepare('INSERT INTO training_rsvp_events(rsvp_id,actor_type,actor_id,from_response,to_response,note) VALUES(?,?,?,?,?,?)')
                ->execute([$rsvpId, $actorType, $actorId, $from, $response, 'Změna odpovědi nebo poznámky k plánovanému tréninku.']);
        }
        $makeupEligible = null;
        $makeupReason = null;
        if ($response === 'not_going' && clubMakeupReady($pdo)) {
            $status = $pdo->prepare('SELECT makeup_excused_at,makeup_denial_reason FROM training_rsvps WHERE id=?');
            $status->execute([$rsvpId]);
            $makeupStatus = $status->fetch(PDO::FETCH_ASSOC);
            $makeupEligible = $makeupStatus['makeup_excused_at'] !== null;
            if (!$makeupEligible) {
                $makeupReason = (string)($makeupStatus['makeup_denial_reason'] ?: 'unavailable');
            }
        }
        $pdo->commit();
        return ['id' => $rsvpId, 'changed' => $changed, 'response' => $response, 'makeup_eligible' => $makeupEligible, 'makeup_reason' => $makeupReason];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception instanceof InvalidArgumentException || $exception instanceof TrainingRsvpException) {
            throw $exception;
        }
        throw new TrainingRsvpException('Odpověď k tréninku se nepodařilo bezpečně uložit.', 0, $exception);
    }
}

/** @param array<string,mixed> $saved */
function trainingRsvpSavedMessage(array $saved): string
{
    if (($saved['response'] ?? '') !== 'not_going') {
        return !empty($saved['changed']) ? 'Účast a zpráva pro trenéra byly uloženy.' : 'Účast a zpráva pro trenéra už byly uložené.';
    }
    if (($saved['makeup_eligible'] ?? null) === true) {
        return 'Omluva byla uložena. Nárok na náhradní lekci vznikne až po ověření skutečné docházky trenérem.';
    }
    if (($saved['makeup_eligible'] ?? null) === null) {
        return 'Omluva byla uložena. Náhradní lekce zatím nejsou dostupné.';
    }
    return 'Omluva byla uložena bez nároku na náhradní lekci. ' . clubMakeupExcuseFailureMessage((string)($saved['makeup_reason'] ?? 'unavailable'));
}

/**
 * Returns one currently eligible roster member per athlete, enriched with the latest answer.
 *
 * @return list<array<string,mixed>>
 */
function trainingRsvpPlanOverview(PDO $pdo, int $planId): array
{
    if ($planId < 1) {
        throw new InvalidArgumentException('Neplatný plán tréninku.');
    }
    if (!trainingRsvpHasTables($pdo, ['training_rsvps','training_roster_links','club_roster_members','planovane_treninky','sportovci'])) {
        return [];
    }
    $planStmt = $pdo->prepare('SELECT datum FROM planovane_treninky WHERE id=?');
    $planStmt->execute([$planId]);
    $date = $planStmt->fetchColumn();
    if (!is_string($date)) {
        return [];
    }
    $ids = trainingRsvpEligibleMemberIds($pdo, $planId, $date);
    if ($ids === []) {
        return [];
    }
    $nameStmt = $pdo->prepare('SELECT MIN(team_name_snapshot) FROM training_roster_links WHERE plan_id=?');
    $nameStmt->execute([$planId]);
    $name = (string)$nameStmt->fetchColumn();
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $statement = $pdo->prepare(
        'SELECT sp.id AS sportovec_id,sp.jmeno,sp.prijmeni,r.response,r.member_note,r.responded_at '
        . 'FROM sportovci sp LEFT JOIN training_rsvps r ON r.plan_id=? AND r.sportovec_id=sp.id '
        . "WHERE sp.id IN ($marks) ORDER BY CASE r.response WHEN 'going' THEN 0 WHEN 'not_going' THEN 2 ELSE 1 END,sp.prijmeni,sp.jmeno,sp.id"
    );
    $statement->execute([$planId, ...$ids]);
    return array_map(static function (array $row) use ($name): array {
        $row['team_name'] = $name;
        return $row;
    }, $statement->fetchAll(PDO::FETCH_ASSOC));
}

/** @param list<int> $planIds @return array<int,array{expected:int,going:int,not_going:int,pending:int}> */
function trainingRsvpPlanSummaries(PDO $pdo, array $planIds): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $planIds), static fn (int $id): bool => $id > 0)));
    if ($ids === [] || !trainingRsvpHasTables($pdo, ['training_rsvps','training_roster_links','club_roster_members','planovane_treninky'])) {
        return [];
    }
    $result = [];
    foreach ($ids as $planId) {
        $rows = trainingRsvpPlanOverview($pdo, $planId);
        $expected = count($rows);
        $going = count(array_filter($rows, static fn (array $row): bool => $row['response'] === 'going'));
        $notGoing = count(array_filter($rows, static fn (array $row): bool => $row['response'] === 'not_going'));
        $result[$planId] = [
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
