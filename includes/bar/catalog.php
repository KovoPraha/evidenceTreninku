<?php

declare(strict_types=1);

require_once __DIR__ . '/access.php';

/** Separate projections keep purchase prices and office stock off the operator's response. */
function barCatalog(PDO $pdo, bool $administration = false): array
{
    barActor($pdo, $administration ? 'catalog_manage' : 'catalog_read');
    $columns = 'p.id,p.name,p.description,p.price_minor,p.stock_stall,p.stock_mode,p.sold_out,c.name AS category,'
        . 'EXISTS(SELECT 1 FROM kis_bar_recipes r WHERE r.product_id=p.id) AS is_composite';
    if ($administration) {
        $columns .= ',p.purchase_minor,p.stock_office,p.archived,p.on_terminal,p.source_id,p.lock_version';
    }
    $where = $administration ? '' : ' WHERE p.archived=0 AND p.on_terminal=1';
    return $pdo->query('SELECT ' . $columns . ' FROM kis_bar_products p JOIN kis_bar_categories c ON c.id=p.category_id'
        . $where . ' ORDER BY c.sort_order,c.id,p.sort_order,p.id')->fetchAll(PDO::FETCH_ASSOC);
}

function barFormatMoney(int $minor): string
{
    return number_format($minor / 100, 2, ',', ' ') . ' Kč';
}
