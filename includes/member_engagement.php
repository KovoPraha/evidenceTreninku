<?php

declare(strict_types=1);

const MEMBER_ENGAGEMENT_LEVELS = [
    'public' => ['label' => 'Veřejnost / zákazník', 'badge' => 'info'],
    'circle' => ['label' => 'Kroužek / kurz', 'badge' => 'warning'],
    'competitive' => ['label' => 'Závodní oddíl', 'badge' => 'danger'],
];

/** Classify and filter the complete directory before reading one bounded page. */
function memberEngagementDirectory(PDO $pdo, string $query, string $levelFilter, int $page = 1, ?string $onDate = null): array
{
    require_once __DIR__ . '/staff_record_list.php';
    $onDate ??= (new DateTimeImmutable('today'))->format('Y-m-d');
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $onDate);
    if (!$date || $date->format('Y-m-d') !== $onDate) {
        throw new InvalidArgumentException('Datum přehledu není platné.');
    }
    $membership = "m.sportovec_id=s.id AND m.status<>'cancelled' AND (m.status<>'removed' OR m.valid_to IS NOT NULL) "
        . 'AND m.valid_from<=? AND (m.valid_to IS NULL OR m.valid_to>=?)';
    $classification = "CASE WHEN EXISTS(SELECT 1 FROM club_roster_members m JOIN club_teams t ON t.id=m.team_id WHERE $membership AND t.activity_kind='competitive') THEN 'competitive' "
        . "WHEN EXISTS(SELECT 1 FROM club_roster_members m JOIN club_teams t ON t.id=m.team_id WHERE $membership AND t.activity_kind='circle') "
        . 'OR EXISTS(SELECT 1 FROM club_program_enrollments e JOIN club_program_offers o ON o.id=e.offer_id '
        . 'JOIN club_programs p ON p.id=o.program_id WHERE e.sportovec_id=s.id '
        . "AND e.status='active' AND e.valid_from<=? AND e.valid_to>=?) THEN 'circle' ELSE 'public' END";
    $params = array_fill(0, 6, $onDate);
    $query = mb_substr(trim($query), 0, 100);
    $where = "s.stav_clenstvi<>'archiv'";
    if ($query !== '') {
        $where .= ' AND (s.jmeno LIKE ? OR s.prijmeni LIKE ? OR s.email LIKE ?)';
        array_push($params, ...array_fill(0, 3, '%' . $query . '%'));
    }
    $from = 'FROM (SELECT s.id,s.jmeno,s.prijmeni,s.narozeni,s.email,s.stav_clenstvi,'
        . $classification . " AS engagement_key FROM sportovci s WHERE $where) directory WHERE 1=1";
    if (isset(MEMBER_ENGAGEMENT_LEVELS[$levelFilter])) {
        $from .= ' AND engagement_key=?';
        $params[] = $levelFilter;
    }
    $counts = array_fill_keys(array_keys(MEMBER_ENGAGEMENT_LEVELS), 0);
    $q = $pdo->prepare('SELECT engagement_key,COUNT(*) AS total ' . $from . ' GROUP BY engagement_key');
    $q->execute($params);
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $counts[$row['engagement_key']] = (int)$row['total'];
    }
    $result = staffListPage($pdo, 'id,jmeno,prijmeni,narozeni,email,stav_clenstvi', $from, 'prijmeni,jmeno,id', $params, $page);
    return $result + ['counts' => $counts, 'levels' => memberEngagementMap($pdo, array_map('intval', array_column($result['rows'], 'id')), $onDate)];
}

/**
 * Vrstva je vždy odvozena z platných účastí a soupisek. Nesmí se ručně ukládat
 * na osobu, jinak by po ukončení programu vznikla druhá, rozporná evidence.
 *
 * @param list<int> $personIds
 * @return array<int,array{key:string,label:string,badge:string,reasons:list<string>}>
 */
function memberEngagementMap(PDO $pdo, array $personIds, ?string $onDate = null): array
{
    $personIds = array_values(array_unique(array_filter(array_map('intval', $personIds), static fn (int $id): bool
    => $id > 0)));
    if ($personIds === []) {
        return [];
    }
    $onDate ??= (new DateTimeImmutable('today'))->format('Y-m-d');
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $onDate);
    if (!$date || $date->format('Y-m-d') !== $onDate) {
        throw new InvalidArgumentException('Datum přehledu není platné.');
    }

    $result = [];
    foreach ($personIds as $personId) {
        $result[$personId] = MEMBER_ENGAGEMENT_LEVELS['public'] + ['key' => 'public', 'reasons' => []];
    }
    $placeholders = implode(',', array_fill(0, count($personIds), '?'));

    $rosters = $pdo->prepare(
        "SELECT m.sportovec_id,t.activity_kind,t.name FROM club_roster_members m "
        . "JOIN club_teams t ON t.id=m.team_id "
        . "WHERE m.sportovec_id IN ($placeholders) AND m.status<>'cancelled' AND (m.status<>'removed' OR m.valid_to IS NOT NULL) "
        . "AND m.valid_from<=? AND (m.valid_to IS NULL OR m.valid_to>=?) "
    );
    $rosters->execute([...$personIds, $onDate, $onDate]);
    $competitive = [];
    foreach ($rosters->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $personId = (int)$row['sportovec_id'];
        if ((string)$row['activity_kind'] === 'competitive') {
            $competitive[$personId] = true;
            $result[$personId]['reasons'][] = 'Aktivní závodní soupiska: ' . (string)$row['name'];
        } elseif ($row['activity_kind'] === 'circle') {
            $result[$personId] = MEMBER_ENGAGEMENT_LEVELS['circle'] + ['key' => 'circle', 'reasons' => array_merge(
                $result[$personId]['reasons'],
                ['Aktivní kroužková soupiska: ' . (string)$row['name']]
            )];
        }
    }

    $programs = $pdo->prepare(
        "SELECT e.sportovec_id,p.name FROM club_program_enrollments e "
        . "JOIN club_program_offers o ON o.id=e.offer_id JOIN club_programs p ON p.id=o.program_id "
        . "WHERE e.sportovec_id IN ($placeholders) AND e.status='active' AND e.valid_from<=? AND e.valid_to>=?"
    );
    $programs->execute([...$personIds, $onDate, $onDate]);
    foreach ($programs->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $personId = (int)$row['sportovec_id'];
        if (!isset($competitive[$personId])) {
            $reasons = $result[$personId]['reasons'];
            $result[$personId] = MEMBER_ENGAGEMENT_LEVELS['circle'] + ['key' => 'circle', 'reasons' => $reasons];
        }
        $result[$personId]['reasons'][] = 'Aktivní program: ' . (string)$row['name'];
    }

    foreach (array_keys($competitive) as $personId) {
        $reasons = $result[$personId]['reasons'];
        $result[$personId] = MEMBER_ENGAGEMENT_LEVELS['competitive'] + ['key' => 'competitive', 'reasons'
    => $reasons];
    }
    foreach ($result as &$level) {
        $level['reasons'] = array_values(array_unique($level['reasons']));
        if ($level['reasons'] === []) {
            $level['reasons'][] = 'Bez aktuálního programu nebo platné soupisky.';
        }
    }
    unset($level);
    return $result;
}

function memberEngagementCustomerOnlyCount(PDO $pdo): int
{
    $statement = $pdo->query(
        "SELECT COUNT(*) FROM verejni_uzivatele a WHERE a.aktivni=1 AND NOT EXISTS ("
        . "SELECT 1 FROM account_person_roles r WHERE r.account_id=a.id AND r.status='approved' "
        . "AND r.valid_from<=CURRENT_TIMESTAMP AND (r.valid_to IS NULL OR r.valid_to>CURRENT_TIMESTAMP))"
    );
    return (int)$statement->fetchColumn();
}
