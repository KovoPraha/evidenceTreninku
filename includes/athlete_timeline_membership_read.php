<?php

declare(strict_types=1);

require_once __DIR__ . '/roster_hierarchy.php';

/** Historical membership projection for one athlete/year/call; never a writer authorization source. */
final class AthleteTimelineMembershipRead
{
    private bool $loaded = false;
    /** @var list<array<string,mixed>> */
    private array $memberships = [];
    /** @var list<array<string,mixed>> */
    private array $edges = [];
    /** @var array<string,array<int,true>> */
    private array $targetsByDate = [];

    public function __construct(private PDO $pdo, private int $personId, private int $year)
    {
    }

    public function contains(int $teamId, string $date): bool
    {
        $date = kisRosterDate($date);
        if ($teamId < 1 || $this->personId < 1) {
            return false;
        }
        if (!$this->loaded) {
            $this->load();
        }
        if (!isset($this->targetsByDate[$date])) {
            $targets = [];
            foreach ($this->memberships as $membership) {
                if ($this->validOn($membership, $date)) {
                    $targets[(int) $membership['team_id']] = true;
                }
            }
            $parents = [];
            foreach ($this->edges as $edge) {
                if ($this->validOn($edge, $date)) {
                    $parents[(int) $edge['child_team_id']][] = (int) $edge['parent_team_id'];
                }
            }
            // Reverse traversal gives exactly the targets whose descendants include a member team.
            $queue = array_keys($targets);
            for ($i = 0; $i < count($queue); $i++) {
                foreach ($parents[$queue[$i]] ?? [] as $parent) {
                    if (!isset($targets[$parent])) {
                        $targets[$parent] = true;
                        $queue[] = $parent;
                    }
                }
            }
            $this->targetsByDate[$date] = $targets;
        }
        return isset($this->targetsByDate[$date][$teamId]);
    }

    /** @param array<string,mixed> $period */
    private function validOn(array $period, string $date): bool
    {
        return $period['valid_from'] <= $date && ($period['valid_to'] === null || $period['valid_to'] >= $date);
    }

    private function load(): void
    {
        $from = sprintf('%04d-01-01', $this->year);
        $to = sprintf('%04d-12-31', $this->year);
        if (kisRosterHierarchyHasTable($this->pdo, 'club_roster_members')) {
            // Keep state comparisons in SQL: database collation and NULL semantics are part of the existing contract.
            $query = $this->pdo->prepare('SELECT team_id,valid_from,valid_to FROM club_roster_members'
                . ' WHERE sportovec_id=? AND team_id IS NOT NULL AND valid_from<=? AND (valid_to IS NULL OR valid_to>=?)'
                . " AND status<>'cancelled' AND (status<>'removed' OR valid_to IS NOT NULL)");
            $query->execute([$this->personId, $to, $from]);
            $this->memberships = $query->fetchAll(PDO::FETCH_ASSOC);
            if ($this->memberships !== [] && kisRosterHierarchyHasTable($this->pdo, 'club_team_edges')) {
                $query = $this->pdo->prepare('SELECT parent_team_id,child_team_id,valid_from,valid_to FROM club_team_edges'
                    . ' WHERE valid_from<=? AND (valid_to IS NULL OR valid_to>=?)');
                $query->execute([$to, $from]);
                $this->edges = $query->fetchAll(PDO::FETCH_ASSOC);
            }
        }
        $this->loaded = true;
    }
}
