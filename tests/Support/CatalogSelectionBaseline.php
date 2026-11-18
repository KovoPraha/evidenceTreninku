<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__, 2) . '/includes/shop_catalog_page_read.php';

function catalogSelectionBaseline(PDO $pdo, array $products, array $input = [], ?array $searchMatches = null): array
{
    static $original = null;
    if ($original === null) {
        $source = (string)file_get_contents(__DIR__ . '/CatalogSelectionBaseline.txt');
        $source = preg_replace('/^function shopCatalogPageSelect/', 'return static function', $source, 1, $count);
        if ($count !== 1) {
            throw new RuntimeException('Frozen selection required.');
        }
        $original = eval('declare(strict_types=1);' . $source . ';');
        if (!$original instanceof Closure) {
            throw new RuntimeException('Frozen selection unavailable.');
        }
    }
    return $original($pdo, $products, $input, $searchMatches);
}
