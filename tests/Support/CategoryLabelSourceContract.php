<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$root = dirname(__DIR__, 2);
$base = '30386ddb29ceed99190a18ed9e792ff02111b139';
$process = proc_open(['git', 'show', $base . ':includes/shop_category.php'], [1 => ['pipe', 'w'], 2 => STDERR], $pipes, $root);
if (!is_resource($process)) {
    throw new RuntimeException('Immutable category source required.');
}
$before = str_replace("\r\n", "\n", (string)stream_get_contents($pipes[1]));
fclose($pipes[1]);
if (proc_close($process) !== 0) {
    throw new RuntimeException('Immutable category source unavailable.');
}
$after = str_replace("\r\n", "\n", (string)file_get_contents($root . '/includes/shop_category.php'));
$scope = static function (string $source): array {
    $start = strpos($source, 'function shopCategoryMenuLabel(');
    $end = strpos($source, '/** @param list<array', $start);
    if ($start === false || $end === false || substr_count($source, 'function shopCategoryMenuLabel(') !== 1) {
        throw new RuntimeException('Unique label scope required.');
    }
    return [substr($source, 0, $start), substr($source, $start, $end - $start), substr($source, $end)];
};
[$prefix, $function, $suffix] = $scope($before);
[$newPrefix, $newFunction, $newSuffix] = $scope($after);
$frozen = str_replace("\r\n", "\n", (string)file_get_contents(__DIR__ . '/CategoryLabelBaseline.txt'));
$menu = str_replace("\r\n", "\n", (string)file_get_contents(__DIR__ . '/CategoryLabelMenuBaseline.txt'));
if (rtrim($function) . "\n" !== $frozen || substr($before, strpos($before, 'function shopStorefrontCategoryMenu(')) !== $menu) {
    throw new RuntimeException('Frozen label/menu differ from immutable baseline.');
}
if ($prefix !== $newPrefix || $suffix !== $newSuffix) {
    throw new RuntimeException('Source outside the label changed.');
}
$expected = str_replace(
    "        array_unshift(\n            \$labels,\n            (string)\$nodes[\$current]['display_name']\n        );",
    "        \$labels[] = (string)\$nodes[\$current]['display_name'];",
    $function,
    $count
);
if ($count !== 1) {
    throw new RuntimeException('Expected append replacement missing.');
}
$expected = str_replace("return implode(' › ', \$labels);", "return implode(' › ', array_reverse(\$labels));", $expected, $count);
if ($count !== 1 || $expected !== $newFunction) {
    throw new RuntimeException('Only append and one reverse are allowed.');
}
echo json_encode(['complete' => true, 'base' => $base, 'only_label_changed' => true,
    'prefix_suffix_byte_equal' => true, 'frozen_label_menu_equal' => true,
    'source_sha256' => hash('sha256', $after), 'frozen_sha256' => hash('sha256', $frozen)], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";
