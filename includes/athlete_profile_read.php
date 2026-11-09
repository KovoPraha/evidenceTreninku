<?php

declare(strict_types=1);

/** Read-only athlete projections. Caller must resolve the current profile session first. */
final class AthleteProfileRead
{
    public const SIZE = 25;

    private static function membership(): string
    {
        return ' FROM treninky t JOIN (
            SELECT trenink_id FROM trenink_sportovec WHERE sportovec_id=:attendance_sid
            UNION SELECT tm.trenink_id FROM trenink_mereni tm
            JOIN mereni_zaznamy mz ON mz.id=tm.mereni_id WHERE mz.sportovec_id=:measurement_sid
        ) owned ON owned.trenink_id=t.id';
    }

    private static function scope(int $sid): array
    {
        return [':attendance_sid' => $sid, ':measurement_sid' => $sid];
    }

    /** The same years/counts as the independent projections, in one narrow read. */
    public static function header(PDO $pdo, int $sid): array
    {
        $rows = self::read($pdo, 'SELECT YEAR(t.datum) AS rok,
            COUNT(*) AS trainings,SUM(owned.measurements) AS measurements
            FROM treninky t JOIN (
                SELECT owned_keys.trenink_id,SUM(owned_keys.measurements) AS measurements FROM (
                    SELECT DISTINCT ts.trenink_id,0 AS measurements
                    FROM trenink_sportovec ts WHERE ts.sportovec_id=:attendance_sid
                    UNION ALL
                    SELECT tm.trenink_id,COUNT(*) AS measurements
                    FROM trenink_mereni tm JOIN mereni_zaznamy mz ON mz.id=tm.mereni_id
                    WHERE mz.sportovec_id=:measurement_sid GROUP BY tm.trenink_id
                ) owned_keys GROUP BY owned_keys.trenink_id
            ) owned ON owned.trenink_id=t.id
            GROUP BY YEAR(t.datum) ORDER BY rok DESC', self::scope($sid));
        $result = ['years' => [], 'trainings' => 0, 'measurements' => 0];
        foreach ($rows as $row) {
            // Preserve PDO year types/nulls; duplicate measurement links remain counted.
            $result['years'][] = $row['rok'];
            $result['trainings'] += (int) $row['trainings'];
            $result['measurements'] += (int) $row['measurements'];
        }
        return $result;
    }

    public static function years(PDO $pdo, int $sid): array
    {
        return array_column(self::read($pdo, 'SELECT DISTINCT YEAR(t.datum) AS rok'
            . self::membership() . ' ORDER BY rok DESC', self::scope($sid)), 'rok');
    }

    public static function countTrainings(PDO $pdo, int $sid): int
    {
        return (int) self::read($pdo, 'SELECT COUNT(*) AS total' . self::membership(), self::scope($sid))[0]['total'];
    }

    private static function measurementFrom(): string
    {
        return ' FROM trenink_mereni tm JOIN mereni_zaznamy mz ON mz.id=tm.mereni_id
            JOIN treninky t ON t.id=tm.trenink_id';
    }

    private static function measurementFields(): string
    {
        return 'tm.trenink_id,tm.poradi,mz.id AS mereni_id,mz.typ,
            mz.vzdalenost,mz.distance_unit,mz.cas,mz.prevod,mz.cvik_id,mz.segment_id,
            mz.vaha,mz.opakovani,mz.rpe,mz.poznamka AS mereni_poznamka,
            c.nazev AS cvik_nazev,seg.nazev AS segment_nazev';
    }

    private static function labels(): string
    {
        return ' LEFT JOIN cviky c ON c.id=mz.cvik_id LEFT JOIN segmenty seg ON seg.id=mz.segment_id';
    }

    public static function countMeasurements(PDO $pdo, int $sid): int
    {
        return (int) self::read($pdo, 'SELECT COUNT(*) AS total' . self::measurementFrom()
            . ' WHERE mz.sportovec_id=?', [$sid])[0]['total'];
    }

    public static function history(PDO $pdo, int $sid, int $year, int $requested): array
    {
        $from = ' FROM treninky t WHERE ';
        $params = self::scope($sid);
        if ($year > 0) {
            $from .= 'YEAR(t.datum)=:year AND ';
            $params[':year'] = $year;
        }
        $from .= '(
            EXISTS (SELECT 1 FROM trenink_sportovec ts
                WHERE ts.trenink_id=t.id AND ts.sportovec_id=:attendance_sid)
            OR EXISTS (SELECT 1 FROM trenink_mereni tm JOIN mereni_zaznamy mz ON mz.id=tm.mereni_id
                WHERE tm.trenink_id=t.id AND mz.sportovec_id=:measurement_sid)
        )';
        $order = ' ORDER BY t.datum DESC,t.id DESC';
        $statement = $pdo->prepare('SELECT t.id,t.delka' . $from . $order);
        $minutes = 0;
        $total = 0;
        $ids = [];
        $lastIds = [];
        $requested = max(1, $requested);
        try {
            $statement->execute($params);
            while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
                // Keep the requested page and the current last page for clamping.
                // Preserve the original ordered PHP duration conversion and additions.
                if ($total % self::SIZE === 0) {
                    $lastIds = [];
                }
                $lastIds[] = $row['id'];
                if (intdiv($total, self::SIZE) + 1 === $requested) {
                    $ids[] = $row['id'];
                }
                $total++;
                if (!empty($row['delka'])) {
                    $minutes += (float) str_replace(',', '.', $row['delka']) * 60;
                }
            }
        } finally {
            $statement->closeCursor();
        }
        unset($statement);
        $result = self::pagination($total, $requested);
        $result['minutes'] = $minutes;
        if ($result['page'] !== $requested) {
            $ids = $lastIds;
        }
        $result += ['rows' => [], 'notes' => [], 'measurements' => []];
        if ($ids === []) {
            return $result;
        }
        $marks = [];
        foreach ($ids as $i => $id) {
            $marks[] = ':id' . $i;
            $params[':id' . $i] = $id;
        }
        $result['rows'] = self::read($pdo, 'SELECT t.id,t.datum,t.delka,t.napln,t.obrazky' . $from
            . ' AND t.id IN (' . implode(',', $marks) . ')' . $order, $params);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        foreach (self::read($pdo, 'SELECT trenink_id,poznamka FROM sportovec_poznamka
            WHERE sportovec_id=? AND trenink_id IN (' . $placeholders . ')', [$sid, ...$ids]) as $row) {
            $result['notes'][(int) $row['trenink_id']] = $row['poznamka'];
        }
        foreach (self::read($pdo, 'SELECT ' . self::measurementFields()
            . ' FROM trenink_mereni tm JOIN mereni_zaznamy mz ON mz.id=tm.mereni_id' . self::labels()
            . ' WHERE tm.trenink_id IN (' . $placeholders . ') AND mz.sportovec_id=?'
            . ' ORDER BY tm.trenink_id ASC,tm.poradi ASC,mz.id ASC', [...$ids, $sid]) as $row) {
            $result['measurements'][(int) $row['trenink_id']][] = $row;
        }
        return $result;
    }

    public static function measurements(PDO $pdo, int $sid, string $type, int $requested): array
    {
        $order = ' ORDER BY t.datum DESC,t.id DESC,tm.poradi ASC,mz.id ASC';
        $binary = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? 'BINARY mz.typ' : 'mz.typ COLLATE BINARY';
        // Aggregate only narrow ordered keys in SQL. Binary grouping preserves PHP's exact
        // case/space-sensitive keys; the first ordinal preserves the old filter-pill order.
        // Materialize only numeric measurement IDs/ordinals in the window, not padded type strings.
        $summary = self::read($pdo, 'SELECT ' . $binary . ' AS typ,COUNT(*) AS num,MIN(ordinal) AS first_position
            FROM (SELECT mz.id AS mereni_id,ROW_NUMBER() OVER (' . $order . ') AS ordinal'
            . self::measurementFrom() . ' WHERE mz.sportovec_id=?) ordered_types
            JOIN mereni_zaznamy mz ON mz.id=ordered_types.mereni_id
            GROUP BY ' . $binary . ' ORDER BY first_position', [$sid]);
        $counts = [];
        $all = 0;
        foreach ($summary as $row) {
            $all += (int) $row['num'];
            if (is_string($row['typ']) || is_int($row['typ'])) {
                $counts[$row['typ']] = (int) $row['num'];
            }
        }
        $result = self::pagination($type === '' ? $all : ($counts[$type] ?? 0), $requested);
        $result += ['types' => $counts, 'all' => $all];
        $params = [$sid];
        $filter = '';
        if ($type !== '') {
            $binary = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? 'BINARY mz.typ' : 'mz.typ COLLATE BINARY';
            $filter = ' AND ' . $binary . '=?';
            $params[] = $type;
        }
        $from = self::measurementFrom();
        $pageParams = $params;
        // Bound the training candidates before sorting measurement links. For late
        // pages retain the original selector instead of growing this intermediate set.
        if ($result['page'] <= 32) {
            $typeScope = $type === '' ? '' : ' AND '
                . ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? 'BINARY own_mz.typ' : 'own_mz.typ COLLATE BINARY') . '=?';
            $from .= ' JOIN (SELECT own_t.id FROM treninky own_t WHERE EXISTS (
                SELECT 1 FROM trenink_mereni own_tm JOIN mereni_zaznamy own_mz ON own_mz.id=own_tm.mereni_id
                WHERE own_tm.trenink_id=own_t.id AND own_mz.sportovec_id=?' . $typeScope . ')
                ORDER BY own_t.datum DESC,own_t.id DESC LIMIT ' . ($result['page'] * self::SIZE) . ') eligible ON eligible.id=t.id';
            $pageParams = [...$params, ...$params];
        }
        // LIMIT still precedes wide values/notes and display labels.
        $result['rows'] = self::read($pdo, 'SELECT ' . self::measurementFields() . ',tm.trenink_datum'
            . ' FROM (SELECT tm.trenink_id,tm.poradi,tm.mereni_id,t.datum AS trenink_datum'
            . $from . ' WHERE mz.sportovec_id=?' . $filter . $order
            . ' LIMIT ' . self::SIZE . ' OFFSET ' . (($result['page'] - 1) * self::SIZE) . ') tm'
            . ' JOIN mereni_zaznamy mz ON mz.id=tm.mereni_id' . self::labels() . ' WHERE mz.sportovec_id=?'
            . ' ORDER BY tm.trenink_datum DESC,tm.trenink_id DESC,tm.poradi ASC,mz.id ASC', [...$pageParams, $sid]);
        return $result;
    }

    private static function pagination(int $total, int $requested): array
    {
        $pages = max(1, (int) ceil($total / self::SIZE));
        return ['total' => $total, 'page' => max(1, min($pages, $requested)), 'pages' => $pages];
    }

    public static function requested(array $input): int
    {
        $value = $input['profile_page'] ?? '';
        return is_scalar($value) ? max(1, min(1000000, (int) $value)) : 1;
    }

    public static function navigation(array $result, array $filters): void
    {
        $escape = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        echo '<nav class="my-3 profile-pagination" aria-label="Stránkování přehledu"><p role="status">Nalezeno: '
            . (int) $result['total'] . ' · strana ' . (int) $result['page'] . ' z ' . (int) $result['pages'] . '</p>';
        foreach (['Předchozí' => $result['page'] - 1, 'Další' => $result['page'] + 1] as $label => $page) {
            if ($page >= 1 && $page <= $result['pages']) {
                echo '<a class="btn btn-outline-secondary me-2" href="ajax_sportovec_treninky.php?'
                    . $escape(http_build_query($filters + ['profile_page' => $page])) . '">' . $label . '</a>';
            }
        }
        echo '</nav>';
    }

    private static function read(PDO $pdo, string $sql, array $params): array
    {
        $statement = $pdo->prepare($sql);
        try {
            $statement->execute($params);
            return $statement->fetchAll(PDO::FETCH_ASSOC);
        } finally {
            $statement->closeCursor();
        }
    }
}
