<?php
declare(strict_types=1);

function shopPurchaseModeTableExists(PDO $pdo, string $table): bool
{
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
        $statement->execute([$table]);
        return $statement->fetchColumn() !== false;
    }
    $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1");
    $statement->execute([$table]);
    return $statement->fetchColumn() !== false;
}

/**
 * Legacy catalog rows can still be marked as goods even when their public
 * identity clearly describes a sportsperson-bound offer. Fail closed: these
 * products must never enter the guest goods checkout until an administrator
 * links a proper program offer.
 */
function shopProductRequiresAthlete(PDO $pdo, int $productId): bool
{
    if ($productId < 1) return false;
    $hasPublications = shopPurchaseModeTableExists($pdo, 'shop_product_publications');
    $statement = $pdo->prepare($hasPublications
        ? 'SELECT p.offer_type,pub.public_name FROM shop_products p LEFT JOIN shop_product_publications pub ON pub.product_id=p.id WHERE p.id=?'
        : 'SELECT p.offer_type,NULL AS public_name FROM shop_products p WHERE p.id=?');
    $statement->execute([$productId]);
    $product = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$product) return false;
    if ((string)$product['offer_type'] === 'program') return true;

    $signals = [(string)($product['public_name'] ?? '')];
    if (shopPurchaseModeTableExists($pdo, 'shop_product_categories')) {
        $categories = $pdo->prepare('SELECT category_path FROM shop_product_categories WHERE product_id=?');
        $categories->execute([$productId]);
        foreach ($categories->fetchAll(PDO::FETCH_COLUMN) as $category) $signals[] = (string)$category;
    }
    $haystack = mb_strtolower(implode("\n", $signals), 'UTF-8');

    return preg_match('/(?:^|[\s>\-])(krouž(?:ek|ky|ku)|předpřípravka|kurz(?:y|u)?|členství|člen(?:ství)?\s+v\s+oddílu)(?:$|[\s<\-:(])/u', $haystack) === 1;
}
