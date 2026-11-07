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
        $grouped = 'SELECT w.id watch_id,MAX(mr.id) last_id,'
            . "MAX(CASE WHEN $exactState='verified' THEN mr.id END) verified_id "
            . self::join($base, 'LEFT JOIN medical_clearance_records mr ON mr.watch_id=w.id') . ' GROUP BY w.id';
        $projection = self::join($base, 'LEFT JOIN (' . $grouped . ') mc ON mc.watch_id=w.id'
            . ' LEFT JOIN medical_clearance_records mv ON mv.id=mc.verified_id'
            . ' LEFT JOIN medical_clearance_records ml ON ml.id=mc.last_id AND mc.verified_id IS NULL');
        // The scoped derived table and the outer list each use the same candidate filters.
        $bindings = array_merge($params, $params);
        $complex = 'mv.id IS NOT NULL AND mv.issued_on>? AND mv.replaces_id IS NOT NULL AND mv.replaces_id<>0';
        $candidates = self::read($pdo, 'SELECT w.id ' . $projection . ' AND (' . $complex . ') LIMIT 129', [...$bindings, $on]);
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
        $simpleStatus = "CASE WHEN mv.id IS NULL THEN CASE $latestState WHEN 'pending' THEN 'pending'"
            . " WHEN 'revoked' THEN 'revoked' ELSE 'missing' END"
            . " WHEN mv.issued_on>? THEN 'not_yet_valid' WHEN mv.valid_until<? THEN 'expired' ELSE 'valid' END";
        $predicate = '(NOT (' . $complex . ') AND (' . $simpleStatus . ')=?)';
        $bindings = [...$bindings, $on, $on, $on, $filters['state']];
        if ($matching !== []) {
            $predicate .= ' OR w.id IN (' . implode(',', array_fill(0, count($matching), '?')) . ')';
            array_push($bindings, ...$matching);
        }
        $filtered = $projection . ' AND (' . $predicate . ')';
        $count = self::read($pdo, 'SELECT COUNT(*) total ' . $filtered, $bindings);
        $total = (int) $count[0]['total'];
        $pages = max(1, (int) ceil($total / 25));
        $page = min($filters['page'], $pages);
        $rows = [];
        if ($total > 0) {
            $select = 'w.*,s.jmeno,s.prijmeni';
            $fields = ['id', 'state', 'issued_on', 'valid_until', 'replaces_id'];
            foreach (['mv', 'ml'] as $alias) {
                foreach ($fields as $field) {
                    $select .= ',' . $alias . '.' . $field . ' AS _medical_read_' . $alias . '_' . $field;
                }
            }
            $rows = self::read($pdo, 'SELECT ' . $select . ' ' . $filtered
                . ' ORDER BY w.next_review_on,w.id LIMIT 25 OFFSET ' . (($page - 1) * 25), $bindings);
            foreach ($rows as &$row) {
                $record = [];
                $alias = $row['_medical_read_mv_id'] !== null ? 'mv' : 'ml';
                foreach ($fields as $field) {
                    $record[$field] = $row['_medical_read_' . $alias . '_' . $field];
                    unset($row['_medical_read_mv_' . $field], $row['_medical_read_ml_' . $field]);
                }
                $row['evaluated_status'] = $statuses[(int) $row['id']]
                    ?? medicalClearanceStatusFromRecords($record['id'] !== null ? [$record] : [], $on);
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
