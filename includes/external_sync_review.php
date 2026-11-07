<?php

declare(strict_types=1);

require_once __DIR__ . '/external_sync_read.php';
require_once __DIR__ . '/sportovec_history_lib.php';

function externalSyncReviewStates(): array
{
    return ['open' => 'K řešení', 'deferred' => 'Odloženo', 'excluded' => 'Nespadá do KIS', 'resolved' => 'Ověřeno po opravě'];
}

/** Fingerprint only; never persist a copy of the canonical identity in the review audit. */
function externalSyncReviewBasis(array $connection, array $run, array $record, array $match, array $people, ?array $link): string
{
    $relevant = [];
    foreach ($people as $person) {
        if (($match['uci_id'] !== null && externalIdentityUci($person['uciid'])['uci'] === $match['uci_id']) || (int)$person['id'] === (int)($link['sportovec_id'] ?? 0)) {
            $relevant[(int)$person['id']] = $person;
        }
    }
    ksort($relevant);
    return hash('sha256', externalSyncJson([$connection['latest_run_id'], (int)$connection['next_sequence'],
        $run['id'], $run['status'], (int)$record['ordinal_no'], hash('sha256', $record['row_json']),
        $match, $relevant, $link]));
}

function externalSyncReviewLatest(PDO $pdo, string $runId): array
{
    $query = externalSyncQuery($pdo, 'SELECT d.* FROM external_sync_decisions d WHERE d.run_id=?
        AND NOT EXISTS(SELECT 1 FROM external_sync_decisions n
            WHERE n.run_id=d.run_id AND n.ordinal_no=d.ordinal_no AND n.revision>d.revision)', [$runId]);
    return array_column($query->fetchAll(PDO::FETCH_ASSOC), null, 'ordinal_no');
}

function externalSyncReviewProjection(?array $decision, string $basis): array
{
    $stale = $decision !== null && !hash_equals($decision['basis_hash'], $basis);
    return ['revision' => (int)($decision['revision'] ?? 0), 'state' => $stale ? 'open' : ($decision['state'] ?? 'open'),
        'stale' => $stale, 'basis_hash' => $basis, 'decision' => $decision];
}

/** Internal context, after authorization. Write callers hold connection then run locks. */
function externalSyncReviewContext(PDO $pdo, array $connection, array $run, int $ordinal, bool $lock = false): array
{
    $record = externalSyncQuery($pdo, 'SELECT * FROM external_sync_records WHERE run_id=? AND ordinal_no=?', [$run['id'], $ordinal])->fetch(PDO::FETCH_ASSOC);
    if (!$record || !in_array($run['status'], ['complete', 'review_required'], true) || $connection['collection'] === 'sportsoft_events') {
        externalSyncReject('unknown_review_record', 404);
    }
    $people = externalSyncPeople($pdo, $lock);
    $links = externalSyncQuery($pdo, 'SELECT entity_key,uci_id,sportovec_id,revision FROM external_identity_links WHERE connection_id=?'
        . ($lock ? externalSyncLock($pdo) : ''), [$connection['id']])->fetchAll(PDO::FETCH_ASSOC);
    $links = array_column($links, null, 'entity_key');
    $conflicts = externalSyncSourceConflicts(externalSyncQuery($pdo, 'SELECT uci_id,row_json FROM external_sync_records WHERE run_id=?', [$run['id']]));
    $match = externalSyncResolveRecord($record, $people, $links, $conflicts, $connection['collection']);
    $basis = externalSyncReviewBasis($connection, $run, $record, $match, $people, $links[$record['entity_key'] ?? ''] ?? null);
    $decision = externalSyncQuery(
        $pdo,
        'SELECT * FROM external_sync_decisions WHERE run_id=? AND ordinal_no=? ORDER BY revision DESC LIMIT 1',
        [$run['id'], $ordinal]
    )->fetch(PDO::FETCH_ASSOC);
    $current = $run['id'] === $connection['latest_run_id']
        || ($run['status'] === 'review_required' && (int)$run['sequence_no'] === (int)$connection['next_sequence']);
    return ['connection' => $connection, 'run' => $run, 'record' => $record, 'match' => $match,
        'source' => json_decode($record['row_json'], true, 16, JSON_THROW_ON_ERROR), 'current' => $current,
        'review' => externalSyncReviewProjection($decision ?: null, $basis)];
}

function externalSyncReviewGet(PDO $pdo, int $trainerId, string $runId, int $ordinal, int $before = PHP_INT_MAX): array
{
    operationalCaseActor($pdo, $trainerId, 'registrar');
    if ($pdo->inTransaction()) {
        throw new LogicException('external_sync_requires_own_transaction');
    }
    $pdo->beginTransaction();
    try {
        $run = externalSyncQuery($pdo, 'SELECT * FROM external_sync_runs WHERE id=?', [$runId])->fetch(PDO::FETCH_ASSOC);
        if (!$run) {
            externalSyncReject('unknown_run', 404);
        }
        $connection = externalSyncQuery($pdo, 'SELECT * FROM external_sync_connections WHERE id=?', [$run['connection_id']])->fetch(PDO::FETCH_ASSOC);
        $result = externalSyncReviewContext($pdo, $connection, $run, $ordinal);
        $history = externalSyncQuery($pdo, 'SELECT * FROM external_sync_decisions WHERE run_id=? AND ordinal_no=? AND revision<?
            ORDER BY revision DESC LIMIT 21', [$runId, $ordinal, $before])->fetchAll(PDO::FETCH_ASSOC);
        $result['history'] = array_slice($history, 0, 20);
        $result['history_has_next'] = count($history) > 20;
        $pdo->commit();
        return $result;
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}

/** Append-only decision; no bypass of the resolver and no source/canonical updates. */
function externalSyncReviewDecide(PDO $pdo, int $trainerId, string $runId, int $ordinal, int $revision, string $basis, string $state, string $reason): int
{
    operationalCaseActor($pdo, $trainerId, 'registrar');
    $reason = trim($reason);
    if (!array_key_exists($state, externalSyncReviewStates()) || mb_strlen($reason) < 10 || mb_strlen($reason) > 1000
        || $ordinal < 1 || $revision < 0 || !preg_match('/^[a-f0-9]{64}$/D', $basis)) {
        externalSyncReject('invalid_review_input', 422);
    }
    if ($pdo->inTransaction()) {
        throw new LogicException('external_sync_requires_own_transaction');
    }
    $located = externalSyncQuery($pdo, 'SELECT connection_id FROM external_sync_runs WHERE id=?', [$runId])->fetchColumn();
    if ($located === false) {
        externalSyncReject('unknown_run', 404);
    }
    $pdo->beginTransaction();
    try {
        $connection = externalSyncQuery($pdo, 'SELECT * FROM external_sync_connections WHERE id=?' . externalSyncLock($pdo), [$located])->fetch(PDO::FETCH_ASSOC);
        $run = externalSyncQuery($pdo, 'SELECT * FROM external_sync_runs WHERE id=?' . externalSyncLock($pdo), [$runId])->fetch(PDO::FETCH_ASSOC);
        // Current locking reads AFTER waiting on the same fence used by publication.
        operationalCaseActor($pdo, $trainerId, 'registrar');
        $context = externalSyncReviewContext($pdo, $connection, $run, $ordinal, true);
        if (!$context['current'] || $context['review']['revision'] !== $revision || !hash_equals($context['review']['basis_hash'], $basis)) {
            externalSyncReject('review_changed', 409);
        }
        if ($state === 'resolved' && $context['match']['status'] !== 'matched') {
            externalSyncReject('identity_not_resolved', 409);
        }
        if (in_array($state, ['excluded', 'deferred'], true) && $context['match']['status'] === 'matched') {
            externalSyncReject('record_already_matched', 409);
        }
        externalSyncQuery($pdo, 'INSERT INTO external_sync_decisions(run_id,ordinal_no,revision,state,reason,actor_id,recorded_at,basis_hash)
            VALUES(?,?,?,?,?,?,?,?)', [$runId, $ordinal, $revision + 1, $state, $reason, $trainerId, time(), $basis]);
        $id = (int)$pdo->lastInsertId();
        externalSyncAudit($pdo, $connection['id'], $runId, 'trainer', (string)$trainerId, 'row_' . $state);
        if ($state === 'resolved') {
            sportovecLogEvent(
                $pdo,
                $context['match']['sportovec_id'],
                'external_sync_review',
                'Ověření ČSC / Sportsoft po opravě',
                [],
                ['decision_id' => $id, 'revision' => $revision + 1],
                'manual',
                $trainerId,
                null,
                'external_sync_decisions',
                $id
            );
        }
        $pdo->commit();
        return $id;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
