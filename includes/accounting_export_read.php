<?php

declare(strict_types=1);

/** Pure projections of the already-authorized immutable export snapshot. */
function accountingExportFinancialExceptions(array $snapshot, string $to): array
{
    $classified = [];
    foreach ($snapshot['classifications'] as $row) {
        $classified[(int) $row['movement_id']] = true;
    }
    $reconciliations = [];
    foreach ($snapshot['reconciliations'] as $row) {
        if ($row['as_of'] === $to) {
            $reconciliations[(int) $row['account_id']] = $row;
        }
    }
    $exceptions = [];
    foreach ($snapshot['movements'] as $movement) {
        if (!in_array($movement['kind'], ['transfer', 'transfer_in', 'transfer_out', 'settlement'], true)
            && !isset($classified[(int) $movement['id']])) {
            $exceptions[] = 'Pohyb #' . $movement['id'] . ' nemá doložené úplné účetní členění.';
        }
    }
    foreach ($snapshot['accounts'] as $account) {
        $proof = $reconciliations[(int) $account['id']] ?? null;
        if (!$proof || !(int) $proof['is_closed'] || (int) $proof['difference_minor'] !== 0) {
            $exceptions[] = 'Účet ' . $account['code'] . ' nemá uzavřené odsouhlasení ke konci období.';
        }
    }
    return $exceptions;
}

/** Preserve row and arithmetic order, including legacy null/zero identifiers. */
function accountingExportFinancialBalanceRows(array $snapshot): array
{
    $byAccount = [];
    $reversals = [];
    foreach ($snapshot['movements'] as $row) {
        $byAccount[(int) $row['account_id']][] = $row;
        $reversals[(int) ($row['reversal_of_id'] ?? 0)][] = (int) $row['amount_minor'];
    }
    $allocations = [];
    foreach ($snapshot['allocations'] as $row) {
        $allocations[(int) $row['movement_id']][] = (int) $row['amount_minor'];
    }
    $cancelled = [];
    foreach ($snapshot['adjustment_events'] as $row) {
        if ($row['event_type'] === 'decision_cancelled') {
            $cancelled[(int) ($row['decision_id'] ?? 0)] = true;
        }
    }
    $payments = [];
    foreach ($snapshot['refund_payments'] as $row) {
        $payments[(int) $row['decision_id']][] = (int) $row['amount_minor'];
    }
    $balances = [];
    foreach ($snapshot['accounts'] as $account) {
        $opening = (int) $account['opening_minor'];
        $turnover = 0;
        foreach ($byAccount[(int) $account['id']] ?? [] as $movement) {
            if ($movement['effective_on'] < $snapshot['period_from']) {
                $opening += (int) $movement['amount_minor'];
            } else {
                $turnover += (int) $movement['amount_minor'];
            }
        }
        $late = $account['opened_on'] > $snapshot['period_from'];
        $meaning = $account['name'] . ($late ? ' — doložený počátek až ' . $account['opened_on'] . ': '
            . number_format((int) $account['opening_minor'] / 100, 2, ',', ' ') . ' ' . $account['currency'] . '; dřívější stav neznámý' : '');
        $balances[] = ['Účet', (string) $account['id'], $meaning, $late ? null : $opening / 100,
            $turnover / 100, ($opening + $turnover) / 100, $account['currency']];
    }
    foreach ($snapshot['movements'] as $movement) {
        if ((int) $movement['amount_minor'] <= 0 || $movement['kind'] !== 'income') {
            continue;
        }
        $net = (int) $movement['amount_minor'];
        foreach ($allocations[(int) $movement['id']] ?? [] as $amount) {
            $net -= $amount;
        }
        foreach ($reversals[(int) $movement['id']] ?? [] as $amount) {
            $net += $amount;
        }
        $balances[] = ['Nepřiděleno', (string) $movement['id'], 'Příjem po přídělech a skutečných vratkách', null, null, $net / 100, $movement['currency']];
    }
    foreach ($snapshot['refund_decisions'] as $decision) {
        $isCancelled = isset($cancelled[(int) $decision['id']]);
        $paid = 0;
        foreach ($payments[(int) $decision['id']] ?? [] as $amount) {
            $paid += $amount;
        }
        $balances[] = ['Vratka k vyplacení', (string) $decision['id'], ($isCancelled ? 'Zrušeno; ' : '')
            . 'pohyb ' . $decision['original_movement_id'] . '; opravný doklad ' . ($decision['correction_document_id'] ?? 'přeplatek'),
            null, null, ($isCancelled ? 0 : (int) $decision['amount_minor'] - $paid) / 100, $decision['currency']];
    }
    return $balances;
}
