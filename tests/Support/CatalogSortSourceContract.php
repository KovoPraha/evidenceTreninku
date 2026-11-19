<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$root = dirname(__DIR__, 2);
$base = '4f43f793510fd7dd1bb136cea875475b258adab6';
$read = static function (string $path) use ($root, $base): string {
    $process = proc_open(['git', 'show', $base . ':' . $path], [1 => ['pipe', 'w'], 2 => STDERR], $pipes, $root);
    if (!is_resource($process)) {
        throw new RuntimeException('Immutable source required.');
    }
    $source = (string)stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('Immutable source unavailable.');
    }
    return str_replace("\r\n", "\n", $source);
};
$local = static fn (string $path): string => str_replace("\r\n", "\n", (string)file_get_contents($path));
$before = $read('includes/shop_catalog_page_read.php');
$after = $local($root . '/includes/shop_catalog_page_read.php');
$scope = static function (string $source): array {
    $start = strpos($source, '    usort($products,');
    $guard = strpos($source, '    $sortKeys = [];');
    if ($guard !== false) {
        $start = $guard;
    }
    $end = strpos($source, '    $filteredProductCount =', $start);
    if ($start === false || $end === false) {
        throw new RuntimeException('Unique sort scope required.');
    }
    return [substr($source, 0, $start), substr($source, $start, $end - $start), substr($source, $end)];
};
[$prefix, $oldBlock, $suffix] = $scope($before);
[$newPrefix, $newBlock, $newSuffix] = $scope($after);
$start = strpos($before, 'function shopCatalogPageSelect(');
$end = strpos($before, '/** One read operation;', $start);
if (rtrim(substr($before, $start, $end - $start)) . "\n" !== $local(__DIR__ . '/CatalogSortBaseline.txt')) {
    throw new RuntimeException('Frozen selection differs from immutable baseline.');
}
if ($prefix !== $newPrefix || $suffix !== $newSuffix || $newBlock !== $local(__DIR__ . '/CatalogSortBlock.txt')
    || $oldBlock === $newBlock || $read('includes/shop_category.php') !== $local($root . '/includes/shop_category.php')
) {
    throw new RuntimeException('Only the pinned sorting block may change.');
}
echo json_encode(
    ['complete' => true, 'base' => $base, 'only_sort_changed' => true, 'prefix_suffix_byte_equal' => true,
    'frozen_selection_equal' => true, 'category_module_unchanged' => true, 'source_sha256' => hash('sha256', $after)],
    JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
), "\n";
