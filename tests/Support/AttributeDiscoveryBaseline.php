<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__, 2) . '/includes/shop_attribute.php';

/** Exact frozen pre-change algorithm, confined to CLI verification. */
function attributeDiscoveryBaseline(PDO $pdo): array
{
    static $original = null;
    if ($original === null) {
        $source = (string)file_get_contents(__DIR__ . '/AttributeDiscoveryBaseline.txt');
        $source = preg_replace('/^function shopAttributeDiscoveredKeys/', 'return static function', $source, 1, $count);
        if ($count !== 1) {
            throw new RuntimeException('The exact frozen discovery function is required.');
        }
        $original = eval($source . ';');
        if (!$original instanceof Closure) {
            throw new RuntimeException('Frozen discovery closure unavailable.');
        }
    }
    return $original($pdo);
}
