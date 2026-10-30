<?php

declare(strict_types=1);

require_once __DIR__ . '/shop_inventory.php';

/** Read-only inventory display; never use for locked stock decisions. */
function shopInventoryReadVariants(PDO $pdo, string $query): array
{
    $statement = $pdo->query('SELECT v.id,v.sku,v.stock_quantity_decimal,p.name FROM shop_variants v '
        . "JOIN shop_products p ON p.id=v.product_id WHERE p.offer_type='goods' ORDER BY p.name,v.sku");
    $rows = [];
    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        // Preserve PHP Unicode substring semantics, including literal %, _, ! and accents.
        if ($query === '' || mb_stripos($row['sku'] . ' ' . $row['name'], $query) !== false) {
            $rows[] = $row;
        }
    }
    return $rows;
}

/** @return Generator<int,array{variant:array,balance:?array}> */
function shopInventoryReadRows(PDO $pdo, array $variants): Generator
{
    if ($variants === []) {
        return;
    }
    $available = shopInventoryTable($pdo);
    for ($offset = 0, $count = count($variants); $offset < $count; $offset += 400) {
        $batch = array_slice($variants, $offset, 400);
        $balances = [];
        if ($available) {
            $ids = array_values(array_unique(array_map('intval', array_column($batch, 'id'))));
            $statement = $pdo->prepare('SELECT * FROM shop_stock_balances WHERE variant_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
            $statement->execute($ids);
            while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
                foreach (['variant_id', 'physical_quantity', 'reserved_quantity', 'blocked_quantity', 'revision'] as $key) {
                    $row[$key] = (int) $row[$key];
                }
                $row['available_quantity'] = $row['physical_quantity'] - $row['reserved_quantity'] - $row['blocked_quantity'];
                $balances[$row['variant_id']] = $row;
            }
        }
        foreach ($batch as $variant) {
            yield ['variant' => $variant, 'balance' => $balances[(int) $variant['id']] ?? null];
        }
    }
}
