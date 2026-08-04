<?php
declare(strict_types=1);

require_once __DIR__ . '/member_charge_read.php';

final class FamilyPortalAccessDenied extends RuntimeException
{
}

function familyPortalTableExists(PDO $pdo, string $table): bool
{
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $statement = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES '
            . 'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1'
        );
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    if ($driver === 'sqlite') {
        $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1");
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    return false;
}

/**
 * Returns only explicitly approved, currently valid links of an active and
 * verified public account. Duplicate relation rows are collapsed by person.
 *
 * @return list<array<string,mixed>>
 */
function familyPortalAuthorizedPeople(PDO $pdo, int $accountId): array
{
    if ($accountId < 1
        || !familyPortalTableExists($pdo, 'account_person_roles')
        || !familyPortalTableExists($pdo, 'verejni_uzivatele')
        || !familyPortalTableExists($pdo, 'sportovci')
    ) {
        return [];
    }

    $statement = $pdo->prepare(
        'SELECT s.id AS sportovec_id, s.jmeno, s.prijmeni, s.narozeni, '
        . 's.stav_clenstvi, r.relation_role '
        . 'FROM account_person_roles r '
        . 'JOIN verejni_uzivatele vu ON vu.id=r.account_id '
        . 'JOIN sportovci s ON s.id=r.sportovec_id '
        . "WHERE r.account_id=? AND r.status='approved' "
        . 'AND r.valid_from<=CURRENT_TIMESTAMP '
        . 'AND (r.valid_to IS NULL OR r.valid_to>CURRENT_TIMESTAMP) '
        . 'AND vu.aktivni=1 AND vu.email_overeno=1 '
        . "AND r.relation_role IN ('self','guardian') "
        . 'ORDER BY s.prijmeni, s.jmeno, s.id, r.relation_role'
    );
    $statement->execute([$accountId]);

    $people = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $personId = (int)$row['sportovec_id'];
        $role = (string)$row['relation_role'];
        if (!isset($people[$personId])) {
            $row['sportovec_id'] = $personId;
            $row['relation_roles'] = [];
            unset($row['relation_role']);
            $people[$personId] = $row;
        }
        if (!in_array($role, $people[$personId]['relation_roles'], true)) {
            $people[$personId]['relation_roles'][] = $role;
        }
    }

    return array_values($people);
}

function familyPortalCanViewPerson(PDO $pdo, int $accountId, int $sportovecId): bool
{
    if ($sportovecId < 1) {
        return false;
    }
    foreach (familyPortalAuthorizedPeople($pdo, $accountId) as $person) {
        if ((int)$person['sportovec_id'] === $sportovecId) {
            return true;
        }
    }
    return false;
}

/** @return array{person:array<string,mixed>,rosters:list<array<string,mixed>>,events:list<array<string,mixed>>,trainings:list<array<string,mixed>>,member_charges:list<array<string,mixed>>} */
function familyPortalPersonOverview(PDO $pdo, int $accountId, int $sportovecId): array
{
    $person = null;
    foreach (familyPortalAuthorizedPeople($pdo, $accountId) as $candidate) {
        if ((int)$candidate['sportovec_id'] === $sportovecId) {
            $person = $candidate;
            break;
        }
    }
    if ($person === null) {
        throw new FamilyPortalAccessDenied('K tomuto profilu nemáte oprávnění.');
    }

    $rosters = [];
    if (familyPortalHasTables($pdo, ['club_roster_members', 'club_teams', 'club_seasons'])) {
        $statement = $pdo->prepare(
            'SELECT m.id, m.status, m.source, m.valid_from, m.valid_to, '
            . 't.id AS team_id, t.code AS team_code, t.name AS team_name, '
            . 't.discipline, t.age_label, s.code AS season_code, s.name AS season_name, '
            . 's.starts_on AS season_starts_on, s.ends_on AS season_ends_on '
            . 'FROM club_roster_members m '
            . 'JOIN club_teams t ON t.id=m.team_id '
            . 'JOIN club_seasons s ON s.id=t.season_id '
            . 'WHERE m.sportovec_id=? '
            . "ORDER BY CASE m.status WHEN 'active' THEN 0 ELSE 1 END, s.starts_on DESC, t.name, m.id"
        );
        $statement->execute([$sportovecId]);
        $rosters = $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    $events = [];
    if (familyPortalHasTables($pdo, ['club_event_registrations', 'club_events'])) {
        $statement = $pdo->prepare(
            'SELECT r.id, r.status, r.registered_at, r.cancelled_at, '
            . 'e.id AS event_id, e.code AS event_code, e.name AS event_name, '
            . 'e.event_type, e.status AS event_status '
            . 'FROM club_event_registrations r '
            . 'JOIN club_events e ON e.id=r.event_id '
            . 'WHERE r.sportovec_id=? '
            . 'ORDER BY r.registered_at DESC, r.id DESC'
        );
        $statement->execute([$sportovecId]);
        $events = $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    $trainings = [];
    if (familyPortalHasTables($pdo, ['trenink_sportovec', 'treninky'])) {
        $statement = $pdo->prepare(
            'SELECT t.id, t.datum, t.napln, t.delka, t.kategorie '
            . 'FROM trenink_sportovec ts '
            . 'JOIN treninky t ON t.id=ts.trenink_id '
            . 'WHERE ts.sportovec_id=? '
            . 'ORDER BY t.datum DESC, t.id DESC'
        );
        $statement->execute([$sportovecId]);
        $trainings = $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    $memberCharges = memberChargeRowsForSportovec($pdo, $sportovecId);

    return ['person' => $person, 'rosters' => $rosters, 'events' => $events, 'trainings' => $trainings, 'member_charges' => $memberCharges];
}

/** @return list<array{person:array<string,mixed>,rosters:list<array<string,mixed>>,events:list<array<string,mixed>>,trainings:list<array<string,mixed>>,member_charges:list<array<string,mixed>>}> */
function familyPortalOverview(PDO $pdo, int $accountId): array
{
    $overview = [];
    foreach (familyPortalAuthorizedPeople($pdo, $accountId) as $person) {
        $overview[] = familyPortalPersonOverview($pdo, $accountId, (int)$person['sportovec_id']);
    }
    return $overview;
}

/** @param list<string> $tables */
function familyPortalHasTables(PDO $pdo, array $tables): bool
{
    foreach ($tables as $table) {
        if (!familyPortalTableExists($pdo, $table)) {
            return false;
        }
    }
    return true;
}
