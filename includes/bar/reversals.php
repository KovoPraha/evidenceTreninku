<?php

declare(strict_types=1);

require_once __DIR__ . '/sale.php';

function barReturnedCups(PDO $pdo, int $saleId): array
{
    $query = $pdo->prepare('SELECT COALESCE(SUM(quantity),0) AS quantity,COALESCE(SUM(total_minor),0) AS amount_minor FROM kis_bar_cup_returns WHERE sale_id=?');
    $query->execute([$saleId]);
    $row = $query->fetch(PDO::FETCH_ASSOC);
    return ['quantity' => (int)$row['quantity'], 'amount_minor' => (int)$row['amount_minor']];
}

/** The refund always uses the original receipt rate, even after the current deposit setting changes. */
function barReturnCups(PDO $pdo, int $saleId, int $cashboxId, int $quantity, string $reason, string $token): array
{
    if ($quantity < 1 || $quantity > 200) {
        throw new InvalidArgumentException('Vratka musí mít jeden až dvě stě kelímků.');
    }
    $reason = barReason($reason);
    return barRunRequest(
        $pdo,
        'cup_return',
        $token,
        [$saleId, $cashboxId, $quantity, $reason],
        function (array $actor, string $suffix) use ($pdo, $saleId, $cashboxId, $quantity, $reason): array {
            barOwnedCashbox($pdo, $cashboxId, $actor['id'], true, $suffix);
            $query = $pdo->prepare("SELECT * FROM kis_bar_sales WHERE id=? AND kind='sale'" . $suffix);
            $query->execute([$saleId]);
            $sale = $query->fetch(PDO::FETCH_ASSOC);
            $payment = barSalePayment($pdo, $saleId, $suffix);
            if (!$sale || !$payment || $payment['status'] !== 'confirmed') {
                throw new DomainException('Doklad nemá potvrzenou platbu a dostupnou zálohu.');
            }
            barRequireUnvoidedSale($pdo, $saleId);
            $returned = barReturnedCups($pdo, $saleId);
            if ($quantity > (int)$sale['cup_quantity'] - $returned['quantity']) {
                throw new DomainException('Tolik nevrácených kelímků na dokladu nezbývá.');
            }
            $amount = barCheckMinor($quantity * (int)$sale['cup_unit_minor']);
            $pdo->prepare('INSERT INTO kis_bar_cup_returns(sale_id,cashbox_id,actor_id,quantity,unit_minor,total_minor,reason,created_at) VALUES(?,?,?,?,?,?,?,' . barSqlUtcNow($pdo) . ')')
                ->execute([$saleId, $cashboxId, $actor['id'], $quantity, $sale['cup_unit_minor'], $amount, $reason]);
            $id = (int)$pdo->lastInsertId();
            barAppendCash($pdo, $actor, $cashboxId, -$amount, 'cup_refund', 'cup_return', $id, $reason);
            barEvent($pdo, $actor['id'], 'cup_return', $id, 'refunded_cash', ['sale_id' => $saleId, 'quantity' => $quantity, 'amount_minor' => $amount, 'reason' => $reason]);
            return ['cup_return_id' => $id, 'sale_id' => $saleId, 'refunded_minor' => $amount];
        }
    );
}

/** A full reversal appends compensation. Neither the original receipt nor its cost/recipe snapshot is overwritten. */
function barVoidSale(PDO $pdo, int $saleId, ?int $refundCashboxId, string $reference, string $reason, string $token, ?int $expectedRefundMinor = null): array
{
    $reason = barReason($reason);
    $reference = trim(barSourceText($reference, 255));
    if ($expectedRefundMinor !== null) {
        barCheckMinor($expectedRefundMinor);
    }
    return barRunRequest(
        $pdo,
        'sale_void',
        $token,
        [$saleId, $refundCashboxId, $reference, $reason, $expectedRefundMinor],
        function (array $actor, string $suffix) use ($pdo, $saleId, $refundCashboxId, $reference, $reason, $expectedRefundMinor): array {
            $sale = barOwnedSale($pdo, $saleId, $actor['id'], $suffix);
            barRequireUnvoidedSale($pdo, $saleId);
            $payment = barSalePayment($pdo, $saleId, $suffix);
            $consumption = $sale['kind'] === 'consumption';
            $paid = !$consumption && $payment && $payment['status'] === 'confirmed';
            if (!$consumption && (!$payment || !in_array($payment['status'], ['confirmed', 'pending'], true))) {
                throw new DomainException('Doklad nemá platný stav platby.');
            }
            if ($paid) {
                if ($refundCashboxId === null) {
                    throw new DomainException('Pro vrácení platby otevřete vlastní kasu.');
                }
                barOwnedCashbox($pdo, $refundCashboxId, $actor['id'], true, $suffix);
                if ($payment['method'] !== 'cash' && mb_strlen($reference, 'UTF-8') < 3) {
                    throw new InvalidArgumentException('Potvrďte skutečné vrácení bezhotovostní platby a doplňte jeho podklad.');
                }
            } elseif (!$consumption) {
                barOwnedCashbox($pdo, (int)$sale['cashbox_id'], $actor['id'], true, $suffix);
            } elseif ($refundCashboxId !== null || $reference !== '') {
                throw new InvalidArgumentException('Storno vlastní spotřeby neobsahuje vrácení platby.');
            }
            $returned = barReturnedCups($pdo, $saleId);
            $remainingCups = (int)$sale['cup_quantity'] - $returned['quantity'];
            $remainingDeposit = (int)$sale['deposit_minor'] - $returned['amount_minor'];
            $reversed = (int)$sale['goods_net_minor'] + $remainingDeposit;
            $method = $paid ? $payment['method'] : 'none';
            if ($expectedRefundMinor !== null && $expectedRefundMinor !== ($paid ? $reversed : 0)) {
                throw new BarConflictException('Částka vratky se změnila, například vrácením kelímků. Obnovte doklad a zkontrolujte skutečně vracenou částku.');
            }
            if ($paid && $method !== 'cash') {
                $used = $pdo->prepare('SELECT id FROM kis_bar_voids WHERE refund_method=? AND refund_reference=?' . $suffix);
                $used->execute([$method, $reference]);
                if ($used->fetchColumn()) {
                    throw new DomainException('Tento podklad bezhotovostní vratky už byl použit pro jiný doklad.');
                }
            }
            $pdo->prepare('INSERT INTO kis_bar_voids(sale_id,actor_id,refund_cashbox_id,goods_minor,deposit_minor,cup_quantity,reversed_minor,'
                . 'refunded_minor,refund_method,refund_reference,reason,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,' . barSqlUtcNow($pdo) . ')')
                ->execute([$saleId, $actor['id'], $paid ? $refundCashboxId : null, $sale['goods_net_minor'], $remainingDeposit, $remainingCups, $reversed,
                    $paid ? $reversed : 0, $method, $paid && $method !== 'cash' ? $reference : null, $reason]);
            $voidId = (int)$pdo->lastInsertId();
            barRestoreSaleComponents($pdo, $actor, $saleId, $voidId, $consumption, $suffix);
            if ($paid && $method === 'cash') {
                barAppendCash($pdo, $actor, $refundCashboxId, -$reversed, 'sale_refund', 'void', $voidId, $reason);
            } elseif ($paid) {
                $pdo->prepare('UPDATE kis_bar_cashboxes SET lock_version=lock_version+1 WHERE id=?')->execute([$refundCashboxId]);
            } elseif (!$consumption) {
                $pdo->prepare("UPDATE kis_bar_sale_payments SET status='cancelled' WHERE sale_id=?")->execute([$saleId]);
            }
            if (!$consumption && !$paid) {
                $pdo->prepare('UPDATE kis_bar_cashboxes SET lock_version=lock_version+1 WHERE id=?')->execute([$sale['cashbox_id']]);
            }
            barEvent(
                $pdo,
                $actor['id'],
                'void',
                $voidId,
                $consumption ? 'consumption_reversed' : 'sale_reversed',
                ['sale_id' => $saleId, 'reversed_minor' => $reversed, 'refunded_minor' => $paid ? $reversed : 0, 'refund_method' => $method, 'reason' => $reason]
            );
            return ['void_id' => $voidId, 'sale_id' => $saleId, 'refunded_minor' => $paid ? $reversed : 0];
        }
    );
}

/** Compensates immutable components; current recipes and purchase prices never rewrite the original withdrawal. */
function barRestoreSaleComponents(PDO $pdo, array $actor, int $saleId, int $voidId, bool $consumption, string $suffix): void
{
    if (!$pdo->inTransaction()) {
        throw new LogicException('Vrácení zásob vyžaduje transakci.');
    }
    $query = $pdo->prepare('SELECT * FROM kis_bar_sale_components WHERE sale_id=? ORDER BY product_id');
    $query->execute([$saleId]);
    foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $component) {
        $productQuery = $pdo->prepare('SELECT * FROM kis_bar_products WHERE id=?' . $suffix);
        $productQuery->execute([$component['product_id']]);
        $product = $productQuery->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            throw new DomainException('Původní surovina už neexistuje.');
        }
        $cost = $component['purchase_unit_minor'] === null ? null : (int)$component['purchase_unit_minor'];
        barRecordPhysicalMovement(
            $pdo,
            $actor,
            $product,
            (int)$component['quantity'],
            $component['location_code'],
            $consumption ? 'consumption_void' : 'sale_void',
            $voidId,
            $cost,
            $component['product_name']
        );
    }
}
