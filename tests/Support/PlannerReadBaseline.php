<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;

/** Pre-optimization planner warning query, frozen from 84f5c7a2. */
final class PlannerReadBaseline
{
    public static function pending(PDO $pdo, string $today, bool $mine, int $actor, int $trainer, int $group, int $team): array
    {
        $where = ["pt.stav = 'planovany'", 'pt.datum < ?'];
        $params = [$today];
        if ($mine) {
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
        $q = $pdo->prepare('SELECT pt.id,pt.datum FROM planovane_treninky pt WHERE ' . implode(' AND ', $where));
        $q->execute($params);
        $rows = $q->fetchAll(PDO::FETCH_ASSOC);
        if ($team > 0) {
            $rows = trainingRosterBridgeFilterPlanRows($pdo, $rows, $team);
        }
        return ['count' => count($rows), 'oldest_date' => trainingPlannerOldestPendingDate($rows)];
    }
}
