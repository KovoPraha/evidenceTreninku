<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('EVIDENCE_PERFORMANCE_CONFIRM') !== 'SYNTHETIC-ONLY') {
    http_response_code(404);
    exit;
}
$output = realpath($argv[1] ?? '');
$count = (int)($argv[2] ?? -1);
$roots = (int)($argv[3] ?? -1);
$shape = $argv[4] ?? '';
if (!in_array($shape, ['flat', 'tree'], true)) {
    throw new RuntimeException('Bounded category shape required.');
}
if ($output === false || $count < 1 || $count > 10000 || $roots < 1 || $roots > 1000) {
    throw new RuntimeException('Owned output and bounded counts required.');
}
$argv = [__FILE__, '--library'];
require_once __DIR__ . '/FullHttpReadProfile.php';
[$runtime, $admin] = fullHttpEnvironment($output);
$admin->exec('USE `' . $runtime['schema'] . '`');
$marker = $output . '/catsort-seed-private.json';
if (!file_exists($marker)) {
    $candidate = $admin->prepare('INSERT INTO shop_catalog_product_candidates(id,run_id,external_product_key,name,offer_type,'
        . 'classification_confidence,needs_manual_review,payload_json) VALUES(?,1,?,?,\'goods\',\'high\',0,\'{}\')');
    $product = $admin->prepare('INSERT INTO shop_products(id,source_candidate_id,source_run_id,external_product_key,name,offer_type,catalog_status)'
        . ' VALUES(?,?,1,?,?,\'goods\',\'inactive\')');
    $publication = $admin->prepare('INSERT INTO shop_product_publications(product_id,status,public_name,public_summary,decision_note)'
        . ' VALUES(?,\'draft\',?,\'LOCALHOST NEPLATIT synteticky katalog\',\'Approved owned fixture\')');
    $variant = $admin->prepare('INSERT INTO shop_variants(id,product_id,source_candidate_id,sku,attributes_json,price_mode,amount_minor,currency,'
        . 'includes_vat,vat_rate_basis_points,stock_quantity_decimal,visible,catalog_status)'
        . ' VALUES(?,?,NULL,?,\'{}\',\'fixed\',?,\'CZK\',1,2100,\'5.000000\',1,\'active\')');
    $admin->beginTransaction();
    for ($i = 1; $i <= 10000; $i++) {
        $key = 'LOCALHOST-CATSORT-' . $i;
        $name = 'LOCALHOST NEPLATIT CATSORT ' . str_pad((string)$i, 5, '0', STR_PAD_LEFT);
        $candidate->execute([200000 + $i, $key, $name]);
        $product->execute([100000 + $i, 200000 + $i, $key, $name]);
        $publication->execute([100000 + $i, $name]);
        $variant->execute([300000 + $i, 100000 + $i, $key, 10000 + $i]);
    }
    $admin->commit();
    file_put_contents($marker, json_encode(['schema' => $runtime['schema'], 'products' => 10000], JSON_THROW_ON_ERROR));
}
$seed = json_decode((string)file_get_contents($marker), true, 512, JSON_THROW_ON_ERROR);
if ($seed !== ['schema' => $runtime['schema'], 'products' => 10000]) {
    throw new RuntimeException('Owned seed marker mismatch.');
}
$admin->beginTransaction();
$admin->exec("UPDATE shop_product_publications SET status='draft'");
$admin->exec("UPDATE shop_products SET catalog_status='inactive' WHERE id BETWEEN 100001 AND 110000");
$last = 100000 + $count;
$admin->exec("UPDATE shop_products SET catalog_status='active' WHERE id BETWEEN 100001 AND " . $last);
$admin->exec("UPDATE shop_product_publications SET status='active' WHERE product_id BETWEEN 100001 AND " . $last);
foreach (['shop_product_categories', 'shop_member_category_rules', 'shop_category_meta'] as $table) {
    $admin->exec('DELETE FROM ' . $table);
}
$meta = $admin->prepare('INSERT INTO shop_category_meta(category_path,display_name,parent_path,sort_order,visible_in_menu) VALUES(?,?,?,?,1)');
for ($i = 0; $i < $roots; $i++) {
    $parent = $shape === 'tree' && $i > 0 ? 'LOCALHOST-' . intdiv($i - 1, 8) : null;
    $meta->execute(['LOCALHOST-' . $i, 'LOCALHOST Group ' . $i, $parent, $i]);
}
$category = $admin->prepare('INSERT INTO shop_product_categories(product_id,category_path,is_default,sort_order) VALUES(?,?,?,?)');
for ($i = 1; $i <= $count; $i++) {
    $category->execute([100000 + $i, 'LOCALHOST-' . (($i - 1) % $roots), 1, 0]);
    if ($roots > 1) {
        $category->execute([100000 + $i, 'LOCALHOST-' . ($i % $roots), 0, 1]);
    }
}
$admin->commit();
require_once dirname(__DIR__, 2) . '/includes/shop_catalog_page_read.php';
if (!defined('APP_BASE_URL')) {
    define('APP_BASE_URL', $runtime['base_url']);
}
$state = shopCatalogPageRead($admin, null, ['kategorie' => 'LOCALHOST-0']);
$expected = $shape === 'tree' || $roots === 1 ? $count : (int)$admin->query("SELECT COUNT(DISTINCT product_id) FROM shop_product_categories WHERE category_path='LOCALHOST-0'")->fetchColumn();
if ($state['activeCatalogCount'] !== $count || count($state['categoryNodes']) !== $roots
    || $state['filteredProductCount'] !== $expected || count($state['selectedPaths']) !== ($shape === 'tree' ? $roots : 1)
) {
    throw new RuntimeException('Actual catalog and selected fixture volumes differ.');
}
file_put_contents($output . '/catsort-volume-private.json', json_encode([
    'products' => $count, 'nodes' => $roots, 'shape' => $shape, 'selected_descendants' => count($state['selectedPaths']),
    'active_catalog' => $state['activeCatalogCount'], 'filtered_products' => $state['filteredProductCount'],
], JSON_THROW_ON_ERROR));
