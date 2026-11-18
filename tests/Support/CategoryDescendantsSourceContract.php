<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$root = dirname(__DIR__, 2);
$base = 'd92fd79067ea9e63da183088d8d806b86e6f9760';
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
$before = $read('includes/shop_category.php');
$after = $local($root . '/includes/shop_category.php');
$scope = static function (string $source): array {
    $start = strpos($source, 'function shopCategoryDescendants(');
    $end = strpos($source, '/** @param array<string,array<string,mixed>> $nodes @return list<array', $start);
    if ($start === false || $end === false || substr_count($source, 'function shopCategoryDescendants(') !== 1) {
        throw new RuntimeException('Unique descendant scope required.');
    }
    return [substr($source, 0, $start), substr($source, $start, $end - $start), substr($source, $end)];
};
[$prefix, $function, $suffix] = $scope($before);
[$newPrefix, $newFunction, $newSuffix] = $scope($after);
$page = $read('includes/shop_catalog_page_read.php');
$start = strpos($page, 'function shopCatalogPageSelect(');
$end = strpos($page, '/** One read operation;', $start);
if (rtrim($function) . "\n" !== $local(__DIR__ . '/CategoryDescendantsBaseline.txt')
    || rtrim(substr($page, $start, $end - $start)) . "\n" !== $local(__DIR__ . '/CategoryDescendantsSelectBaseline.txt')
) {
    throw new RuntimeException('Frozen helper/selection differs from immutable baseline.');
}
if ($prefix !== $newPrefix || $suffix !== $newSuffix
    || $newFunction !== $local(__DIR__ . '/CategoryDescendantsCandidate.txt') . "\n"
    || $page !== $local($root . '/includes/shop_catalog_page_read.php')
) {
    throw new RuntimeException('Only the pinned descendant helper may change.');
}
echo json_encode(['complete' => true, 'base' => $base, 'only_descendants_changed' => true,
    'prefix_suffix_byte_equal' => true, 'frozen_helper_selection_equal' => true, 'page_module_unchanged' => true,
    'source_sha256' => hash('sha256', $after), 'frozen_sha256' => hash('sha256', $function)], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";
