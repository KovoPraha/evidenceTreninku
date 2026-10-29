<?php

declare(strict_types=1);

/**
 * Evaluate rows already selected by the canonical capacity reader.
 * This function cannot decide which orders are payable or acquire any locks.
 * Legacy offer-local reads key activePeople by enrollment ID, not person ID;
 * therefore active-person suppression applies only to shared capacity.
 *
 * @param array<int,true> $activePeople
 * @param list<array<string,mixed>> $heldRows
 * @return array{active_enrollment_count:int,held_order_count:int,occupied_count:int,available_count:?int}
 */
function clubProgramCapacityFromRows(array $activePeople, array $heldRows, bool $shared, int $excludeHeldSportovecId, mixed $capacity): array
{
    $heldIdentities = [];
    foreach ($heldRows as $row) {
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
    $activeCount = count($activePeople);
    $heldCount = array_sum($heldIdentities);
    $occupied = $activeCount + $heldCount;
    return ['active_enrollment_count' => $activeCount,
        'held_order_count' => $heldCount,
        'occupied_count' => $occupied,
        'available_count' => $capacity === null ? null : max(0, (int) $capacity - $occupied)];
}
