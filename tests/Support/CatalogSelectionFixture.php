<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/CatalogTopCountsFixture.php';

/** Owned synthetic schema required by callers. */
function catalogSelectionFixture(PDO $pdo, int $products, int $nodes, string $shape): array
{
    if (!in_array($shape, ['flat', 'tree'], true)) {
        throw new InvalidArgumentException('Bounded category shape required.');
    }
    $result = catalogTopCountsFixture($pdo, $products, $nodes);
    $insert = $pdo->prepare('INSERT INTO shop_category_meta(category_path,display_name,parent_path,sort_order,visible_in_menu) VALUES(?,?,?,?,1)');
    for ($i = 0; $i < $nodes; $i++) {
        $parent = $shape === 'tree' && $i > 0 ? 'LOCALHOST-' . intdiv($i - 1, 8) : null;
        $insert->execute(['LOCALHOST-' . $i, 'LOCALHOST Group ' . $i, $parent, $i]);
    }
    return $result;
}
