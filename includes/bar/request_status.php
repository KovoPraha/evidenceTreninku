<?php

declare(strict_types=1);

require_once __DIR__ . '/requests.php';

/** Reads only the current actor's result reference; never exposes stored payloads, other actors or purchase prices. */
function barRequestStatus(PDO $pdo, string $token): array
{
    $actor = barActor($pdo, 'request_status');
    if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
        throw new InvalidArgumentException('Neplatný identifikátor potvrzení.');
    }
    $query = $pdo->prepare('SELECT operation,result_json FROM kis_bar_requests WHERE token=? AND actor_id=?');
    $query->execute([$token, $actor['id']]);
    $row = $query->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $result = json_decode($row['result_json'], true, 16, JSON_THROW_ON_ERROR);
        $route = match ($row['operation']) {
            'sale', 'consumption', 'payment_confirm', 'sale_void' => 'bar/receipt.php?id=' . (int)$result['sale_id'],
            'cashbox_open', 'cashbox_close', 'cash_adjust' => 'bar/cashbox.php?id=' . (int)$result['cashbox_id'],
            'cup_return' => 'bar/cashbox.php',
            'cup_deposit_setting' => 'bar/settings.php',
            'invoice_upload', 'invoice_save', 'invoice_receive', 'invoice_reverse' => 'bar/invoices.php?id=' . (int)$result['invoice_id'],
            'offer_save', 'offer_publish' => 'bar/offers.php?id=' . (int)$result['offer_id'],
            'accounting_export' => 'bar/accounting.php?export=' . (int)$result['export_id'],
            'cashbox_recovery' => 'bar/operations.php?id=' . (int)$result['cashbox_id'],
            default => null,
        };
        return ['state' => 'completed', 'result_route' => $route];
    }
    if ($actor['position'] === 'bar_admin') {
        $query = $pdo->prepare('SELECT product_id FROM kis_bar_stock_operations WHERE token=? AND actor_id=?');
        $query->execute([$token, $actor['id']]);
        if ($product = $query->fetchColumn()) {
            return ['state' => 'completed', 'result_route' => 'bar/stock.php?product_id=' . (int)$product];
        }
    }
    return ['state' => 'unknown', 'result_route' => null];
}
