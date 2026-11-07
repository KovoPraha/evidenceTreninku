<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;

/** Frozen pre-optimization list algorithm from f7c944e6; only bootstrap/function wrapping differs. */
final class MedicalClearanceListBaseline
{
    public static function read(PDO $pdo, array $input): array
    {
        $f = staffListFilters($input, 'medical_', ['missing' => 'Nedoloženo','pending' => 'Čeká na ověření','valid' => 'Platné',
            'expired' => 'Platnost skončila','not_yet_valid' => 'Dosud neplatné','revoked' => 'Odvoláno']);
        $params = [];
        $where = staffListWhere($f, ['s.jmeno','s.prijmeni','w.purpose','w.id'], 'w.next_review_on', null, $params);
        $person = max(0, (int)staffListText($input, 'person_id'));
        if ($person) {
            $where .= ' AND w.sportovec_id=?';
            $params[] = $person;
        }
        $base = 'FROM medical_clearance_watches w JOIN sportovci s ON s.id=w.sportovec_id WHERE '.$where;
        $q = $pdo->prepare('SELECT w.*,s.jmeno,s.prijmeni '.$base.' ORDER BY w.next_review_on,w.id');
        $q->execute($params);
        $rows = $q->fetchAll(PDO::FETCH_ASSOC);
        // Batch-load only status metadata; no document content or file paths enter this list.
        $q = $pdo->prepare('SELECT r.id,r.watch_id,r.state,r.issued_on,r.valid_until,r.replaces_id
            FROM medical_clearance_records r JOIN medical_clearance_watches w ON w.id=r.watch_id
            JOIN sportovci s ON s.id=w.sportovec_id WHERE '.$where.' ORDER BY r.id DESC');
        $q->execute($params);
        $records = [];
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $records[(int)$r['watch_id']][] = $r;
        }
        $filtered = [];
        $on = date('Y-m-d');
        foreach ($rows as $row) {
            $row['evaluated_status'] = medicalClearanceStatusFromRecords($records[(int)$row['id']] ?? [], $on);
            if ($f['state'] === '' || $f['state'] === $row['evaluated_status']['status']) {
                $filtered[] = $row;
            }
        }
        $total = count($filtered);
        $pages = max(1, (int)ceil($total / 25));
        $page = min($f['page'], $pages);
        return ['filters' => $f,'rows' => array_slice($filtered, ($page - 1) * 25, 25),'total' => $total,'page' => $page,'pages' => $pages];
    }
}
