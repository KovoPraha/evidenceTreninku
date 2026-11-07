<?php

declare(strict_types=1);

/** Registrar list projection only; no persisted statuses, health decisions or authorization cache. */
final class MedicalClearanceStateRead
{
    public static function page(PDO $pdo, string $base, array $params, array $filters, string $on): array
    {
        // A person's few purposes and explicit transactions retain the canonical streaming path.
        if ($pdo->inTransaction() || str_contains($base, 'w.sportovec_id=?')) {
            return medicalClearanceStatePage($pdo, $base, $params, $filters, $on);
        }
        $exactState = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? 'BINARY mr.state' : 'mr.state COLLATE BINARY';
        $verifiedOnly = in_array($filters['state'], ['valid', 'expired', 'not_yet_valid'], true);
        $latestId = $verifiedOnly ? 'NULL' : 'MAX(mr.id)';
        $verifiedId = $verifiedOnly ? 'MAX(mr.id)' : "MAX(CASE WHEN $exactState='verified' THEN mr.id END)";
        $recordJoin = $verifiedOnly ? 'JOIN' : 'LEFT JOIN';
        $verifiedWhere = $verifiedOnly ? " AND $exactState='verified'" : '';
        $grouped = 'SELECT w.id watch_id,' . $latestId . ' last_id,' . $verifiedId . ' verified_id '
            . self::join($base, $recordJoin . ' medical_clearance_records mr ON mr.watch_id=w.id') . $verifiedWhere . ' GROUP BY w.id';
        if ($params === []) {
            // The unrestricted list needs no repeated person/watch joins inside the history aggregation.
            // Missing watches still survive the outer LEFT JOIN with a null current record.
            $grouped = 'SELECT mr.watch_id,' . $latestId . ' last_id,' . $verifiedId . ' verified_id'
                . ' FROM medical_clearance_records mr'
                . ($verifiedOnly ? " WHERE $exactState='verified'" : '') . ' GROUP BY mr.watch_id';
        }
        $headJoin = $verifiedOnly ? 'JOIN' : 'LEFT JOIN';
        $projection = self::join($base, $headJoin . ' (' . $grouped . ') mc ON mc.watch_id=w.id'
            . ' ' . $headJoin . ' medical_clearance_records mv ON mv.id=mc.verified_id'
            . ($verifiedOnly ? '' : ' LEFT JOIN medical_clearance_records ml ON ml.id=mc.last_id AND mc.verified_id IS NULL'));
        // The scoped derived table and the outer list each use the same candidate filters.
        $bindings = array_merge($params, $params);
        $complex = 'mv.id IS NOT NULL AND mv.issued_on>? AND mv.replaces_id IS NOT NULL AND mv.replaces_id<>0';
        $futureState = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? 'BINARY mf.state' : 'mf.state COLLATE BINARY';
        $newerState = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? 'BINARY mn.state' : 'mn.state COLLATE BINARY';
        $candidates = self::read($pdo, 'SELECT w.id '
            . self::join($base, 'JOIN medical_clearance_records mf ON mf.watch_id=w.id')
            . " AND $futureState='verified' AND mf.issued_on>? AND mf.replaces_id IS NOT NULL AND mf.replaces_id<>0"
            . ' AND NOT EXISTS (SELECT 1 FROM medical_clearance_records mn WHERE mn.watch_id=w.id'
            . " AND $newerState='verified' AND mn.id>mf.id) LIMIT 129", [...$params, $on]);
        if (count($candidates) > 128) {
            return medicalClearanceStatePage($pdo, $base, $params, $filters, $on);
        }
        $statuses = [];
        $matching = [];
        if ($candidates !== []) {
            $ids = array_column($candidates, 'id');
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $records = self::read($pdo, 'SELECT id,watch_id,state,issued_on,valid_until,replaces_id'
                . ' FROM medical_clearance_records WHERE watch_id IN (' . $marks . ') ORDER BY id DESC LIMIT 4097', $ids);
            if (count($records) > 4096) {
                unset($records);
                return medicalClearanceStatePage($pdo, $base, $params, $filters, $on);
            }
            $byWatch = [];
            foreach ($records as $record) {
                $byWatch[(int) $record['watch_id']][] = $record;
            }
            foreach ($ids as $id) {
                $status = medicalClearanceStatusFromRecords($byWatch[(int) $id] ?? [], $on);
                $statuses[(int) $id] = $status;
                if ($status['status'] === $filters['state']) {
                    $matching[] = (int) $id;
                }
            }
        }
        $latestState = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? 'BINARY ml.state' : 'ml.state COLLATE BINARY';
        $noVerified = $verifiedOnly ? '' : "WHEN mv.id IS NULL THEN CASE $latestState WHEN 'pending' THEN 'pending'"
            . " WHEN 'revoked' THEN 'revoked' ELSE 'missing' END ";
        $simpleStatus = 'CASE ' . $noVerified
            . "WHEN mv.issued_on>? THEN 'not_yet_valid' WHEN mv.valid_until<? THEN 'expired' ELSE 'valid' END";
        $predicate = '(NOT (' . $complex . ') AND (' . $simpleStatus . ')=?)';
        $bindings = [...$bindings, $on, $on, $on, $filters['state']];
        if ($matching !== []) {
            $predicate .= ' OR w.id IN (' . implode(',', array_fill(0, count($matching), '?')) . ')';
            array_push($bindings, ...$matching);
        }
        $filtered = $projection . ' AND (' . $predicate . ')';
        $select = 'w.id';
        $fields = ['id', 'state', 'issued_on', 'valid_until', 'replaces_id'];
        foreach (['mv', 'ml'] as $alias) {
            foreach ($fields as $field) {
                $value = $verifiedOnly && $alias === 'ml' ? 'NULL' : $alias . '.' . $field;
                $select .= ',' . $value . ' AS _medical_read_' . $alias . '_' . $field;
            }
        }
        // One aggregation for both the exact total and the clamped page, including an excessive page number.
        $window = 'SELECT ' . $select . ',COUNT(*) OVER () AS _medical_read_total,'
            . 'ROW_NUMBER() OVER (ORDER BY w.next_review_on,w.id) AS _medical_read_number ' . $filtered;
        $requestedStart = ($filters['page'] - 1) * 25 + 1;
        $start = 'CASE WHEN ' . $requestedStart . '>mp._medical_read_total'
            . ' THEN mp._medical_read_total-((mp._medical_read_total-1)%25) ELSE ' . $requestedStart . ' END';
        $selected = self::read($pdo, 'SELECT * FROM (' . $window . ') mp WHERE mp._medical_read_number>=(' . $start
            . ') AND mp._medical_read_number<(' . $start . ')+25 ORDER BY mp._medical_read_number', $bindings);
        $total = (int) ($selected[0]['_medical_read_total'] ?? 0);
        $pages = max(1, (int) ceil($total / 25));
        $page = min($filters['page'], $pages);
        foreach ($selected as $row) {
            $record = [];
            $alias = $row['_medical_read_mv_id'] !== null ? 'mv' : 'ml';
            foreach ($fields as $field) {
                $record[$field] = $row['_medical_read_' . $alias . '_' . $field];
            }
            $statuses[(int) $row['id']] ??= medicalClearanceStatusFromRecords($record['id'] !== null ? [$record] : [], $on);
        }
        // Keep names and the full watch projection out of the window's working set.
        $rows = [];
        if ($selected !== []) {
            $ids = array_column($selected, 'id');
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $rows = self::read($pdo, 'SELECT w.*,s.jmeno,s.prijmeni FROM medical_clearance_watches w'
                . ' JOIN sportovci s ON s.id=w.sportovec_id WHERE w.id IN (' . $marks . ') ORDER BY w.next_review_on,w.id', $ids);
            foreach ($rows as &$row) {
                $row['evaluated_status'] = $statuses[(int) $row['id']];
            }
            unset($row);
        }
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /** Inject joins before this list's WHERE, without rewriting the nested projection. */
    private static function join(string $base, string $joins): string
    {
        $position = strpos($base, ' WHERE ');
        if ($position === false) {
            throw new InvalidArgumentException('Expected internal list query.');
        }
        return substr($base, 0, $position) . ' ' . $joins . substr($base, $position);
    }

    /** Always release unbuffered native cursors too; do not change the caller's PDO settings. */
    private static function read(PDO $pdo, string $sql, array $params): array
    {
        $query = $pdo->prepare($sql);
        try {
            $query->execute($params);
            return $query->fetchAll(PDO::FETCH_ASSOC);
        } finally {
            $query->closeCursor();
        }
    }
}
