<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__, 2) . '/includes/shop_category.php';

/** Frozen canonical algorithm, only available to CLI verification. */
function categoryMenuBaseline(PDO $pdo, array $products): array
{
    static $original = null;
    if ($original === null) {
        $source = (string)file_get_contents(__DIR__ . '/CategoryMenuBaseline.txt');
        $source = preg_replace('/^function shopStorefrontCategoryMenu/', 'return static function', $source, 1, $count);
        if ($count !== 1) {
            throw new RuntimeException('Frozen category menu required.');
        }
        $original = eval($source . ';');
        if (!$original instanceof Closure) {
            throw new RuntimeException('Frozen category menu unavailable.');
        }
    }
    return $original($pdo, $products);
}
