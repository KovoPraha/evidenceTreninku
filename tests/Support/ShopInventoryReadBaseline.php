<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/shop_inventory.php';

/** Read-only inventory display; never use for locked stock decisions. */
function w91InventoryVariantsBaseline(PDO $pdo, string $query): array
{
    $rows = $pdo->query("SELECT v.id,v.sku,v.stock_quantity_decimal,p.name FROM shop_variants v JOIN shop_products p ON p.id=v.product_id WHERE "
        . "p.offer_type='goods' ORDER BY p.name,v.sku")
        ->fetchAll(PDO::FETCH_ASSOC);
    return $query === '' ? $rows : array_values(array_filter($rows, static fn (array $row): bool => mb_stripos($row['sku'] . ' ' . $row['name'], $query) !== false));
}

/** @return Generator<int,array{variant:array,balance:?array}> */
function w91InventoryRowsBaseline(PDO $pdo, array $variants): Generator
{
    foreach ($variants as $variant) {
        yield ['variant' => $variant, 'balance' => shopInventoryBalance($pdo, (int) $variant['id'])];
    }
}
