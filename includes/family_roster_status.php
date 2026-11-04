<?php

declare(strict_types=1);

/** Presentation only: a scheduled end is still active through its last day. */
function familyPortalRosterDisplayStatus(array $roster, string $on): array
{
    $status = (string) $roster['status'];
    if (!in_array($status, ['active', 'removed'], true)) {
        return ['label' => ['pending' => 'Čeká', 'inactive' => 'Neaktivní', 'ended' => 'Ukončeno',
            'cancelled' => 'Zrušeno'][$status] ?? 'Neznámý stav', 'class' => 'text-bg-secondary'];
    }
    if (($status === 'removed' && $roster['valid_to'] === null)
        || ($roster['valid_to'] !== null && $roster['valid_to'] < $on)) {
        return ['label' => 'Ukončeno', 'class' => 'text-bg-secondary'];
    }
    if ($roster['valid_from'] > $on) {
        return ['label' => 'Budoucí', 'class' => 'text-bg-info'];
    }
    return ['label' => 'Aktivní', 'class' => 'text-bg-success'];
}
