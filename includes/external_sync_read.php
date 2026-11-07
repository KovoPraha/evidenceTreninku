<?php

declare(strict_types=1);

require_once __DIR__ . '/external_sync_ingest.php';
require_once __DIR__ . '/operational_cases.php';

/** Readable proposal for the later review UI; does not publish or mutate links. */
function externalSyncReadReview(PDO $pdo, string $runId, int $trainerId): array
{
    operationalCaseActor($pdo, $trainerId, 'registrar');
    $run = externalSyncQuery($pdo, "SELECT * FROM external_sync_runs WHERE id=? AND status='review_required'", [$runId])->fetch(PDO::FETCH_ASSOC);
    if (!$run) {
        externalSyncReject('unknown_review', 404);
    }
    $connection = externalSyncQuery($pdo, 'SELECT * FROM external_sync_connections WHERE id=?', [$run['connection_id']])->fetch(PDO::FETCH_ASSOC);
    return ['run_id' => $run['id'], 'status' => 'review_required',
        'superseded' => (int)$run['sequence_no'] !== (int)$connection['next_sequence'],
        'records' => externalSyncProjectRecords($pdo, $connection, $run)];
}

/** Registrar-only projection; never trust stored person links after canonical changes. */
function externalSyncReadSnapshot(PDO $pdo, string $connectionId, int $trainerId): array
{
    operationalCaseActor($pdo, $trainerId, 'registrar');
    $connection = externalSyncQuery($pdo, 'SELECT * FROM external_sync_connections WHERE id=?', [$connectionId])->fetch(PDO::FETCH_ASSOC);
    if (!$connection || $connection['latest_run_id'] === null) {
        return ['run_id' => null, 'stale' => true, 'records' => []];
    }
    $run = externalSyncQuery(
        $pdo,
        "SELECT * FROM external_sync_runs WHERE id=? AND status='complete'",
        [$connection['latest_run_id']]
    )->fetch(PDO::FETCH_ASSOC);
    if (!$run) {
        externalSyncReject('invalid_snapshot_pointer', 409);
    }
    return ['run_id' => $run['id'], 'finished_at' => (int)$run['finished_at'],
        'stale' => (int)$run['finished_at'] < time() - 86400 || !(int)$connection['enabled'],
        'records' => externalSyncProjectRecords($pdo, $connection, $run)];
}

/** Internal helper; public service entry points verify the registrar first. */
function externalSyncProjectRecords(PDO $pdo, array $connection, array $run): array
{
    $records = externalSyncQuery($pdo, 'SELECT * FROM external_sync_records WHERE run_id=? ORDER BY ordinal_no', [$run['id']])->fetchAll(PDO::FETCH_ASSOC);
    $people = $connection['collection'] === 'sportsoft_events' ? [] : externalSyncPeople($pdo);
    $links = externalSyncQuery($pdo, 'SELECT * FROM external_identity_links WHERE connection_id=?', [$connection['id']])->fetchAll(PDO::FETCH_ASSOC);
    $links = array_column($links, null, 'entity_key');
    $conflicts = externalSyncSourceConflicts($records);
    foreach ($records as &$record) {
        $record['match'] = externalSyncResolveRecord($record, $people, $links, $conflicts, $connection['collection']);
        // Never expose a stale stored pointer alongside the freshly checked match.
        unset($record['sportovec_id'], $record['match_status']);
    }
    unset($record);
    return $records;
}
