<?php

declare(strict_types=1);

require_once __DIR__ . '/management.php';

/** Journal and audit are part of the same transaction; stock never belongs to a catalogue edit. */
function barStockOperation(PDO $pdo, int $productId, int $version, string $kind, int $quantity, string $token, string $reason): array
{
    $reason = barReason($reason);
    $kinds = ['receive_stall', 'receive_office', 'to_stall', 'to_office', 'inventory_stall', 'inventory_office'];
    if (!in_array($kind, $kinds, true) || $quantity < (str_starts_with($kind, 'inventory') ? 0 : 1) || $quantity > 1000000
        || $productId < 1 || $version < 1 || !preg_match('/^[a-f0-9]{64}$/D', $token)) {
        throw new InvalidArgumentException('Neplatná skladová operace, množství nebo token.');
    }
    $requestHash = hash('sha256', json_encode([$productId, $version, $kind, $quantity, $reason], JSON_THROW_ON_ERROR));
    $pdo->beginTransaction();
    try {
        $suffix = barLockState($pdo);
        $actor = barActor($pdo, 'stock_manage', true);
        $query = $pdo->prepare('SELECT id,actor_id,request_sha256 FROM kis_bar_stock_operations WHERE token=?' . $suffix);
        $query->execute([$token]);
        $old = $query->fetch(PDO::FETCH_ASSOC);
        if ($old) {
            if ((int)$old['actor_id'] !== $actor['id'] || !hash_equals($old['request_sha256'], $requestHash)) {
                throw new BarConflictException('Token už patří jiné operaci nebo uživateli.');
            }
            $pdo->commit();
            return ['id' => (int)$old['id'], 'repeated' => true];
        }
        $product = barLockedProduct($pdo, $productId, $version, $suffix);
        if (str_starts_with($kind, 'inventory_') && barHasPendingStock($pdo, $productId, substr($kind, 10))) {
            throw new DomainException('Položka je rezervovaná čekající platbou. Před fyzickou inventurou platbu potvrďte nebo objednávku stornujte.');
        }
        $query = $pdo->prepare('SELECT product_id FROM kis_bar_recipes WHERE product_id=? LIMIT 1');
        $query->execute([$productId]);
        if ($product['stock_mode'] !== 'sledovat' || (int)$product['archived'] === 1 || $query->fetchColumn()) {
            throw new DomainException('Skladovou operaci lze provést jen pro aktivní jednoduchý sledovaný produkt.');
        }
        $stall = (int)$product['stock_stall'];
        $office = (int)$product['stock_office'];
        [$newStall, $newOffice] = match ($kind) {
            'receive_stall' => [$stall + $quantity, $office], 'receive_office' => [$stall, $office + $quantity],
            'to_stall' => [$stall + $quantity, $office - $quantity], 'to_office' => [$stall - $quantity, $office + $quantity],
            'inventory_stall' => [$quantity, $office], 'inventory_office' => [$stall, $quantity],
        };
        if (min($newStall, $newOffice) < 0 || max($newStall, $newOffice) > 1000000) {
            throw new DomainException('Ve zdrojovém místě není dost zásoby nebo by byl překročen skladový limit.');
        }
        $pdo->prepare('INSERT INTO kis_bar_stock_operations(token,request_sha256,product_id,actor_id,kind,reason,created_at) VALUES(?,?,?,?,?,?,' . barSqlUtcNow($pdo) . ')')
            ->execute([$token, $requestHash, $productId, $actor['id'], $kind, $reason]);
        $id = (int)$pdo->lastInsertId();
        $insert = $pdo->prepare('INSERT INTO kis_bar_stock_movements(operation_id,product_id,location_code,quantity_delta,quantity_after,purchase_minor,product_name) '
            . 'VALUES(?,?,?,?,?,?,?)');
        foreach (['stall' => [$stall, $newStall], 'office' => [$office, $newOffice]] as $location => [$before, $after]) {
            if ($before !== $after || $kind === 'inventory_' . $location) {
                $insert->execute([$id, $productId, $location, $after - $before, $after, $product['purchase_minor'], $product['name']]);
            }
        }
        $pdo->prepare('UPDATE kis_bar_products SET stock_stall=?,stock_office=?,lock_version=lock_version+1 WHERE id=?')
            ->execute([$newStall, $newOffice, $productId]);
        barEvent($pdo, $actor['id'], 'stock_operation', $id, $kind, ['product_id' => $productId, 'quantity' => $quantity, 'reason' => $reason]);
        $pdo->commit();
        return ['id' => $id, 'repeated' => false];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
