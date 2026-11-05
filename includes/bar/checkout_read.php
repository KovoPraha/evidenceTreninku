<?php

declare(strict_types=1);

require_once __DIR__ . '/reversals.php';

function barSaleReceipt(PDO $pdo, int $id): array
{
    $actor = barActor($pdo, 'sell');
    $sale = barOwnedSale($pdo, $id, $actor['id']);
    $query = $pdo->prepare('SELECT product_id,product_name,description,quantity,unit_price_minor,gross_minor,net_minor FROM kis_bar_sale_lines WHERE sale_id=? ORDER BY id');
    $query->execute([$id]);
    $lines = $query->fetchAll(PDO::FETCH_ASSOC);
    $query = $pdo->prepare('SELECT id,refunded_minor,reversed_minor,reason,created_at,refund_method,refund_reference FROM kis_bar_voids WHERE sale_id=?');
    $query->execute([$id]);
    return ['sale' => $sale, 'lines' => $lines, 'payment' => barSalePayment($pdo, $id), 'void' => $query->fetch(PDO::FETCH_ASSOC) ?: null,
        'returned_cups' => barReturnedCups($pdo, $id)];
}

function barRecentOwnSales(PDO $pdo, bool $consumption = false): array
{
    $actor = barActor($pdo, 'sell');
    $query = $pdo->prepare('SELECT s.id,s.cashbox_id,s.created_at,s.total_minor,p.method,p.status,p.verification_kind,v.id AS void_id '
        . 'FROM kis_bar_sales s LEFT JOIN kis_bar_sale_payments p ON p.sale_id=s.id LEFT JOIN kis_bar_voids v ON v.sale_id=s.id '
        . 'WHERE s.actor_id=? AND s.kind=? ORDER BY s.id DESC LIMIT 50');
    $query->execute([$actor['id'], $consumption ? 'consumption' : 'sale']);
    return $query->fetchAll(PDO::FETCH_ASSOC);
}

function barCashboxView(PDO $pdo, ?int $id = null): array
{
    $pdo->beginTransaction();
    try {
        $actor = barActor($pdo, 'sell');
        $query = $pdo->prepare('SELECT id,opened_at,closed_at FROM kis_bar_cashboxes WHERE owner_id=? ORDER BY id DESC LIMIT 20');
        $query->execute([$actor['id']]);
        $history = $query->fetchAll(PDO::FETCH_ASSOC);
        $box = $id === null ? barOwnOpenCashbox($pdo) : barOwnedCashbox($pdo, $id, $actor['id'], false);
        $totals = null;
        $movements = [];
        if ($box !== null) {
            $totals = $box['closed_at'] === null ? barCashboxTotals($pdo, (int)$box['id'])
                : json_decode($box['closing_snapshot_json'], true, 16, JSON_THROW_ON_ERROR);
            $query = $pdo->prepare('SELECT kind,amount_minor,reason,created_at FROM kis_bar_cash_movements WHERE cashbox_id=? ORDER BY id DESC LIMIT 50');
            $query->execute([$box['id']]);
            $movements = $query->fetchAll(PDO::FETCH_ASSOC);
        }
        $sales = barRecentOwnSales($pdo);
        $pdo->commit();
        return ['box' => $box, 'totals' => $totals, 'movements' => $movements, 'history' => $history, 'sales' => $sales];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
