<?php
declare(strict_types=1);

require_once __DIR__ . '/shop_storefront.php';

function shopPublicNavigationH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** @return list<array<string,mixed>> */
function shopPublicNavigationItems(PDO $pdo): array
{
    return shopStorefrontCategoryMenu($pdo, shopStorefrontCatalog($pdo));
}

/** @param list<array<string,mixed>> $items */
function shopPublicNavigationHtml(array $items, ?string $activeCategory = null, bool $homeActive = false): string
{
    $html = '<nav class="app-shop-nav" aria-label="Navigace e-shopu"><div class="container app-shop-nav-inner">';
    $html .= '<a class="app-shop-home' . ($homeActive ? ' active' : '') . '" href="eshop.php"' . ($homeActive ? ' aria-current="page"' : '') . '><i class="bi bi-shop" aria-hidden="true"></i><span>E-shop – domů</span></a>';
    if ($items !== []) {
        $html .= '<div class="app-shop-nav-categories" aria-label="Kategorie produktů">';
        $groups = [];
        foreach ($items as $item) {
            $path = trim((string)($item['category_path'] ?? ''));
            if ($path === '') continue;
            $root = explode(' > ', $path)[0];
            $groups[$root][] = $item;
        }
        foreach ($groups as $root => $group) {
            $rootItem = null;
            foreach ($group as $item) {
                if ((string)($item['category_path'] ?? '') === $root) $rootItem = $item;
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
                $html .= '<a class="app-shop-nav-link' . ($active ? ' active' : '') . '" href="eshop.php?kategorie=' . rawurlencode($singlePath) . '"' . ($active ? ' aria-current="page"' : '') . '>' . shopPublicNavigationH($singleName) . '</a>';
                continue;
            }
            $dropdownId = 'shop-category-' . substr(hash('sha256', $root), 0, 10);
            $html .= '<div class="dropdown"><button class="dropdown-toggle' . ($active ? ' active' : '') . '" type="button" id="' . $dropdownId . '" data-bs-toggle="dropdown" aria-expanded="false">' . shopPublicNavigationH($rootName) . '</button>';
            $html .= '<ul class="dropdown-menu" aria-labelledby="' . $dropdownId . '">';
            if ($rootItem !== null) {
                $html .= '<li><a class="dropdown-item fw-semibold' . ($activeCategory === $root ? ' active' : '') . '" href="eshop.php?kategorie=' . rawurlencode($root) . '">Vše: ' . shopPublicNavigationH($rootName) . '</a></li>';
            }
            foreach ($group as $item) {
                $path = (string)$item['category_path'];
                if ($path === $root) continue;
                $name = (string)($item['menu_label'] ?? $item['display_name'] ?? $path);
                $itemActive = $activeCategory === $path;
                $html .= '<li><a class="dropdown-item' . ($itemActive ? ' active' : '') . '" href="eshop.php?kategorie=' . rawurlencode($path) . '"' . ($itemActive ? ' aria-current="page"' : '') . '>' . shopPublicNavigationH($name) . '</a></li>';
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
