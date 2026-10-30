<?php

declare(strict_types=1);

require_once __DIR__ . '/roster_hierarchy.php';

/** One fresh read projection; never shared with a later write or request. */
final class TrainingRosterFilterRead
{
    /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
    public static function filter(PDO $pdo, array $rows, int $filterTeamId, string $ownerColumn): array
    {
        if (!in_array($ownerColumn, ['plan_id', 'trenink_id'], true)) {
            throw new InvalidArgumentException('Unsupported training owner.');
        }
        if ($filterTeamId < 1 || $rows === []) {
            return $rows;
        }
        $ids = array_values(array_unique(array_map(static fn (array $row): int => (int) $row['id'], $rows)));
        $links = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            $marks = implode(',', array_fill(0, count($chunk), '?'));
            $query = $pdo->prepare("SELECT $ownerColumn,team_id FROM training_roster_links WHERE $ownerColumn IN ($marks)");
            $query->execute($chunk);
            foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $link) {
                $links[(int) $link[$ownerColumn]][] = (int) $link['team_id'];
            }
        }
        $dates = [];
        foreach ($rows as $row) {
            $rawDate = (string) $row['datum'];
            $dates[$rawDate] ??= kisRosterDate($rawDate);
        }
        $edges = [];
        if (kisRosterHierarchyHasTable($pdo, 'club_team_edges')) {
            $query = $pdo->prepare('SELECT parent_team_id,child_team_id,valid_from,valid_to FROM club_team_edges'
                . ' WHERE valid_from<=? AND (valid_to IS NULL OR valid_to>=?)');
            $query->execute([max($dates), min($dates)]);
            $edges = $query->fetchAll(PDO::FETCH_ASSOC);
        }
        $cache = [];
        $children = [];
        $childrenDate = null;
        $descendants = static function (int $teamId, string $date) use ($edges, &$cache, &$children, &$childrenDate): array {
            if ($teamId < 1) {
                return [];
            }
            if (isset($cache[$teamId][$date])) {
                return $cache[$teamId][$date];
            }
            if ($childrenDate !== $date) {
                $children = [];
                foreach ($edges as $edge) {
                    if ($edge['valid_from'] <= $date && ($edge['valid_to'] === null || $edge['valid_to'] >= $date)) {
                        $children[(int) $edge['parent_team_id']][] = (int) $edge['child_team_id'];
                    }
                }
                $childrenDate = $date;
            }
            $seen = [$teamId => true];
            $queue = [$teamId];
            for ($i = 0; $i < count($queue); $i++) {
                foreach ($children[$queue[$i]] ?? [] as $child) {
                    if (!isset($seen[$child])) {
                        $seen[$child] = true;
                        $queue[] = $child;
                    }
                }
            }
            return $cache[$teamId][$date] = $queue;
        };
        $filtered = [];
        foreach ($rows as $row) {
            $date = $dates[(string) $row['datum']];
            $filterDescendants = $descendants($filterTeamId, $date);
            foreach ($links[(int) $row['id']] ?? [] as $target) {
                $targetDescendants = $descendants($target, $date);
                if (in_array($target, $filterDescendants, true) || in_array($filterTeamId, $targetDescendants, true)) {
                    $filtered[] = $row;
                    break;
                }
            }
        }
        return $filtered;
    }
}
