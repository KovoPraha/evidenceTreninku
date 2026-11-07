<?php

declare(strict_types=1);

require_once __DIR__ . '/training_rsvp.php';

/** One fresh planner GET projection. No authority or membership data survives this call. */
final class TrainingPlannerRsvpRead
{
    public static function summaries(PDO $pdo, array $planIds): array
    {
        // Preserve the canonical path and its hierarchy locks inside transactions.
        if ($pdo->inTransaction()) {
            return trainingRsvpPlanSummaries($pdo, $planIds);
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $planIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }
        $scope = FamilySchemaReadScope::begin($pdo);
        try {
            if (!trainingRsvpHasTables($pdo, ['training_rsvps', 'training_roster_links', 'club_roster_members', 'planovane_treninky'])) {
                return [];
            }
            $empty = ['expected' => 0, 'going' => 0, 'not_going' => 0, 'pending' => 0];
            $result = array_fill_keys($ids, $empty);
            if (!familyPortalTableExists($pdo, 'sportovci')) {
                return $result;
            }
            $plans = [];
            $targets = [];
            foreach (array_chunk($ids, 500) as $chunk) {
                $marks = implode(',', array_fill(0, count($chunk), '?'));
                $q = $pdo->prepare("SELECT id,datum FROM planovane_treninky WHERE id IN ($marks)");
                $q->execute($chunk);
                foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $plans[(int)$row['id']] = $row['datum'];
                }
                $q = $pdo->prepare("SELECT plan_id,team_id FROM training_roster_links WHERE plan_id IN ($marks)");
                $q->execute($chunk);
                foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $targets[(int)$row['plan_id']][] = (int)$row['team_id'];
                }
            }
            $dates = [];
            foreach ($plans as $id => $date) {
                if (is_string($date) && !empty($targets[$id])) {
                    $dates[$id] = kisRosterDate($date);
                }
            }
            if ($dates === []) {
                return $result;
            }
            $edges = [];
            if (kisRosterHierarchyHasTable($pdo, 'club_team_edges')) {
                $q = $pdo->prepare('SELECT parent_team_id,child_team_id,valid_from,valid_to FROM club_team_edges
                    WHERE valid_from<=? AND (valid_to IS NULL OR valid_to>=?)');
                $q->execute([max($dates), min($dates)]);
                $edges = $q->fetchAll(PDO::FETCH_ASSOC);
            }
            $graphs = [];
            $expanded = [];
            $allTeams = [];
            foreach ($dates as $id => $date) {
                if (!isset($graphs[$date])) {
                    $graphs[$date] = [];
                    foreach ($edges as $edge) {
                        if ($edge['valid_from'] <= $date && ($edge['valid_to'] === null || $edge['valid_to'] >= $date)) {
                            $graphs[$date][(int)$edge['parent_team_id']][] = (int)$edge['child_team_id'];
                        }
                    }
                }
                $seen = [];
                $queue = array_values(array_unique(array_filter($targets[$id], static fn (int $team): bool => $team > 0)));
                foreach ($queue as $team) {
                    $seen[$team] = true;
                }
                for ($i = 0; $i < count($queue); $i++) {
                    foreach ($graphs[$date][$queue[$i]] ?? [] as $child) {
                        if (!isset($seen[$child])) {
                            $seen[$child] = true;
                            $queue[] = $child;
                        }
                    }
                }
                $expanded[$id] = $queue;
                $allTeams += $seen;
            }
            if ($allTeams === []) {
                return $result;
            }
            $members = [];
            foreach (array_chunk(array_keys($allTeams), 500) as $chunk) {
                $marks = implode(',', array_fill(0, count($chunk), '?'));
                $q = $pdo->prepare("SELECT m.team_id,m.sportovec_id,m.valid_from,m.valid_to FROM club_roster_members m
                    JOIN sportovci s ON s.id=m.sportovec_id WHERE m.team_id IN ($marks) AND m.valid_from<=?
                    AND (m.valid_to IS NULL OR m.valid_to>=?) AND m.status<>'cancelled'
                    AND (m.status<>'removed' OR m.valid_to IS NOT NULL)");
                $q->execute([...$chunk, max($dates), min($dates)]);
                foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $members[(int)$row['team_id']][] = $row;
                }
            }
            $eligible = [];
            foreach ($expanded as $id => $teams) {
                foreach ($teams as $team) {
                    foreach ($members[$team] ?? [] as $member) {
                        if ($member['valid_from'] <= $dates[$id] && ($member['valid_to'] === null || $member['valid_to'] >= $dates[$id])) {
                            $eligible[$id][(int)$member['sportovec_id']] = true;
                        }
                    }
                }
                $result[$id]['expected'] = count($eligible[$id] ?? []);
                $result[$id]['pending'] = $result[$id]['expected'];
            }
            foreach (array_chunk($ids, 500) as $chunk) {
                $marks = implode(',', array_fill(0, count($chunk), '?'));
                $q = $pdo->prepare("SELECT plan_id,sportovec_id,response FROM training_rsvps WHERE plan_id IN ($marks)");
                $q->execute($chunk);
                foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $id = (int)$row['plan_id'];
                    if (isset($eligible[$id][(int)$row['sportovec_id']]) && in_array($row['response'], ['going', 'not_going'], true)) {
                        $result[$id][$row['response']]++;
                        $result[$id]['pending']--;
                    }
                }
            }
            return $result;
        } finally {
            $scope->close();
        }
    }
}
