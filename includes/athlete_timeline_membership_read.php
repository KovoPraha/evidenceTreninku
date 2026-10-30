<?php

declare(strict_types=1);

require_once __DIR__ . '/roster_membership_read_projection.php';

/** One-person/year adapter; shares the historical contract with the private calendar. */
final class AthleteTimelineMembershipRead
{
    private RosterMembershipReadProjection $projection;

    public function __construct(PDO $pdo, private int $personId, int $year)
    {
        $this->projection = new RosterMembershipReadProjection($pdo, [$personId], sprintf('%04d-01-01', $year), sprintf('%04d-12-31', $year));
    }

    public function contains(int $teamId, string $date): bool
    {
        if ($this->personId < 1) {
            kisRosterDate($date);
            return false;
        }
        return isset($this->projection->membersForTarget($teamId, $date)[$this->personId]);
    }
}
