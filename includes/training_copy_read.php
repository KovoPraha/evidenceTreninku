<?php

declare(strict_types=1);

require_once __DIR__ . '/training_roster_bridge.php';

/** Limits detailed copy-list aggregation after the canonical historical team filter. */
final class TrainingCopyRead
{
    /** The query and its predicates come only from duplikovat_trenink.php. */
    public static function rows(PDO $pdo, string $sql, array $params, ?int $team, bool $groupFiltered): array
    {
        $where = strpos($sql, 'WHERE 1=1');
        if ($where === false || !str_starts_with(ltrim($sql), 'SELECT')) {
            throw new InvalidArgumentException('Expected internal copy-list query.');
        }
        $from = ' FROM treninky t ';
        if ($groupFiltered) {
            $from .= 'LEFT JOIN trenink_skupina tsk ON tsk.trenink_id = t.id ';
        }
        $candidateSql = 'SELECT t.id,t.datum' . $from . substr($sql, $where)
            . ' GROUP BY t.id ORDER BY t.datum DESC';
        if ($team === null) {
            $candidateSql .= ' LIMIT 101';
        }
        $candidates = self::read($pdo, $candidateSql, $params);
        if ($team !== null) {
            $candidates = trainingRosterBridgeFilterTrainingRows($pdo, $candidates, $team);
        }
        // The legacy date-only sort leaves ties undefined, including the 100th row.
        // Execute that exact query when a tie could change the visible selection.
        $window = array_slice($candidates, 0, 101);
        $dates = array_column($window, 'datum');
        if (count($dates) !== count(array_unique($dates))) {
            $rows = self::read($pdo, $sql . ' GROUP BY t.id ORDER BY t.datum DESC'
                . ($team === null ? ' LIMIT 100' : ''), $params);
            return $team === null ? $rows : array_slice(trainingRosterBridgeFilterTrainingRows($pdo, $rows, $team), 0, 100);
        }
        $candidates = array_slice($window, 0, 100);
        if ($candidates === []) {
            return [];
        }
        $ids = array_column($candidates, 'id');
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $rows = self::read($pdo, $sql . ' AND t.id IN (' . $marks . ') GROUP BY t.id', [...$params, ...$ids]);
        $byId = array_column($rows, null, 'id');
        $ordered = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $ordered[] = $byId[$id];
            }
        }
        return $ordered;
    }

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
