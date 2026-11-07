<?php

declare(strict_types=1);

require_once __DIR__ . '/training_roster_bridge.php';
require_once __DIR__ . '/training_planner_view.php';

/** Planner warning only: fresh, bounded read projection; writers keep their canonical filter. */
final class TrainingPlannerPendingRead
{
    /** @param list<string> $where @param list<int|string> $parameters */
    public static function summary(PDO $pdo, array $where, array $parameters, int $team): array
    {
        $base = ' FROM planovane_treninky pt WHERE ' . implode(' AND ', $where);
        if ($pdo->inTransaction()) {
            return self::canonical($pdo, $base, $parameters, $team);
        }
        // The common small history keeps one base read and the existing filter.
        $probe = $pdo->prepare('SELECT pt.id,pt.datum' . $base . ' LIMIT 65');
        $probe->execute($parameters);
        $rows = $probe->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) <= 64) {
            return self::filtered($pdo, $rows, $team);
        }
        $query = $pdo->prepare('SELECT DISTINCT pt.datum' . $base . ' ORDER BY pt.datum');
        $query->execute($parameters);
        $dates = $query->fetchAll(PDO::FETCH_COLUMN);
        if (count($dates) > 4096) {
            return self::canonical($pdo, $base, $parameters, $team);
        }
        foreach ($dates as $date) {
            try {
                if (kisRosterDate((string) $date) !== $date) {
                    return self::canonical($pdo, $base, $parameters, $team);
                }
            } catch (InvalidArgumentException) {
                // Preserve the original validation and missing-table exception ordering.
                return self::canonical($pdo, $base, $parameters, $team);
            }
        }
        if ($dates === []) {
            return ['count' => 0, 'oldest_date' => null];
        }
        $edges = [];
        if (kisRosterHierarchyHasTable($pdo, 'club_team_edges')) {
            $query = $pdo->prepare('SELECT parent_team_id,child_team_id,valid_from,valid_to FROM club_team_edges'
                . ' WHERE valid_from<=? AND (valid_to IS NULL OR valid_to>=?) LIMIT 513');
            $query->execute([max($dates), min($dates)]);
            $edges = $query->fetchAll(PDO::FETCH_ASSOC);
            if (count($edges) > 512) {
                return self::canonical($pdo, $base, $parameters, $team);
            }
        }
        $groups = [];
        $previous = null;
        $teams = [];
        foreach ($dates as $date) {
            $active = [];
            foreach ($edges as $index => $edge) {
                if ($edge['valid_from'] <= $date && ($edge['valid_to'] === null || $edge['valid_to'] >= $date)) {
                    $active[] = $index;
                }
            }
            if ($active !== $previous) {
                $children = $parents = [];
                foreach ($active as $index) {
                    $parent = (int) $edges[$index]['parent_team_id'];
                    $child = (int) $edges[$index]['child_team_id'];
                    $children[$parent][] = $child;
                    $parents[$child][] = $parent;
                }
                // Separate directed traversals: an undirected walk would include siblings.
                $teams = array_values(array_unique(array_merge(
                    self::reachable($children, $team),
                    array_filter(self::reachable($parents, $team), static fn (int $id): bool => $id > 0)
                )));
                sort($teams, SORT_NUMERIC);
                $previous = $active;
            }
            $last = count($groups) - 1;
            if ($last >= 0 && $groups[$last]['teams'] === $teams) {
                $groups[$last]['to'] = $date;
            } else {
                $groups[] = ['from' => $date, 'to' => $date, 'teams' => $teams];
                if (count($groups) > 128) {
                    return self::canonical($pdo, $base, $parameters, $team);
                }
            }
        }
        $clauses = [];
        $bindings = $parameters;
        foreach ($groups as $group) {
            $marks = implode(',', array_fill(0, count($group['teams']), '?'));
            $clause = 'rl.team_id IN (' . $marks . ')';
            if (in_array(0, $group['teams'], true)) {
                $clause = '(' . $clause . ' OR rl.team_id IS NULL)';
            }
            if (count($groups) > 1) {
                $clause = '(pt.datum BETWEEN ? AND ? AND ' . $clause . ')';
                array_push($bindings, $group['from'], $group['to']);
            }
            array_push($bindings, ...$group['teams']);
            $clauses[] = $clause;
            if (count($bindings) > 900) {
                return self::canonical($pdo, $base, $parameters, $team);
            }
        }
        $query = $pdo->prepare('SELECT COUNT(*) total,MIN(pt.datum) oldest_date' . $base
            . ' AND EXISTS (SELECT 1 FROM training_roster_links rl WHERE rl.plan_id=pt.id AND ('
            . implode(' OR ', $clauses) . '))');
        $query->execute($bindings);
        $row = $query->fetch(PDO::FETCH_ASSOC);
        return ['count' => (int) $row['total'], 'oldest_date' => $row['oldest_date'] ?: null];
    }

    /** @param array<int,list<int>> $adjacency @return list<int> */
    private static function reachable(array $adjacency, int $root): array
    {
        $seen = [$root => true];
        $queue = [$root];
        for ($i = 0; $i < count($queue); $i++) {
            foreach ($adjacency[$queue[$i]] ?? [] as $next) {
                if (!isset($seen[$next])) {
                    $seen[$next] = true;
                    $queue[] = $next;
                }
            }
        }
        return $queue;
    }

    private static function canonical(PDO $pdo, string $base, array $parameters, int $team): array
    {
        $query = $pdo->prepare('SELECT pt.id,pt.datum' . $base);
        $query->execute($parameters);
        return self::filtered($pdo, $query->fetchAll(PDO::FETCH_ASSOC), $team);
    }

    private static function filtered(PDO $pdo, array $rows, int $team): array
    {
        $rows = trainingRosterBridgeFilterPlanRows($pdo, $rows, $team);
        return ['count' => count($rows), 'oldest_date' => trainingPlannerOldestPendingDate($rows)];
    }
}
