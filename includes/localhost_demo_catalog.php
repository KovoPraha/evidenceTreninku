<?php

declare(strict_types=1);

require_once __DIR__ . '/shop_manual_catalog.php';

/**
 * Dedicated synthetic fallback; never repurpose imported goods as a program.
 * The localhost-only CLI seed owns the environment and actor checks.
 *
 * @return array{id:int,name:string,variant_id:int}
 */
function localhostDemoCatalogEnsureProduct(PDO $pdo, int $actorId, string $kind): array
{
    if (!in_array($kind, ['goods', 'program'], true)) {
        throw new InvalidArgumentException('Unsupported localhost demo product kind.');
    }
    $sku = $kind === 'goods' ? 'KP-LOCAL-DEMO-GOODS' : 'KP-LOCAL-DEMO-PROGRAM';
    $find = $pdo->prepare(
        'SELECT p.id,p.name,p.origin,p.offer_type,p.created_by_trainer_id,v.id variant_id '
        . 'FROM shop_products p JOIN shop_variants v ON v.product_id=p.id WHERE v.sku=?'
    );
    $find->execute([$sku]);
    $existing = $find->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        if ($existing['origin'] !== 'manual' || $existing['offer_type'] !== $kind
            || (int)$existing['created_by_trainer_id'] !== $actorId) {
            throw new RuntimeException('Localhost demo SKU belongs to another product.');
        }
        return ['id' => (int)$existing['id'], 'name' => (string)$existing['name'], 'variant_id' => (int)$existing['variant_id']];
    }
    $name = $kind === 'goods' ? 'LOCALHOST – zboží (NEPLATIT)' : 'LOCALHOST – placený kroužek (NEPLATIT)';
    $created = shopManualCatalogCreate(
        $pdo,
        $actorId,
        [
            'name' => $name,
            'short_description' => 'Syntetická nabídka pouze pro localhost. NEPLATIT.',
            'offer_type' => $kind,
            'item_type' => $kind === 'goods' ? 'product' : 'service',
            'visibility' => 'visible',
            'packeta_shipping_enabled' => 0,
        ],
        [
            'sku' => $sku,
            'attributes_json' => '{}',
            'amount_minor' => 24900,
            'stock_quantity_decimal' => $kind === 'goods' ? '12' : '',
            'unit_code' => $kind === 'goods' ? 'ks' : 'person',
            'visible' => 1,
        ],
        'LOCALHOST seed: samostatné syntetické zboží a program bez změny původního produktu.',
        true
    );
    return ['id' => (int)$created['product_id'], 'name' => $name, 'variant_id' => (int)$created['variant_id']];
}
