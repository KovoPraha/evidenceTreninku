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
        foreach ($items as $item) {
            $path = (string)($item['category_path'] ?? '');
            $name = (string)($item['display_name'] ?? $path);
            if ($path === '' || $name === '') {
                continue;
            }
            $depth = max(0, (int)($item['depth'] ?? 0));
            $active = $activeCategory !== null && $activeCategory === $path;
            $html .= '<a class="app-shop-nav-link' . ($active ? ' active' : '') . '" href="eshop.php?kategorie=' . rawurlencode($path) . '"' . ($active ? ' aria-current="page"' : '') . '>';
            if ($depth > 0) {
                $html .= '<span class="app-shop-nav-depth" aria-hidden="true">↳</span>';
            }
            $html .= shopPublicNavigationH($name) . '</a>';
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
