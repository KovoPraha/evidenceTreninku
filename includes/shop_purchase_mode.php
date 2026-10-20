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
 * Publication approval keeps the imported catalogue visible; it does not
 * approve treating a club registration as ordinary goods at checkout.
 * @param array<string,mixed> $product
 */
function shopPurchaseModeApprovedShoptetGoods(array$product):bool
{
    return(string)($product['offer_type']??'')==='goods'&&(string)($product['origin']??'')==='import'
        &&preg_match('/^shoptet:(?:pair|sku):/D',(string)($product['external_product_key']??''))===1;
}

/**
 * These imported classes open irregularly. The legacy 250 CZK is a single
 * lesson price, not an approved price for an entire course. Keep their public
 * pages as contact-only until a complete course offer is deliberately issued.
 *
 * @param array<string,mixed> $product
 */
function shopProductIsInterestOnlyYoungestCourse(array $product): bool
{
    return in_array((int)($product['product_id'] ?? 0), [157, 158, 159], true)
        && (string)($product['origin'] ?? '') === 'import'
        && str_starts_with((string)($product['external_product_key'] ?? ''), 'shoptet:');
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
        ? 'SELECT p.*,pub.public_name FROM shop_products p LEFT JOIN shop_product_publications pub ON pub.product_id=p.id WHERE p.id=?'
        : 'SELECT p.*,NULL AS public_name FROM shop_products p WHERE p.id=?');
    $statement->execute([$productId]);
    $product = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$product) return false;
    if ((string)$product['offer_type'] === 'program') return true;
    // The reviewed Shoptet catalogue also places these two non-participation
    // products in Kroužky. Match both their stable source key and public name:
    // category membership alone must not turn a shirt or bike storage into
    // enrollment, and this exception must never reopen other imported clubs.
    $nonParticipationProducts = [
        'shoptet:pair:250' => 'Trička',
        'shoptet:pair:852' => 'Uskladnění kol na školní pololetí',
    ];
    $sourceKey = (string)($product['external_product_key'] ?? '');
    if (shopPurchaseModeApprovedShoptetGoods($product)
        && isset($nonParticipationProducts[$sourceKey])
        && trim((string)($product['public_name'] ?? '')) === $nonParticipationProducts[$sourceKey]) {
        return false;
    }
    $signals = [(string)($product['public_name'] ?? '')];
    if (shopPurchaseModeTableExists($pdo, 'shop_product_categories')) {
        $categories = $pdo->prepare('SELECT category_path FROM shop_product_categories WHERE product_id=?');
        $categories->execute([$productId]);
        foreach ($categories->fetchAll(PDO::FETCH_COLUMN) as $category) $signals[] = (string)$category;
    }
    $haystack = mb_strtolower(implode("\n", $signals), 'UTF-8');

    return preg_match('/(?:^|[\s>\-])(krouž(?:ek|ky|ku)|předpřípravka|kurz(?:y|u)?|členství|člen(?:ství)?\s+v\s+oddílu)(?:$|[\s<\-:(])/u', $haystack) === 1;
}
