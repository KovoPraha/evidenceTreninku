<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/roster_hierarchy.php';

function w82FilterTrainingBaseline(PDO $pdo, array $rows, int $filterTeamId): array
{
    if ($filterTeamId < 1 || $rows === []) {
        return $rows;
    }
    $ids = array_values(array_unique(array_map(static fn (array $row): int => (int)$row['id'], $rows)));
    $links = [];
    foreach (array_chunk($ids, 500) as $chunk) {
        $marks = implode(',', array_fill(0, count($chunk), '?'));
        $stmt = $pdo->prepare("SELECT trenink_id,team_id FROM training_roster_links WHERE trenink_id IN ($marks)");
        $stmt->execute($chunk);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $link) {
            $links[(int)$link['trenink_id']][] = (int)$link['team_id'];
        }
    }
    $cache = [];
    return array_values(array_filter($rows, static function (array $row) use ($pdo, $filterTeamId, $links, &$cache): bool {
        $date = (string)$row['datum'];
        $filterDescendants = $cache[$filterTeamId][$date] ??= kisRosterDescendantIds($pdo, $filterTeamId, $date);
        foreach ($links[(int)$row['id']] ?? [] as $targetId) {
            $targetDescendants = $cache[$targetId][$date] ??= kisRosterDescendantIds($pdo, $targetId, $date);
            if (in_array($targetId, $filterDescendants, true) || in_array($filterTeamId, $targetDescendants, true)) {
                return true;
            }
        }
        return false;
    }));
}

/** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
function w82FilterPlanBaseline(PDO $pdo, array $rows, int $filterTeamId): array
{
    if ($filterTeamId < 1 || $rows === []) {
        return $rows;
    }
    $ids = array_values(array_unique(array_map(static fn (array $row): int => (int)$row['id'], $rows)));
    $links = [];
    foreach (array_chunk($ids, 500) as $chunk) {
        $marks = implode(',', array_fill(0, count($chunk), '?'));
        $stmt = $pdo->prepare("SELECT plan_id,team_id FROM training_roster_links WHERE plan_id IN ($marks)");
        $stmt->execute($chunk);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $link) {
            $links[(int)$link['plan_id']][] = (int)$link['team_id'];
        }
    }
    $cache = [];
    return array_values(array_filter($rows, static function (array $row) use ($pdo, $filterTeamId, $links, &$cache): bool {
        $date = (string)$row['datum'];
        $filterDescendants = $cache[$filterTeamId][$date] ??= kisRosterDescendantIds($pdo, $filterTeamId, $date);
        foreach ($links[(int)$row['id']] ?? [] as $targetId) {
            $targetDescendants = $cache[$targetId][$date] ??= kisRosterDescendantIds($pdo, $targetId, $date);
            if (in_array($targetId, $filterDescendants, true) || in_array($filterTeamId, $targetDescendants, true)) {
                return true;
            }
        }
        return false;
    }));
}
