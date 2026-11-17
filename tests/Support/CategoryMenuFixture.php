<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/** Owned synthetic tables only; callers validate their schema owner first. */
function categoryMenuFixture(PDO $pdo, int $count, string $shape, bool $full = false): array
{
    if (!in_array($shape, ['flat', 'tree', 'chain', 'mixed'], true) || $count < 1 || $count > 1000) {
        throw new InvalidArgumentException('Explicit bounded category fixture required.');
    }
    $pdo->exec('DELETE FROM shop_product_categories');
    $pdo->exec('DELETE FROM shop_member_category_rules');
    $pdo->exec('DELETE FROM shop_category_meta');
    $insert = $pdo->prepare('INSERT INTO shop_category_meta(category_path,display_name,parent_path,sort_order,visible_in_menu) VALUES(?,?,?,?,?)');
    $pdo->beginTransaction();
    for ($i = 0; $i < $count; $i++) {
        $parent = match ($shape) {
            'tree' => $i === 0 ? null : 'LOCALHOST-' . intdiv($i - 1, 8),
            'chain' => $i === 0 ? null : 'LOCALHOST-' . ($i - 1),
            'mixed' => $i % 5 === 0 ? null : 'LOCALHOST-' . (($i * 17 + 5) % $count),
            default => null,
        };
        $insert->execute(['LOCALHOST-' . $i, 'LOCALHOST NEPLATIT Group ' . ($i % 17), $parent, $i % 3,
            $shape === 'mixed' && $i % 4 === 0 ? 0 : 1]);
    }
    $pdo->commit();
    $leaf = 'LOCALHOST-' . ($count - 1);
    if ($full) {
        $pdo->prepare('INSERT INTO shop_product_categories(product_id,category_path) VALUES(9001,?)')->execute([$leaf]);
    } else {
        $pdo->prepare('INSERT INTO shop_product_categories(product_id,category_path) VALUES(1,?)')->execute([$leaf]);
    }
    return [['product_id' => $full ? 9001 : 1, 'categories' => [$leaf]]];
}
