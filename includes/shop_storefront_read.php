<?php

declare(strict_types=1);

require_once __DIR__ . '/shop_checkout.php';
require_once __DIR__ . '/shop_variant_saleability.php';

/** Columns for this read only: never retained across PDO connections or mutations.
 * @return array<string,true>
 */
function shopStorefrontReadProductColumns(PDO $pdo): array
{
    if ((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $rows = $pdo->query(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='shop_products'"
        )->fetchAll(PDO::FETCH_COLUMN);
    } else {
        $rows = array_column($pdo->query('PRAGMA table_info(shop_products)')->fetchAll(PDO::FETCH_ASSOC), 'name');
    }
    return array_fill_keys(array_map('strval', $rows), true);
}

/**
 * Public read projection. Checkout still performs its own current reads and locks.
 * Navigation deliberately omits descriptions, attributes and payment presentation.
 * @return list<array<string,mixed>>
 */
function shopStorefrontReadRows(
    PDO $pdo,
    ?int $productId = null,
    ?DateTimeImmutable $now = null,
    bool $navigation = false,
    ?ClubProgramReadContext $readContext = null
): array {
    if ($productId !== null && $productId < 1) {
        return [];
    }
    $readContext ??= new ClubProgramReadContext($pdo, $now);
    $readContext->assertConnection($pdo);
    $columns = shopStorefrontReadProductColumns($pdo);
    $sortOrder = isset($columns['sort_order']) ? 'p.sort_order' : '0';
    $origin = isset($columns['origin']) ? 'p.origin' : 'NULL';
    $externalKey = isset($columns['external_product_key']) ? 'p.external_product_key' : 'NULL';
    $paymentPolicy = isset($columns['payment_method_policy']) ? 'p.payment_method_policy' : "'" . SHOP_PAYMENT_POLICY_BANK_ONLY . "'";
    $sql = 'SELECT p.id AS product_id,p.offer_type,' . $origin . ' AS origin,' . $externalKey . ' AS external_product_key,'
        . 'p.catalog_status AS product_status,'
        . ($navigation ? '' : $paymentPolicy . ' AS payment_method_policy,')
        . $sortOrder . ' AS sort_order,pub.status AS publication_status,pub.public_name,'
        . ($navigation ? '' : 'pub.public_summary,')
        . 'v.id AS variant_id,v.sku,v.catalog_status,v.visible,v.price_mode,'
        . ($navigation ? '' : 'v.attributes_json,')
        . 'v.amount_minor,v.currency,v.stock_quantity_decimal '
        . 'FROM shop_products p JOIN shop_product_publications pub ON pub.product_id=p.id '
        . 'JOIN shop_variants v ON v.product_id=p.id '
        . "WHERE p.offer_type IN ('goods','program') AND p.catalog_status='active' AND pub.status='active' "
        . "AND v.catalog_status='active' AND (v.visible=1 OR v.visible IS NULL) "
        . "AND v.price_mode='fixed' AND v.amount_minor>=0 AND v.currency='CZK' "
        . ($productId === null ? '' : 'AND p.id=? ')
        . 'ORDER BY sort_order,pub.public_name,v.sku,v.id';
    $statement = $pdo->prepare($sql);
    $statement->execute($productId === null ? [] : [$productId]);
    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
    $programVariantIds = [];
    foreach ($rows as $row) {
        if ($row['offer_type'] === 'program' && shopVariantIsStructurallySaleable($row)) {
            $programVariantIds[] = (int) $row['variant_id'];
        }
    }
    $readContext->warmTermsForVariants($programVariantIds);
    $saleable = [];
    // Lazy, operation-local metadata. Program-only reads never probe stock schema.
    $inventoryAvailable = null;
    foreach ($rows as $row) {
        if (!shopVariantIsStructurallySaleable($row)) {
            continue;
        }
        if ($row['offer_type'] === 'goods') {
            $inventoryAvailable ??= shopInventoryTable($pdo);
            if ($inventoryAvailable && $row['stock_quantity_decimal'] === null) {
                continue;
            }
        } elseif (!$readContext->saleState((int) $row['variant_id'])['saleable']) {
            continue;
        }
        if (!$navigation) {
            $decoded = json_decode((string) $row['attributes_json'], true);
            $row['attributes'] = is_array($decoded) ? $decoded : [];
        }
        $saleable[] = $row;
    }
    return $saleable;
}

/**
 * All links count, including inactive ones. A product link is not saleability.
 * Separate variant lookup preserves legacy rows whose product_id does not match
 * the current variant owner; those still go through the canonical offer resolver.
 * @param list<int> $productIds
 * @param list<int> $variantIds
 * @return array{products:array<int,true>,variants:array<int,true>}
 */
function shopStorefrontReadOfferLinks(PDO $pdo, array $productIds, array $variantIds): array
{
    $result = ['products' => [], 'variants' => []];
    foreach (['product_id' => $productIds, 'variant_id' => $variantIds] as $column => $ids) {
        $key = $column === 'product_id' ? 'products' : 'variants';
        foreach (array_chunk(array_values(array_unique($ids)), 1000) as $batch) {
            $marks = implode(',', array_fill(0, count($batch), '?'));
            $statement = $pdo->prepare("SELECT DISTINCT $column FROM club_program_offers WHERE $column IN ($marks)");
            $statement->execute($batch);
            foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $id) {
                $result[$key][(int) $id] = true;
            }
        }
    }
    return $result;
}
