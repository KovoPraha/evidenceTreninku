<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__, 2) . '/includes/shop_category.php';

function categoryLabelFrozenClosure(string $file, string $name, bool $menu = false): Closure
{
    $source = (string)file_get_contents(__DIR__ . '/' . $file);
    $source = preg_replace('/^function ' . preg_quote($name, '/') . '/', 'return static function', $source, 1, $count);
    if ($count !== 1) {
        throw new RuntimeException('Frozen category label scope unavailable.');
    }
    if ($menu) {
        $source = str_replace('shopCategoryMenuLabel(', 'categoryLabelBaseline(', $source);
    }
    $closure = eval($source . ';');
    if (!$closure instanceof Closure) {
        throw new RuntimeException('Frozen category label closure unavailable.');
    }
    return $closure;
}

function categoryLabelBaseline(array $nodes, string $path): string
{
    static $original = null;
    $original ??= categoryLabelFrozenClosure('CategoryLabelBaseline.txt', 'shopCategoryMenuLabel');
    return $original($nodes, $path);
}

function categoryLabelMenuBaseline(PDO $pdo, array $products): array
{
    static $original = null;
    $original ??= categoryLabelFrozenClosure('CategoryLabelMenuBaseline.txt', 'shopStorefrontCategoryMenu', true);
    return $original($pdo, $products);
}
