<?php

declare(strict_types=1);

require_once __DIR__ . '/staff_record_list.php';
require_once __DIR__ . '/medical_clearance_state_read.php';
require_once __DIR__ . '/medical_clearance_filtered_read.php';

/** Load complete replacement chains only for the bounded set of watches being evaluated. */
function medicalClearanceListStatuses(PDO $pdo, array $rows, string $on): array
{
    if ($rows === []) {
        return [];
    }
    $ids = array_column($rows, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $q = $pdo->prepare('SELECT id,watch_id,state,issued_on,valid_until,replaces_id
        FROM medical_clearance_records WHERE watch_id IN (' . $placeholders . ') ORDER BY id DESC');
    $q->execute($ids);
    $records = [];
    while ($record = $q->fetch(PDO::FETCH_ASSOC)) {
        $records[(int)$record['watch_id']][] = $record;
    }
    foreach ($rows as &$row) {
        $row['evaluated_status'] = medicalClearanceStatusFromRecords($records[(int)$row['id']] ?? [], $on);
    }
    unset($row);
    return $rows;
}

/** Stream one watch's complete history at a time; retain only the requested and last result page. */
function medicalClearanceStatePage(PDO $pdo, string $base, array $params, array $filters, string $on): array
{
    $total = 0;
    $requested = [];
    $lastPage = [];
    $offset = ($filters['page'] - 1) * 25;
    $consume = static function (array $watch, array $records) use (&$total, &$requested, &$lastPage, $offset, $filters, $on): void {
        $watch['evaluated_status'] = medicalClearanceStatusFromRecords($records, $on);
        if ($watch['evaluated_status']['status'] !== $filters['state']) {
            return;
        }
        if ($total % 25 === 0) {
            $lastPage = [];
        }
        $lastPage[] = $watch;
        if ($total >= $offset && $total < $offset + 25) {
            $requested[] = $watch;
        }
        $total++;
    };
    $columns = ['id', 'state', 'issued_on', 'valid_until', 'replaces_id'];
    $select = implode(',', array_map(static fn (string $column): string => 'r.' . $column . ' AS medical_record_' . $column, $columns));
    $joined = str_replace(' WHERE ', ' LEFT JOIN medical_clearance_records r ON r.watch_id=w.id WHERE ', $base);
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $buffered = $mysql ? $pdo->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY) : null;
    $q = null;
    try {
        if ($mysql) {
            $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        }
        $q = $pdo->prepare('SELECT w.id,' . $select . ' ' . $joined . ' ORDER BY w.next_review_on,w.id,r.id DESC');
        $q->execute($params);
        $watch = null;
        $records = [];
        while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
            if ($watch === null || $watch['id'] !== $row['id']) {
                if ($watch !== null) {
                    $consume($watch, $records);
                }
                $watch = ['id' => $row['id']];
                $records = [];
            }
            if ($row['medical_record_id'] !== null) {
                $record = [];
                foreach ($columns as $column) {
                    $record[$column] = $row['medical_record_' . $column];
                }
                $records[] = $record;
            }
        }
        if ($watch !== null) {
            $consume($watch, $records);
        }
    } finally {
        try {
            if ($q !== null) {
                $q->closeCursor();
            }
        } finally {
            if ($mysql) {
                $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, $buffered);
            }
        }
    }
    $pages = max(1, (int)ceil($total / 25));
    $page = min($filters['page'], $pages);
    $selected = $page === $filters['page'] ? $requested : $lastPage;
    $rows = [];
    if ($selected !== []) {
        $ids = array_column($selected, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $q = $pdo->prepare('SELECT w.*,s.jmeno,s.prijmeni FROM medical_clearance_watches w JOIN sportovci s ON s.id=w.sportovec_id
            WHERE w.id IN (' . $placeholders . ') ORDER BY w.next_review_on,w.id');
        $q->execute($ids);
        $statuses = array_column($selected, 'evaluated_status', 'id');
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row['evaluated_status'] = $statuses[$row['id']];
            $rows[] = $row;
        }
    }
    return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages];
}

function medicalClearanceList(PDO $pdo, array $input): array
{
    $f = staffListFilters($input, 'medical_', ['missing' => 'Nedoloženo', 'pending' => 'Čeká na ověření',
        'valid' => 'Platné', 'expired' => 'Platnost skončila', 'not_yet_valid' => 'Dosud neplatné', 'revoked' => 'Odvoláno']);
    $params = [];
    $where = staffListWhere($f, ['s.jmeno', 's.prijmeni', 'w.purpose', 'w.id'], 'w.next_review_on', null, $params);
    $person = max(0, (int)staffListText($input, 'person_id'));
    if ($person) {
        $where .= ' AND w.sportovec_id=?';
        $params[] = $person;
    }
    $base = 'FROM medical_clearance_watches w JOIN sportovci s ON s.id=w.sportovec_id WHERE ' . $where;
    $on = date('Y-m-d');
    if ($f['error'] === '' && $params !== [] && !$person && !$pdo->inTransaction()) {
        return ['filters' => $f] + MedicalClearanceFilteredRead::page($pdo, $base, $params, $f, $on);
    }
    if ($f['state'] !== '' && $f['error'] === '') {
        return ['filters' => $f] + MedicalClearanceStateRead::page($pdo, $base, $params, $f, $on);
    }
    $page = staffListPage($pdo, 'w.*,s.jmeno,s.prijmeni', $base, 'w.next_review_on,w.id', $params, $f['page']);
    $page['rows'] = medicalClearanceListStatuses($pdo, $page['rows'], $on);
    return ['filters' => $f] + $page;
}

/** Registrar-only picker: bounded, without excluding archived people or exposing extra personal fields. */
function medicalClearancePersonChoices(PDO $pdo, array $input, int $selected): array
{
    medicalClearanceRegistrar($pdo);
    $filters = staffListFilters(['person_q' => staffListText($input, 'medical_person_q'),
        'person_page' => staffListText($input, 'medical_person_page')], 'person_', []);
    $params = [];
    $where = staffListWhere($filters, ['s.jmeno', 's.prijmeni', 's.id'], 's.id', null, $params);
    $result = staffListPage(
        $pdo,
        's.id,s.jmeno,s.prijmeni',
        'FROM sportovci s WHERE ' . $where,
        's.prijmeni,s.jmeno,s.id',
        $params,
        $filters['page']
    );
    $result['query'] = $filters['q'];
    $result['selected'] = null;
    foreach ($result['rows'] as $person) {
        if ((int)$person['id'] === $selected) {
            $result['selected'] = $person;
        }
    }
    if ($selected > 0 && $result['selected'] === null) {
        $q = $pdo->prepare('SELECT id,jmeno,prijmeni FROM sportovci WHERE id=?');
        $q->execute([$selected]);
        $person = $q->fetch(PDO::FETCH_ASSOC);
        if ($person !== false) {
            $result['selected'] = $person;
            array_unshift($result['rows'], $person);
        }
    }
    return $result;
}
