<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/** Only callers holding an owned synthetic schema may mutate these tables. */
function catalogTopCountsFixture(PDO $pdo, int $products, int $roots): array
{
    if ($products < 0 || $products > 10000 || $roots < 0 || $roots > 1000) {
        throw new InvalidArgumentException('Explicit bounded synthetic counts required.');
    }
    foreach (['shop_product_categories', 'shop_member_category_rules', 'shop_category_meta'] as $table) {
        $pdo->exec('DELETE FROM ' . $table);
    }
    $insert = $pdo->prepare('INSERT INTO shop_product_categories(product_id,category_path) VALUES(?,?)');
    for ($i = 0; $i < $roots; $i++) {
        $insert->execute([$i + 1, 'LOCALHOST-' . $i]);
    }
    $result = [];
    for ($i = 0; $i < $products; $i++) {
        $result[] = ['product_id' => $i + 1, 'public_name' => 'LOCALHOST ' . str_pad((string)$i, 6, '0', STR_PAD_LEFT),
            'public_summary' => 'NEPLATIT', 'min_amount_minor' => 10000 + $i,
            'categories' => $roots === 0 ? [] : ['LOCALHOST-' . ($i % $roots), 'LOCALHOST-' . (($i + 1) % $roots)]];
    }
    return $result;
}
