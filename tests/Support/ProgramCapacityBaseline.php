<?php

declare(strict_types=1);

// Frozen benchmark baseline from 9da70c97:includes/club_program.php. Not application code.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function programCapacityProfileBaseline(PDO $pdo, array $offer, ?DateTimeImmutable $now = null, bool $lock = false, int $excludeHeldSportovecId = 0, ?ClubProgramReadContext $readContext = null): array
{
    if ($lock) {
        $readContext = null;
    }
    $readContext?->assertConnection($pdo);
    $teams = clubProgramPhysicalTeamIds($pdo, $offer, $readContext);
    if (count($teams) > 1) {
        $states = [];
        foreach ($teams as $teamId) {
            $states[$teamId] = wave6BaselineCapacityForTeam($pdo, $offer, $teamId, $now, $lock, $excludeHeldSportovecId, $readContext);
        }
        $most = array_reduce($states, static fn (?array $carry, array $state): array => $carry === null || $state['occupied_count'] > $carry['occupied_count'] ? $state : $carry, null);
        $most['available_count'] = min(array_column($states, 'available_count'));
        $most['physical_team_states'] = $states;
        return $most;
    }
    return wave6BaselineCapacityForTeam($pdo, $offer, $teams[0] ?? 0, $now, $lock, $excludeHeldSportovecId, $readContext);
}
/** @param array<string,mixed> $offer */
function wave6BaselineCapacityForTeam(
    PDO $pdo,
    array $offer,
    int $teamId,
    ?DateTimeImmutable $now = null,
    bool $lock = false,
    int $excludeHeldSportovecId = 0,
    ?ClubProgramReadContext $readContext = null
): array {
    if ($lock) {
        $readContext = null;
    }
    $readContext?->assertConnection($pdo);
    $offerId = (int) ($offer['id'] ?? $offer['offer_id'] ?? 0);
    $productId = (int) ($offer['product_id'] ?? 0);
    $variantId = (int) ($offer['variant_id'] ?? 0);
    if (min($offerId, $productId, $variantId) < 1) {
        throw new LogicException('Výpočet kapacity vyžaduje nabídku, produkt a variantu.');
    }
    $mysql = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $startsOn = (string) ($offer['starts_on'] ?? '');
    $endsOn = (string) ($offer['ends_on'] ?? '');
    $shared = $teamId > 0 &&
         clubProgramDateOrNull($startsOn) !== null &&
         clubProgramDateOrNull($endsOn) !== null &&
         clubProgramColumnExists(
             $pdo,
             'club_program_enrollments',
             'sportovec_id',
             $readContext
         );
    $linked = $shared && clubProgramRuntimeTableExists($pdo, 'club_program_physical_teams', $readContext);
    $teamCondition = $linked ? '(x.team_id=? OR EXISTS(SELECT 1 FROM club_program_physical_teams pt WHERE pt.program_id=x.program_id AND pt.team_id=?))' : 'x.team_id=?';
    $activeSql = $shared
        ? "SELECT e.sportovec_id FROM club_program_enrollments e JOIN club_program_offers x ON x.id=e.offer_id "
        . "WHERE {$teamCondition} AND x.starts_on<=? AND x.ends_on>=? AND e.status='active'"
        : "SELECT id FROM club_program_enrollments WHERE offer_id=? AND status='active'";
    if ($lock && $mysql) {
        $activeSql .= ' FOR UPDATE';
    }
    $active = $pdo->prepare($activeSql);
    $active->execute($shared ? $linked ? [$teamId, $teamId, $endsOn, $startsOn] : [$teamId, $endsOn, $startsOn] : [$offerId]);
    $activePeople = [];
    foreach ($active->fetchAll(PDO::FETCH_COLUMN) as $personId) {
        $activePeople[(int) $personId] = true;
    }
    $activeCount = count($activePeople);
    $timezone = new DateTimeZone('Europe/Prague');
    $now = ($now ?? new DateTimeImmutable('now', $timezone))->setTimezone($timezone);
    $hasHoldSchema = clubProgramColumnExists($pdo, 'shop_order_items', 'variant_id', $readContext) && clubProgramColumnExists($pdo, 'shop_orders', 'payment_expires_at', $readContext);
    $heldCount = 0;
    if ($hasHoldSchema) {
        $hasBeneficiary = clubProgramColumnExists($pdo, 'shop_order_items', 'beneficiary_sportovec_id', $readContext);
        $hasRequest = clubProgramColumnExists($pdo, 'shop_order_items', 'athlete_registration_request_id', $readContext);
        $beneficiarySelect = $hasBeneficiary ? 'oi.beneficiary_sportovec_id' : 'NULL AS beneficiary_sportovec_id';
        $requestSelect = $hasRequest ? 'oi.athlete_registration_request_id' : 'NULL AS athlete_registration_request_id';
        $heldSql = "SELECT oi.id,oi.quantity,{$beneficiarySelect},{$requestSelect} FROM shop_order_items oi JOIN shop_orders o ON o.id=oi.order_id ";
        if ($shared) {
            $heldSql .= clubProgramColumnExists(
                $pdo,
                'shop_order_items',
                'club_program_offer_id',
                $readContext
            )
                ? 'JOIN club_program_offers x ON x.id=oi.club_program_offer_id AND '
                . 'x.product_id=oi.product_id AND x.variant_id=oi.variant_id '
                : 'JOIN club_program_offers x ON x.product_id=oi.product_id AND x.variant_id=oi.variant_id ';
        }
        $heldSql .= $shared ? "WHERE {$teamCondition} AND x.starts_on<=? AND x.ends_on>=? " : 'WHERE oi.product_id=? AND oi.variant_id=? ';
        $heldSql .= "AND ((o.status='placed' AND o.payment_status='pending' AND o.payment_expires_at IS NOT NULL AND o.payment_expires_at>=?)";
        if ($hasBeneficiary && $hasRequest) {
            $heldSql .= " OR (o.status IN ('processing','ready','completed') AND o.payment_status='paid' AND oi.beneficiary_sportovec_id IS NULL AND oi.athlete_registration_request_id>0)";
        }
        $heldSql .= ') ';
        if ($hasRequest && clubProgramColumnExists($pdo, 'account_person_claim_requests', 'status', $readContext)) {
            $heldSql .= "AND NOT EXISTS(SELECT 1 FROM account_person_claim_requests r WHERE r.id=oi.athlete_registration_request_id AND r.status IN ('rejected','cancelled')) ";
        }
        if (clubProgramColumnExists($pdo, 'club_program_enrollments', 'source_order_item_id', $readContext)) {
            $heldSql .= "AND NOT EXISTS(SELECT 1 FROM club_program_enrollments e WHERE e.source_order_item_id=oi.id AND e.status='active') ";
        }
        if ($lock && $mysql) {
            $heldSql .= ' FOR UPDATE';
        }
        $held = $pdo->prepare($heldSql);
        $held->execute($shared ? $linked ? [$teamId,
             $teamId,
             $endsOn,
             $startsOn,
             $now->format('Y-m-d H:i:s')] : [$teamId,
             $endsOn,
             $startsOn,
             $now->format('Y-m-d H:i:s')] : [$productId,
             $variantId,
             $now->format('Y-m-d H:i:s')]);
        $heldIdentities = [];
        foreach ($held->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $personId = (int) ($row['beneficiary_sportovec_id'] ?? 0);
            if ($personId > 0) {
                if ($personId === $excludeHeldSportovecId || $shared && isset($activePeople[$personId])) {
                    continue;
                }
                $key = 'person:' . $personId;
            } elseif ((int) ($row['athlete_registration_request_id'] ?? 0) > 0) {
                $key = 'request:' . (int) $row['athlete_registration_request_id'];
            } else {
                $key = 'item:' . (int) $row['id'];
            }
            $heldIdentities[$key] = max((int) ($heldIdentities[$key] ?? 0), (int) $row['quantity']);
        }
        $heldCount = array_sum($heldIdentities);
    }
    $occupied = $activeCount + $heldCount;
    $capacity = $offer['capacity'] ?? null;
    return ['active_enrollment_count' => $activeCount,
         'held_order_count' => $heldCount,
         'occupied_count' => $occupied,
         'available_count' => $capacity === null ? null : max(
             0,
             (int) $capacity - $occupied
         )];
}
