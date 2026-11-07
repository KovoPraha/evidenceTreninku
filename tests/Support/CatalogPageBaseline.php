<?php

declare(strict_types=1);

// Frozen controller from merged PR #358 main 37d0ad68; formatting normalized only.
require_once dirname(__DIR__, 2) . '/includes/shop_catalog_cards.php';
require_once dirname(__DIR__, 2) . '/includes/shop_member_pricing_read.php';
function beforeShopCatalogPage(PDO $pdo, ?int $accountId, array $input): array
{
    $isLoggedIn = $accountId !== null;
    $accountId ??= 0;
    $_GET = $input;
    $selectedPaths = [];
    $catalogProducts = shopPublicCatalogCards($pdo);
    if ($isLoggedIn) {
        $catalogProducts = shopMemberPricesApplyToCatalog($pdo, $accountId, $catalogProducts);
    }
    $products = [];
    foreach ($catalogProducts as $product) {
        $product['has_member_price'] = count(array_filter($product['variants'], static fn (array $variant): bool => ($variant['member_price']['is_member_price']
        ?? false) === true)) > 0;

        $product['interest_only_course'] = shopProductIsInterestOnlyYoungestCourse($product);
        $product['min_amount_minor'] = min(array_column($product['variants'], 'amount_minor'));
        $product['max_amount_minor'] = max(array_column($product['variants'], 'amount_minor'));
        $product['currency'] = (string)$product['variants'][0]['currency'];
        $product['in_stock'] = count(array_filter($product['variants'], static fn (array $variant): bool => (bool)$variant['in_stock'
        ])) > 0;

        $products[] = $product;
    }
    unset($catalogProducts);
    $activeCatalogCount = count($products);
    $allProducts = $products;
    $categoryNodes = shopCategoryNodes($pdo);
    $categoryMenu = shopStorefrontCategoryMenu($pdo, $products);
    $selectedCategory = trim((string)($_GET['kategorie'] ?? ''));
    $selectedCategoryLabel = '';
    if ($selectedCategory !== '') {
        try {
            $selectedCategory = shopCategoryPath($selectedCategory);
            $selectedPaths = shopCategoryDescendants($categoryNodes, $selectedCategory);
        } catch (InvalidArgumentException) {
            $selectedPaths = [];
        }
        $selectedCategoryLabel = (string)($categoryNodes[$selectedCategory]['display_name'] ?? $selectedCategory);

        $products = array_values(array_filter($products, static fn (array$product): bool => array_intersect(
            $selectedPaths,
            $product['categories']
        ) !== []));
    }
    $catalogSearch = trim((string)($_GET['q'] ?? ''));
    $catalogSort = (string)($_GET['razeni'] ?? 'order');
    if (!in_array($catalogSort, ['order','name','price'], true)) {
        $catalogSort = 'order';
    }
    if ($catalogSearch !== '') {
        $needle = mb_strtolower($catalogSearch, 'UTF-8');
        $products = array_values(array_filter($products, static fn (array$product): bool => str_contains(mb_strtolower((string)
        $product['public_name']
        .
' '.(string)$product['public_summary'], 'UTF-8'), $needle)));
    }
    usort($products, static function (array$a, array$b) use ($catalogSort): int {
        if ($catalogSort === 'price') {
            return[(int)$a['min_amount_minor'],mb_strtolower((string)$a['public_name'], 'UTF-8'),(int)$a['product_id']] <=> [
    (int)$b['min_amount_minor']
    ,
mb_strtolower((string)$b['public_name'], 'UTF-8'),(int)$b['product_id']];
        }if ($catalogSort === 'name') {
            return[mb_strtolower((string)$a['public_name'], 'UTF-8'),(int)$a['product_id']] <=> [mb_strtolower(
                (string)$b['public_name'],
                'UTF-8'
            ),(int)$b['product_id']];
        }return[(int)($a['sort_order'] ?? 0),mb_strtolower((string)$a['public_name'], 'UTF-8'),(int)$a['product_id']] <=> [(int)
        ($b['sort_order'] ?? 0)
        ,
mb_strtolower((string)$b['public_name'], 'UTF-8'),(int)$b['product_id']];
    });
    $filteredProductCount = count($products);
    $perPage = 12;
    $page = max(1, (int)($_GET['strana'] ?? 1));
    $totalPages = max(1, (int)ceil($filteredProductCount / $perPage));
    $page = min($page, $totalPages);
    $products = array_slice($products, ($page - 1) * $perPage, $perPage);
    $topCategories = [];
    foreach ($categoryMenu as $categoryItem) {
        $path = (string)($categoryItem['category_path'] ?? '');
        if ($path === '' || str_contains($path, ' > ')) {
            continue;
        }$count = 0;
        foreach ($allProducts as $product) {
            foreach ($product['categories'] as $productPath) {
                if ($productPath === $path || str_starts_with($productPath, $path.' > ')) {
                    $count++;
                    break;
                }
            }
        }if ($count > 0) {
            $topCategories[] = ['path' => $path,'name' => (string)($categoryItem['display_name'] ?? $categoryItem['menu_label'
    ] ?? $path)
    ,
'count' => $count];
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
