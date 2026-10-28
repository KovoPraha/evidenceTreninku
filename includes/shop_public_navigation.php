<?php

declare(strict_types=1);

require_once __DIR__ . '/shop_storefront.php';
require_once __DIR__ . '/shop_purchase_mode.php';
require_once __DIR__ . '/club_program.php';

function shopPublicNavigationH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** @return list<array<string,mixed>> */
function shopPublicSaleableCatalog(PDO $pdo): array
{
    return shopPublicFilterSaleableProducts($pdo, shopStorefrontCatalog($pdo));
}

/** @param list<array<string,mixed>> $catalog @return list<array<string,mixed>> */
function shopPublicFilterSaleableProducts(PDO $pdo, array $catalog): array
{
    $productIds = [];
    $variantIds = [];
    foreach ($catalog as $product) {
        if (shopPurchaseModeApprovedShoptetGoods($product)) {
            continue;
        }
        $productIds[] = (int) $product['product_id'];
        foreach ($product['variants'] as $variant) {
            $variantIds[] = (int) $variant['variant_id'];
        }
    }
    $links = shopStorefrontReadOfferLinks($pdo, $productIds, $variantIds);
    $products = [];
    foreach ($catalog as $product) {
        // Keep the approved full import visible, including interest-only club
        // products. Athlete/offer validation still applies at every checkout.
        if (shopPurchaseModeApprovedShoptetGoods($product)) {
            $products[] = $product;
            continue;
        }
        $hasProgramOffer = isset($links['products'][(int) $product['product_id']]);
        $isProgram = shopProductRequiresAthleteFromData($product, $product['categories']);
        $product['variants'] = array_values(array_filter($product['variants'], static function (array $variant) use ($pdo, $hasProgramOffer, $isProgram, $links): bool {
            $variantId = (int) $variant['variant_id'];
            if ($hasProgramOffer) {
                return clubProgramVariantSaleState($pdo, $variantId)['saleable'];
            }
            // A program without an approved offer can collect interest by direct link,
            // but does not belong in the purchasable catalog or its category menu.
            return !$isProgram && (!isset($links['variants'][$variantId]) || clubProgramOfferForVariant($pdo, $variantId) === false);
        }));
        if ($product['variants'] !== []) {
            $products[] = $product;
        }
    }
    return $products;
}

/** @return list<array<string,mixed>> */
function shopPublicNavigationItems(PDO $pdo): array
{
    $products = [];
    foreach (shopStorefrontReadRows($pdo, null, null, true) as $row) {
        $id = (int) $row['product_id'];
        if (!isset($products[$id])) {
            $products[$id] = [
                'product_id' => $id,
                'offer_type' => (string) $row['offer_type'],
                'origin' => $row['origin'],
                'external_product_key' => $row['external_product_key'],
                'public_name' => shopProductPublicName($row),
                'categories' => [],
                'variants' => [],
            ];
        }
        $products[$id]['variants'][] = $row;
    }
    $categories = shopStorefrontCategories($pdo, array_keys($products));
    foreach ($products as $id => &$product) {
        $product['categories'] = $categories[$id] ?? [];
    }
    unset($product);
    return shopStorefrontCategoryMenu($pdo, shopPublicFilterSaleableProducts($pdo, array_values($products)));
}

/** @param list<array<string,mixed>> $items */
function shopPublicNavigationHtml(array $items, ?string $activeCategory = null, bool $homeActive = false): string
{
    $html = '<nav class="app-shop-nav" aria-label="Navigace e-shopu"><div class="container app-shop-nav-inner">';
    $html .= '<a class="app-shop-home' .
         ($homeActive ? ' active' : '') .
         '" href="eshop.php"' .
         ($homeActive ? ' aria-current="page"' : '') .
         '><i class="bi bi-shop" aria-hidden="true"></i><span>E-shop – domů</span></a>';
    if ($items !== []) {
        $html .= '<div class="app-shop-nav-categories" aria-label="Kategorie produktů">';
        $groups = [];
        foreach ($items as $item) {
            $path = trim((string)($item['category_path'] ?? ''));
            if ($path === '') {
                continue;
            }
            $root = explode(' > ', $path)[0];
            $groups[$root][] = $item;
        }
        foreach ($groups as $root => $group) {
            $rootItem = null;
            foreach ($group as $item) {
                if ((string)($item['category_path'] ?? '') === $root) {
                    $rootItem = $item;
                }
            }
            $firstItem = $group[0];
            $firstMenuLabel = (string)($firstItem['menu_label'] ?? '');
            $rootName = $rootItem !== null
                ? (string)($rootItem['display_name'] ?? $root)
                : (explode(' › ', $firstMenuLabel)[0] ?: $root);
            $active = $activeCategory !== null && ($activeCategory === $root || str_starts_with($activeCategory, $root . ' > '));
            if (count($group) === 1) {
                $singlePath = (string)$firstItem['category_path'];
                $singleName = (string)($firstItem['display_name'] ?? $firstItem['menu_label'] ?? $singlePath);
                $html .= '<a class="app-shop-nav-link' .
                     ($active ? ' active' : '') .
                     '" href="eshop.php?kategorie=' .
                     rawurlencode($singlePath) .
                     '"' .
                     ($active ? ' aria-current="page"' : '') .
                     '>' .
                     shopPublicNavigationH($singleName) .
                     '</a>';
                continue;
            }
            $dropdownId = 'shop-category-' . substr(hash('sha256', $root), 0, 10);
            $html .= '<div class="dropdown"><button class="dropdown-toggle' .
                 ($active ? ' active' : '') .
                 '" type="button" id="' .
                 $dropdownId .
                 '" data-bs-toggle="dropdown" aria-expanded="false">' .
                 shopPublicNavigationH($rootName) .
                 '</button>';
            $html .= '<ul class="dropdown-menu" aria-labelledby="' . $dropdownId . '">';
            if ($rootItem !== null) {
                $html .= '<li><a class="dropdown-item fw-semibold' .
                     ($activeCategory === $root ? ' active' : '') .
                     '" href="eshop.php?kategorie=' .
                     rawurlencode($root) .
                     '">Vše: ' .
                     shopPublicNavigationH($rootName) .
                     '</a></li>';
            }
            foreach ($group as $item) {
                $path = (string)$item['category_path'];
                if ($path === $root) {
                    continue;
                }
                $name = (string)($item['menu_label'] ?? $item['display_name'] ?? $path);
                $itemActive = $activeCategory === $path;
                $html .= '<li><a class="dropdown-item' .
                     ($itemActive ? ' active' : '') .
                     '" href="eshop.php?kategorie=' .
                     rawurlencode($path) .
                     '"' .
                     ($itemActive ? ' aria-current="page"' : '') .
                     '>' .
                     shopPublicNavigationH($name) .
                     '</a></li>';
            }
            $html .= '</ul></div>';
        }
        $html .= '</div>';
    }
    return $html . '</div></nav>';
}

/** @param list<array<string,mixed>>|null $items */
function shopPublicNavigation(PDO $pdo, ?string $activeCategory = null, bool $homeActive = false, ?array $items = null): void
{
    echo shopPublicNavigationHtml($items ?? shopPublicNavigationItems($pdo), $activeCategory, $homeActive);
}
