<?php

declare(strict_types=1);

require_once __DIR__ . '/training_roster_bridge.php';

/** Copy preview only. The POST operation independently re-reads and validates its inputs. */
function trainingPlannerCopyCount(
    PDO $pdo,
    string $from,
    string $to,
    bool $onlyMine,
    int $currentTrainer,
    int $trainer,
    int $group,
    int $team
): int {
    $where = ['datum BETWEEN ? AND ?', "stav = 'planovany'"];
    $parameters = [$from, $to];
    if ($onlyMine) {
        $where[] = 'trener_id = ?';
        $parameters[] = $currentTrainer;
    } elseif ($trainer) {
        $where[] = 'trener_id = ?';
        $parameters[] = $trainer;
    }
    if ($group) {
        $where[] = 'skupina_id = ?';
        $parameters[] = $group;
    }
    $columns = $team > 0 ? 'id,datum' : 'COUNT(*)';
    $query = $pdo->prepare('SELECT ' . $columns . ' FROM planovane_treninky WHERE ' . implode(' AND ', $where));
    $query->execute($parameters);
    return $team > 0
        ? count(trainingRosterBridgeFilterPlanRows($pdo, $query->fetchAll(PDO::FETCH_ASSOC), $team))
        : (int) $query->fetchColumn();
}
