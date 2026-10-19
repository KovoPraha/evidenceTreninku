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

function shopPurchaseModeColumnExists(PDO $pdo, string $table, string $column): bool
{
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement=$pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1');
        $statement->execute([$table,$column]);return$statement->fetchColumn()!==false;
    }
    foreach($pdo->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC)as$row)if((string)$row['name']===$column)return true;
    return false;
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
    $hasMigrationIdentity=shopPurchaseModeColumnExists($pdo,'shop_products','origin')&&shopPurchaseModeColumnExists($pdo,'shop_products','external_product_key');
    $migrationIdentity=$hasMigrationIdentity?',p.origin,p.external_product_key':",NULL AS origin,NULL AS external_product_key";
    $statement = $pdo->prepare($hasPublications
        ? 'SELECT p.offer_type,pub.public_name'.$migrationIdentity.' FROM shop_products p LEFT JOIN shop_product_publications pub ON pub.product_id=p.id WHERE p.id=?'
        : 'SELECT p.offer_type,NULL AS public_name'.$migrationIdentity.' FROM shop_products p WHERE p.id=?');
    $statement->execute([$productId]);
    $product = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$product) return false;
    if ((string)$product['offer_type'] === 'program') return true;
    // The explicitly approved full Shoptet replacement remains a conventional
    // shop catalogue. Its legacy services and clubs are intentionally sold as
    // ordinary order lines until they are separately migrated to KIS programs.
    if((string)$product['offer_type']==='goods'&&(string)($product['origin']??'')==='import'
        &&preg_match('/^shoptet:(?:pair|sku):/D',(string)($product['external_product_key']??''))===1)return false;

    $signals = [(string)($product['public_name'] ?? '')];
    if (shopPurchaseModeTableExists($pdo, 'shop_product_categories')) {
        $categories = $pdo->prepare('SELECT category_path FROM shop_product_categories WHERE product_id=?');
        $categories->execute([$productId]);
        foreach ($categories->fetchAll(PDO::FETCH_COLUMN) as $category) $signals[] = (string)$category;
    }
    $haystack = mb_strtolower(implode("\n", $signals), 'UTF-8');

    return preg_match('/(?:^|[\s>\-])(krouž(?:ek|ky|ku)|předpřípravka|kurz(?:y|u)?|členství|člen(?:ství)?\s+v\s+oddílu)(?:$|[\s<\-:(])/u', $haystack) === 1;
}
