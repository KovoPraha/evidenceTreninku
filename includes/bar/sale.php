<?php

declare(strict_types=1);

require_once __DIR__ . '/quote.php';

function barRecordPhysicalMovement(PDO $pdo, array $actor, array $product, int $delta, string $location, string $kind, int $sourceId, ?int $unitCost, string $name): int
{
    if (!$pdo->inTransaction() || !in_array($location, ['stall', 'office'], true)) {
        throw new LogicException('Skladový pohyb vyžaduje transakci a známé místo.');
    }
    $column = $location === 'stall' ? 'stock_stall' : 'stock_office';
    $after = (int)$product[$column] + $delta;
    if ($after < 0 || $after > 1000000 || $product['stock_mode'] !== 'sledovat' || (int)$product['archived'] === 1) {
        throw new DomainException('Zásoba nestačí nebo původní skladová položka není aktivní. Zkontrolujte sklad.');
    }
    $reason = $kind . ' #' . $sourceId;
    $key = hash('sha256', json_encode(['kis_bar', $kind, $sourceId, (int)$product['id'], $location], JSON_THROW_ON_ERROR));
    $pdo->prepare('INSERT INTO kis_bar_stock_operations(token,request_sha256,product_id,actor_id,kind,reason,created_at) VALUES(?,?,?,?,?,?,' . barSqlUtcNow($pdo) . ')')
        ->execute([$key, hash('sha256', $key . ':' . $delta), $product['id'], $actor['id'], $kind, $reason]);
    $id = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO kis_bar_stock_movements(operation_id,product_id,location_code,quantity_delta,quantity_after,purchase_minor,product_name) VALUES(?,?,?,?,?,?,?)')
        ->execute([$id, $product['id'], $location, $delta, $after, $unitCost, $name]);
    $pdo->prepare('UPDATE kis_bar_products SET ' . $column . '=?,lock_version=lock_version+1 WHERE id=?')->execute([$after, $product['id']]);
    return $id;
}

function barSubmitSale(PDO $pdo, int $cashboxId, array $cart, int $discount, int $cups, string $fingerprint, string $method, string $token): array
{
    $cart = barNormalizeCart($cart);
    if (!in_array($method, ['cash', 'card', 'qr'], true)) {
        throw new InvalidArgumentException('Vyberte způsob platby.');
    }
    return barRunRequest(
        $pdo,
        'sale',
        $token,
        [$cashboxId, $cart, $discount, $cups, $fingerprint, $method],
        function (array $actor, string $suffix) use ($pdo, $cashboxId, $cart, $discount, $cups, $fingerprint, $method): array {
            barOwnedCashbox($pdo, $cashboxId, $actor['id'], true, $suffix);
            $snapshot = barSaleSnapshot($pdo, $cart, $discount, $cups, false, false, $suffix);
            barRequireQuoteFingerprint($snapshot, $fingerprint);
            if ($snapshot['total_minor'] === 0 && $method !== 'cash') {
                throw new InvalidArgumentException('Nulová objednávka se potvrzuje bez úhrady. Nevytvářejte pro ni čekající platbu.');
            }
            $id = barInsertSaleSnapshot($pdo, $actor, $cashboxId, $snapshot, 'sale', 'stall', 'Prodej v pokladně');
            $confirmed = $method === 'cash';
            $pdo->prepare('INSERT INTO kis_bar_sale_payments(sale_id,method,status,amount_minor,verification_kind,confirmed_by,confirmed_at) '
                . 'VALUES(?,?,?,?,?,?,CASE WHEN ? IS NOT NULL THEN ' . barSqlUtcNow($pdo) . ' ELSE NULL END)')
                    ->execute([$id, $method, $confirmed ? 'confirmed' : 'pending', $snapshot['total_minor'],
                        $confirmed ? ($snapshot['total_minor'] === 0 ? 'zero_amount' : 'cash') : null,
                    $confirmed ? $actor['id'] : null, $confirmed ? $actor['id'] : null]);
            if ($confirmed) {
                barAppendCash($pdo, $actor, $cashboxId, $snapshot['total_minor'], 'sale', 'sale', $id, 'Hotovostní prodej #' . $id);
            } else {
                $pdo->prepare('UPDATE kis_bar_cashboxes SET lock_version=lock_version+1 WHERE id=?')->execute([$cashboxId]);
            }
            barEvent($pdo, $actor['id'], 'sale', $id, 'created', ['cashbox_id' => $cashboxId, 'method' => $method,
                'payment_status' => $confirmed ? 'confirmed' : 'pending', 'total_minor' => $snapshot['total_minor']]);
            return ['sale_id' => $id, 'total_minor' => $snapshot['total_minor'], 'payment_status' => $confirmed ? 'confirmed' : 'pending'];
        }
    );
}

function barRequireQuoteFingerprint(array $snapshot, string $fingerprint): void
{
    if (!preg_match('/^[a-f0-9]{64}$/D', $fingerprint) || !hash_equals($snapshot['fingerprint'], $fingerprint)) {
        throw new BarConflictException('Cena, produkt, záloha nebo receptura se změnila. Sestavte nový náhled a potvrďte jej.');
    }
}

function barSubmitConsumption(PDO $pdo, array $cart, string $location, string $fingerprint, string $reason, string $token): array
{
    $cart = barNormalizeCart($cart);
    $reason = barReason($reason);
    if (!in_array($location, ['stall', 'office'], true)) {
        throw new InvalidArgumentException('Neplatné skladové místo.');
    }
    return barRunRequest(
        $pdo,
        'consumption',
        $token,
        [$cart, $location, $fingerprint, $reason],
        function (array $actor, string $suffix) use ($pdo, $cart, $location, $fingerprint, $reason): array {
            // A replay is read-only; every new withdrawal checks the explicit grant after acquiring the module lock.
            barActor($pdo, 'own_consumption', true);
            if ($location === 'office' && $actor['position'] !== 'bar_admin') {
                throw new DomainException('Obsluha může odebírat pouze ze stánku.');
            }
            $snapshot = barSaleSnapshot($pdo, $cart, 0, 0, true, $actor['position'] === 'bar_admin', $suffix);
            barRequireQuoteFingerprint($snapshot, $fingerprint);
            $id = barInsertSaleSnapshot($pdo, $actor, null, $snapshot, 'consumption', $location, $reason);
            barEvent($pdo, $actor['id'], 'consumption', $id, 'withdrawn', ['location' => $location, 'reason' => $reason]);
            return ['sale_id' => $id, 'total_minor' => 0, 'kind' => 'consumption'];
        }
    );
}

function barInsertSaleSnapshot(PDO $pdo, array $actor, ?int $cashboxId, array $snapshot, string $kind, string $location, string $reason): int
{
    $columns = ['goods_gross_minor', 'discount_percent', 'discount_minor', 'goods_net_minor', 'cup_quantity', 'cup_unit_minor', 'deposit_minor', 'total_minor'];
    $pdo->prepare('INSERT INTO kis_bar_sales(cashbox_id,actor_id,kind,currency,' . implode(',', $columns) . ',reason,created_at) VALUES('
        . implode(',', array_fill(0, 13, '?')) . ',' . barSqlUtcNow($pdo) . ')')->execute([$cashboxId, $actor['id'], $kind, 'CZK',
            ...array_map(static fn (string $key): int => $snapshot[$key], $columns), $reason]);
    $id = (int)$pdo->lastInsertId();
    $lineInsert = $pdo->prepare('INSERT INTO kis_bar_sale_lines(sale_id,product_id,product_name,description,quantity,unit_price_minor,gross_minor,net_minor,purchase_unit_minor) '
        . 'VALUES(?,?,?,?,?,?,?,?,?)');
    foreach ($snapshot['lines'] as $line) {
        $lineInsert->execute([$id, $line['product_id'], $line['product_name'], $line['description'], $line['quantity'], $line['unit_price_minor'],
            $line['gross_minor'], $line['net_minor'], $line['purchase_unit_minor']]);
    }
    $componentInsert = $pdo->prepare('INSERT INTO kis_bar_sale_components(sale_id,product_id,product_name,quantity,location_code,purchase_unit_minor,stock_operation_id) VALUES(?,?,?,?,?,?,?)');
    foreach ($snapshot['deductions'] as $productId => $quantity) {
        $product = $snapshot['products'][$productId];
        $unitCost = $product['purchase_minor'] === null ? null : (int)$product['purchase_minor'];
        $operationId = barRecordPhysicalMovement($pdo, $actor, $product, -$quantity, $location, $kind, $id, $unitCost, $product['name']);
        $componentInsert->execute([$id, $productId, $product['name'], $quantity, $location, $unitCost, $operationId]);
    }
    return $id;
}

function barConfirmPayment(PDO $pdo, int $saleId, string $reference, string $token): array
{
    $reference = trim(barSourceText($reference, 255, true));
    if (mb_strlen($reference, 'UTF-8') < 3) {
        throw new InvalidArgumentException('Doplňte podklad ručního potvrzení platby.');
    }
    return barRunRequest($pdo, 'payment_confirm', $token, [$saleId, $reference], function (array $actor, string $suffix) use ($pdo, $saleId, $reference): array {
        $sale = barOwnedSale($pdo, $saleId, $actor['id'], $suffix);
        barOwnedCashbox($pdo, (int)$sale['cashbox_id'], $actor['id'], true, $suffix);
        return barConfirmSalePaymentLocked($pdo, $actor, $sale, $reference, $suffix);
    });
}

function barOwnedSale(PDO $pdo, int $id, int $owner, string $suffix = ''): array
{
    $query = $pdo->prepare('SELECT * FROM kis_bar_sales WHERE id=? AND actor_id=?' . $suffix);
    $query->execute([$id, $owner]);
    return $query->fetch(PDO::FETCH_ASSOC) ?: throw new DomainException('Doklad neexistuje nebo nepatří vašemu účtu.');
}

function barRequireUnvoidedSale(PDO $pdo, int $id): void
{
    $query = $pdo->prepare('SELECT id FROM kis_bar_voids WHERE sale_id=?');
    $query->execute([$id]);
    if ($query->fetchColumn()) {
        throw new BarConflictException('Doklad už byl stornovaný.');
    }
}

function barSalePayment(PDO $pdo, int $id, string $suffix = ''): ?array
{
    $query = $pdo->prepare('SELECT * FROM kis_bar_sale_payments WHERE sale_id=?' . $suffix);
    $query->execute([$id]);
    return $query->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** Shared manual confirmation after the caller has authorized and locked the appropriate cashbox. */
function barConfirmSalePaymentLocked(PDO $pdo, array $actor, array $sale, string $reference, string $suffix): array
{
    if (!$pdo->inTransaction()) {
        throw new LogicException('Potvrzení platby vyžaduje transakci.');
    }
    $saleId = (int)$sale['id'];
    barRequireUnvoidedSale($pdo, $saleId);
    $payment = barSalePayment($pdo, $saleId, $suffix);
    if (!$payment || $payment['status'] !== 'pending' || !in_array($payment['method'], ['card', 'qr'], true)) {
        throw new DomainException('Platba už nečeká na potvrzení.');
    }
    $used = $pdo->prepare('SELECT sale_id FROM kis_bar_sale_payments WHERE method=? AND confirmation_reference=?' . $suffix);
    $used->execute([$payment['method'], $reference]);
    if ($used->fetchColumn()) {
        throw new DomainException('Tento podklad platby už byl použit pro jiný doklad.');
    }
    $pdo->prepare("UPDATE kis_bar_sale_payments SET status='confirmed',verification_kind='manual',confirmation_reference=?,confirmed_by=?,confirmed_at=" . barSqlUtcNow($pdo) . " WHERE sale_id=?")
        ->execute([$reference, $actor['id'], $saleId]);
    $pdo->prepare('UPDATE kis_bar_cashboxes SET lock_version=lock_version+1 WHERE id=?')->execute([$sale['cashbox_id']]);
    barEvent($pdo, $actor['id'], 'sale', $saleId, 'payment_manually_confirmed', ['method' => $payment['method'], 'reference' => $reference]);
    return ['sale_id' => $saleId, 'payment_status' => 'confirmed', 'verification_kind' => 'manual'];
}
