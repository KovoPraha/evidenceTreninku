<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/roster_hierarchy.php';

/**
 * Date-sensitive plans and actual trainings for one athlete in one calendar year.
 * A plan linked to an actual training remains one item. Direct attendance is
 * included even when an old training has no roster target.
 *
 * @return list<array<string,mixed>>
 */
function w81TimelineBaseline(PDO $pdo, int $sportovecId, int $year): array
{
    if ($sportovecId < 1 || $year < 1900 || $year > 2100) {
        throw new InvalidArgumentException('Neplatný sportovec nebo rok.');
    }
    $from = sprintf('%04d-01-01', $year);
    $to = sprintf('%04d-12-31', $year);
    $rows = [];
    $actualCovered = [];
    $membershipCache = [];
    $belongs = static function (int $teamId, string $date) use ($pdo, $sportovecId, &$membershipCache): bool {
        $key = $teamId . ':' . $date;
        if (!array_key_exists($key, $membershipCache)) {
            $membershipCache[$key] = kisRosterPersonTargetIds($pdo, $sportovecId, [$teamId], $date) !== [];
        }
        return $membershipCache[$key];
    };

    if (kisRosterHierarchyHasTable($pdo, 'training_roster_links') && kisRosterHierarchyHasTable($pdo, 'planovane_treninky')) {
        $sql = 'SELECT p.id,p.datum,p.cas_od,p.cas_do,p.nazev,p.popis,p.stav,p.trenink_id,'
            . 'l.team_id,l.team_name_snapshot FROM planovane_treninky p '
            . 'JOIN training_roster_links l ON l.plan_id=p.id '
            . "WHERE p.datum BETWEEN ? AND ? AND p.stav<>'zruseny' ORDER BY p.datum,p.cas_od,p.id,l.team_id";
        $query = $pdo->prepare($sql);
        $query->execute([$from, $to]);
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $plan) {
            if (!$belongs((int)$plan['team_id'], (string)$plan['datum'])) {
                continue;
            }
            $planId = (int)$plan['id'];
            if (!isset($rows['plan:' . $planId])) {
                $rows['plan:' . $planId] = [
                    'kind' => 'plan', 'id' => $planId, 'datum' => (string)$plan['datum'],
                    'cas_od' => (string)($plan['cas_od'] ?? ''), 'cas_do' => (string)($plan['cas_do'] ?? ''),
                    'title' => (string)$plan['nazev'], 'description' => (string)($plan['popis'] ?? ''),
                    'state' => (string)$plan['stav'], 'actual_id' => (int)($plan['trenink_id'] ?? 0),
                    'targets' => [], 'response' => null, 'attended' => false,
                ];
            }
            $rows['plan:' . $planId]['targets'][(int)$plan['team_id']] = (string)$plan['team_name_snapshot'];
            if ((int)($plan['trenink_id'] ?? 0) > 0) {
                $actualCovered[(int)$plan['trenink_id']] = true;
            }
        }
    }

    $responses = [];
    if (kisRosterHierarchyHasTable($pdo, 'training_rsvps')) {
        $query = $pdo->prepare('SELECT plan_id,response FROM training_rsvps WHERE sportovec_id=?');
        $query->execute([$sportovecId]);
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $response) {
            $responses[(int)$response['plan_id']] = (string)$response['response'];
        }
    }
    $attendance = [];
    if (kisRosterHierarchyHasTable($pdo, 'trenink_sportovec')) {
        $query = $pdo->prepare('SELECT trenink_id FROM trenink_sportovec WHERE sportovec_id=?');
        $query->execute([$sportovecId]);
        $attendance = array_fill_keys(array_map('intval', $query->fetchAll(PDO::FETCH_COLUMN)), true);
    }
    foreach ($rows as &$row) {
        $row['targets'] = array_values(array_unique(array_values($row['targets'])));
        $row['response'] = $responses[$row['id']] ?? null;
        $row['attended'] = $row['actual_id'] > 0 && isset($attendance[$row['actual_id']]);
    }
    unset($row);

    if (kisRosterHierarchyHasTable($pdo, 'treninky')) {
        $actuals = $pdo->prepare(
            'SELECT t.id,t.datum,t.napln,l.team_id,l.team_name_snapshot '
            . 'FROM treninky t LEFT JOIN training_roster_links l ON l.trenink_id=t.id '
            . 'WHERE t.datum BETWEEN ? AND ? ORDER BY t.datum,t.id,l.team_id'
        );
        $actuals->execute([$from, $to]);
        foreach ($actuals->fetchAll(PDO::FETCH_ASSOC) as $actual) {
            $actualId = (int)$actual['id'];
            if (isset($actualCovered[$actualId])) {
                continue;
            }
            $attended = isset($attendance[$actualId]);
            $teamId = (int)($actual['team_id'] ?? 0);
            if (!$attended && ($teamId < 1 || !$belongs($teamId, (string)$actual['datum']))) {
                continue;
            }
            $key = 'actual:' . $actualId;
            if (!isset($rows[$key])) {
                $rows[$key] = [
                    'kind' => 'actual', 'id' => $actualId, 'datum' => (string)$actual['datum'],
                    'cas_od' => '', 'cas_do' => '', 'title' => (string)$actual['napln'],
                    'description' => '', 'state' => 'evidovany', 'actual_id' => $actualId,
                    'targets' => [], 'response' => null, 'attended' => $attended,
                ];
            }
            if ($teamId > 0 && $belongs($teamId, (string)$actual['datum'])) {
                $rows[$key]['targets'][$teamId] = (string)$actual['team_name_snapshot'];
            }
        }
        foreach ($rows as &$row) {
            if ($row['kind'] === 'actual') {
                $row['targets'] = array_values(array_unique(array_values($row['targets'])));
            }
        }
        unset($row);
    }
    // Raw attendance remains visible as evidence but only a closed actual revision
    // confirms attendance. A planned row and its actual training still form one item.
    if (is_file(dirname(__DIR__, 2).'/includes/training_closure.php') && kisRosterHierarchyHasTable($pdo, 'training_closures')) {
        require_once dirname(__DIR__, 2).'/includes/training_closure.php';
        $closed = [];
        foreach (trainingClosedActivitiesForPerson($pdo, $sportovecId, $from, $to) as $activity) {
            $closed[(int)$activity['training_id']] = $activity;
        }
        foreach ($rows as &$row) {
            $actualId = (int)$row['actual_id'];
            $row['attendance_recorded'] = $row['attended'];
            $row['attended'] = isset($closed[$actualId]);
            $row['closure_version'] = $closed[$actualId]['version'] ?? null;
            $row['duration_minutes'] = $closed[$actualId]['duration_minutes'] ?? null;
        }
        unset($row);
    }
    $result = array_values($rows);
    usort($result, static fn (array $a, array $b): int => [$b['datum'], $b['cas_od'], $b['id']] <=> [$a['datum'], $a['cas_od'], $a['id']]);
    return $result;
}
