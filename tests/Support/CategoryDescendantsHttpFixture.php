<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('EVIDENCE_PERFORMANCE_CONFIRM') !== 'SYNTHETIC-ONLY') {
    http_response_code(404);
    exit;
}
$output = realpath($argv[1] ?? '');
$count = (int)($argv[2] ?? 0);
$shape = $argv[3] ?? '';
if ($output === false) {
    throw new RuntimeException('Owned output required.');
}
$argv = [__FILE__, '--library'];
require_once __DIR__ . '/FullHttpReadProfile.php';
require_once __DIR__ . '/CategoryMenuFixture.php';
require_once dirname(__DIR__, 2) . '/includes/shop_category.php';
[$runtime, $admin] = fullHttpEnvironment($output);
$admin->exec('USE `' . $runtime['schema'] . '`');
categoryMenuFixture($admin, $count, $shape, true);
$nodes = shopCategoryNodes($admin);
$paths = shopCategoryDescendants($nodes, 'LOCALHOST-0');
if (count($nodes) !== $count || (in_array($shape, ['tree', 'chain'], true) && count($paths) !== $count)) {
    throw new RuntimeException('Expected owned node and traversal volumes.');
}
file_put_contents($output . '/descendants-volume-private.json', json_encode([
    'shape' => $shape, 'nodes' => count($nodes), 'selected_descendants' => count($paths),
], JSON_THROW_ON_ERROR));
