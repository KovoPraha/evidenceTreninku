<?php

declare(strict_types=1);

require_once __DIR__ . '/requests.php';

/** Separate from the original zero-stock catalogue import. Never guesses identities from names. */
function barOpeningStockPlan(PDO $pdo, array $source): array
{
    $rows = [];
    $seen = [];
    $query = $pdo->prepare('SELECT id,source_id,stock_stall,stock_office,lock_version FROM kis_bar_products WHERE source_id=?');
    foreach ($source as $product) {
        $sourceId = barSourceInteger($product['id'] ?? null, 1, 2147483647);
        if (isset($seen[$sourceId])) {
            throw new DomainException('Duplicitní zdrojová položka zásob.');
        }
        $seen[$sourceId] = true;
        $query->execute([$sourceId]);
        $matches = $query->fetchAll(PDO::FETCH_ASSOC);
        if (count($matches) !== 1) {
            throw new DomainException('Zdrojovou položku nelze jednoznačně přiřadit.');
        }
        $target = $matches[0];
        $rows[] = ['source_id' => $sourceId, 'product_id' => (int)$target['id'], 'version' => (int)$target['lock_version'],
            'before_stall' => (int)$target['stock_stall'], 'before_office' => (int)$target['stock_office'],
            'stall' => barSourceInteger($product['sklad'] ?? null, 0, 1000000),
            'office' => barSourceInteger($product['predsklad'] ?? null, 0, 1000000)];
    }
    if ($rows === [] || count($rows) > 1000 || count($rows) !== (int)$pdo->query('SELECT COUNT(*) FROM kis_bar_products WHERE source_id IS NOT NULL')->fetchColumn()) {
        throw new DomainException('Snímek musí obsahovat všechny převedené produkty.');
    }
    usort($rows, static fn (array $a, array $b): int => $a['source_id'] <=> $b['source_id']);
    return $rows;
}

/** All products and both locations commit together; a lost response replays the same receipt. */
function barApplyOpeningStocks(PDO $pdo, array $plan, string $token, string $reason, array $displaySettings = []): array
{
    $reason = barReason($reason);
    foreach ($displaySettings as $key => $value) {
        if (!in_array($key, ['obrazovka_nadpis', 'obrazovka_podtitul', 'obrazovka_obnova', 'obrazovka_vzhled', 'obrazovka_vyprodane'], true)
            || !is_string($value) || !mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > 500) {
            throw new InvalidArgumentException('Neplatné veřejné nastavení obrazovky.');
        }
    }
    return barRunRequest($pdo, 'legacy_opening_stock', $token, [$plan, $reason, $displaySettings], function (array $actor, string $suffix) use (
        $pdo,
        $plan,
        $token,
        $reason,
        $displaySettings
    ): array {
        if ($plan === [] || count($plan) > 1000) {
            throw new InvalidArgumentException('Chybí konkrétní plán zásob.');
        }
        $products = [];
        $seen = [];
        $sourceIds = [];
        foreach ($plan as $row) {
            $id = barSourceInteger($row['product_id'] ?? null, 1, 2147483647);
            $sourceId = barSourceInteger($row['source_id'] ?? null, 1, 2147483647);
            if (isset($seen[$id]) || isset($sourceIds[$sourceId])) {
                throw new DomainException('Duplicitní cílová položka.');
            }
            $seen[$id] = true;
            $sourceIds[$sourceId] = true;
            $query = $pdo->prepare('SELECT * FROM kis_bar_products WHERE id=?' . $suffix);
            $query->execute([$id]);
            $product = $query->fetch(PDO::FETCH_ASSOC);
            if (!$product || (int)$product['source_id'] !== ($row['source_id'] ?? null)
                || (int)$product['lock_version'] !== ($row['version'] ?? null)
                || (int)$product['stock_stall'] !== 0 || (int)$product['stock_office'] !== 0
                || ($row['before_stall'] ?? null) !== 0 || ($row['before_office'] ?? null) !== 0) {
                throw new BarConflictException('Cílový produkt se změnil nebo již má zásobu; převod nic nepřepíše.');
            }
            $query = $pdo->prepare('SELECT id FROM kis_bar_stock_movements WHERE product_id=? LIMIT 1');
            $query->execute([$id]);
            if ($query->fetchColumn() || barHasPendingStock($pdo, $id)) {
                throw new BarConflictException('Produkt již má skladovou historii nebo rezervaci.');
            }
            barSourceInteger($row['stall'] ?? null, 0, 1000000);
            barSourceInteger($row['office'] ?? null, 0, 1000000);
            $products[$id] = $product;
        }
        if (count($products) !== (int)$pdo->query('SELECT COUNT(*) FROM kis_bar_products WHERE source_id IS NOT NULL')->fetchColumn()) {
            throw new BarConflictException('Mapa převedených produktů se změnila.');
        }
        $operations = 0;
        foreach ($plan as $row) {
            $product = $products[$row['product_id']];
            foreach (['stall', 'office'] as $location) {
                $quantity = $row[$location];
                $pdo->prepare('INSERT INTO kis_bar_stock_operations(token,request_sha256,product_id,actor_id,kind,reason,created_at) '
                    . 'VALUES(?,?,?,?,?,?,' . barSqlUtcNow($pdo) . ')')->execute([
                        hash('sha256', $token . ':' . $product['id'] . ':' . $location), hash('sha256', json_encode($row, JSON_THROW_ON_ERROR)),
                        $product['id'], $actor['id'], 'inventory_' . $location, $reason,
                    ]);
                $operationId = (int)$pdo->lastInsertId();
                $pdo->prepare('INSERT INTO kis_bar_stock_movements(operation_id,product_id,location_code,quantity_delta,quantity_after,purchase_minor,product_name) '
                    . 'VALUES(?,?,?,?,?,?,?)')->execute([$operationId, $product['id'], $location, $quantity, $quantity, $product['purchase_minor'], $product['name']]);
                $operations++;
            }
            $pdo->prepare('UPDATE kis_bar_products SET stock_stall=?,stock_office=?,lock_version=lock_version+1 WHERE id=?')
                ->execute([$row['stall'], $row['office'], $product['id']]);
        }
        if ($displaySettings !== []) {
            $sql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
                ? 'INSERT INTO nastaveni(klic,hodnota) VALUES(?,?) ON DUPLICATE KEY UPDATE hodnota=VALUES(hodnota)'
                : 'INSERT INTO nastaveni(klic,hodnota) VALUES(?,?) ON CONFLICT(klic) DO UPDATE SET hodnota=excluded.hodnota';
            $pdo->prepare($sql)->execute(['bar_display_settings', json_encode($displaySettings, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
        }
        $result = ['products' => count($products), 'operations' => $operations,
            'stall_total' => array_sum(array_column($plan, 'stall')), 'office_total' => array_sum(array_column($plan, 'office'))];
        barEvent($pdo, $actor['id'], 'stock_transfer', 0, 'legacy_opening_stock', $result + ['reason' => $reason]);
        return $result;
    }, 'stock_manage');
}
