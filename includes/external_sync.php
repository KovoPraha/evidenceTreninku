<?php

declare(strict_types=1);

require_once __DIR__ . '/external_sync_contract.php';

function externalSyncQuery(PDO $pdo, string $sql, array $args = []): PDOStatement
{
    $query = $pdo->prepare($sql);
    $query->execute($args);
    return $query;
}

function externalSyncLock(PDO $pdo): string
{
    return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
}

function externalSyncAudit(PDO $pdo, string $connection, ?string $run, string $kind, string $actor, string $action): void
{
    externalSyncQuery($pdo, 'INSERT INTO external_sync_audit(connection_id,run_id,actor_kind,actor_id,action_code,recorded_at)
        VALUES(?,?,?,?,?,?)', [$connection, $run, $kind, $actor, $action, time()]);
}

/** Connection lock precedes device/run locks, including revocation and publication. */
function externalSyncDeviceTransaction(PDO $pdo, string $token, callable $work): array
{
    if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
        externalSyncReject('unauthorized', 401);
    }
    if ($pdo->inTransaction()) {
        throw new LogicException('external_sync_requires_own_transaction');
    }
    try {
        // Locate the lock outside the transaction: an early consistent read would
        // pin a stale InnoDB REPEATABLE READ snapshot while waiting for a writer.
        $hash = hash('sha256', $token);
        $device = externalSyncQuery($pdo, 'SELECT * FROM external_sync_devices WHERE token_hash=?', [$hash])->fetch(PDO::FETCH_ASSOC);
        if (!$device) {
            externalSyncReject('unauthorized', 401);
        }
        $pdo->beginTransaction();
        $connection = externalSyncQuery(
            $pdo,
            'SELECT * FROM external_sync_connections WHERE id=?' . externalSyncLock($pdo),
            [$device['connection_id']]
        )->fetch(PDO::FETCH_ASSOC);
        $device = externalSyncQuery(
            $pdo,
            'SELECT * FROM external_sync_devices WHERE token_hash=?' . externalSyncLock($pdo),
            [$hash]
        )->fetch(PDO::FETCH_ASSOC);
        if (!$device || !(int)$device['enabled'] || !$connection || !(int)$connection['enabled']) {
            externalSyncReject('unauthorized', 401);
        }
        if ($device['connection_id'] !== $connection['id']) {
            externalSyncReject('device_scope_changed', 409);
        }
        externalSyncQuery($pdo, 'UPDATE external_sync_devices SET last_seen_at=? WHERE id=?', [time(), $device['id']]);
        $result = $work($connection, $device);
        $pdo->commit();
        return $result;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function externalSyncRun(PDO $pdo, array $connection, array $device, string $id, int $generation, bool $terminal = false): array
{
    $run = externalSyncQuery($pdo, 'SELECT * FROM external_sync_runs WHERE id=?' . externalSyncLock($pdo), [$id])->fetch(PDO::FETCH_ASSOC);
    if (!$run || $run['connection_id'] !== $connection['id'] || $run['device_id'] !== $device['id']) {
        externalSyncReject('run_scope_denied', 403);
    }
    if ((int)$run['lease_generation'] !== $generation) {
        externalSyncReject('stale_lease', 409);
    }
    if ($terminal && in_array($run['status'], ['complete', 'review_required'], true)) {
        return $run;
    }
    if ($run['status'] !== 'collecting' || (int)$run['lease_until'] <= time()
        || $connection['active_run_id'] !== $id || (int)$connection['next_sequence'] !== (int)$run['sequence_no']) {
        externalSyncReject('stale_lease', 409);
    }
    return $run;
}

function externalSyncClaim(PDO $pdo, array $connection, array $device): array
{
    $valid = match ($connection['provider']) {
        'csc' => ['csc_licenses'],
        'sportsoft' => ['sportsoft_members', 'sportsoft_events', 'sportsoft_registrations'],
        default => [],
    };
    if (!in_array($connection['collection'], $valid, true)
        || (($connection['collection'] === 'sportsoft_registrations') !== ($connection['event_id'] !== null))) {
        externalSyncReject('connection_configuration_invalid', 409);
    }
    if ($connection['active_run_id'] !== null) {
        $active = externalSyncQuery($pdo, 'SELECT * FROM external_sync_runs WHERE id=?', [$connection['active_run_id']])->fetch(PDO::FETCH_ASSOC);
        if ($active && $active['status'] === 'collecting' && (int)$active['lease_until'] > time()) {
            if ($active['device_id'] !== $device['id']) {
                externalSyncReject('connection_busy', 409);
            }
            return externalSyncJob($connection, $active);
        }
        if ($active && $active['status'] === 'collecting') {
            externalSyncQuery($pdo, "UPDATE external_sync_runs SET status='expired',finished_at=? WHERE id=?", [time(), $active['id']]);
        }
    }
    $recent = externalSyncQuery(
        $pdo,
        'SELECT COUNT(*) FROM external_sync_runs WHERE connection_id=? AND started_at>?',
        [$connection['id'], time() - 60]
    )->fetchColumn();
    if ((int)$recent >= 10) {
        externalSyncReject('claim_rate_limited', 429);
    }
    $hex = bin2hex(random_bytes(16));
    $id = substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4' . substr($hex, 13, 3)
        . '-8' . substr($hex, 17, 3) . '-' . substr($hex, 20);
    $sequence = (int)$connection['next_sequence'] + 1;
    $run = ['id' => $id, 'sequence_no' => $sequence, 'lease_generation' => 1, 'lease_until' => time() + 600];
    externalSyncQuery($pdo, 'INSERT INTO external_sync_runs(id,connection_id,device_id,sequence_no,lease_generation,lease_until,started_at,status)
        VALUES(?,?,?,?,?,?,?,?)', [$id, $connection['id'], $device['id'], $sequence, 1, $run['lease_until'], time(), 'collecting']);
    externalSyncQuery($pdo, 'UPDATE external_sync_connections SET next_sequence=?,active_run_id=? WHERE id=?', [$sequence, $id, $connection['id']]);
    externalSyncAudit($pdo, $connection['id'], $id, 'device', $device['id'], 'claimed');
    return externalSyncJob($connection, $run);
}

function externalSyncJob(array $connection, array $run): array
{
    return ['run_id' => $run['id'], 'connection_id' => $connection['id'], 'collection' => $connection['collection'],
        'scope_id' => $connection['scope_id'], 'event_id' => $connection['event_id'],
        'lease_generation' => (int)$run['lease_generation'], 'lease_until' => (int)$run['lease_until'], 'schema_version' => 1];
}

function externalSyncUpload(PDO $pdo, array $connection, array $device, array $data, string $raw): array
{
    $batch = externalSyncBatch($data);
    foreach (['connection_id' => 'id', 'scope_id' => 'scope_id', 'collection' => 'collection', 'event_id' => 'event_id'] as $key => $column) {
        if ($batch[$key] !== $connection[$column]) {
            externalSyncReject('batch_scope_denied', 403);
        }
    }
    $run = externalSyncRun($pdo, $connection, $device, $batch['run_id'], $batch['lease_generation']);
    $observed = strtotime(substr($batch['observed_at'], 0, 19) . 'Z');
    if ($observed > time() + 300 || $observed < (int)$run['started_at'] - 300) {
        externalSyncReject('observation_outside_run');
    }
    $hash = hash('sha256', $raw);
    $old = externalSyncQuery(
        $pdo,
        'SELECT request_hash FROM external_sync_parts WHERE run_id=? AND part_no=?',
        [$run['id'], $batch['part_no']]
    )->fetchColumn();
    if ($old !== false) {
        if (!hash_equals($old, $hash)) {
            externalSyncReject('part_content_conflict', 409);
        }
        return ['part_no' => $batch['part_no'], 'sha256' => $hash, 'duplicate' => true];
    }
    $stats = externalSyncQuery(
        $pdo,
        'SELECT COUNT(*) AS n,COALESCE(SUM(payload_bytes),0) AS bytes FROM external_sync_parts WHERE run_id=?',
        [$run['id']]
    )->fetch(PDO::FETCH_ASSOC);
    if ((int)$stats['n'] >= 1000 || (int)$stats['bytes'] + strlen($raw) > 67108864) {
        externalSyncReject('run_limit_exceeded', 413);
    }
    externalSyncQuery(
        $pdo,
        'INSERT INTO external_sync_parts(run_id,part_no,request_hash,payload_json,payload_bytes) VALUES(?,?,?,?,?)',
        [$run['id'], $batch['part_no'], $hash, externalSyncJson($batch), strlen($raw)]
    );
    return ['part_no' => $batch['part_no'], 'sha256' => $hash, 'duplicate' => false];
}
