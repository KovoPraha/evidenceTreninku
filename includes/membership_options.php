<?php

declare(strict_types=1);

/** Existing storage and payment references retain the monthly prefix for compatibility. */
function membershipInterval(array $tariff): string
{
    $interval = (string)($tariff['billing_interval'] ?? 'month');
    if (!in_array($interval, ['month', 'year'], true)) {
        throw new DomainException('Neplatný interval členství.');
    }
    return $interval;
}

function membershipIntervalLabel(array $tariff): string
{
    return membershipInterval($tariff) === 'year' ? 'rok' : 'měsíc';
}

function membershipPeriodEnd(string $startsOn, string $today, string $interval): string
{
    if ($interval === 'month') {
        return (new DateTimeImmutable($today))->format('Y-m-t');
    }
    $start = new DateTimeImmutable(substr($startsOn, 0, 7) . '-01');
    while ($start->modify('+12 months')->format('Y-m-d') <= $today) {
        $start = $start->modify('+12 months');
    }
    return $start->modify('+12 months -1 day')->format('Y-m-d');
}

/** @return list<int> */
function membershipTeamIds(array $values): array
{
    $ids = [];
    foreach ($values as $value) {
        if ((!is_int($value) && !is_string($value)) || !ctype_digit((string)$value) || (int)$value < 1) {
            throw new InvalidArgumentException('Neplatná disciplína.');
        }
        $ids[] = (int)$value;
    }
    $ids = array_values(array_unique($ids));
    sort($ids, SORT_NUMERIC);
    return $ids;
}

/** @return list<array<string,mixed>> */
function membershipTariffTeams(PDO $pdo, int $tariffId): array
{
    $q = $pdo->prepare('SELECT t.id,t.name FROM monthly_membership_tariff_teams tt JOIN club_teams t ON t.id=tt.team_id '
        . 'WHERE tt.tariff_id=? ORDER BY t.name,t.id');
    $q->execute([$tariffId]);
    return $q->fetchAll(PDO::FETCH_ASSOC);
}

/** Validate only direct, active roster children. A group is never a direct membership target. */
function membershipValidateTeams(PDO $pdo, int $parent, array $ids, string $date): void
{
    $q = $pdo->prepare('SELECT * FROM club_teams WHERE id=?' . accountingDocumentLock($pdo));
    $q->execute([$parent]);
    $team = $q->fetch(PDO::FETCH_ASSOC);
    if (!$team || $team['status'] !== 'active') {
        throw new DomainException('Skupina nebo soupiska není aktivní.');
    }
    if ($ids === []) {
        if (($team['team_kind'] ?? 'roster') === 'group') {
            throw new DomainException('Vyberte alespoň jednu disciplínu ve skupině.');
        }
        return;
    }
    if (($team['team_kind'] ?? 'roster') !== 'group') {
        throw new DomainException('Disciplíny lze vybírat pouze pod skupinou.');
    }
    foreach ($ids as $id) {
        $q = $pdo->prepare("SELECT t.id FROM club_teams t JOIN club_team_edges e ON e.child_team_id=t.id "
            . "WHERE t.id=? AND t.status='active' AND t.team_kind='roster' AND e.parent_team_id=? "
            . 'AND e.valid_from<=? AND (e.valid_to IS NULL OR e.valid_to>=?)' . accountingDocumentLock($pdo));
        $q->execute([$id, $parent, $date, $date]);
        if (!$q->fetchColumn()) {
            throw new DomainException('Vybraná disciplína již není aktivní soupiskou této skupiny. Obnovte nabídku.');
        }
    }
}
