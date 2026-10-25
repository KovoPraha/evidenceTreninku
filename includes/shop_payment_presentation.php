<?php
declare(strict_types=1);
require_once __DIR__.'/accounting_ledger.php';

/** Read-only customer view. Caller must first authorize access to the order. */
function shopOrderPaymentPresentation(PDO $pdo, array $order): array
{
    $status = (string)$order['payment_record_status'];
    $result = ['status'=>$status, 'refunded_minor'=>0, 'refunded_on'=>$order['refund_sent_at'] ?? null];
    if (!in_array($status, ['paid','refund_required'], true)) return $result;
    $coverage = accountingLedgerOrderRefundCoverage($pdo, (int)$order['id'], (string)$order['currency']);
    if ($coverage['total'] <= 0) return $result;
    $result['status'] = $coverage['total'] >= (int)$order['total_minor'] ? 'refunded' : 'partially_refunded';
    $result['refunded_minor'] = $coverage['total'];
    $result['refunded_on'] = $coverage['last_date'];
    return $result;
}
