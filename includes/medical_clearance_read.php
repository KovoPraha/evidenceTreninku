<?php

declare(strict_types=1);

require_once __DIR__ . '/staff_record_list.php';

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

/** Filtered totals remain exact; only one candidate batch and two result pages are retained. */
function medicalClearanceStatePage(PDO $pdo, string $base, array $params, array $filters, string $on): array
{
    $total = 0;
    $requested = [];
    $lastPage = [];
    $offset = ($filters['page'] - 1) * 25;
    $cursor = null;
    do {
        $after = $cursor === null ? '' : ' AND (w.next_review_on>? OR (w.next_review_on=? AND w.id>?))';
        $q = $pdo->prepare('SELECT w.*,s.jmeno,s.prijmeni ' . $base . $after . ' ORDER BY w.next_review_on,w.id LIMIT 200');
        $q->execute($cursor === null ? $params : [...$params, $cursor['next_review_on'], $cursor['next_review_on'], $cursor['id']]);
        $rows = $q->fetchAll(PDO::FETCH_ASSOC);
        $batchCount = count($rows);
        $cursor = $rows === [] ? null : $rows[array_key_last($rows)];
        foreach (medicalClearanceListStatuses($pdo, $rows, $on) as $row) {
            if ($row['evaluated_status']['status'] !== $filters['state']) {
                continue;
            }
            if ($total % 25 === 0) {
                $lastPage = [];
            }
            $lastPage[] = $row;
            if ($total >= $offset && $total < $offset + 25) {
                $requested[] = $row;
            }
            $total++;
        }
    } while ($batchCount === 200);
    $pages = max(1, (int)ceil($total / 25));
    $page = min($filters['page'], $pages);
    return ['rows' => $page === $filters['page'] ? $requested : $lastPage,
        'total' => $total, 'page' => $page, 'pages' => $pages];
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
    if ($f['state'] !== '' && $f['error'] === '') {
        return ['filters' => $f] + medicalClearanceStatePage($pdo, $base, $params, $f, $on);
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
