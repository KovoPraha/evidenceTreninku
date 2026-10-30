<?php

declare(strict_types=1);
require_once __DIR__.'/accounting_ledger_read_context.php';
function accountingLedgerReadSource(PDO $pdo, string $sourceType, int $sourceId, ?AccountingLedgerReadContext $read = null): array
{
    $read?->assertConnection($pdo);
    if (!in_array(
        $sourceType,
        ['payments',
    'club_finance_receipts',
    'member_payer_receipts',
    'fio_account_movements',
    'legacy_refund'],
        true
    ) ||
    $sourceId < 1) {
        throw new AccountingLedgerException('Neznámý zdroj peněžního pohybu.');
    }
    $table = $sourceType === 'legacy_refund' ? 'payments' : $sourceType;
    $q = $pdo->prepare('SELECT * FROM '.$table.' WHERE id=?');
    $q->execute([$sourceId]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new AccountingLedgerException('Zdrojový záznam nebyl nalezen.');
    }
    $result = ['source_type' => $sourceType,
    'source_id' => $sourceId,
    'amount_minor' => (int)$row['amount_minor'],
    'currency' => (string)$row['currency'],
    'effective_on' => null,
    'source_context' => '',
    'source_reference' => '',
    'case_type' => null,
    'case_id' => null,
    'account_kind' => null,
    'aliases' => [['source_type' => $sourceType,
    'source_id' => $sourceId]],
    'flags' => ['unmapped_account']];
    if ($sourceType === 'payments' || $sourceType === 'legacy_refund') {
        if (!in_array($row['status'], ['paid','refund_required','refunded'], true)) {
            throw new AccountingLedgerException('Platební požadavek není důkazem přijatých peněz.');
        }
        if ($sourceType === 'legacy_refund') {
            if ($row['status'] !== 'refunded' || empty($row['refund_sent_at'])) {
                throw new AccountingLedgerException('Původní odeslání vratky není doloženo.');
            }
            if (accountingLedgerRefundDerived($pdo, $row)) {
                throw new AccountingLedgerException('Tato vratka je odvozené dokončení již evidovaných účetních výplat.');
            }
            $result['amount_minor'] = -$result['amount_minor'];
            $result['source_context'] = 'legacy-refund';
            $result['source_reference'] = (string)$sourceId;
        } else {
            if (accountingLedgerPaymentDerived($pdo, $row)) {
                throw new AccountingLedgerException('Odvozená úhrada je rozdělení původních příjmů, nikoli další peníze.');
            }
            $result['account_kind'] = match($row['payment_source'] ?? $row['method'] ?? '') {
                'cash' => 'cash','bank_transfer' => 'bank','stripe','sumup' => 'provider',
                default => null
            };
            $result['source_context'] = empty($row['bank_movement_id']) ? 'payment' : 'bank-reference';
            $result['source_reference'] = empty($row['bank_movement_id']) ? (string)$sourceId : (string)$row['bank_movement_id'];
        }
        $result['case_type'] = (string)$row['payable_type'];
        $result['case_id'] = (int)$row['payable_id'];
    } elseif ($sourceType === 'fio_account_movements') {
        $result['source_context'] = 'bank-reference';
        $result['source_reference'] = (string)$row['fio_movement_id'];
        $result['effective_on'] = (string)$row['booked_on'];
    } else {
        $result['source_context'] = 'bank-reference';
        $result['source_reference'] = (string)$row['reference'];
        $result['effective_on'] = (string)$row['paid_on'];
    }
    if ($result['source_context'] === 'bank-reference') {
        $result['account_kind'] = 'bank';
        foreach (['fio_account_movements' => 'fio_movement_id','club_finance_receipts' => 'reference',
    'member_payer_receipts' => 'reference','payments' => 'bank_movement_id'] as $aliasTable => $column) {
            if (!($read !== null ? $read->tableExists($pdo, $aliasTable) : accountingLedgerTable(
                $pdo,
                $aliasTable
            ))) {
                continue;
            }
            try {
                $q = $pdo->prepare('SELECT * FROM '.$aliasTable.' WHERE '.$column.'=?');
                $q->execute([$result['source_reference']]);
            } catch (PDOException $e) {
                if ($aliasTable === 'payments' &&
    str_contains(
        $e->getMessage(),
        'column'
    )) {
                    continue;
                }throw $e;
            }
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $alias) {
                if ($aliasTable === 'payments' && (!in_array($alias['status'], ['paid',
    'refund_required','refunded'], true) || accountingLedgerPaymentDerived($pdo, $alias))) {
                    continue;
                }
                $date = $aliasTable === 'fio_account_movements' ? $alias['booked_on'] : ($alias['paid_on'] ?? null);
                if ((int)$alias['amount_minor'] !== $result['amount_minor'] ||
    (string)$alias['currency'] !== $result['currency'] ||
    ($date !== null &&
    $result['effective_on'] !== null &&
    $date !== $result['effective_on'])) {
                    throw new AccountingLedgerException('Zdrojová reference má rozporné částky, měnu nebo data.');
                }
                if ($date !== null) {
                    $result['effective_on'] = $date;
                }
                if ($aliasTable === 'payments') {
                    $result['case_type'] = $alias['payable_type'];
                    $result['case_id'] = (int)$alias['payable_id'];
                }
                $result['aliases'][] = ['source_type' => $aliasTable,'source_id' => (int)$alias['id']];
            }
        }
    }
    $unique = [];
    foreach ($result['aliases'] as $alias) {
        $unique[$alias['source_type'].':'.$alias['source_id']] = $alias;
    }$result['aliases'] = array_values($unique);
    $result['canonical_key'] = $result['source_context'].':'.$result['source_reference'];
    if ($result['effective_on'] === null) {
        $result['flags'][] = 'unknown_date';
    }
    return $result;
}

function accountingLedgerReadPendingSources(PDO $pdo, int $limit = 200): array
{
    $read = new AccountingLedgerReadContext($pdo);
    $limit = max(1, min(10000, $limit));
    $result = [];
    $seen = [];
    foreach (['fio_account_movements','club_finance_receipts','member_payer_receipts','payments',
    'legacy_refund'] as $type) {
        $table = $type === 'legacy_refund' ? 'payments' : $type;
        if (!$read->tableExists($pdo, $table)) {
            continue;
        }
        $where = $type === 'payments' ? " WHERE s.status IN ('paid','refund_required','refunded')" : ($type === 'legacy_refund' ? " WHERE s.status='refunded'" : ' WHERE 1=1');
        $q = $pdo->prepare('SELECT s.* FROM '.$table.' s'.$where.' AND NOT EXISTS (SELECT 1 FROM accounting_movement_sources a WHERE a.source_type=? AND a.source_id=s.id) ORDER BY s.id LIMIT '
    .$limit);
        $q->execute([$type]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($type === 'payments' && accountingLedgerPaymentDerived($pdo, $row)) {
                continue;
            }
            if ($type === 'legacy_refund' && accountingLedgerRefundDerived($pdo, $row)) {
                continue;
            }
            try {
                $source = accountingLedgerReadSource(
                    $pdo,
                    $type,
                    (int)$row['id'],
                    $read
                );
                foreach (accountingLedgerReadSourceAllocations(
                    $pdo,
                    $source,
                    $read
                ) as $allocation) {
                    if ($allocation['effective_on'] === null) {
                        $source['flags'][] = 'unknown_allocation_date';
                        break;
                    }
                }
            } catch (AccountingLedgerException $error) {
                $source = ['source_type' => $type,
    'source_id' => (int)$row['id'],
    'amount_minor' => (int)$row['amount_minor'],
    'currency' => $row['currency'],
    'effective_on' => null,
    'canonical_key' => $type.':'.$row['id'],
    'flags' => ['source_conflict'],
    'error' => $error->getMessage()];
            }
            if (isset($seen[$source['canonical_key']])) {
                continue;
            }$seen[$source['canonical_key']] = true;
            $result[] = $source;
            if (count($result) >= $limit) {
                return $result;
            }
        }
    }
    // A receipt may be mapped before a later allocation. Surface that delta instead of silently
    // reporting its money as free credit. Reconciliation repeats only the missing source links.
    $q = $pdo->query(('SELECT s.source_type,s.source_id,s.movement_id,m.amount_minor AS mapped_amount_minor,m.currency AS m'
 . 'apped_currency,m.effective_on AS mapped_effective_on FROM accounting_movement_sources s JOIN account'
 . 'ing_movements m ON m.id=s.movement_id ORDER BY s.id LIMIT ').($limit * 5 + 1));
    $mappedRows = $q->fetchAll(PDO::FETCH_ASSOC);
    $scanTruncated = count($mappedRows) > $limit * 5;
    if ($scanTruncated) {
        array_pop($mappedRows);
    }
    foreach ($mappedRows as $mapped) {
        try {
            $source = accountingLedgerReadSource(
                $pdo,
                $mapped['source_type'],
                (int)$mapped['source_id'],
                $read
            );
            if ((int)$mapped['mapped_amount_minor'] !== $source['amount_minor'] ||
    $mapped['mapped_currency'] !== $source['currency'] ||
    ($source['effective_on'] !== null &&
    $mapped['mapped_effective_on'] !== $source['effective_on'])) {
                throw new AccountingLedgerException('Původní zdroj se po účetním přiřazení změnil; ověřte částku, měnu a skutečné datum.');
            }
            if (isset($seen[$source['canonical_key']])) {
                continue;
            }
            foreach (accountingLedgerReadSourceAllocations($pdo, $source, $read) as $allocation) {
                $check = $pdo->prepare('SELECT amount_minor FROM accounting_allocations WHERE request_key=?');
                $check->execute([$allocation['request_key']]);
                $amount = $check->fetchColumn();
                if ($amount === false || (int)$amount !== $allocation['amount_minor']) {
                    $source['flags'] = ['allocation_sync_required'];
                    if ($allocation['effective_on'] === null) {
                        $source['flags'][] = 'unknown_allocation_date';
                    }$source['movement_id'] = (int)$mapped['movement_id'];
                    $result[] = $source;
                    $seen[$source['canonical_key']] = true;
                    break;
                }
            }
        } catch (AccountingLedgerException $e) {
            $result[] = ['source_type' => $mapped['source_type'],
    'source_id' => (int)$mapped['source_id'],
    'amount_minor' => 0,
    'currency' => 'CZK',
    'effective_on' => null,
    'canonical_key' => $mapped['source_type'].':'.$mapped['source_id'],
    'flags' => ['source_conflict'],
    'error' => $e->getMessage()];
        }
        if (count($result) >= $limit) {
            return $result;
        }
    }
    if ($scanTruncated) {
        $result[] = ['source_type' => 'coverage',
        'source_id' => 0,
        'amount_minor' => 0,
        'currency' => 'CZK',
        'effective_on' => null,
        'canonical_key' => 'coverage:allocation-scan',
        'flags' => ['source_scan_truncated'],
        'error' => 'Kontrola již přiřazených zdrojů přesáhla limit. Úplnost nelze potvrdit.'];
    }
    return $result;
}

function accountingLedgerReadSourceAllocations(PDO $pdo, array $source, ?AccountingLedgerReadContext $read = null): array
{
    $read?->assertConnection($pdo);
    if ($source['amount_minor'] < 1) {
        return [];
    }
    $allocations = [];
    foreach ($source['aliases'] as $alias) {
        $type = $alias['source_type'];
        $id = (int)$alias['source_id'];
        $table = match($type) {
            'club_finance_receipts' => 'club_finance_allocations','member_payer_receipts' => 'member_payer_receipt_allocations',
            default => null
        };
        if ($table !== null && ($read !== null ? $read->tableExists($pdo, $table) : accountingLedgerTable(
            $pdo,
            $table
        ))) {
            $q = $pdo->prepare('SELECT * FROM '.$table.' WHERE receipt_id=?');
            $q->execute([$id]);
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $caseType = !empty($row['charge_id']) ? 'member_charge' : 'shop_order';
                $caseId = (int)($row['charge_id'] ?? $row['order_id'] ?? 0);
                $date = substr((string)($row['created_at'] ?? ''), 0, 10);
                $key = 'source-alloc:'.$table.':'.($row['id'] ?? ($id.'-'.$caseType.'-'
    .$caseId));
                $allocations[$key] = ['case_type' => $caseType,
    'case_id' => $caseId,
    'amount_minor' => (int)$row['amount_minor'],
    'request_key' => $key,
    'effective_on' => preg_match(
        '/^\d{4}-\d{2}-\d{2}$/D',
        $date
    ) ? $date : null];
            }
        } elseif ($type === 'payments') {
            $q = $pdo->prepare('SELECT * FROM payments WHERE id=?');
            $q->execute([$id]);
            $payment = $q->fetch(PDO::FETCH_ASSOC);
            if (!$payment || accountingLedgerPaymentDerived($pdo, $payment) || !in_array(
                $payment['payable_type'],
                ['shop_order','member_charge'],
                true
            )) {
                continue;
            }
            $date = substr((string)($payment['paid_at'] ?? ''), 0, 10);
            $key = 'source-alloc:payments:'.$id;
            $allocations[$key] = ['case_type' => $payment['payable_type'],
    'case_id' => (int)$payment['payable_id'],
    'amount_minor' => (int)$payment['amount_minor'],
    'request_key' => $key,
    'effective_on' => preg_match(
        '/^\d{4}-\d{2}-\d{2}$/D',
        $date
    ) ? $date : null];
        }
    }
    // A direct payment and receipt carrying the same bank reference are duplicate source aliases;
    // prefer the actual receipt allocations, otherwise their paid state would allocate twice.
    $hasReceiptAllocations = (bool)array_filter(array_keys($allocations), static fn ($key) => !str_starts_with(
        $key,
        'source-alloc:payments:'
    ));
    if ($hasReceiptAllocations) {
        $allocations = array_filter($allocations, static fn ($row) => !str_starts_with(
            $row['request_key'],
            'source-alloc:payments:'
        ));
    }
    return array_values($allocations);
}
