<?php
declare(strict_types=1);

/** End only memberships of the dedicated production UAT athletes and team. */
function kisUatCleanupRosterMemberships(PDO $pdo, string $today): int
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d',$today);
    if (!$date || $date->format('Y-m-d') !== $today) {
        throw new InvalidArgumentException('Invalid UAT cleanup date.');
    }
    $lock = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    $query = $pdo->prepare(
        "SELECT m.id,m.valid_from FROM club_roster_members m JOIN club_teams t ON t.id=m.team_id "
        . "JOIN sportovci s ON s.id=m.sportovec_id "
        . "WHERE t.code='TEST-UAT-DETI' AND m.source='admin' AND m.status='active' "
        . "AND LOWER(s.prijmeni)='tester' AND (LOWER(s.jmeno) IN ('ema','adam') OR s.jmeno LIKE 'Platba%')"
        . $lock
    );
    $query->execute();
    $members = $query->fetchAll(PDO::FETCH_ASSOC);
    $update = $pdo->prepare(
        'UPDATE club_roster_members SET status=?,valid_to=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND status=\'active\''
    );
    $changed = 0;
    foreach ($members as $member) {
        $future = $today < (string)$member['valid_from'];
        $update->execute([$future ? 'cancelled' : 'removed', $future ? $member['valid_from'] : $today, (int)$member['id']]);
        $changed += $update->rowCount();
    }
    return $changed;
}
