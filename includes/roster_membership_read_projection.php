<?php

declare(strict_types=1);

require_once __DIR__ . '/roster_hierarchy.php';

/** Historical read data for explicit people during one call, never writer authorization. */
final class RosterMembershipReadProjection
{
    private bool $loaded = false;
    /** @var list<int> */
    private array $personIds;
    /** @var list<array<string,mixed>> */
    private array $memberships = [];
    /** @var list<array<string,mixed>> */
    private array $edges = [];
    /** @var array<string,array<int,array<int,true>>> */
    private array $targetsByDate = [];

    /** @param list<int> $personIds */
    public function __construct(private PDO $pdo, array $personIds, private ?string $from, private string $to)
    {
        // Preserve the caller's explicit authorized IDs, including legacy zero IDs.
        $this->personIds = array_values(array_unique($personIds));
    }

    /** @return array<int,true> */
    public function membersForTarget(int $teamId, string $date): array
    {
        $date = kisRosterDate($date);
        if ($teamId < 1 || $this->personIds === []) {
            return [];
        }
        if (!$this->loaded) {
            $this->load();
        }
        if (!isset($this->targetsByDate[$date])) {
            $members = [];
            foreach ($this->memberships as $row) {
                if ($this->validOn($row, $date)) {
                    $members[(int) $row['sportovec_id']][(int) $row['team_id']] = true;
                }
            }
            $parents = [];
            foreach ($this->edges as $edge) {
                if ($this->validOn($edge, $date)) {
                    $parents[(int) $edge['child_team_id']][] = (int) $edge['parent_team_id'];
                }
            }
            $targets = [];
            foreach ($members as $personId => $teams) {
                $queue = array_keys($teams);
                for ($i = 0; $i < count($queue); $i++) {
                    $targets[$queue[$i]][$personId] = true;
                    foreach ($parents[$queue[$i]] ?? [] as $parent) {
                        if (!isset($teams[$parent])) {
                            $teams[$parent] = true;
                            $queue[] = $parent;
                        }
                    }
                }
            }
            $this->targetsByDate[$date] = $targets;
        }
        return $this->targetsByDate[$date][$teamId] ?? [];
    }

    /** @param array<string,mixed> $period */
    private function validOn(array $period, string $date): bool
    {
        return $period['valid_from'] <= $date && ($period['valid_to'] === null || $period['valid_to'] >= $date);
    }

    private function load(): void
    {
        if (kisRosterHierarchyHasTable($this->pdo, 'club_roster_members')) {
            // A long calendar event may start before its requested window: a null lower bound keeps that history.
            $period = 'valid_from<=?' . ($this->from === null ? '' : ' AND (valid_to IS NULL OR valid_to>=?)');
            $parameters = $this->from === null ? [$this->to] : [$this->to, $this->from];
            foreach (array_chunk($this->personIds, 400) as $chunk) {
                $marks = implode(',', array_fill(0, count($chunk), '?'));
                $query = $this->pdo->prepare('SELECT sportovec_id,team_id,valid_from,valid_to FROM club_roster_members'
                    . " WHERE sportovec_id IN ($marks) AND team_id IS NOT NULL AND " . $period
                    . " AND status<>'cancelled' AND (status<>'removed' OR valid_to IS NOT NULL)");
                $query->execute([...$chunk, ...$parameters]);
                foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $this->memberships[] = $row;
                }
            }
            if ($this->memberships !== [] && kisRosterHierarchyHasTable($this->pdo, 'club_team_edges')) {
                $query = $this->pdo->prepare('SELECT parent_team_id,child_team_id,valid_from,valid_to FROM club_team_edges WHERE ' . $period);
                $query->execute($parameters);
                $this->edges = $query->fetchAll(PDO::FETCH_ASSOC);
            }
        }
        $this->loaded = true;
    }
}
