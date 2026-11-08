<?php

declare(strict_types=1);

require_once __DIR__ . '/training_history_read.php';

/** Read-only paging of the coach's existing scoped history predicates. */
final class CoachHistoryRead
{
    public static function page(PDO $pdo, string $from, array $params, int $team, int $requested): array
    {
        $order = ' ORDER BY t.datum DESC,t.id DESC';
        $candidates = self::read($pdo, 'SELECT t.id,t.datum ' . $from . $order, $params);
        if ($team > 0) {
            $candidates = trainingRosterBridgeFilterTrainingRows($pdo, $candidates, $team);
        }
        $total = count($candidates);
        $pages = max(1, (int) ceil($total / TrainingHistoryRead::SIZE));
        $page = min(max(1, min(1000000, $requested)), $pages);
        $selected = array_slice($candidates, ($page - 1) * TrainingHistoryRead::SIZE, TrainingHistoryRead::SIZE);
        unset($candidates);
        $rows = [];
        if ($selected !== []) {
            $marks = [];
            foreach (array_values(array_unique(array_column($selected, 'id'))) as $i => $id) {
                $key = ':history_id_' . $i;
                $marks[] = $key;
                $params[$key] = $id;
            }
            // Retain every original predicate, including the fresh coach scope.
            $details = self::read($pdo, 'SELECT t.id,t.datum,t.delka,t.napln,t.poznamka,t.obrazky,t.mereni '
                . $from . ' AND t.id IN (' . implode(',', $marks) . ')' . $order, $params);
            $byId = [];
            foreach ($details as $row) {
                $byId[$row['id']] = $row;
            }
            // Preserve the original multiplicity even if legacy joins contain duplicates.
            foreach ($selected as $candidate) {
                if (isset($byId[$candidate['id']])) {
                    $rows[] = $byId[$candidate['id']];
                }
            }
        }
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages];
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
