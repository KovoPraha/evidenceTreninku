<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('EVIDENCE_PERFORMANCE_CONFIRM') !== 'SYNTHETIC-ONLY') {
    http_response_code(404);
    exit;
}
$output = realpath($argv[1] ?? '');
if ($output === false) {
    throw new RuntimeException('Owned output required.');
}
$argv = [__FILE__, '--library'];
require_once __DIR__ . '/FullHttpReadProfile.php';
require_once dirname(__DIR__, 2) . '/includes/shop_category_admin.php';
require_once dirname(__DIR__, 2) . '/includes/shop_attribute_admin.php';
[$runtime, $pdo] = fullHttpEnvironment($output);
$pdo->exec('USE `' . $runtime['schema'] . '`');
$credentials = json_decode((string)file_get_contents($output . '/credentials.json'), true, 512, JSON_THROW_ON_ERROR);
$actor = (int)$credentials['catalog_manager']['id'];
foreach (['LOCALHOST', 'LOCALHOST > Equipment', 'LOCALHOST > Hidden'] as $index => $path) {
    shopCategoryAdminSave($pdo, $actor, [
        'category_path' => $path, 'display_name' => 'LOCALHOST category ' . $index,
        'parent_path' => '', 'sort_order' => $index, 'visible_in_menu' => $index === 2 ? '0' : '1',
        'description' => 'LOCALHOST NEPLATIT synthetic category',
    ], 'LOCALHOST synthetic category fixture', true);
}
$product = (int)$pdo->query('SELECT MIN(id) FROM shop_products')->fetchColumn();
if ($product < 1) {
    throw new RuntimeException('Populated product fixture required.');
}
shopCategoryAdminAssignProduct($pdo, $actor, $product, ['LOCALHOST > Equipment'], 'LOCALHOST > Equipment', 'LOCALHOST fixture', true);
$ids = [];
foreach (['text', 'number', 'choice'] as $index => $type) {
    $ids[$type] = shopAttributeAdminSave($pdo, $actor, [
        'definition_id' => 0, 'attribute_key' => 'LOCALHOST_asset_' . $type,
        'display_name' => 'LOCALHOST ' . $type, 'value_type' => $type, 'unit' => $type === 'number' ? 'kg' : '',
        'sort_order' => $index, 'choice_values' => ['LOCALHOST A', 'LOCALHOST B'],
        'show_in_listing' => '1', 'show_in_detail' => '1', 'active' => '1',
    ], 'LOCALHOST NEPLATIT synthetic attribute', true)['id'];
}
// The edit view must also retain inactive definitions and inactive historical choices.
$pdo->prepare('UPDATE shop_attribute_definitions SET active=0 WHERE id=?')->execute([$ids['text']]);
$pdo->prepare('UPDATE shop_attribute_choices SET active=0 WHERE attribute_id=? AND value=?')->execute([$ids['choice'], 'LOCALHOST B']);
file_put_contents($output . '/category-attribute-ids.json', json_encode([
    'product' => $product, 'attributes' => $ids, 'category' => 'LOCALHOST > Equipment',
], JSON_THROW_ON_ERROR));
