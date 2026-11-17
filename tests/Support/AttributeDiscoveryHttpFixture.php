<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('EVIDENCE_PERFORMANCE_CONFIRM') !== 'SYNTHETIC-ONLY') {
    http_response_code(404);
    exit;
}
$output = realpath($argv[1] ?? '');
$count = (int)($argv[2] ?? 0);
$shape = $argv[3] ?? '';
if ($output === false || !in_array($count, [1000, 10000, 30000], true) || !in_array($shape, ['repeated', 'unique'], true)) {
    throw new RuntimeException('Owned output, size and shape required.');
}
$argv = [__FILE__, '--library'];
require_once __DIR__ . '/FullHttpReadProfile.php';
[$runtime, $pdo] = fullHttpEnvironment($output);
$pdo->exec('USE `' . $runtime['schema'] . '`');
$product = (int)$pdo->query('SELECT MIN(id) FROM shop_products')->fetchColumn();
if ($product < 1) {
    throw new RuntimeException('Populated synthetic product required.');
}
$pdo->exec('DELETE FROM shop_variants WHERE id>=9000000 AND id<9030000');
$insert = $pdo->prepare('INSERT INTO shop_variants(id,product_id,source_candidate_id,sku,attributes_json,price_mode,amount_minor,currency,'
    . 'includes_vat,vat_rate_basis_points,stock_quantity_decimal,visible,catalog_status) VALUES(?,?,NULL,?,?,\'fixed\',10000,\'CZK\',1,2100,NULL,1,\'active\')');
$pdo->beginTransaction();
for ($i = 0; $i < $count; $i++) {
    $data = [];
    for ($key = 0; $key < 16; $key++) {
        $data['LOCALHOST_key_' . $key] = str_repeat('x', 64) . ($shape === 'unique' ? $i : $i % 8);
    }
    $insert->execute([9000000 + $i, $product, 'LOCALHOST-ATTR-' . $i, json_encode($data, JSON_THROW_ON_ERROR)]);
}
$pdo->commit();
echo json_encode(['synthetic_variants' => $count, 'shape' => $shape], JSON_THROW_ON_ERROR), "\n";
