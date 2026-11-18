<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__, 2) . '/includes/shop_catalog_page_read.php';

function categoryDescendantsFrozenClosure(string $file, string $name, bool $selection = false): Closure
{
    $source = (string)file_get_contents(__DIR__ . '/' . $file);
    $source = preg_replace('/^function ' . preg_quote($name, '/') . '/', 'return static function', $source, 1, $count);
    if ($count !== 1) {
        throw new RuntimeException('Frozen category scope required.');
    }
    if ($selection) {
        $source = str_replace(
            ['shopCategoryDescendants(', 'shopStorefrontCategoryMenu('],
            ['categoryDescendantsBaseline(', 'categoryDescendantsMenuBaseline('],
            $source
        );
    }
    $closure = eval('declare(strict_types=1);' . $source . ';');
    if (!$closure instanceof Closure) {
        throw new RuntimeException('Frozen category closure unavailable.');
    }
    return $closure;
}

function categoryDescendantsBaseline(array $nodes, string $path): array
{
    static $original = null;
    $original ??= categoryDescendantsFrozenClosure('CategoryDescendantsBaseline.txt', 'shopCategoryDescendants');
    return $original($nodes, $path);
}

function categoryDescendantsSelectBaseline(PDO $pdo, array $products, array $input = [], ?array $searchMatches = null): array
{
    static $original = null;
    $original ??= categoryDescendantsFrozenClosure('CategoryDescendantsSelectBaseline.txt', 'shopCatalogPageSelect', true);
    return $original($pdo, $products, $input, $searchMatches);
}

function categoryDescendantsMenuBaseline(PDO $pdo, array $products): array
{
    static $original = null;
    $original ??= categoryDescendantsFrozenClosure('CategoryDescendantsMenuBaseline.txt', 'shopStorefrontCategoryMenu');
    return $original($pdo, $products);
}
