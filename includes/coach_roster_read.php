<?php

declare(strict_types=1);

require_once __DIR__ . '/coach_scope.php';
require_once __DIR__ . '/roster_hierarchy.php';

function coachRosterRequireActor(PDO $pdo): int
{
    if (!staffActivePositionIs('coach') || !coachTrainingActorAllowed($pdo)) {
        throw new DomainException('Přístup k trenérským soupiskám je odepřen.');
    }
    return (int)$_SESSION['trener_id'];
}

/** @return list<array<string,mixed>> */
function coachRosterAssignedTeams(PDO $pdo, string $date): array
{
    coachRosterRequireActor($pdo);
    $date = kisRosterDate($date);
    $scope = coachScopeTargetPredicate($pdo, 'team', 't.id');
    $statement = $pdo->prepare(
        'SELECT t.id,t.name,t.team_kind,t.activity_kind,s.name AS season_name,s.starts_on,s.ends_on '
        . 'FROM club_teams t LEFT JOIN club_seasons s ON s.id=t.season_id '
        . "WHERE t.status='active' AND ($scope) "
        . 'AND (s.starts_on IS NULL OR s.starts_on<=?) AND (s.ends_on IS NULL OR s.ends_on>=?) '
        . 'ORDER BY s.starts_on DESC,t.name,t.id'
    );
    $statement->execute([$date, $date]);
    $teams = $statement->fetchAll(PDO::FETCH_ASSOC);
    $count = $pdo->prepare(
        'SELECT COUNT(DISTINCT m.sportovec_id) FROM club_roster_members m '
        . "WHERE m.team_id=? AND m.status='active' AND m.valid_from<=? "
        . 'AND (m.valid_to IS NULL OR m.valid_to>=?)'
    );
    foreach ($teams as &$team) {
        $count->execute([(int)$team['id'], $date, $date]);
        $team['member_count'] = (int)$count->fetchColumn();
        $team['offer'] = coachRosterOfferSummary($pdo, (int)$team['id'], $date);
    }
    unset($team);
    return $teams;
}

/** @return array<string,mixed> */
function coachRosterTeam(PDO $pdo, int $teamId, string $date): array
{
    coachRosterRequireActor($pdo);
    $date = kisRosterDate($date);
    if ($teamId < 1) {
        throw new DomainException('Soupiska není ve vašem pracovním rozsahu.');
    }
    $scope = coachScopeTargetPredicate($pdo, 'team', 't.id');
    $statement = $pdo->prepare(
        'SELECT t.id,t.name,t.team_kind,t.activity_kind,s.name AS season_name,s.starts_on,s.ends_on '
        . 'FROM club_teams t LEFT JOIN club_seasons s ON s.id=t.season_id '
        . "WHERE t.id=? AND t.status='active' AND ($scope) "
        . 'AND (s.starts_on IS NULL OR s.starts_on<=?) AND (s.ends_on IS NULL OR s.ends_on>=?) LIMIT 1'
    );
    $statement->execute([$teamId, $date, $date]);
    $team = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$team) {
        throw new DomainException('Soupiska není ve vašem pracovním rozsahu.');
    }
    return $team;
}

/** @return list<array{id:int,jmeno:string,prijmeni:string}> */
function coachRosterMembers(PDO $pdo, int $teamId, string $date, string $search = ''): array
{
    coachRosterTeam($pdo, $teamId, $date);
    $date = kisRosterDate($date);
    $search = mb_substr(trim($search), 0, 100);
    $sql = 'SELECT DISTINCT p.id,p.jmeno,p.prijmeni FROM club_roster_members m '
        . 'JOIN sportovci p ON p.id=m.sportovec_id '
        . "WHERE m.team_id=? AND m.status='active' AND m.valid_from<=? "
        . 'AND (m.valid_to IS NULL OR m.valid_to>=?)';
    $params = [$teamId, $date, $date];
    if ($search !== '') {
        $fullName = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? "CONCAT(p.jmeno,' ',p.prijmeni)"
            : "(p.jmeno || ' ' || p.prijmeni)";
        $reversedName = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? "CONCAT(p.prijmeni,' ',p.jmeno)"
            : "(p.prijmeni || ' ' || p.jmeno)";
        $sql .= " AND (p.jmeno LIKE ? OR p.prijmeni LIKE ? OR $fullName LIKE ? OR $reversedName LIKE ?)";
        $needle = '%' . $search . '%';
        array_push($params, $needle, $needle, $needle, $needle);
    }
    $sql .= ' ORDER BY p.prijmeni,p.jmeno,p.id';
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array{capacity:?int,available:?int,place_time:list<string>} */
function coachRosterOfferSummary(PDO $pdo, int $teamId, string $date): array
{
    $empty = ['capacity' => null, 'available' => null, 'place_time' => []];
    if (!kisRosterHierarchyHasTable($pdo, 'club_program_offers')
        || !kisRosterHierarchyHasTable($pdo, 'club_program_enrollments')) {
        return $empty;
    }
    require_once __DIR__ . '/club_program.php';
    $linked = kisRosterHierarchyHasTable($pdo, 'club_program_physical_teams');
    $teamCondition = $linked
        ? 'o.team_id=? OR EXISTS(SELECT 1 FROM club_program_physical_teams pt WHERE pt.program_id=o.program_id AND pt.team_id=?)'
        : 'o.team_id=?';
    $statement = $pdo->prepare(
        'SELECT o.* FROM club_program_offers o WHERE (' . $teamCondition . ') '
        . "AND o.status='active' AND o.starts_on<=? AND o.ends_on>=? ORDER BY o.id"
    );
    $statement->execute($linked ? [$teamId, $teamId, $date, $date] : [$teamId, $date, $date]);
    $offers = $statement->fetchAll(PDO::FETCH_ASSOC);
    if ($offers === []) {
        return $empty;
    }
    $capacities = array_values(array_unique(array_map(
        static fn (array $offer): string => $offer['capacity'] === null ? 'none' : (string)$offer['capacity'],
        $offers
    )));
    if (count($capacities) !== 1 || $capacities[0] === 'none') {
        return $empty;
    }
    $available = [];
    foreach ($offers as $offer) {
        $state = clubProgramOfferCapacityStateForTeam($pdo, $offer, $teamId);
        if ($state['available_count'] !== null) {
            $available[] = (int)$state['available_count'];
        }
    }
    $placeTime = [];
    $programIds = array_values(array_unique(array_map(static fn (array $offer): int => (int)$offer['program_id'], $offers)));
    if (count($programIds) === 1 && kisRosterHierarchyHasTable($pdo, 'club_program_schedule_slots')) {
        $slots = $pdo->prepare(
            'SELECT weekday,starts_at,ends_at,location_name FROM club_program_schedule_slots '
            . 'WHERE program_id=? AND (valid_from IS NULL OR valid_from<=?) '
            . 'AND (valid_to IS NULL OR valid_to>=?) ORDER BY weekday,starts_at LIMIT 4'
        );
        $slots->execute([$programIds[0], $date, $date]);
        $days = [1 => 'Po', 2 => 'Út', 3 => 'St', 4 => 'Čt', 5 => 'Pá', 6 => 'So', 7 => 'Ne'];
        foreach ($slots->fetchAll(PDO::FETCH_ASSOC) as $slot) {
            $placeTime[] = ($days[(int)$slot['weekday']] ?? '') . ' '
                . substr((string)$slot['starts_at'], 0, 5) . '–' . substr((string)$slot['ends_at'], 0, 5)
                . ($slot['location_name'] ? ' · ' . $slot['location_name'] : '');
        }
    }
    return [
        'capacity' => (int)$capacities[0],
        'available' => $available === [] ? null : min($available),
        'place_time' => $placeTime,
    ];
}
