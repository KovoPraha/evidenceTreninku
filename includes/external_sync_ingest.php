<?php

declare(strict_types=1);

require_once __DIR__ . '/external_sync.php';

function externalSyncManifest(array $data): array
{
    externalSyncKeys($data, ['run_id', 'lease_generation', 'parts', 'page_count', 'row_count', 'end_reached', 'filters_clear']);
    externalSyncText($data['run_id'], 36, false);
    externalSyncInteger($data['lease_generation'], 1, PHP_INT_MAX);
    externalSyncInteger($data['page_count'], 1, 1000);
    externalSyncInteger($data['row_count'], 0, 100000);
    if ($data['end_reached'] !== true || $data['filters_clear'] !== true || !is_array($data['parts'])
        || !array_is_list($data['parts']) || count($data['parts']) < 1 || count($data['parts']) > 1000) {
        externalSyncReject('incomplete_manifest');
    }
    foreach ($data['parts'] as $index => $part) {
        if (!is_array($part)) {
            externalSyncReject('invalid_manifest_part');
        }
        externalSyncKeys($part, ['part_no', 'sha256']);
        if ($part['part_no'] !== $index + 1 || !is_string($part['sha256']) || !preg_match('/^[a-f0-9]{64}$/D', $part['sha256'])) {
            externalSyncReject('invalid_manifest_part');
        }
    }
    return $data;
}

/** Verifies continuous page/offset coverage; client assertions cannot replace source totals. */
function externalSyncCollect(PDO $pdo, array $run, array $manifest): array
{
    $parts = externalSyncQuery($pdo, 'SELECT * FROM external_sync_parts WHERE run_id=? ORDER BY part_no', [$run['id']])->fetchAll(PDO::FETCH_ASSOC);
    if (count($parts) !== count($manifest['parts'])) {
        externalSyncReject('missing_parts');
    }
    $rows = [];
    $page = 1;
    $offset = 0;
    $total = null;
    $version = null;
    foreach ($parts as $index => $part) {
        if ((int)$part['part_no'] !== $index + 1 || !hash_equals($manifest['parts'][$index]['sha256'], $part['request_hash'])) {
            externalSyncReject('manifest_hash_mismatch');
        }
        $batch = json_decode($part['payload_json'], true, 32, JSON_THROW_ON_ERROR);
        if ($index === 0) {
            $total = $batch['page']['reported_total'];
            $version = $batch['adapter_version'];
        }
        if ($batch['page']['reported_total'] !== $total || $batch['adapter_version'] !== $version) {
            externalSyncReject('source_changed_during_run');
        }
        if ($batch['page']['index'] === $page + 1 && $offset > 0) {
            $page++;
            $offset = 0;
        }
        if ($batch['page']['index'] !== $page || $batch['page']['row_offset'] !== $offset
            || ($batch['page']['row_count'] === 0 && count($parts) !== 1)) {
            externalSyncReject('page_gap_or_overlap');
        }
        $offset += $batch['page']['row_count'];
        foreach ($batch['rows'] as $rowIndex => $row) {
            $rows[] = ['row' => $row, 'part_no' => (int)$part['part_no'], 'row_index' => $rowIndex];
        }
        if (count($rows) > 100000) {
            externalSyncReject('run_limit_exceeded', 413);
        }
    }
    if ($page !== $manifest['page_count'] || count($rows) !== $manifest['row_count']
        || ($total !== null && $total !== count($rows))) {
        externalSyncReject('incomplete_rows');
    }
    return $rows;
}

function externalSyncNormalized(string $collection, array $row): array
{
    $normalized = ['uci_id' => externalIdentityUci($row['uci_id_raw'] ?? null)['uci']];
    if ($collection === 'csc_licenses') {
        $normalized['license_status'] = match ($row['license_status_raw']) {
            'Platná licence' => 'valid', 'Neplatná licence' => 'invalid', default => 'unknown',
        };
        $year = $row['validity_year_raw'];
        $normalized['validity_year'] = $year !== null && preg_match('/^[0-9]{4}$/D', $year) ? (int)$year : null;
        $normalized['invalid_year'] = $year !== null && $normalized['validity_year'] === null;
    }
    if (array_key_exists('birth_date_raw', $row)) {
        $normalized['birth_date'] = externalIdentityDate($row['birth_date_raw']);
        $normalized['invalid_birth_date'] = $row['birth_date_raw'] !== null && $normalized['birth_date'] === null;
    }
    if ($collection === 'sportsoft_registrations') {
        $normalized['paid'] = $row['paid_raw'] === 'ano' ? 'paid' : 'unknown';
    }
    if ($collection === 'sportsoft_events') {
        $normalized['event_date'] = externalIdentityDate($row['event_date_raw']);
        $normalized['registration_presence'] = match ($row['registration_action_label']) {
            'Prohlédnout / změnit přihlášku' => 'existing', 'Vytvořit přihlášku' => 'not_created', default => 'unknown',
        };
    }
    return $normalized;
}

/** No source rows or names appear in the audit. Review candidates are local integer IDs only. */
function externalSyncStoreRows(PDO $pdo, array $connection, array $run, array $rows): void
{
    $keys = [];
    foreach ($rows as $source) {
        $row = $source['row'];
        $key = externalSyncEntityKey($connection['collection'], $row);
        if ($key !== null) {
            $keys[$key] = ($keys[$key] ?? 0) + 1;
        }
    }
    if ($connection['collection'] === 'csc_licenses' && $keys !== [] && max($keys) > 1) {
        externalSyncReject('duplicate_license_id');
    }
    foreach ($rows as $index => $source) {
        $row = $source['row'];
        $key = externalSyncEntityKey($connection['collection'], $row);
        $status = $key === null ? 'missing_source_key' : (($keys[$key] > 1) ? 'duplicate_source_key' : 'pending');
        externalSyncQuery($pdo, 'INSERT INTO external_sync_records(run_id,ordinal_no,part_no,row_index,entity_key,uci_id,row_json,normalized_json,match_status)
            VALUES(?,?,?,?,?,?,?,?,?)', [$run['id'], $index + 1, $source['part_no'], $source['row_index'], $status === 'duplicate_source_key' ? null : $key,
                externalIdentityUci($row['uci_id_raw'] ?? null)['uci'], externalSyncJson($row),
                externalSyncJson(externalSyncNormalized($connection['collection'], $row)), $status]);
    }
}

function externalSyncPeople(PDO $pdo, bool $lock = false): array
{
    return $pdo->query('SELECT id,jmeno,prijmeni,narozeni,uciid FROM sportovci ORDER BY id' . ($lock ? externalSyncLock($pdo) : ''))->fetchAll(PDO::FETCH_ASSOC);
}

/** Source contradictions affect every row sharing the UCI, even across different licences. */
function externalSyncSourceConflicts(iterable $records): array
{
    $seen = [];
    foreach ($records as $record) {
        if ($record['uci_id'] === null) {
            continue;
        }
        $row = json_decode($record['row_json'], true, 16, JSON_THROW_ON_ERROR);
        $name = externalIdentityName($row['display_name'] ?? null);
        $birth = externalIdentityDate($row['birth_date_raw'] ?? null);
        if ($name !== []) {
            $seen[$record['uci_id']]['names'][externalSyncJson($name)] = true;
        }
        if ($birth !== null) {
            $seen[$record['uci_id']]['births'][$birth] = true;
        }
    }
    $conflicts = [];
    foreach ($seen as $uci => $values) {
        if (count($values['names'] ?? []) > 1 || count($values['births'] ?? []) > 1) {
            $conflicts[$uci] = true;
        }
    }
    return $conflicts;
}

function externalSyncResolveRecord(array $record, array $people, array $links, array $conflicts, string $collection): array
{
    $row = json_decode($record['row_json'], true, 16, JSON_THROW_ON_ERROR);
    if ($collection === 'sportsoft_events') {
        return ['status' => $record['entity_key'] === null ? 'missing_source_key' : 'not_applicable',
            'sportovec_id' => null, 'uci_id' => null, 'candidate_ids' => []];
    }
    $previous = $record['entity_key'] === null ? null : ($links[$record['entity_key']] ?? null);
    $result = externalIdentityResolve($row, $people, $previous);
    if (isset($conflicts[$record['uci_id']])) {
        $result['status'] = 'identity_conflict';
    } elseif ($result['status'] === 'matched' && $record['entity_key'] === null) {
        $result['status'] = $record['match_status'] === 'duplicate_source_key' ? 'duplicate_source_key' : 'missing_source_key';
    }
    if ($result['status'] !== 'matched') {
        $result['sportovec_id'] = null;
    }
    return $result;
}

/** Caller holds the connection/run locks. Writes only integration tables. */
function externalSyncPublish(PDO $pdo, array $connection, array $run): void
{
    if ((int)$run['sequence_no'] <= (int)$connection['latest_sequence']) {
        externalSyncReject('stale_snapshot', 409);
    }
    $records = externalSyncQuery($pdo, 'SELECT * FROM external_sync_records WHERE run_id=? ORDER BY ordinal_no', [$run['id']])->fetchAll(PDO::FETCH_ASSOC);
    $people = $connection['collection'] === 'sportsoft_events' ? [] : externalSyncPeople($pdo, true);
    $links = externalSyncQuery(
        $pdo,
        'SELECT * FROM external_identity_links WHERE connection_id=?' . externalSyncLock($pdo),
        [$connection['id']]
    )->fetchAll(PDO::FETCH_ASSOC);
    $links = array_column($links, null, 'entity_key');
    $conflicts = externalSyncSourceConflicts($records);
    externalSyncQuery($pdo, "UPDATE external_identity_links SET status='not_seen',revision=revision+1 WHERE connection_id=?", [$connection['id']]);
    foreach ($records as $record) {
        $match = externalSyncResolveRecord($record, $people, $links, $conflicts, $connection['collection']);
        externalSyncQuery(
            $pdo,
            'UPDATE external_sync_records SET match_status=?,sportovec_id=? WHERE id=?',
            [$match['status'], $match['sportovec_id'], $record['id']]
        );
        $key = $record['entity_key'];
        if ($key !== null && isset($links[$key])) {
            externalSyncQuery(
                $pdo,
                'UPDATE external_identity_links SET status=?,run_id=?,revision=revision+1 WHERE connection_id=? AND entity_key=?',
                [$match['status'], $run['id'], $connection['id'], $key]
            );
        } elseif ($key !== null && $match['status'] === 'matched') {
            externalSyncQuery($pdo, 'INSERT INTO external_identity_links(connection_id,entity_key,uci_id,sportovec_id,status,run_id)
                VALUES(?,?,?,?,?,?)', [$connection['id'], $key, $match['uci_id'], $match['sportovec_id'], 'matched', $run['id']]);
        }
        if (!in_array($match['status'], ['matched', 'not_applicable'], true)) {
            externalSyncQuery(
                $pdo,
                'INSERT INTO external_sync_reviews(run_id,ordinal_no,reason_code,candidate_ids_json) VALUES(?,?,?,?)',
                [$run['id'], $record['ordinal_no'], $match['status'], externalSyncJson($match['candidate_ids'])]
            );
        }
    }
    externalSyncQuery($pdo, "UPDATE external_sync_runs SET status='complete',finished_at=? WHERE id=?", [time(), $run['id']]);
    externalSyncQuery(
        $pdo,
        'UPDATE external_sync_connections SET latest_run_id=?,latest_sequence=?,latest_row_count=? WHERE id=?',
        [$run['id'], $run['sequence_no'], count($records), $connection['id']]
    );
}

function externalSyncComplete(PDO $pdo, array $connection, array $device, array $data): array
{
    $manifest = externalSyncManifest($data);
    $run = externalSyncRun($pdo, $connection, $device, $manifest['run_id'], $manifest['lease_generation'], true);
    $hash = hash('sha256', externalSyncJson($manifest));
    if (in_array($run['status'], ['complete', 'review_required'], true)) {
        if (!hash_equals($run['manifest_hash'], $hash)) {
            externalSyncReject('manifest_conflict', 409);
        }
        return ['status' => $run['status'], 'duplicate' => true];
    }
    $rows = externalSyncCollect($pdo, $run, $manifest);
    externalSyncStoreRows($pdo, $connection, $run, $rows);
    $review = $connection['latest_run_id'] === null || count($rows) === 0
        || count($rows) * 5 < (int)$connection['latest_row_count'] * 4;
    externalSyncQuery(
        $pdo,
        'UPDATE external_sync_runs SET row_count=?,manifest_hash=?,finished_at=?,status=? WHERE id=?',
        [count($rows), $hash, time(), $review ? 'review_required' : 'collecting', $run['id']]
    );
    if (!$review) {
        externalSyncPublish($pdo, $connection, $run);
    }
    externalSyncQuery($pdo, 'UPDATE external_sync_connections SET active_run_id=NULL WHERE id=?', [$connection['id']]);
    externalSyncAudit($pdo, $connection['id'], $run['id'], 'device', $device['id'], $review ? 'review_required' : 'complete');
    return ['status' => $review ? 'review_required' : 'complete', 'duplicate' => false];
}

/** S1 service seam for later registrar UI/CLI; never routed from the machine endpoint. */
function externalSyncApproveSnapshot(PDO $pdo, string $runId, int $trainerId): void
{
    require_once __DIR__ . '/operational_cases.php';
    if ($pdo->inTransaction()) {
        throw new LogicException('external_sync_requires_own_transaction');
    }
    try {
        $run = externalSyncQuery($pdo, 'SELECT * FROM external_sync_runs WHERE id=?', [$runId])->fetch(PDO::FETCH_ASSOC);
        if (!$run) {
            externalSyncReject('unknown_run', 404);
        }
        $pdo->beginTransaction();
        $connection = externalSyncQuery(
            $pdo,
            'SELECT * FROM external_sync_connections WHERE id=?' . externalSyncLock($pdo),
            [$run['connection_id']]
        )->fetch(PDO::FETCH_ASSOC);
        $device = externalSyncQuery(
            $pdo,
            'SELECT * FROM external_sync_devices WHERE id=?' . externalSyncLock($pdo),
            [$run['device_id']]
        )->fetch(PDO::FETCH_ASSOC);
        $run = externalSyncQuery($pdo, 'SELECT * FROM external_sync_runs WHERE id=?' . externalSyncLock($pdo), [$runId])->fetch(PDO::FETCH_ASSOC);
        operationalCaseActor($pdo, $trainerId, 'registrar');
        if (!(int)$connection['enabled'] || !$device || !(int)$device['enabled'] || $device['connection_id'] !== $connection['id']
            || $run['status'] !== 'review_required' || (int)$run['sequence_no'] !== (int)$connection['next_sequence']) {
            externalSyncReject('review_no_longer_current', 409);
        }
        externalSyncPublish($pdo, $connection, $run);
        externalSyncAudit($pdo, $connection['id'], $runId, 'trainer', (string)$trainerId, 'snapshot_approved');
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function externalSyncDispatch(PDO $pdo, string $token, string $action, string $raw): array
{
    if (strlen($raw) > 1048576) {
        externalSyncReject('payload_too_large', 413);
    }
    return externalSyncDeviceTransaction($pdo, $token, static function (array $connection, array $device) use ($pdo, $action, $raw): array {
        try {
            $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            externalSyncReject('invalid_json', 400);
        }
        if (!is_array($data) || !str_starts_with(ltrim($raw), '{')) {
            externalSyncReject('invalid_json_object', 400);
        }
        if ($action === 'claim') {
            externalSyncKeys($data, []);
            return externalSyncClaim($pdo, $connection, $device);
        }
        if ($action === 'upload_part') {
            return externalSyncUpload($pdo, $connection, $device, $data, $raw);
        }
        if ($action === 'complete') {
            return externalSyncComplete($pdo, $connection, $device, $data);
        }
        if (!in_array($action, ['heartbeat', 'fail'], true)) {
            externalSyncReject('unknown_action', 400);
        }
        externalSyncKeys($data, $action === 'fail' ? ['run_id', 'lease_generation', 'failure_code'] : ['run_id', 'lease_generation']);
        $id = externalSyncText($data['run_id'], 36, false);
        $generation = externalSyncInteger($data['lease_generation'], 1, PHP_INT_MAX);
        $run = externalSyncRun($pdo, $connection, $device, $id, $generation);
        if ($action === 'heartbeat') {
            if ((int)$run['started_at'] + 7200 <= time()) {
                externalSyncReject('run_deadline_exceeded', 409);
            }
            $until = min(time() + 600, (int)$run['started_at'] + 7200);
            externalSyncQuery($pdo, 'UPDATE external_sync_runs SET lease_until=? WHERE id=?', [$until, $id]);
            return ['lease_until' => $until];
        }
        if (!in_array($data['failure_code'], ['auth_required', 'layout_changed', 'incomplete', 'network_error', 'not_created'], true)) {
            externalSyncReject('invalid_failure_code');
        }
        if ($data['failure_code'] === 'not_created' && $connection['collection'] !== 'sportsoft_registrations') {
            externalSyncReject('invalid_failure_scope');
        }
        externalSyncQuery($pdo, "UPDATE external_sync_runs SET status='failed',failure_code=?,finished_at=? WHERE id=?", [$data['failure_code'], time(), $id]);
        externalSyncQuery($pdo, 'UPDATE external_sync_connections SET active_run_id=NULL WHERE id=?', [$connection['id']]);
        externalSyncAudit($pdo, $connection['id'], $id, 'device', $device['id'], $data['failure_code']);
        return ['status' => 'failed'];
    });
}
