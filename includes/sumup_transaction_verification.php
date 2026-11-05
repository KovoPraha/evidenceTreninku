<?php

declare(strict_types=1);

/** Validate the independent transaction resource before the first local payment transition. */
function sumupAssertTransactionPaid(array $detail, string $id, int $amountMinor, string $currency, string $merchant): void
{
    if (($detail['id'] ?? null) !== $id || ($detail['status'] ?? null) !== 'SUCCESSFUL'
        || ($detail['payment_type'] ?? null) !== 'ECOM' || ($detail['merchant_code'] ?? null) !== $merchant
        || ($detail['currency'] ?? null) !== $currency || sumupAmountToMinor($detail['amount'] ?? null) !== $amountMinor) {
        throw new SumUpGatewayException('Detail SumUp transakce nepotvrzuje odpovídající online úhradu.');
    }
    if ((array_key_exists('simple_status', $detail) && !in_array($detail['simple_status'], ['SUCCESSFUL', 'PAID_OUT'], true))
        || (array_key_exists('refunded_amount', $detail) && sumupAmountToMinor($detail['refunded_amount']) !== 0)) {
        throw new SumUpGatewayException('SumUp transakce byla vrácena nebo vyžaduje kontrolu.');
    }
    $historyPresent = false;
    foreach (['events' => 'type', 'transaction_events' => 'event_type'] as $field => $typeField) {
        if (!isset($detail[$field])) {
            continue;
        }
        $events = $detail[$field];
        if (!is_array($events) || !array_is_list($events)) {
            throw new SumUpGatewayException('SumUp nevrátil platnou historii transakce.');
        }
        $historyPresent = true;
        foreach ($events as $event) {
            if (!is_array($event) || !in_array($event[$typeField] ?? null, ['PAYOUT', 'REFUND', 'CHARGE_BACK', 'PAYOUT_DEDUCTION'], true)
                || !in_array($event['status'] ?? null, ['FAILED', 'CANCELLED', 'PAID_OUT', 'PENDING', 'RECONCILED', 'REFUNDED', 'SCHEDULED', 'SUCCESSFUL'], true)) {
                throw new SumUpGatewayException('Událost SumUp transakce vyžaduje kontrolu.');
            }
            if ($event[$typeField] !== 'PAYOUT' && !in_array($event['status'], ['FAILED', 'CANCELLED'], true)) {
                throw new SumUpGatewayException('SumUp transakce obsahuje vratku nebo zpochybnění úhrady.');
            }
        }
    }
    if (!$historyPresent) {
        throw new SumUpGatewayException('SumUp nevrátil historii transakce pro ověření vratek.');
    }
}
