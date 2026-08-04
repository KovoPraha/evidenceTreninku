<?php
declare(strict_types=1);

require_once __DIR__ . '/kis_import_run_lib.php';

final class KisMemberChargePromotionException extends RuntimeException {}

function kisMemberChargePromotionReason(string $reason): string
{
    $reason = trim((string)preg_replace('/\s+/u', ' ', $reason));
    $length = mb_strlen($reason, 'UTF-8');
    if ($length < 5 || $length > 1000) throw new InvalidArgumentException('Důvod musí mít 5 až 1000 znaků.');
    return $reason;
}

function kisMemberChargePromotionAssertAllowed(bool $confirmed, bool $allowed, int $actorId): void
{
    if (!$allowed) throw new KisMemberChargePromotionException('Přenos členských předpisů je povolen pouze administrátorovi na localhostu.');
    if (!$confirmed) throw new InvalidArgumentException('Akci je nutné výslovně potvrdit.');
    if ($actorId < 1) throw new InvalidArgumentException('Chybí auditní identita administrátora.');
}

/** @return array{run:array<string,mixed>,report:array<string,mixed>} */
function kisMemberChargeLockedParity(PDO $pdo, int $runId, string $fingerprint): array
{
    if ($runId < 1 || preg_match('/^[a-f0-9]{64}$/D', $fingerprint) !== 1) {
        throw new InvalidArgumentException('Neplatný běh nebo fingerprint paritního reportu.');
    }
    $sql = 'SELECT * FROM kis_import_runs WHERE id=?';
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $sql .= ' FOR UPDATE';
    $statement = $pdo->prepare($sql);
    $statement->execute([$runId]);
    $run = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$run || (string)$run['status'] !== 'preview') throw new KisMemberChargePromotionException('KIS preview běh nebyl nalezen nebo už není v náhledu.');
    $stored = kisImportStoredParityReport($run);
    $fresh = kisImportBuildParityReport($pdo, $runId);
    if ($stored === null
        || !hash_equals((string)$stored['fingerprint'], (string)$fresh['fingerprint'])
        || !hash_equals($fingerprint, (string)$fresh['fingerprint'])) {
        throw new KisMemberChargePromotionException('Paritní report se změnil nebo neprošel kontrolou fingerprintu.');
    }
    return ['run' => $run, 'report' => $fresh];
}

/** @return list<array<string,mixed>> */
function kisMemberChargePromotionRows(PDO $pdo, int $runId): array
{
    $statement = $pdo->prepare(
        'SELECT p.*,im.match_status,im.sportovec_id '
        . 'FROM kis_import_payment_rows p '
        . 'JOIN kis_import_rows ir ON ir.id=p.import_row_id AND ir.run_id=p.run_id '
        . 'LEFT JOIN kis_import_matches im ON im.row_id=ir.id AND im.run_id=ir.run_id '
        . 'WHERE p.run_id=? ORDER BY p.id'
    );
    $statement->execute([$runId]);
    $rows = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ((string)($row['match_status'] ?? '') !== 'matched' || (int)($row['sportovec_id'] ?? 0) < 1) {
            throw new KisMemberChargePromotionException('Každý předpis musí mít jednoznačně přiřazeného existujícího sportovce.');
        }
        $projection = memberChargeProjection([
            'payment_external_id' => $row['payment_external_id'],
            'status' => $row['status_snapshot'],
            'amount_minor' => $row['amount_minor'],
            'outstanding_minor' => $row['outstanding_minor'],
            'currency' => $row['currency'],
            'due_on' => $row['due_on'],
            'paid_on' => $row['paid_on'],
        ]);
        if ($projection['status'] === 'pending' && $projection['outstanding_minor'] !== $projection['amount_minor']) {
            throw new KisMemberChargePromotionException('Částečně uhrazený předpis zatím nelze bezpečně převést do platebního modelu.');
        }
        $snapshot = [
            'contract' => MEMBER_CHARGE_CONTRACT,
            'source_ref' => (string)$row['source_ref'],
            'sportovec_id' => (int)$row['sportovec_id'],
            'projection' => $projection,
        ];
        $rows[] = $row + [
            'projection' => $projection,
            'snapshot_fingerprint' => hash('sha256', kisImportJson($snapshot)),
        ];
    }
    if ($rows === []) throw new KisMemberChargePromotionException('Import neobsahuje žádné jednotlivé členské předpisy.');
    return $rows;
}

function kisMemberChargePublicCode(int $runId, string $sourceRef): string
{
    if (preg_match('/^payment:([1-9][0-9]*)$/D', $sourceRef, $match) !== 1) throw new KisMemberChargePromotionException('Předpis nemá platnou neprůhlednou referenci.');
    $code = 'KIS-' . $runId . '-' . $match[1];
    if (strlen($code) > 32) throw new KisMemberChargePromotionException('Kód importovaného předpisu je příliš dlouhý.');
    return $code;
}

function kisMemberChargeVariableSymbol(int $runId, string $sourceRef): string
{
    $number = hexdec(substr(hash('sha256', 'kis-member-charge:' . $runId . ':' . $sourceRef), 0, 8)) % 1000000000;
    return '8' . str_pad((string)$number, 9, '0', STR_PAD_LEFT);
}

/** @return array<string,mixed> */
function kisMemberChargePromotionForRun(PDO $pdo, int $runId): array
{
    if (!kisImportTableExists($pdo, 'kis_import_charge_promotions')) return [];
    $statement = $pdo->prepare(
        'SELECT p.*,'
        . '(SELECT COUNT(*) FROM kis_import_charge_promotion_items i WHERE i.promotion_id=p.id AND i.status=\'active\') AS active_items,'
        . '(SELECT COUNT(*) FROM kis_import_charge_promotion_events e WHERE e.promotion_id=p.id) AS event_count '
        . 'FROM kis_import_charge_promotions p WHERE p.import_run_id=?'
    );
    $statement->execute([$runId]);
    return $statement->fetch(PDO::FETCH_ASSOC) ?: [];
}

/** @param array<string,mixed> $row @param array<string,mixed>|null $storedItem */
function kisMemberChargeCreateTarget(PDO $pdo, int $runId, int $promotionId, array $row, int $actorId, string $reason, ?array $storedItem = null): array
{
    $projection = $row['projection'];
    $publicCode = $storedItem ? (string)$storedItem['public_code'] : kisMemberChargePublicCode($runId, (string)$row['source_ref']);
    $variableSymbol = $projection['status'] === 'paid'
        ? ($storedItem && $storedItem['variable_symbol'] !== null ? (string)$storedItem['variable_symbol'] : kisMemberChargeVariableSymbol($runId, (string)$row['source_ref']))
        : null;
    $existing = $pdo->prepare("SELECT id FROM club_member_charges WHERE source_system='kis_import' AND source_external_id=?");
    $existing->execute([$projection['source_external_id']]);
    if ($existing->fetchColumn()) throw new KisMemberChargePromotionException('Cílový členský předpis už existuje mimo tento auditovaný přenos.');
    if ($variableSymbol !== null) {
        $existingPayment = $pdo->prepare('SELECT id FROM payments WHERE variable_symbol=?');
        $existingPayment->execute([$variableSymbol]);
        if ($existingPayment->fetchColumn()) throw new KisMemberChargePromotionException('Odvozený variabilní symbol koliduje s existující platbou.');
    }

    $pdo->prepare(
        'INSERT INTO club_member_charges(sportovec_id,payer_account_id,public_code,charge_type,title_snapshot,period_from,period_to,amount_minor,currency,due_on,status,source_system,source_external_id,source_import_run_id) '
        . "VALUES (?,NULL,?,'membership','Členský předpis KIS',NULL,NULL,?,?,?,?, 'kis_import',?,?)"
    )->execute([
        (int)$row['sportovec_id'],
        $publicCode,
        $projection['amount_minor'],
        $projection['currency'],
        $projection['due_on'],
        $projection['status'],
        $projection['source_external_id'],
        $runId,
    ]);
    $chargeId = (int)$pdo->lastInsertId();
    $chargeSnapshot = kisImportJson([
        'contract' => MEMBER_CHARGE_CONTRACT,
        'source_ref' => (string)$row['source_ref'],
        'status' => $projection['status'],
        'amount_minor' => $projection['amount_minor'],
        'currency' => $projection['currency'],
    ]);
    $pdo->prepare(
        "INSERT INTO club_member_charge_events(charge_id,action,from_status,to_status,actor_type,actor_id,reason,snapshot_json) VALUES (?,'kis_import_create',NULL,?,'trainer',?,?,?)"
    )->execute([$chargeId, $projection['status'], $actorId, $reason, $chargeSnapshot]);

    $paymentId = null;
    if ($projection['status'] === 'paid') {
        $dueAt = ($projection['due_on'] ?? $projection['paid_on']) . ' 00:00:00';
        $paidAt = $projection['paid_on'] . ' 00:00:00';
        $pdo->prepare(
            'INSERT INTO payments(payable_type,payable_id,method,status,amount_minor,currency,variable_symbol,iban_snapshot,bic_snapshot,account_label_snapshot,spd_payload,due_at,paid_at,confirmed_by_trainer_id,confirmation_note) '
            . "VALUES ('member_charge',?,'kis_import','paid',?,?,?,'',NULL,'Historický import KIS','',?,?,?,?)"
        )->execute([
            $chargeId,
            $projection['amount_minor'],
            $projection['currency'],
            $variableSymbol,
            $dueAt,
            $paidAt,
            $actorId,
            $reason,
        ]);
        $paymentId = (int)$pdo->lastInsertId();
    }
    return ['charge_id' => $chargeId, 'payment_id' => $paymentId, 'public_code' => $publicCode, 'variable_symbol' => $variableSymbol];
}

/** @param list<array<string,mixed>> $rows */
function kisMemberChargeVerifyApplied(PDO $pdo, array $promotion, array $rows): void
{
    $statement = $pdo->prepare('SELECT * FROM kis_import_charge_promotion_items WHERE promotion_id=? ORDER BY staged_payment_row_id');
    $statement->execute([(int)$promotion['id']]);
    $items = $statement->fetchAll(PDO::FETCH_ASSOC);
    if (count($items) !== count($rows) || (int)$promotion['item_count'] !== count($rows)) {
        throw new KisMemberChargePromotionException('Položky přenosu neodpovídají uzamčenému stagingu.');
    }
    $byStagedId = [];
    foreach ($rows as $row) $byStagedId[(int)$row['id']] = $row;
    foreach ($items as $item) {
        $row = $byStagedId[(int)$item['staged_payment_row_id']] ?? null;
        if (!$row || (string)$item['status'] !== 'active' || !hash_equals((string)$item['snapshot_fingerprint'], (string)$row['snapshot_fingerprint'])) {
            throw new KisMemberChargePromotionException('Auditní položka přenosu se změnila.');
        }
        $chargeStatement = $pdo->prepare('SELECT * FROM club_member_charges WHERE id=?');
        $chargeStatement->execute([(int)$item['charge_id']]);
        $charge = $chargeStatement->fetch(PDO::FETCH_ASSOC);
        $projection = $row['projection'];
        if (!$charge
            || (int)$charge['sportovec_id'] !== (int)$row['sportovec_id']
            || (string)$charge['public_code'] !== (string)$item['public_code']
            || (string)$charge['source_system'] !== 'kis_import'
            || (string)$charge['source_external_id'] !== (string)$projection['source_external_id']
            || (int)$charge['source_import_run_id'] !== (int)$promotion['import_run_id']
            || (string)$charge['status'] !== (string)$projection['status']
            || (int)$charge['amount_minor'] !== (int)$projection['amount_minor']
            || (string)$charge['currency'] !== (string)$projection['currency']
            || trim((string)($charge['due_on'] ?? '')) !== trim((string)($projection['due_on'] ?? ''))) {
            throw new KisMemberChargePromotionException('Cílový členský předpis se od auditovaného přenosu liší.');
        }
        $eventStatement = $pdo->prepare('SELECT action,actor_type,actor_id FROM club_member_charge_events WHERE charge_id=? ORDER BY id');
        $eventStatement->execute([(int)$charge['id']]);
        $chargeEvents = $eventStatement->fetchAll(PDO::FETCH_ASSOC);
        if (count($chargeEvents) !== 1
            || (string)$chargeEvents[0]['action'] !== 'kis_import_create'
            || (string)$chargeEvents[0]['actor_type'] !== 'trainer'
            || (int)$chargeEvents[0]['actor_id'] !== (int)$promotion['applied_by']) {
            throw new KisMemberChargePromotionException('Auditní historie předpisu se změnila; rollback je zablokovaný.');
        }
        $paymentStatement = $pdo->prepare("SELECT * FROM payments WHERE payable_type='member_charge' AND payable_id=?");
        $paymentStatement->execute([(int)$charge['id']]);
        $payment = $paymentStatement->fetch(PDO::FETCH_ASSOC);
        if ($projection['status'] === 'paid') {
            if (!$payment
                || (int)$payment['id'] !== (int)$item['payment_id']
                || (string)$payment['method'] !== 'kis_import'
                || (string)$payment['status'] !== 'paid'
                || (int)$payment['amount_minor'] !== (int)$projection['amount_minor']
                || (string)$payment['currency'] !== (string)$projection['currency']
                || (string)$payment['variable_symbol'] !== (string)$item['variable_symbol']
                || substr((string)$payment['paid_at'], 0, 10) !== (string)$projection['paid_on']) {
                throw new KisMemberChargePromotionException('Samostatný platební záznam se od auditovaného přenosu liší.');
            }
        } elseif ($payment || $item['payment_id'] !== null) {
            throw new KisMemberChargePromotionException('Neuhrazený předpis nesmí mít importovanou platbu.');
        }
    }
}

function kisMemberChargePromotionEvent(PDO $pdo, int $promotionId, string $action, int $actorId, string $reason, string $sourceFingerprint, int $itemCount, int $paymentCount): void
{
    $snapshot = kisImportJson([
        'contract' => 'kis-member-charge-promotion-event-v1',
        'member_charge_contract' => MEMBER_CHARGE_CONTRACT,
        'source_fingerprint' => $sourceFingerprint,
        'item_count' => $itemCount,
        'payment_count' => $paymentCount,
    ]);
    $pdo->prepare('INSERT INTO kis_import_charge_promotion_events(promotion_id,action,actor_id,reason,snapshot_json) VALUES(?,?,?,?,?)')
        ->execute([$promotionId, $action, $actorId, $reason, $snapshot]);
}

/** @return array<string,mixed> */
function kisMemberChargePromote(PDO $pdo, int $runId, string $parityFingerprint, int $actorId, string $reason, bool $confirmed, bool $allowed): array
{
    kisMemberChargePromotionAssertAllowed($confirmed, $allowed, $actorId);
    $reason = kisMemberChargePromotionReason($reason);
    $ownsTransaction = !$pdo->inTransaction();
    $savepoint = 'kis_charge_promote';
    $ownsTransaction ? $pdo->beginTransaction() : $pdo->exec('SAVEPOINT ' . $savepoint);
    try {
        $locked = kisMemberChargeLockedParity($pdo, $runId, $parityFingerprint);
        $rows = kisMemberChargePromotionRows($pdo, $runId);
        $sourceFingerprint = (string)($locked['report']['source_fingerprints']['payment_prescriptions'] ?? '');
        if (preg_match('/^[a-f0-9]{64}$/D', $sourceFingerprint) !== 1) throw new KisMemberChargePromotionException('Chybí fingerprint stagingu členských předpisů.');

        $sql = 'SELECT * FROM kis_import_charge_promotions WHERE import_run_id=?';
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $sql .= ' FOR UPDATE';
        $statement = $pdo->prepare($sql);
        $statement->execute([$runId]);
        $promotion = $statement->fetch(PDO::FETCH_ASSOC);
        if ($promotion && (string)$promotion['status'] === 'applied') {
            if (!hash_equals((string)$promotion['source_fingerprint'], $sourceFingerprint)) throw new KisMemberChargePromotionException('Staging se od aplikovaného přenosu změnil.');
            kisMemberChargeVerifyApplied($pdo, $promotion, $rows);
            $ownsTransaction ? $pdo->commit() : $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
            return array_merge(kisMemberChargePromotionForRun($pdo, $runId), ['idempotent' => true, 'reapplied' => false]);
        }

        $domain = $locked['report']['domains']['payment_prescriptions'] ?? [];
        if ((int)($locked['report']['summary']['row_blockers'] ?? -1) !== 0
            || ($locked['report']['coverage_blockers'] ?? []) !== ['payment_prescriptions_not_promoted']
            || (int)($domain['staged_rows'] ?? 0) !== count($rows)
            || (int)($domain['target_missing'] ?? -1) !== count($rows)
            || (int)($domain['target_same'] ?? -1) !== 0
            || (int)($domain['target_different'] ?? -1) !== 0) {
            throw new KisMemberChargePromotionException('Paritní report není čistý podklad pro přenos členských předpisů.');
        }

        $paymentCount = count(array_filter($rows, static fn(array $row): bool => $row['projection']['status'] === 'paid'));
        $reapplied = false;
        $storedItems = [];
        if (!$promotion) {
            $pdo->prepare(
                "INSERT INTO kis_import_charge_promotions(import_run_id,source_fingerprint,contract_version,status,item_count,payment_count,applied_by,apply_reason) VALUES (?,?,?,'applying',?,?,?,?)"
            )->execute([$runId, $sourceFingerprint, MEMBER_CHARGE_CONTRACT, count($rows), $paymentCount, $actorId, $reason]);
            $promotionId = (int)$pdo->lastInsertId();
        } else {
            if ((string)$promotion['status'] !== 'rolled_back'
                || !hash_equals((string)$promotion['source_fingerprint'], $sourceFingerprint)
                || (int)$promotion['item_count'] !== count($rows)
                || (int)$promotion['payment_count'] !== $paymentCount) {
                throw new KisMemberChargePromotionException('Vrácený přenos neodpovídá aktuálnímu stagingu.');
            }
            $promotionId = (int)$promotion['id'];
            $itemStatement = $pdo->prepare('SELECT * FROM kis_import_charge_promotion_items WHERE promotion_id=?');
            $itemStatement->execute([$promotionId]);
            foreach ($itemStatement->fetchAll(PDO::FETCH_ASSOC) as $item) $storedItems[(int)$item['staged_payment_row_id']] = $item;
            if (count($storedItems) !== count($rows)) throw new KisMemberChargePromotionException('Vrácený přenos nemá úplný auditní seznam položek.');
            $reapplied = true;
        }

        foreach ($rows as $row) {
            $storedItem = $storedItems[(int)$row['id']] ?? null;
            if ($reapplied && (!$storedItem || !hash_equals((string)$storedItem['snapshot_fingerprint'], (string)$row['snapshot_fingerprint']))) {
                throw new KisMemberChargePromotionException('Vrácená auditní položka neodpovídá stagingu.');
            }
            $target = kisMemberChargeCreateTarget($pdo, $runId, $promotionId, $row, $actorId, $reason, $storedItem);
            if ($storedItem) {
                $pdo->prepare("UPDATE kis_import_charge_promotion_items SET charge_id=?,payment_id=?,status='active',updated_at=CURRENT_TIMESTAMP WHERE id=?")
                    ->execute([$target['charge_id'], $target['payment_id'], (int)$storedItem['id']]);
            } else {
                $pdo->prepare(
                    "INSERT INTO kis_import_charge_promotion_items(promotion_id,staged_payment_row_id,source_ref,public_code,variable_symbol,snapshot_fingerprint,charge_id,payment_id,status) VALUES (?,?,?,?,?,?,?,?,'active')"
                )->execute([$promotionId, (int)$row['id'], (string)$row['source_ref'], $target['public_code'], $target['variable_symbol'], (string)$row['snapshot_fingerprint'], $target['charge_id'], $target['payment_id']]);
            }
        }
        if ($reapplied) {
            $pdo->prepare(
                "UPDATE kis_import_charge_promotions SET status='applied',apply_count=apply_count+1,applied_by=?,apply_reason=?,applied_at=CURRENT_TIMESTAMP,rolled_back_by=NULL,rollback_reason=NULL,rolled_back_at=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=?"
            )->execute([$actorId, $reason, $promotionId]);
        } else {
            $pdo->prepare("UPDATE kis_import_charge_promotions SET status='applied',updated_at=CURRENT_TIMESTAMP WHERE id=?")
                ->execute([$promotionId]);
        }
        kisMemberChargePromotionEvent($pdo, $promotionId, $reapplied ? 'reapplied' : 'applied', $actorId, $reason, $sourceFingerprint, count($rows), $paymentCount);
        kisImportFinalizeParityReport($pdo, $runId);
        $ownsTransaction ? $pdo->commit() : $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
        return array_merge(kisMemberChargePromotionForRun($pdo, $runId), ['idempotent' => false, 'reapplied' => $reapplied]);
    } catch (Throwable $exception) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        elseif (!$ownsTransaction && $pdo->inTransaction()) {
            $pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
            $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
        }
        throw $exception;
    }
}

/** @return array<string,mixed> */
function kisMemberChargeRollback(PDO $pdo, int $runId, string $parityFingerprint, int $actorId, string $reason, bool $confirmed, bool $allowed): array
{
    kisMemberChargePromotionAssertAllowed($confirmed, $allowed, $actorId);
    $reason = kisMemberChargePromotionReason($reason);
    $ownsTransaction = !$pdo->inTransaction();
    $savepoint = 'kis_charge_rollback';
    $ownsTransaction ? $pdo->beginTransaction() : $pdo->exec('SAVEPOINT ' . $savepoint);
    try {
        $locked = kisMemberChargeLockedParity($pdo, $runId, $parityFingerprint);
        $rows = kisMemberChargePromotionRows($pdo, $runId);
        $sourceFingerprint = (string)($locked['report']['source_fingerprints']['payment_prescriptions'] ?? '');
        $sql = 'SELECT * FROM kis_import_charge_promotions WHERE import_run_id=?';
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $sql .= ' FOR UPDATE';
        $statement = $pdo->prepare($sql);
        $statement->execute([$runId]);
        $promotion = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$promotion || !hash_equals((string)$promotion['source_fingerprint'], $sourceFingerprint)) throw new KisMemberChargePromotionException('Auditovaný přenos pro rollback nebyl nalezen nebo staging nesouhlasí.');
        if ((string)$promotion['status'] === 'rolled_back') {
            $ownsTransaction ? $pdo->commit() : $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
            return array_merge(kisMemberChargePromotionForRun($pdo, $runId), ['idempotent' => true]);
        }
        if ((string)$promotion['status'] !== 'applied') throw new KisMemberChargePromotionException('Přenos nelze vrátit z aktuálního stavu.');
        kisMemberChargeVerifyApplied($pdo, $promotion, $rows);

        $items = $pdo->prepare('SELECT * FROM kis_import_charge_promotion_items WHERE promotion_id=? ORDER BY id DESC');
        $items->execute([(int)$promotion['id']]);
        foreach ($items->fetchAll(PDO::FETCH_ASSOC) as $item) {
            if ($item['payment_id'] !== null) {
                $deletedPayment = $pdo->prepare("DELETE FROM payments WHERE id=? AND payable_type='member_charge' AND payable_id=? AND method='kis_import'");
                $deletedPayment->execute([(int)$item['payment_id'], (int)$item['charge_id']]);
                if ($deletedPayment->rowCount() !== 1) throw new KisMemberChargePromotionException('Importovanou platbu nelze bezpečně vrátit.');
            }
            $pdo->prepare('DELETE FROM club_member_charge_events WHERE charge_id=?')->execute([(int)$item['charge_id']]);
            $deletedCharge = $pdo->prepare("DELETE FROM club_member_charges WHERE id=? AND source_system='kis_import' AND source_import_run_id=?");
            $deletedCharge->execute([(int)$item['charge_id'], $runId]);
            if ($deletedCharge->rowCount() !== 1) throw new KisMemberChargePromotionException('Importovaný předpis nelze bezpečně vrátit.');
            $pdo->prepare("UPDATE kis_import_charge_promotion_items SET charge_id=NULL,payment_id=NULL,status='rolled_back',updated_at=CURRENT_TIMESTAMP WHERE id=?")
                ->execute([(int)$item['id']]);
        }
        $pdo->prepare(
            "UPDATE kis_import_charge_promotions SET status='rolled_back',rolled_back_by=?,rollback_reason=?,rolled_back_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE id=?"
        )->execute([$actorId, $reason, (int)$promotion['id']]);
        kisMemberChargePromotionEvent($pdo, (int)$promotion['id'], 'rolled_back', $actorId, $reason, $sourceFingerprint, (int)$promotion['item_count'], (int)$promotion['payment_count']);
        kisImportFinalizeParityReport($pdo, $runId);
        $ownsTransaction ? $pdo->commit() : $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
        return array_merge(kisMemberChargePromotionForRun($pdo, $runId), ['idempotent' => false]);
    } catch (Throwable $exception) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        elseif (!$ownsTransaction && $pdo->inTransaction()) {
            $pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
            $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
        }
        throw $exception;
    }
}
