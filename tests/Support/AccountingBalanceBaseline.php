<?php

declare(strict_types=1);

function w10BalanceRowsBaseline(array $snapshot): array
{
    $balances = [];
    foreach ($snapshot['accounts'] as $account) {
        $opening = (int)$account['opening_minor'];
        $turnover = 0;
        foreach ($snapshot['movements'] as $m) {
            if ((int)$m['account_id'] === (int)$account['id']) {
                if ($m['effective_on'] < $snapshot['period_from']) {
                    $opening += (int)$m['amount_minor'];
                } else {
                    $turnover += (int)$m['amount_minor'];
                }
            }
        }$late = $account['opened_on'] > $snapshot['period_from'];
        $meaning = $account['name'].($late ? ' — doložený počátek až '.$account['opened_on'].': '.number_format(
            (int)$account['opening_minor'] / 100,
            2,
            ',',
            ' '
        ).' '.$account['currency'].'; dřívější stav neznámý' : '');
        $balances[] = ['Účet',
        (string)$account['id'],
        $meaning,
        $late ? null : $opening / 100,
        $turnover / 100,
        ($opening + $turnover) / 100,
        $account['currency']];
    }
    foreach ($snapshot['movements'] as $m) {
        if ((int)$m['amount_minor'] > 0 &&
        $m['kind'] === 'income') {
            $net = (int)$m['amount_minor'];
            foreach ($snapshot['allocations'] as $a) {
                if ((int)$a['movement_id'] === (int)$m['id']) {
                    $net -= (int)$a['amount_minor'];
                }
            }foreach ($snapshot['movements'] as $r) {
                if ((int)($r['reversal_of_id'] ?? 0) === (int)$m['id']) {
                    $net += (int)$r['amount_minor'];
                }
            }$balances[] = ['Nepřiděleno',
            (string)$m['id'],
            'Příjem po přídělech a skutečných vratkách',
            null,
            null,
            $net / 100,
            $m['currency']];
        }
    }
    foreach ($snapshot['refund_decisions'] as $decision) {
        $cancelled = false;
        foreach ($snapshot['adjustment_events'] as $event) {
            if ((int)($event['decision_id'] ?? 0) === (int)$decision['id'] &&
            $event['event_type'] === 'decision_cancelled') {
                $cancelled = true;
            }
        }$paid = 0;
        foreach ($snapshot['refund_payments'] as $payment) {
            if ((int)$payment['decision_id'] === (int)$decision['id']) {
                $paid += (int)$payment['amount_minor'];
            }
        }$balances[] = ['Vratka k vyplacení',
        (string)$decision['id'],
        ($cancelled ? 'Zrušeno; ' : '').'pohyb '.$decision['original_movement_id'].'; opravný doklad '.($decision['correction_document_id'] ?? 'přeplatek'),
        null,
        null,
        ($cancelled ? 0 : (int)$decision['amount_minor'] - $paid) / 100,
        $decision['currency']];
    }
    return $balances;
}

function w10FinancialExceptionsBaseline(array $snapshot, string $to): array
{
    $snapshot['exceptions'] = [];
    foreach ($snapshot['movements'] as $m) {
        if (in_array($m['kind'], ['transfer','transfer_in','transfer_out','settlement'], true)) {
            continue;
        }
        $classification = null;
        foreach ($snapshot['classifications'] as $c) {
            if ((int)$c['movement_id'] === (int)$m['id']) {
                $classification = $c;
            }
        }
        if (!$classification) {
            $snapshot['exceptions'][] = 'Pohyb #'.$m['id'].' nemá doložené úplné účetní členění.';
        }
    }
    foreach ($snapshot['accounts'] as $account) {
        $proof = null;
        foreach ($snapshot['reconciliations'] as $r) {
            if ((int)$r['account_id'] === (int)$account['id'] && $r['as_of'] === $to) {
                $proof = $r;
            }
        }
        if (!$proof || !(int)$proof['is_closed'] || (int)$proof['difference_minor'] !== 0) {
            $snapshot['exceptions'][] = 'Účet '.$account['code'].' nemá uzavřené odsouhlasení ke konci období.';
        }
    }
    return $snapshot['exceptions'];
}
