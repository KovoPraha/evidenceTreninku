<?php

declare(strict_types=1);

require_once __DIR__ . '/staff_workspaces.php';
require_once __DIR__ . '/member_charge_read.php';

/** Training operations use active employment and ownership, not roster grants. */
function coachTrainingActorAllowed(PDO $pdo): bool
{
    $actor = (int)($_SESSION['trener_id'] ?? 0);
    $position = staffActivePosition();
    if ($actor < 1 || !in_array($position, ['coach', 'sports_lead'], true)) {
        return false;
    }
    $lock = $pdo->inTransaction() && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    $q = $pdo->prepare('SELECT id FROM treneri WHERE id=? AND aktivni=1' . $lock);
    $q->execute([$actor]);
    if (!$q->fetchColumn()) {
        return false;
    }
    $q = $pdo->prepare('SELECT trainer_id FROM staff_user_positions WHERE trainer_id=? AND position_code=?' . $lock);
    $q->execute([$actor, $position]);
    if ($q->fetchColumn()) {
        return true;
    }
    if (!memberChargeReadTableExists($pdo, 'staff_superadmins')) {
        return false;
    }
    $q = $pdo->prepare('SELECT trainer_id FROM staff_superadmins WHERE trainer_id=?' . $lock);
    $q->execute([$actor]);
    return (bool)$q->fetchColumn();
}

function coachTrainingRequireActor(PDO $pdo): void
{
    if (!coachTrainingActorAllowed($pdo)) {
        throw new RuntimeException('Pracovní oprávnění trenéra již neplatí.');
    }
}

/** Only basic participant selection is broadened; private profiles retain their grants. */
function coachTrainingPersonPredicate(PDO $pdo): string
{
    return coachTrainingActorAllowed($pdo) ? '1=1' : '0=1';
}

function coachTrainingAssertPeople(PDO $pdo, array $ids): void
{
    coachTrainingRequireActor($pdo);
    $q = $pdo->prepare('SELECT id FROM sportovci WHERE id=?');
    foreach (array_unique(array_map('intval', $ids)) as $id) {
        $q->execute([$id]);
        if ($id < 1 || !$q->fetchColumn()) {
            throw new RuntimeException('Vybraný sportovec neexistuje.');
        }
    }
}

function coachTrainingAssertTeams(PDO $pdo, array $ids, ?string $date = null): void
{
    coachTrainingRequireActor($pdo);
    require_once __DIR__ . '/roster_hierarchy.php';
    kisRosterDate($date ?? date('Y-m-d'));
    $q = $pdo->prepare("SELECT id FROM club_teams WHERE id=? AND status='active'");
    foreach (array_unique(array_map('intval', $ids)) as $id) {
        $q->execute([$id]);
        if ($id < 1 || !$q->fetchColumn()) {
            throw new RuntimeException('Vybraná skupina nebo soupiska není aktivní pro datum tréninku.');
        }
    }
}

/** Assignment is a display preference; it never limits a training substitute. */
function coachTrainingPreferredTeams(PDO $pdo, string $date): array
{
    if (!memberChargeReadTableExists($pdo, 'coach_scope_grants')) {
        return [];
    }
    $q = $pdo->prepare("SELECT scope_id FROM coach_scope_grants WHERE trainer_id=? AND scope_kind='team' "
        . 'AND revoked_at IS NULL AND valid_from<=CURRENT_TIMESTAMP AND (valid_to IS NULL OR valid_to>CURRENT_TIMESTAMP)');
    $q->execute([(int)($_SESSION['trener_id'] ?? 0)]);
    require_once __DIR__ . '/roster_hierarchy.php';
    $ids = [];
    foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $ids[(int)$id] = true;
        foreach (kisRosterDescendantIds($pdo, (int)$id, $date) as $child) {
            $ids[(int)$child] = true;
        }
    }
    return $ids;
}

function coachTrainingPreferredPersonPredicate(PDO $pdo, string $alias = 's.id'): string
{
    $base = coachScopePersonPredicate($pdo, $alias);
    $ids = array_keys(coachTrainingPreferredTeams($pdo, date('Y-m-d')));
    if ($ids === [] || !memberChargeReadTableExists($pdo, 'club_roster_members')) {
        return $base;
    }
    $teams = implode(',', array_map('intval', $ids));
    return '(' . $base . " OR EXISTS(SELECT 1 FROM club_roster_members pref WHERE pref.sportovec_id=$alias "
        . "AND pref.team_id IN ($teams) AND pref.status='active' AND pref.valid_from<=CURRENT_DATE "
        . 'AND (pref.valid_to IS NULL OR pref.valid_to>=CURRENT_DATE)))';
}
