<?php
declare(strict_types=1);

require_once __DIR__ . '/kis_import_run_lib.php';

final class KisImportSandboxException extends RuntimeException {}

function kisImportSandboxReason(string $reason): string
{
    $reason = trim((string)preg_replace('/\s+/u', ' ', $reason));
    if (mb_strlen($reason, 'UTF-8') < 5 || mb_strlen($reason, 'UTF-8') > 500) {
        throw new InvalidArgumentException('Důvod musí mít 5 až 500 znaků.');
    }
    return $reason;
}

function kisImportSandboxAssertAllowed(bool $confirmed, bool $sandboxAllowed, int $actorId): void
{
    if (!$sandboxAllowed) {
        throw new KisImportSandboxException('Sandbox promote je povolen pouze administrátorovi na localhostu.');
    }
    if (!$confirmed) {
        throw new InvalidArgumentException('Akci je nutné výslovně potvrdit.');
    }
    if ($actorId < 1) {
        throw new InvalidArgumentException('Chybí auditní identita administrátora.');
    }
}

/** @return array{run:array<string,mixed>,report:array<string,mixed>} */
function kisImportSandboxLockedPreview(PDO $pdo, int $runId, string $fingerprint): array
{
    if ($runId < 1 || preg_match('/^[a-f0-9]{64}$/D', $fingerprint) !== 1) {
        throw new InvalidArgumentException('Neplatný běh nebo fingerprint náhledu.');
    }
    $sql = 'SELECT * FROM kis_import_runs WHERE id=?';
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $sql .= ' FOR UPDATE';
    }
    $statement = $pdo->prepare($sql);
    $statement->execute([$runId]);
    $run = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$run || (string)$run['status'] !== 'preview') {
        throw new KisImportSandboxException('KIS preview běh nebyl nalezen nebo už není v náhledu.');
    }
    $stored = kisImportStoredPreviewReport($run);
    $fresh = kisImportBuildPreviewReport($pdo, $runId);
    if ($stored === null
        || !hash_equals((string)$stored['fingerprint'], (string)$fresh['fingerprint'])
        || !hash_equals($fingerprint, (string)$fresh['fingerprint'])) {
        throw new KisImportSandboxException('Náhled se změnil nebo neprošel kontrolou fingerprintu.');
    }
    if (($fresh['status'] ?? null) !== 'ready_for_test_review'
        || (int)($fresh['summary']['blocker_rows'] ?? -1) !== 0
        || (int)($fresh['summary']['total_rows'] ?? 0) < 1
        || (int)$fresh['summary']['classified_rows'] !== (int)$fresh['summary']['total_rows']) {
        throw new KisImportSandboxException('Náhled obsahuje blokátory nebo není úplně klasifikovaný.');
    }
    $fieldContract = kisFieldContractStoredReport($run);
    if ($fieldContract === null
        || ($fieldContract['status'] ?? null) !== 'ready_for_parity'
        || (int)($fieldContract['summary']['total_blockers'] ?? -1) !== 0) {
        throw new KisImportSandboxException('Datovy kontrakt KIS ID neni pripraven pro sandbox promote.');
    }
    foreach ($fresh['rows'] as $row) {
        if (!in_array($row['action'] ?? null, ['create', 'exact_match'], true)) {
            throw new KisImportSandboxException('Náhled obsahuje akci nepovolenou pro sandbox promote.');
        }
    }
    return ['run' => $run, 'report' => $fresh];
}

/** @param array<string,mixed> $report */
function kisImportSandboxExpectedItems(array $report): array
{
    $items = [];
    foreach ($report['rows'] as $row) {
        $items[] = [
            'source_ref' => (string)$row['source_ref'],
            'action' => (string)$row['action'],
            'target_ref' => isset($row['target_ref']) ? (string)$row['target_ref'] : null,
        ];
    }
    return $items;
}

/** @return array<string,mixed> */
function kisImportSandboxPromotionForRun(PDO $pdo, int $runId): array
{
    $statement = $pdo->prepare(
        'SELECT p.*,'
        . '(SELECT COUNT(*) FROM kis_import_sandbox_items i WHERE i.promotion_id=p.id AND i.active=1) AS active_items,'
        . '(SELECT COUNT(*) FROM kis_import_sandbox_events e WHERE e.promotion_id=p.id) AS event_count '
        . 'FROM kis_import_sandbox_promotions p WHERE p.import_run_id=? ORDER BY p.id DESC LIMIT 1'
    );
    $statement->execute([$runId]);
    return $statement->fetch(PDO::FETCH_ASSOC) ?: [];
}

/** @return array<string,mixed> */
function kisImportSandboxPromote(PDO $pdo, int $runId, string $fingerprint, int $actorId, string $reason, bool $confirmed, bool $sandboxAllowed): array
{
    kisImportSandboxAssertAllowed($confirmed, $sandboxAllowed, $actorId);
    $reason = kisImportSandboxReason($reason);
    $ownsTransaction = !$pdo->inTransaction();
    $savepoint = 'kis_sandbox_promote';
    $ownsTransaction ? $pdo->beginTransaction() : $pdo->exec('SAVEPOINT ' . $savepoint);
    try {
        $preview = kisImportSandboxLockedPreview($pdo, $runId, $fingerprint);
        $items = kisImportSandboxExpectedItems($preview['report']);
        $sql = 'SELECT * FROM kis_import_sandbox_promotions WHERE import_run_id=? AND preview_fingerprint=?';
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $sql .= ' FOR UPDATE';
        }
        $statement = $pdo->prepare($sql);
        $statement->execute([$runId, $fingerprint]);
        $promotion = $statement->fetch(PDO::FETCH_ASSOC);
        if ($promotion && (string)$promotion['status'] === 'applied') {
            kisImportSandboxVerifyItems($pdo, (int)$promotion['id'], $items, true);
            $ownsTransaction ? $pdo->commit() : $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
            return array_merge($promotion, ['idempotent' => true, 'reapplied' => false]);
        }

        $reapplied = false;
        if (!$promotion) {
            $statement = $pdo->prepare(
                "INSERT INTO kis_import_sandbox_promotions(import_run_id,preview_fingerprint,status,item_count,applied_by,apply_reason) VALUES(?,?,'applied',?,?,?)"
            );
            $statement->execute([$runId, $fingerprint, count($items), $actorId, $reason]);
            $promotionId = (int)$pdo->lastInsertId();
            $insert = $pdo->prepare(
                'INSERT INTO kis_import_sandbox_items(promotion_id,source_ref,action,target_ref,active) VALUES(?,?,?,?,1)'
            );
            foreach ($items as $item) {
                $insert->execute([$promotionId, $item['source_ref'], $item['action'], $item['target_ref']]);
            }
            $eventAction = 'applied';
        } else {
            if ((string)$promotion['status'] !== 'rolled_back') {
                throw new KisImportSandboxException('Sandbox promote má neznámý stav.');
            }
            $promotionId = (int)$promotion['id'];
            kisImportSandboxVerifyItems($pdo, $promotionId, $items, false);
            $pdo->prepare('UPDATE kis_import_sandbox_items SET active=1,updated_at=CURRENT_TIMESTAMP WHERE promotion_id=?')->execute([$promotionId]);
            $pdo->prepare(
                "UPDATE kis_import_sandbox_promotions SET status='applied',apply_count=apply_count+1,applied_by=?,apply_reason=?,applied_at=CURRENT_TIMESTAMP,rolled_back_by=NULL,rollback_reason=NULL,rolled_back_at=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=?"
            )->execute([$actorId, $reason, $promotionId]);
            $eventAction = 'reapplied';
            $reapplied = true;
        }
        kisImportSandboxEvent($pdo, $promotionId, $eventAction, $actorId, $reason, $fingerprint, count($items));
        $ownsTransaction ? $pdo->commit() : $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
        return array_merge(kisImportSandboxPromotionForRun($pdo, $runId), ['idempotent' => false, 'reapplied' => $reapplied]);
    } catch (Throwable $exception) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        } elseif (!$ownsTransaction && $pdo->inTransaction()) {
            $pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
            $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
        }
        throw $exception;
    }
}

/** @return array<string,mixed> */
function kisImportSandboxRollback(PDO $pdo, int $runId, string $fingerprint, int $actorId, string $reason, bool $confirmed, bool $sandboxAllowed): array
{
    kisImportSandboxAssertAllowed($confirmed, $sandboxAllowed, $actorId);
    $reason = kisImportSandboxReason($reason);
    $ownsTransaction = !$pdo->inTransaction();
    $savepoint = 'kis_sandbox_rollback';
    $ownsTransaction ? $pdo->beginTransaction() : $pdo->exec('SAVEPOINT ' . $savepoint);
    try {
        if ($runId < 1 || preg_match('/^[a-f0-9]{64}$/D', $fingerprint) !== 1) {
            throw new InvalidArgumentException('Neplatný běh nebo fingerprint rollbacku.');
        }
        $sql = 'SELECT * FROM kis_import_sandbox_promotions WHERE import_run_id=? AND preview_fingerprint=?';
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $sql .= ' FOR UPDATE';
        }
        $statement = $pdo->prepare($sql);
        $statement->execute([$runId, $fingerprint]);
        $promotion = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$promotion) {
            throw new KisImportSandboxException('Sandbox promote pro rollback neexistuje.');
        }
        if (!hash_equals((string)$promotion['preview_fingerprint'], $fingerprint)) {
            throw new KisImportSandboxException('Fingerprint rollbacku neodpovídá auditovanému promote.');
        }
        if ((string)$promotion['status'] === 'rolled_back') {
            $ownsTransaction ? $pdo->commit() : $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
            return array_merge($promotion, ['idempotent' => true]);
        }
        if ((string)$promotion['status'] !== 'applied') {
            throw new KisImportSandboxException('Sandbox promote nelze vrátit z aktuálního stavu.');
        }
        $promotionId = (int)$promotion['id'];
        $active = (int)$pdo->query('SELECT COUNT(*) FROM kis_import_sandbox_items WHERE promotion_id=' . $promotionId . ' AND active=1')->fetchColumn();
        if ($active !== (int)$promotion['item_count']) {
            throw new KisImportSandboxException('Sandbox položky neodpovídají auditovanému promote.');
        }
        $pdo->prepare('UPDATE kis_import_sandbox_items SET active=0,updated_at=CURRENT_TIMESTAMP WHERE promotion_id=?')->execute([$promotionId]);
        $pdo->prepare(
            "UPDATE kis_import_sandbox_promotions SET status='rolled_back',rolled_back_by=?,rollback_reason=?,rolled_back_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE id=?"
        )->execute([$actorId, $reason, $promotionId]);
        kisImportSandboxEvent($pdo, $promotionId, 'rolled_back', $actorId, $reason, $fingerprint, (int)$promotion['item_count']);
        $ownsTransaction ? $pdo->commit() : $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
        return array_merge(kisImportSandboxPromotionForRun($pdo, $runId), ['idempotent' => false]);
    } catch (Throwable $exception) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        } elseif (!$ownsTransaction && $pdo->inTransaction()) {
            $pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
            $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
        }
        throw $exception;
    }
}

/** @param list<array{source_ref:string,action:string,target_ref:?string}> $expected */
function kisImportSandboxVerifyItems(PDO $pdo, int $promotionId, array $expected, bool $active): void
{
    $statement = $pdo->prepare('SELECT source_ref,action,target_ref,active FROM kis_import_sandbox_items WHERE promotion_id=? ORDER BY source_ref');
    $statement->execute([$promotionId]);
    $actual = $statement->fetchAll(PDO::FETCH_ASSOC);
    usort($expected, static fn(array $a, array $b): int => strcmp($a['source_ref'], $b['source_ref']));
    if (count($actual) !== count($expected)) {
        throw new KisImportSandboxException('Sandbox položky mají neočekávaný počet.');
    }
    foreach ($expected as $index => $item) {
        if ((string)$actual[$index]['source_ref'] !== $item['source_ref']
            || (string)$actual[$index]['action'] !== $item['action']
            || (($actual[$index]['target_ref'] ?? null) === null ? null : (string)$actual[$index]['target_ref']) !== $item['target_ref']
            || (int)$actual[$index]['active'] !== ($active ? 1 : 0)) {
            throw new KisImportSandboxException('Sandbox položky neodpovídají uzamčenému náhledu.');
        }
    }
}

function kisImportSandboxEvent(PDO $pdo, int $promotionId, string $action, int $actorId, string $reason, string $fingerprint, int $itemCount): void
{
    $snapshot = kisImportJson([
        'contract' => 'kis-import-sandbox-event-v1',
        'preview_fingerprint' => $fingerprint,
        'item_count' => $itemCount,
    ]);
    $pdo->prepare(
        'INSERT INTO kis_import_sandbox_events(promotion_id,action,actor_id,reason,snapshot_json) VALUES(?,?,?,?,?)'
    )->execute([$promotionId, $action, $actorId, $reason, $snapshot]);
}
