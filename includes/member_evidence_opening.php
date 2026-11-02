<?php

declare(strict_types=1);

/** Explicit initial state. Historical imports never determine an earlier opening date. */
function memberEvidenceOpeningRows(PDO $pdo, array $config, string $on): array
{
    if (!isset($config['opening'])) {
        return [];
    }
    $opening = $config['opening'];
    $ids = array_map('intval', (array) ($opening['member_ids'] ?? []));
    sort($ids, SORT_NUMERIC);
    if (!$ids || min($ids) < 1 || count($ids) !== count(array_unique($ids))
        || count($ids) !== (int) ($opening['expected_count'] ?? 0)) {
        throw new InvalidArgumentException('Výchozí členství vyžaduje přesný neprázdný seznam ID a počet.');
    }
    $query = $pdo->prepare("SELECT * FROM club_roster_members WHERE source='kis_shadow' AND status='active' "
        . 'AND valid_from<=? AND (valid_to IS NULL OR valid_to>=?) ORDER BY id');
    $query->execute([$on, $on]);
    $rows = $query->fetchAll(PDO::FETCH_ASSOC);
    if (array_map('intval', array_column($rows, 'id')) !== $ids) {
        throw new RuntimeException('Aktivní výchozí členství se liší od potvrzeného seznamu.');
    }
    foreach ($rows as $row) {
        if (!empty($row['source_ref'])) {
            throw new RuntimeException('Výchozí import již má zdrojový nárok; vyžaduje samostatné vypořádání.');
        }
    }
    return $rows;
}

/** Keep source identity mappings available for the explicitly pending final synchronization. */
function memberEvidenceDetachClaims(PDO $pdo, array $config): array
{
    $ids = array_map('intval', (array) ($config['detach_legacy_claim_ids'] ?? []));
    if (count($ids) !== count(array_unique($ids)) || ($ids && min($ids) < 1)) {
        throw new InvalidArgumentException('Neplatná ID převodních vazeb.');
    }
    if (!$ids) {
        return [];
    }
    $deleted = [];
    foreach ($config['delete'] ?? [] as $item) {
        if (($item['table'] ?? '') === 'club_roster_members') {
            $deleted[(int) $item['id']] = true;
        }
    }
    $rows = [];
    $query = $pdo->prepare('SELECT * FROM kis_legacy_roster_claims WHERE id=?');
    foreach ($ids as $id) {
        $query->execute([$id]);
        $row = $query->fetch(PDO::FETCH_ASSOC);
        if (!$row || !isset($deleted[(int) $row['member_id']]) || !in_array($row['status'], ['matched', 'approved'], true)) {
            throw new RuntimeException('Odpojit lze jen vypořádanou vazbu na vybrané historické členství.');
        }
        $rows[$id] = $row;
    }
    ksort($rows);
    return $rows;
}

/** Build an explicit candidate, then let the normal preview validate every FK and snapshot. */
function memberEvidencePrepareOpening(PDO $pdo, array $config, int $activeCount, int $historicalCount): array
{
    $on = kisRosterDate((string) $config['cutover_on']);
    $query = $pdo->prepare("SELECT id FROM club_roster_members WHERE source='kis_shadow' AND status='active' "
        . 'AND valid_from<=? AND (valid_to IS NULL OR valid_to>=?) ORDER BY id');
    $query->execute([$on, $on]);
    $active = array_map('intval', $query->fetchAll(PDO::FETCH_COLUMN));
    $query = $pdo->prepare("SELECT id FROM club_roster_members WHERE source='kis_shadow' AND status='removed' AND valid_to<? ORDER BY id");
    $query->execute([$on]);
    $history = array_map('intval', $query->fetchAll(PDO::FETCH_COLUMN));
    if (count($active) !== $activeCount || count($history) !== $historicalCount) {
        throw new RuntimeException('Počty se liší od schváleného výchozího stavu.');
    }
    $selected = array_fill_keys($history, true);
    $delete = [];
    $append = static function (string $table, int $id) use (&$delete): void {
        $delete[] = ['table' => $table, 'id' => $id, 'classification' => 'imported_history',
            'reason' => 'Schválený úklid historického importu; archiv zůstává v původním KIS.'];
    };
    foreach ($history as $id) {
        $append('club_roster_members', $id);
    }
    foreach ($pdo->query('SELECT id,roster_member_id FROM club_roster_events ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (isset($selected[(int) $row['roster_member_id']])) {
            $append('club_roster_events', (int) $row['id']);
        }
    }
    $detach = [];
    foreach ($pdo->query('SELECT id,member_id FROM kis_legacy_roster_claims ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (isset($selected[(int) $row['member_id']])) {
            $detach[] = (int) $row['id'];
        }
    }
    return array_replace($config, ['opening' => ['member_ids' => $active, 'expected_count' => $activeCount,
        'final_sync_pending' => true], 'delete' => $delete, 'detach_legacy_claim_ids' => $detach]);
}

function memberEvidenceApplyOpening(PDO $pdo, array $rows, string $on, int $actor, string $reason): void
{
    $query = $pdo->prepare("UPDATE club_roster_members SET valid_from=?,source='manual',source_ref=? WHERE id=?");
    foreach ($rows as $row) {
        $reference = 'kis-opening:' . $row['id'];
        $query->execute([$on, $reference, $row['id']]);
        kisRosterEvent(
            $pdo,
            (int) $row['team_id'],
            (int) $row['id'],
            $actor,
            'opening_balance',
            $row,
            array_replace($row, ['valid_from' => $on, 'source' => 'manual', 'source_ref' => $reference]),
            $reason
        );
    }
}
