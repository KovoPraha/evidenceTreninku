<?php

declare(strict_types=1);

require_once __DIR__ . '/shop_catalog_cards.php';
require_once __DIR__ . '/shop_member_pricing_read.php';

/** @param array<string,mixed> $product @return array<string,mixed> */
function shopCatalogPageAmounts(array $product): array
{
    $product['has_member_price'] = count(array_filter(
        $product['variants'],
        static fn (array $variant): bool => ($variant['member_price']['is_member_price'] ?? false) === true
    )) > 0;
    $product['interest_only_course'] = shopProductIsInterestOnlyYoungestCourse($product);
    $product['min_amount_minor'] = min(array_column($product['variants'], 'amount_minor'));
    $product['max_amount_minor'] = max(array_column($product['variants'], 'amount_minor'));
    $product['currency'] = (string) $product['variants'][0]['currency'];
    $product['in_stock'] = count(array_filter($product['variants'], static fn (array $variant): bool => (bool) $variant['in_stock'])) > 0;
    return $product;
}

/** Selection preserves the existing PHP search, category and tie semantics.
 * @param list<array<string,mixed>> $products
 * @param array<string,mixed> $input
 * @param array<int,true>|null $searchMatches
 * @return array<string,mixed>
 */
function shopCatalogPageSelect(PDO $pdo, array $products, array $input, ?array $searchMatches = null): array
{
    $selectedPaths = [];
    $activeCatalogCount = count($products);
    $allProducts = $products;
    $categoryNodes = shopCategoryNodes($pdo);
    $categoryMenu = shopStorefrontCategoryMenu($pdo, $products);
    $selectedCategory = trim((string) ($input['kategorie'] ?? ''));
    $selectedCategoryLabel = '';
    if ($selectedCategory !== '') {
        try {
            $selectedCategory = shopCategoryPath($selectedCategory);
            $selectedPaths = shopCategoryDescendants($categoryNodes, $selectedCategory);
        } catch (InvalidArgumentException) {
            $selectedPaths = [];
        }
        $selectedCategoryLabel = (string) ($categoryNodes[$selectedCategory]['display_name'] ?? $selectedCategory);
        $products = array_values(array_filter(
            $products,
            static fn (array $product): bool => array_intersect($selectedPaths, $product['categories']) !== []
        ));
    }
    $catalogSearch = trim((string) ($input['q'] ?? ''));
    $catalogSort = (string) ($input['razeni'] ?? 'order');
    if (!in_array($catalogSort, ['order', 'name', 'price'], true)) {
        $catalogSort = 'order';
    }
    if ($catalogSearch !== '') {
        $needle = mb_strtolower($catalogSearch, 'UTF-8');
        $products = array_values(array_filter($products, static function (array $product) use ($needle, $searchMatches): bool {
            return $searchMatches !== null ? isset($searchMatches[(int) $product['product_id']])
                : str_contains(mb_strtolower((string) $product['public_name'] . ' ' . (string) $product['public_summary'], 'UTF-8'), $needle);
        }));
    }
    usort($products, static function (array $a, array $b) use ($catalogSort): int {
        $aName = mb_strtolower((string) $a['public_name'], 'UTF-8');
        $bName = mb_strtolower((string) $b['public_name'], 'UTF-8');
        if ($catalogSort === 'price') {
            return [(int) $a['min_amount_minor'], $aName, (int) $a['product_id']]
                <=> [(int) $b['min_amount_minor'], $bName, (int) $b['product_id']];
        }
        if ($catalogSort === 'name') {
            return [$aName, (int) $a['product_id']] <=> [$bName, (int) $b['product_id']];
        }
        // Existing cards have no product sort_order; retain their implicit zero.
        return [(int) ($a['sort_order'] ?? 0), $aName, (int) $a['product_id']]
            <=> [(int) ($b['sort_order'] ?? 0), $bName, (int) $b['product_id']];
    });
    $filteredProductCount = count($products);
    $perPage = 12;
    $page = max(1, (int) ($input['strana'] ?? 1));
    $totalPages = max(1, (int) ceil($filteredProductCount / $perPage));
    $page = min($page, $totalPages);
    $products = array_slice($products, ($page - 1) * $perPage, $perPage);
    $topCategories = [];
    foreach ($categoryMenu as $categoryItem) {
        $path = (string) ($categoryItem['category_path'] ?? '');
        if ($path === '' || str_contains($path, ' > ')) {
            continue;
        }
        $count = 0;
        foreach ($allProducts as $product) {
            foreach ($product['categories'] as $productPath) {
                if ($productPath === $path || str_starts_with($productPath, $path . ' > ')) {
                    $count++;
                    break;
                }
            }
        }
        if ($count > 0) {
            $topCategories[] = ['path' => $path,
                'name' => (string) ($categoryItem['display_name'] ?? $categoryItem['menu_label'] ?? $path), 'count' => $count];
        }
    }
    return compact(
        'products',
        'activeCatalogCount',
        'categoryNodes',
        'categoryMenu',
        'selectedCategory',
        'selectedCategoryLabel',
        'selectedPaths',
        'catalogSearch',
        'catalogSort',
        'filteredProductCount',
        'perPage',
        'page',
        'totalPages',
        'topCategories'
    );
}

/** One read operation; no cache survives a call or crosses into checkout.
 * Null means anonymous; an integer preserves the logged-in controller contract.
 * @param array<string,mixed> $input @return array<string,mixed>
 */
function shopCatalogPageRead(PDO $pdo, ?int $accountId, array $input): array
{
    if ($pdo->inTransaction()) {
        $products = shopPublicCatalogCards($pdo);
        if ($accountId !== null) {
            $products = shopMemberPricesApplyToCatalog($pdo, $accountId, $products);
        }
        return shopCatalogPageSelect($pdo, array_map('shopCatalogPageAmounts', $products), $input);
    }
    $context = new ClubProgramReadContext($pdo);
    $products = [];
    foreach (shopStorefrontReadRows($pdo, null, null, true, $context) as $row) {
        $id = (int) $row['product_id'];
        if (!isset($products[$id])) {
            $products[$id] = [
                'product_id' => $id, 'offer_type' => (string) $row['offer_type'],
                'origin' => $row['origin'], 'external_product_key' => $row['external_product_key'],
                'public_name' => shopProductPublicName($row), 'public_summary' => '',
                'categories' => [], 'variants' => [],
            ];
        }
        $row['in_stock'] = $row['offer_type'] === 'program'
            || $row['stock_quantity_decimal'] === null || (float) $row['stock_quantity_decimal'] > 0.0;
        $products[$id]['variants'][] = $row;
    }
    foreach (array_chunk(array_keys($products), 500) as $ids) {
        foreach (shopStorefrontCategories($pdo, $ids) as $id => $paths) {
            $products[$id]['categories'] = $paths;
        }
    }
    $products = shopPublicFilterSaleableProducts($pdo, array_values($products), $context);
    if ($accountId !== null) {
        $products = shopMemberPricesApplyToCatalog($pdo, $accountId, $products);
    }
    // Summaries are needed only for search. Bounded batches avoid retaining full
    // descriptions across the catalog, while PHP retains literal/Unicode matching.
    $search = trim((string) ($input['q'] ?? ''));
    if ($search !== '') {
        $needle = mb_strtolower($search, 'UTF-8');
        $matches = [];
        foreach (array_chunk($products, 100) as $batch) {
            $ids = array_column($batch, 'product_id');
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $statement = $pdo->prepare("SELECT product_id,public_summary FROM shop_product_publications WHERE product_id IN ($marks)");
            $statement->execute($ids);
            $summaries = $statement->fetchAll(PDO::FETCH_KEY_PAIR);
            foreach ($batch as $product) {
                if (str_contains(mb_strtolower($product['public_name'] . ' ' . (string) ($summaries[$product['product_id']] ?? ''), 'UTF-8'), $needle)) {
                    $matches[(int) $product['product_id']] = true;
                }
            }
        }
    }
    $state = shopCatalogPageSelect($pdo, array_map('shopCatalogPageAmounts', $products), $input, $matches ?? null);
    unset($products);
    $selected = $state['products'];
    $ids = array_map('intval', array_column($selected, 'product_id'));
    $cards = [];
    foreach (shopPublicCatalogCards($pdo, $ids, $context) as $product) {
        $cards[(int) $product['product_id']] = $product;
    }
    $state['products'] = [];
    foreach ($selected as $indexProduct) {
        $id = (int) $indexProduct['product_id'];
        if (!isset($cards[$id])) {
            continue;
        }
        $product = $cards[$id];
        $prices = [];
        foreach ($indexProduct['variants'] as $variant) {
            $prices[(int) $variant['variant_id']] = $variant;
        }
        foreach ($product['variants'] as &$variant) {
            foreach (['amount_minor', 'public_amount_minor', 'member_price'] as $key) {
                if (array_key_exists($key, $prices[(int) $variant['variant_id']] ?? [])) {
                    $variant[$key] = $prices[(int) $variant['variant_id']][$key];
                }
            }
        }
        unset($variant);
        $state['products'][] = shopCatalogPageAmounts($product);
    }
    return $state;
}
