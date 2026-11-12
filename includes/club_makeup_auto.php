<?php

declare(strict_types=1);

require_once __DIR__ . '/club_program.php';

/** @return array<string,mixed>|null */
function clubMakeupAutoSettings(PDO $pdo): ?array
{
    if (!familyPortalTableExists($pdo, 'club_makeup_auto_settings')) {
        return null;
    }
    $row = $pdo->query('SELECT * FROM club_makeup_auto_settings WHERE id=1')->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function clubMakeupAutoEnabled(PDO $pdo): bool
{
    return (int) (clubMakeupAutoSettings($pdo)['enabled'] ?? 0) === 1;
}

function clubMakeupAutoSettingsSave(
    PDO $pdo,
    int $actorId,
    bool $enabled,
    int $excuseHours,
    int $validDays,
    int $maxCredits,
    int $cancelHours,
    bool $applyFuture = false
): void {
    if ($actorId < 1 || $excuseHours < 0 || $excuseHours > 720 || $validDays < 1 || $validDays > 730
        || $maxCredits < 0 || $maxCredits > 100 || $cancelHours < 0 || $cancelHours > 720) {
        throw new InvalidArgumentException('Neplatná společná pravidla náhrad.');
    }
    $before = clubMakeupAutoSettings($pdo);
    if ($before === null) {
        throw new ClubMakeupException('Nejprve aplikujte migraci automatických náhrad.');
    }
    $ownTransaction = !$pdo->inTransaction();
    if ($ownTransaction) {
        $pdo->beginTransaction();
    }
    try {
        $pdo->prepare('UPDATE club_makeup_auto_settings SET enabled=?,excuse_hours=?,credit_valid_days=?,max_credits=?, '
        . 'cancel_hours=?,updated_by_trainer_id=? WHERE id=1')->execute([
            $enabled ? 1 : 0, $excuseHours, $validDays, $maxCredits, $cancelHours, $actorId,
        ]);
        if ($enabled) {
            $offers = $pdo->query('SELECT o.id FROM club_program_offers o '
                . 'WHERE o.birth_year_from IS NOT NULL AND o.birth_year_to IS NOT NULL '
                . 'AND NOT EXISTS(SELECT 1 FROM club_makeup_offer_policies m WHERE m.offer_id=o.id)')
                ->fetchAll(PDO::FETCH_COLUMN);
            foreach ($offers as $offerId) {
                if (clubMakeupAutoOfferEnabled($pdo, (int) $offerId)) {
                    clubMakeupPolicySave($pdo, $actorId, (int) $offerId, true, $excuseHours, true);
                }
            }
            if ($applyFuture) {
                $rules = $pdo->query('SELECT m.offer_id,m.policy_id FROM club_makeup_offer_policies m '
                    . 'JOIN club_makeup_policies q ON q.id=m.policy_id AND q.enabled=1')->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rules as $rule) {
                    if (!clubMakeupAutoOfferEnabled($pdo, (int) $rule['offer_id'])) {
                        continue;
                    }
                    $hours = clubMakeupAutoValue($pdo, (int) $rule['offer_id'], 'excuse_hours');
                    $pdo->prepare('UPDATE club_makeup_excuse_rules SET excuse_hours=? WHERE policy_id=? '
                        . "AND plan_id IN (SELECT id FROM planovane_treninky WHERE stav='planovany' AND datum>=?)")
                        ->execute([$hours, (int) $rule['policy_id'], clubMakeupNow()->format('Y-m-d')]);
                }
            }
        }
        clubMakeupConfigEvent($pdo, 'auto_settings', 1, $actorId, $before, [
            'enabled' => $enabled, 'excuse_hours' => $excuseHours, 'credit_valid_days' => $validDays,
            'max_credits' => $maxCredits, 'cancel_hours' => $cancelHours,
            'apply_future' => $applyFuture,
        ]);
        if ($ownTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $error) {
        if ($ownTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

/** @return array<string,mixed>|null */
function clubMakeupAutoOfferSettings(PDO $pdo, int $offerId): ?array
{
    $query = $pdo->prepare('SELECT * FROM club_makeup_offer_auto WHERE offer_id=?');
    $query->execute([$offerId]);
    return $query->fetch(PDO::FETCH_ASSOC) ?: null;
}

function clubMakeupAutoOfferSave(
    PDO $pdo,
    int $actorId,
    int $offerId,
    bool $enabled,
    ?int $excuseHours,
    ?int $validDays,
    ?int $maxCredits,
    ?int $cancelHours
): void {
    if ($actorId < 1 || $offerId < 1 || ($excuseHours !== null && ($excuseHours < 0 || $excuseHours > 720))
        || ($validDays !== null && ($validDays < 1 || $validDays > 730))
        || ($maxCredits !== null && ($maxCredits < 0 || $maxCredits > 100))
        || ($cancelHours !== null && ($cancelHours < 0 || $cancelHours > 720))) {
        throw new InvalidArgumentException('Neplatná výjimka nabídky.');
    }
    $offer = $pdo->prepare('SELECT id FROM club_program_offers WHERE id=?');
    $offer->execute([$offerId]);
    if (!$offer->fetchColumn()) {
        throw new ClubMakeupException('Nabídka neexistuje.');
    }
    $ownTransaction = !$pdo->inTransaction();
    if ($ownTransaction) {
        $pdo->beginTransaction();
    }
    try {
        $before = clubMakeupAutoOfferSettings($pdo, $offerId);
        if ($before) {
            $pdo->prepare('UPDATE club_makeup_offer_auto SET enabled=?,excuse_hours=?,credit_valid_days=?, '
                . 'max_credits=?,cancel_hours=?,updated_by_trainer_id=? WHERE offer_id=?')->execute([
                    $enabled ? 1 : 0, $excuseHours, $validDays, $maxCredits, $cancelHours, $actorId, $offerId,
                ]);
        } else {
            $pdo->prepare('INSERT INTO club_makeup_offer_auto '
                . '(offer_id,enabled,excuse_hours,credit_valid_days,max_credits,cancel_hours,updated_by_trainer_id) '
                . 'VALUES(?,?,?,?,?,?,?)')->execute([
                    $offerId, $enabled ? 1 : 0, $excuseHours, $validDays, $maxCredits, $cancelHours, $actorId,
                ]);
        }
        if ($enabled && clubMakeupAutoEnabled($pdo)) {
            clubMakeupPolicySave(
                $pdo,
                $actorId,
                $offerId,
                true,
                clubMakeupAutoValue($pdo, $offerId, 'excuse_hours'),
                true
            );
        }
        clubMakeupConfigEvent($pdo, 'auto_offer', $offerId, $actorId, $before, [
            'enabled' => $enabled, 'excuse_hours' => $excuseHours, 'credit_valid_days' => $validDays,
            'max_credits' => $maxCredits, 'cancel_hours' => $cancelHours,
        ]);
        if ($ownTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $error) {
        if ($ownTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function clubMakeupAutoValue(PDO $pdo, int $offerId, string $name): int
{
    if (!in_array($name, ['excuse_hours', 'credit_valid_days', 'max_credits', 'cancel_hours'], true)) {
        throw new InvalidArgumentException('Neznámé pravidlo náhrad.');
    }
    $override = clubMakeupAutoOfferSettings($pdo, $offerId);
    $settings = clubMakeupAutoSettings($pdo);
    return (int) ($override[$name] ?? $settings[$name] ?? 0);
}

function clubMakeupAutoOfferEnabled(PDO $pdo, int $offerId): bool
{
    $query = $pdo->prepare('SELECT a.enabled FROM club_makeup_offer_auto a WHERE a.offer_id=?');
    $query->execute([$offerId]);
    $value = $query->fetchColumn();
    return $value === false || (int) $value === 1;
}

function clubMakeupAutoRouteException(PDO $pdo, int $sourceOfferId, int $targetOfferId): ?bool
{
    if (!familyPortalTableExists($pdo, 'club_makeup_route_exceptions')) {
        return null;
    }
    $query = $pdo->prepare('SELECT allowed FROM club_makeup_route_exceptions WHERE source_offer_id=? AND target_offer_id=?');
    $query->execute([$sourceOfferId, $targetOfferId]);
    $value = $query->fetchColumn();
    return $value === false ? null : (bool) $value;
}

function clubMakeupAutoRouteExceptionSave(
    PDO $pdo,
    int $actorId,
    int $sourceOfferId,
    int $targetOfferId,
    ?bool $allowed
): void {
    if ($actorId < 1 || $sourceOfferId < 1 || $targetOfferId < 1 || $sourceOfferId === $targetOfferId) {
        throw new InvalidArgumentException('Vyberte dvě různé nabídky.');
    }
    $query = $pdo->prepare('SELECT id FROM club_program_offers WHERE id IN (?,?)');
    $query->execute([$sourceOfferId, $targetOfferId]);
    if (count($query->fetchAll(PDO::FETCH_COLUMN)) !== 2) {
        throw new ClubMakeupException('Nabídka neexistuje.');
    }
    $before = clubMakeupAutoRouteException($pdo, $sourceOfferId, $targetOfferId);
    if ($allowed === null) {
        $pdo->prepare('DELETE FROM club_makeup_route_exceptions WHERE source_offer_id=? AND target_offer_id=?')
            ->execute([$sourceOfferId, $targetOfferId]);
    } elseif ($before === null) {
        $pdo->prepare('INSERT INTO club_makeup_route_exceptions '
            . '(source_offer_id,target_offer_id,allowed,updated_by_trainer_id) VALUES(?,?,?,?)')
            ->execute([$sourceOfferId, $targetOfferId, $allowed ? 1 : 0, $actorId]);
    } else {
        $pdo->prepare('UPDATE club_makeup_route_exceptions SET allowed=?,updated_by_trainer_id=? '
            . 'WHERE source_offer_id=? AND target_offer_id=?')
            ->execute([$allowed ? 1 : 0, $actorId, $sourceOfferId, $targetOfferId]);
    }
    if ($before !== $allowed) {
        clubMakeupConfigEvent(
            $pdo,
            'auto_route',
            $sourceOfferId,
            $actorId,
            ['target_offer_id' => $targetOfferId, 'allowed' => $before],
            ['target_offer_id' => $targetOfferId, 'allowed' => $allowed]
        );
    }
}

function clubMakeupAutoRouteAllowed(PDO $pdo, int $sourceOfferId, int $targetOfferId): bool
{
    if (!clubMakeupAutoEnabled($pdo) || !clubMakeupAutoOfferEnabled($pdo, $sourceOfferId)
        || !clubMakeupAutoOfferEnabled($pdo, $targetOfferId)) {
        return false;
    }
    $query = $pdo->prepare('SELECT birth_year_from,birth_year_to FROM club_program_offers WHERE id IN (?,?) ORDER BY id');
    $query->execute([$sourceOfferId, $targetOfferId]);
    $rows = $query->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) !== 2) {
        return false;
    }
    foreach ($rows as $row) {
        if ($row['birth_year_from'] === null || $row['birth_year_to'] === null) {
            return false;
        }
    }
    return max((int) $rows[0]['birth_year_from'], (int) $rows[1]['birth_year_from'])
        <= min((int) $rows[0]['birth_year_to'], (int) $rows[1]['birth_year_to']);
}

function clubMakeupAutoPlanExcluded(PDO $pdo, int $planId): bool
{
    $query = $pdo->prepare('SELECT 1 FROM club_makeup_plan_exclusions WHERE plan_id=?');
    $query->execute([$planId]);
    return (bool) $query->fetchColumn();
}

function clubMakeupAutoPlanExclusionSave(PDO $pdo, int $actorId, int $planId, bool $excluded): void
{
    if ($actorId < 1 || $planId < 1) {
        throw new InvalidArgumentException('Neplatný termín.');
    }
    $query = $pdo->prepare('SELECT id FROM planovane_treninky WHERE id=?');
    $query->execute([$planId]);
    if (!$query->fetchColumn()) {
        throw new ClubMakeupException('Termín neexistuje.');
    }
    $before = clubMakeupAutoPlanExcluded($pdo, $planId);
    if ($excluded && !$before) {
        $pdo->prepare('INSERT INTO club_makeup_plan_exclusions(plan_id,updated_by_trainer_id) VALUES(?,?)')
            ->execute([$planId, $actorId]);
    } elseif (!$excluded && $before) {
        $pdo->prepare('DELETE FROM club_makeup_plan_exclusions WHERE plan_id=?')->execute([$planId]);
    }
    if ($before !== $excluded) {
        clubMakeupConfigEvent(
            $pdo,
            'auto_plan',
            $planId,
            $actorId,
            ['excluded' => $before],
            ['excluded' => $excluded]
        );
    }
}

/** @param array<string,mixed> $offer */
function clubMakeupAutoFreePlaces(PDO $pdo, array $offer, int $planId, bool $lock = false): int
{
    if ($offer['capacity'] === null || (int) $offer['capacity'] < 1) {
        return 0;
    }
    $capacity = clubProgramOfferCapacityState($pdo, $offer, null, $lock);
    $query = $pdo->prepare("SELECT COUNT(*) FROM club_makeup_reservations WHERE target_plan_id=? AND status='booked'");
    $query->execute([$planId]);
    return max(0, (int) ($capacity['available_count'] ?? 0) - (int) $query->fetchColumn());
}

/** @param array<string,mixed> $offer */
function clubMakeupAutoLockCapacityTeams(PDO $pdo, array $offer): void
{
    $ids = clubProgramPhysicalTeamIds($pdo, $offer);
    sort($ids, SORT_NUMERIC);
    $sql = 'SELECT id FROM club_teams WHERE id=?';
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $sql .= ' FOR UPDATE';
    }
    $lock = $pdo->prepare($sql);
    foreach ($ids as $id) {
        $lock->execute([$id]);
        if ($lock->fetchColumn() === false) {
            throw new ClubMakeupException('Soupiska cílové nabídky neexistuje.');
        }
    }
}

/** Maximum booked makeup guests on a future plan covered by this offer. */
function clubMakeupFutureReservedForOffer(PDO $pdo, array $offer): int
{
    if (!familyPortalTableExists($pdo, 'club_makeup_reservations')) {
        return 0;
    }
    $query = $pdo->prepare("SELECT p.id,p.datum,COUNT(*) AS booked FROM club_makeup_reservations r "
        . 'JOIN planovane_treninky p ON p.id=r.target_plan_id '
        . "WHERE r.status='booked' AND p.stav='planovany' AND p.datum>=? AND p.datum<=? "
        . 'GROUP BY p.id,p.datum');
    $query->execute([max(clubMakeupNow()->format('Y-m-d'), (string) $offer['starts_on']), (string) $offer['ends_on']]);
    $maximum = 0;
    foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (clubMakeupPlanCoversTeam($pdo, (int) $row['id'], (int) $offer['team_id'], (string) $row['datum'])) {
            $maximum = max($maximum, (int) $row['booked']);
        }
    }
    return $maximum;
}

/** @param array<string,mixed> $credit @return list<array<string,mixed>> */
function clubMakeupAutoCandidates(PDO $pdo, array $credit, int $personId, ?int $onlyPlanId = null): array
{
    if (!clubMakeupAutoEnabled($pdo)
        || !clubMakeupAutoOfferEnabled($pdo, (int) $credit['source_offer_id'])) {
        return [];
    }
    $birth = $pdo->prepare('SELECT narozeni FROM sportovci WHERE id=?');
    $birth->execute([$personId]);
    $born = (string) $birth->fetchColumn();
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $born) !== 1) {
        return [];
    }
    $year = (int) substr($born, 0, 4);
    $sql = 'SELECT o.*,p.id AS plan_id,p.nazev,p.datum,p.cas_od,p.stav,p.trener_id FROM planovane_treninky p '
        . 'JOIN club_program_offers o ON o.starts_on<=p.datum AND o.ends_on>=p.datum '
        . 'JOIN club_makeup_offer_policies m ON m.offer_id=o.id '
        . 'JOIN club_makeup_policies q ON q.id=m.policy_id AND q.enabled=1 '
        . "WHERE p.stav='planovany' AND p.datum>=? AND p.datum<=? AND o.status='active' "
        . 'AND o.birth_year_from<=? AND o.birth_year_to>=? AND o.capacity IS NOT NULL '
        . 'AND NOT EXISTS(SELECT 1 FROM club_makeup_plan_exclusions x WHERE x.plan_id=p.id) '
        . 'AND NOT EXISTS(SELECT 1 FROM club_makeup_slots s WHERE s.plan_id=p.id)';
    $params = [clubMakeupNow()->format('Y-m-d'), (string) $credit['expires_on'], $year, $year];
    if ($onlyPlanId !== null) {
        $sql .= ' AND p.id=?';
        $params[] = $onlyPlanId;
    }
    $sql .= ' ORDER BY p.datum,p.cas_od,p.id,o.id';
    $query = $pdo->prepare($sql);
    $query->execute($params);
    $candidates = [];
    $teamsByPlan = [];
    foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $planId = (int) $row['plan_id'];
        $teamId = (int) $row['team_id'];
        if ((int) $row['trener_id'] < 1 || $planId === (int) $credit['source_plan_id']
            || !clubMakeupPlanCoversTeam($pdo, $planId, $teamId, (string) $row['datum'])
            || !clubMakeupRouteAllowed($pdo, (int) $credit['source_offer_id'], (int) $row['id'])
            || !clubMakeupAutoOfferEnabled($pdo, (int) $row['id'])
            || clubMakeupPersonIsRegular($pdo, $planId, $personId, (string) $row['datum'])
            || clubMakeupStart($row) <= clubMakeupNow()
            || !clubMakeupCreditUsableOn($pdo, $credit, (string) $row['datum'])) {
            continue;
        }
        $teamsByPlan[$planId][$teamId] = true;
        if (isset($candidates[$planId])) {
            continue;
        }
        $free = clubMakeupAutoFreePlaces($pdo, $row, $planId);
        if ($free < 1) {
            continue;
        }
        $row['offer_id'] = (int) $row['id'];
        $row['offer_name'] = $row['name'];
        $row['offer_capacity'] = (int) $row['capacity'];
        $row['booked'] = 0;
        $row['capacity'] = min(500, $free);
        $row['birth_year_from'] = (int) $row['birth_year_from'];
        $row['birth_year_to'] = (int) $row['birth_year_to'];
        $candidates[$planId] = $row;
    }
    foreach ($teamsByPlan as $planId => $teamIds) {
        if (count($teamIds) !== 1) {
            unset($candidates[$planId]);
        }
    }
    return array_values($candidates);
}
