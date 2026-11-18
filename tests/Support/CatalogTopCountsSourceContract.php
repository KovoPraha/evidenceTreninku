<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$root = dirname(__DIR__, 2);
$base = 'a1bf2ba28a6e248ae99eb403d06b75749dcc04cf';
$process = proc_open(['git', 'show', $base . ':includes/shop_catalog_page_read.php'], [1 => ['pipe', 'w'], 2 => STDERR], $pipes, $root);
if (!is_resource($process)) {
    throw new RuntimeException('Immutable catalog source required.');
}
$before = str_replace("\r\n", "\n", (string)stream_get_contents($pipes[1]));
fclose($pipes[1]);
if (proc_close($process) !== 0) {
    throw new RuntimeException('Immutable catalog source unavailable.');
}
$after = str_replace("\r\n", "\n", (string)file_get_contents($root . '/includes/shop_catalog_page_read.php'));
$scope = static function (string $source): array {
    $marker = '    $products = array_slice($products, ($page - 1) * $perPage, $perPage);' . "\n";
    $start = strpos($source, $marker);
    if ($start === false || substr_count($source, $marker) !== 1) {
        throw new RuntimeException('Unique count scope required.');
    }
    $start += strlen($marker);
    $end = strpos($source, '    return compact(', $start);
    if ($end === false) {
        throw new RuntimeException('Selection return required.');
    }
    return [substr($source, 0, $start), substr($source, $start, $end - $start), substr($source, $end)];
};
[$prefix, $oldBlock, $suffix] = $scope($before);
[$newPrefix, $newBlock, $newSuffix] = $scope($after);
$start = strpos($before, 'function shopCatalogPageSelect(');
$end = strpos($before, '/** One read operation;', $start);
$frozen = str_replace("\r\n", "\n", (string)file_get_contents(__DIR__ . '/CatalogTopCountsBaseline.txt'));
$expected = str_replace("\r\n", "\n", (string)file_get_contents(__DIR__ . '/CatalogTopCountsBlock.txt'));
if (rtrim(substr($before, $start, $end - $start)) . "\n" !== $frozen) {
    throw new RuntimeException('Frozen selection differs from immutable baseline.');
}
if ($prefix !== $newPrefix || $suffix !== $newSuffix || $expected !== $newBlock || $oldBlock === $newBlock) {
    throw new RuntimeException('Only the pinned category count block may change.');
}
echo json_encode(['complete' => true, 'base' => $base, 'only_counts_changed' => true, 'prefix_suffix_byte_equal' => true,
    'frozen_selection_equal' => true, 'source_sha256' => hash('sha256', $after), 'frozen_sha256' => hash('sha256', $frozen)], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";
