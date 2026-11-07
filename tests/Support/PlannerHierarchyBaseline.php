<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;

/** Frozen from main 69f49033: independent pre-optimization warning read. */
function plannerHierarchyPendingBaseline(PDO $pdo, string $today, bool $onlyMine, int $actor, int $trainer, int $group, int $team): array
{
    $where = ["pt.stav = 'planovany'", 'pt.datum < ?'];
    $params = [$today];
    if ($onlyMine) {
        $where[] = 'pt.trener_id = ?';
        $params[] = $actor;
    } elseif ($trainer) {
        $where[] = 'pt.trener_id = ?';
        $params[] = $trainer;
    }
    if ($group) {
        $where[] = 'pt.skupina_id = ?';
        $params[] = $group;
    }
    $columns = $team > 0 ? 'pt.id,pt.datum' : 'COUNT(*) total,MIN(pt.datum) oldest_date';
    $q = $pdo->prepare('SELECT ' . $columns . ' FROM planovane_treninky pt WHERE ' . implode(' AND ', $where));
    $q->execute($params);
    if ($team > 0) {
        $rows = trainingRosterBridgeFilterPlanRows($pdo, $q->fetchAll(PDO::FETCH_ASSOC), $team);
        return ['count' => count($rows), 'oldest_date' => trainingPlannerOldestPendingDate($rows)];
    }
    $row = $q->fetch(PDO::FETCH_ASSOC);
    return ['count' => (int)$row['total'], 'oldest_date' => $row['oldest_date'] ?: null];
}
