<?php

declare(strict_types=1);

require_once __DIR__ . '/roster_message.php';

/** Fresh decision for one queued recipient; no shared authority cache. */
function rosterMessageRecipientAllowed(PDO $pdo, array $row): bool
{
    rosterMessageText((string) $row['subject_plain'], 255, 'Předmět');
    rosterMessageText((string) $row['body_plain'], 10000, 'Zpráva');
    $date = (new DateTimeImmutable('today'))->format('Y-m-d');
    $teamId = (int) $row['team_id'];
    $team = $pdo->prepare('SELECT t.id FROM club_teams t LEFT JOIN club_seasons s ON s.id=t.season_id '
        . "WHERE t.id=? AND t.status='active' AND (t.season_id IS NULL OR s.status='active')");
    $team->execute([$teamId]);
    if ($team->fetchColumn() === false) {
        throw new RosterMessageException('Aktivní skupina nebo soupiska nebyla nalezena.');
    }
    $teamIds = kisRosterDescendantIds($pdo, $teamId, $date);
    if (!$teamIds || !kisRosterHierarchyHasTable($pdo, 'club_roster_members')) {
        return false;
    }
    $at = $date === date('Y-m-d') ? date('Y-m-d H:i:s') : $date . ' 00:00:00';
    $marks = implode(',', array_fill(0, count($teamIds), '?'));
    $account = $pdo->prepare('SELECT a.email FROM verejni_uzivatele a WHERE a.id=? AND a.aktivni=1 AND a.email_overeno=1 '
        . 'AND EXISTS(SELECT 1 FROM account_person_roles r JOIN club_roster_members m ON m.sportovec_id=r.sportovec_id '
        . "WHERE r.account_id=a.id AND r.status='approved' AND r.relation_role IN ('self','guardian') "
        . 'AND r.valid_from<=? AND (r.valid_to IS NULL OR r.valid_to>?) '
        . "AND m.team_id IN ($marks) AND m.valid_from<=? AND (m.valid_to IS NULL OR m.valid_to>=?) "
        . "AND m.status<>'cancelled' AND (m.status<>'removed' OR m.valid_to IS NOT NULL))");
    $account->execute([(int) $row['account_id'], $at, $at, ...$teamIds, $date, $date]);
    $email = $account->fetchColumn();
    return is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false && $email === $row['recipient_email'];
}
