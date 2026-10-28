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
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1');
        $statement->execute([$table,$column]);
        return$statement->fetchColumn() !== false;
    }
    foreach ($pdo->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ((string)$row['name'] === $column) {
            return true;
        }
    }
    return false;
}

/**
 * Publication approval keeps the imported catalogue visible; it does not
 * approve treating a club registration as ordinary goods at checkout.
 * @param array<string,mixed> $product
 */
function shopPurchaseModeApprovedShoptetGoods(array$product): bool
{
    return(string)($product['offer_type'] ?? '') === 'goods' && (string)($product['origin'] ?? '') === 'import'
        && preg_match('/^shoptet:(?:pair|sku):/D', (string)($product['external_product_key'] ?? '')) === 1;
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

/** @param array<string,mixed> $product */
function shopProductPublicName(array $product): string
{
    $sourceName = (string)($product['public_name'] ?? '');
    if (!shopProductIsInterestOnlyYoungestCourse($product)) {
        return $sourceName;
    }

    // Imported lesson titles contain a historical weekday/time. These courses
    // open irregularly, so show only their reviewed age groups in public UI.
    return match ((int)$product['product_id']) {
        157 => 'Děti 8–14 měsíců',
        158 => 'Děti 1–2 roky',
        159 => 'Děti 2–3 roky',
    };
}

/**
 * Legacy catalog rows can still be marked as goods even when their public
 * identity clearly describes a sportsperson-bound offer. Fail closed: these
 * products must never enter the guest goods checkout until an administrator
 * links a proper program offer.
 */
function shopProductRequiresAthlete(PDO $pdo, int $productId): bool
{
    if ($productId < 1) {
        return false;
    }
    $hasPublications = shopPurchaseModeTableExists($pdo, 'shop_product_publications');
    $statement = $pdo->prepare($hasPublications
        ? 'SELECT p.*,pub.public_name FROM shop_products p LEFT JOIN shop_product_publications pub ON pub.product_id=p.id WHERE p.id=?'
        : 'SELECT p.*,NULL AS public_name FROM shop_products p WHERE p.id=?');
    $statement->execute([$productId]);
    $product = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$product) {
        return false;
    }
    if ((string)$product['offer_type'] === 'program') {
        return true;
    }
    if (shopPurchaseModeNonParticipationGoods($product)) {
        return false;
    }
    $categories = [];
    if (shopPurchaseModeTableExists($pdo, 'shop_product_categories')) {
        $statement = $pdo->prepare('SELECT category_path FROM shop_product_categories WHERE product_id=?');
        $statement->execute([$productId]);
        $categories = array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }
    return shopProductRequiresAthleteFromData($product, $categories);
}

/** @param array<string,mixed> $product */
function shopPurchaseModeNonParticipationGoods(array $product): bool
{
    $names = ['shoptet:pair:250' => 'Trička', 'shoptet:pair:852' => 'Uskladnění kol na školní pololetí'];
    $key = (string) ($product['external_product_key'] ?? '');
    return shopPurchaseModeApprovedShoptetGoods($product) && isset($names[$key])
        && trim((string) ($product['public_name'] ?? '')) === $names[$key];
}

/** Same classification for canonical PDO callers and an operation-local read projection.
 * @param array<string,mixed> $product
 * @param list<string> $categories
 */
function shopProductRequiresAthleteFromData(array $product, array $categories): bool
{
    if ((string) ($product['offer_type'] ?? '') === 'program') {
        return true;
    }
    if (shopPurchaseModeNonParticipationGoods($product)) {
        return false;
    }
    $signals = [(string) ($product['public_name'] ?? ''), ...$categories];
    $haystack = mb_strtolower(implode("\n", $signals), 'UTF-8');

    return preg_match('/(?:^|[\s>\-])(krouž(?:ek|ky|ku)|předpřípravka|kurz(?:y|u)?|členství|člen(?:ství)?\s+v\s+oddílu)(?:$|[\s<\-:(])/u', $haystack) === 1;
}
