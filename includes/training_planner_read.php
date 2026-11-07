<?php

declare(strict_types=1);

require_once __DIR__ . '/training_roster_bridge.php';
require_once __DIR__ . '/training_planner_view.php';
require_once __DIR__ . '/training_planner_rsvp_read.php';
require_once __DIR__ . '/training_planner_pending_read.php';

/** Planner-only optimization; the shared writer and transaction read path remain canonical. */
function trainingPlannerRsvpSummaries(PDO $pdo, array $planIds): array
{
    return TrainingPlannerRsvpRead::summaries($pdo, $planIds);
}

function trainingPlannerPendingSummary(PDO $pdo, string $today, bool $onlyMine, int $actor, int $trainer, int $group, int $team): array
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
    if ($team > 0) {
        return TrainingPlannerPendingRead::summary($pdo, $where, $params, $team);
    }
    $columns = 'COUNT(*) total,MIN(pt.datum) oldest_date';
    $q = $pdo->prepare('SELECT ' . $columns . ' FROM planovane_treninky pt WHERE ' . implode(' AND ', $where));
    $q->execute($params);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    return ['count' => (int)$row['total'], 'oldest_date' => $row['oldest_date'] ?: null];
}

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
