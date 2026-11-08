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
        $from = self::membership();
        $params = self::scope($sid);
        if ($year > 0) {
            $from .= ' WHERE YEAR(t.datum)=:year';
            $params[':year'] = $year;
        }
        $order = ' ORDER BY t.datum DESC,t.id DESC';
        $candidates = self::read($pdo, 'SELECT t.id,t.delka' . $from . $order, $params);
        $minutes = 0;
        foreach ($candidates as $row) {
            if (!empty($row['delka'])) {
                $minutes += (float) str_replace(',', '.', $row['delka']) * 60;
            }
        }
        $result = self::pagination(count($candidates), $requested);
        $result['minutes'] = $minutes;
        $ids = array_column(array_slice($candidates, ($result['page'] - 1) * self::SIZE, self::SIZE), 'id');
        unset($candidates);
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
            . ($year > 0 ? ' AND' : ' WHERE') . ' t.id IN (' . implode(',', $marks) . ')' . $order, $params);
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
        // Narrow ordered values preserve PHP's case/space-sensitive keys and first-occurrence pill order.
        $types = array_column(self::read($pdo, 'SELECT mz.typ' . self::measurementFrom()
            . ' WHERE mz.sportovec_id=?' . $order, [$sid]), 'typ');
        $counts = array_count_values(array_filter($types, static fn ($v): bool => is_string($v) || is_int($v)));
        $result = self::pagination($type === '' ? count($types) : ($counts[$type] ?? 0), $requested);
        $result += ['types' => $counts, 'all' => count($types)];
        unset($types);
        $params = [$sid];
        $filter = '';
        if ($type !== '') {
            $binary = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? 'BINARY mz.typ' : 'mz.typ COLLATE BINARY';
            $filter = ' AND ' . $binary . '=?';
            $params[] = $type;
        }
        $result['rows'] = self::read($pdo, 'SELECT ' . self::measurementFields() . ',t.datum AS trenink_datum'
            . self::measurementFrom() . self::labels() . ' WHERE mz.sportovec_id=?' . $filter . $order
            . ' LIMIT ' . self::SIZE . ' OFFSET ' . (($result['page'] - 1) * self::SIZE), $params);
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
