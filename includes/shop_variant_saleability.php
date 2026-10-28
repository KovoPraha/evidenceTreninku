<?php

declare(strict_types=1);

/** Structural rules only. A positive result does not authorize a purchase.
 * @param array<string,mixed> $variant
 */
function shopVariantIsStructurallySaleable(array $variant): bool
{
    return ($variant['product_status'] ?? null) === 'active'
        && ($variant['publication_status'] ?? null) === 'active'
        && ($variant['catalog_status'] ?? null) === 'active'
        && (($variant['visible'] ?? null) === null || (int) $variant['visible'] === 1)
        && ($variant['price_mode'] ?? null) === 'fixed'
        && ($variant['amount_minor'] ?? null) !== null
        && (int) $variant['amount_minor'] >= 0
        && ($variant['currency'] ?? null) === 'CZK';
}
