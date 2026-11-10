<?php

declare(strict_types=1);

require_once __DIR__ . '/web.php';

function barDisplayPrice(int $minor): string
{
    return number_format($minor / 100, $minor % 100 === 0 ? 0 : 2, ',', ' ') . ' Kč';
}

/** Keep the original category splitting, including a category continued in column two. */
function barDisplayColumns(array $categories): array
{
    $target = max(1, (int)ceil(array_sum(array_map(static fn (array $c): int => count($c['produkty']) + 1, $categories)) / 2));
    $columns = [[], []];
    $weight = 0;
    foreach ($categories as $category) {
        $products = array_values($category['produkty']);
        if ($weight < $target) {
            $first = array_splice($products, 0, min(count($products), max(1, $target - $weight - 1)));
            if ($first !== []) {
                $columns[0][] = array_replace($category, ['produkty' => $first]);
                $weight += count($first) + 1;
            }
        }
        if ($products !== []) {
            $columns[1][] = array_replace($category, ['produkty' => $products]);
        }
    }
    return $columns;
}

function barScreenHtml(array $menu, string $view): string
{
    $settings = $menu['display_settings'] ?? [];
    $nadpis = trim((string)($settings['obrazovka_nadpis'] ?? '')) ?: 'KOVO BAR';
    $podtitul = trim((string)($settings['obrazovka_podtitul'] ?? '')) ?: (string)($menu['offer']['title'] ?? '');
    $obnova = max(15, min(600, (int)($settings['obrazovka_obnova'] ?? 60)));
    $vzhled = (string)($settings['obrazovka_vzhled'] ?? 'standard');
    if (!in_array($vzhled, ['standard', 'kompaktni', 'velke_ceny', 'tisk'], true)) {
        $vzhled = 'standard';
    }
    $categories = [];
    $produkty = [];
    foreach ($menu['products'] as $product) {
        if (($settings['obrazovka_vyprodane'] ?? '1') === '0' && (int)$product['sold_out'] === 1) {
            continue;
        }
        $category = (string)$product['category'];
        $categoryKey = (string)($product['category_id'] ?? $category);
        $color = ((int)($product['color_index'] ?? 0) % 6 + 6) % 6;
        $categories[$categoryKey] ??= ['nazev' => $category, 'barva' => $color, 'produkty' => []];
        $row = ['id' => (int)$product['id'], 'nazev' => (string)$product['name'],
            'popis' => (string)$product['description'], 'cena' => (int)$product['price_minor'],
            'vyprodano' => (int)$product['sold_out'] === 1];
        $categories[$categoryKey]['produkty'][] = $row;
        $produkty[] = array_replace($row, ['cena' => barDisplayPrice($row['cena']), 'kategorie' => $category,
            'barva' => $color, 'obrazek' => $product['image_id'] === null ? null : 'menu-image.php?id=' . (int)$product['image_id']]);
    }
    $kategorie = array_values($categories);
    $sloupce = barDisplayColumns($kategorie);
    $json = json_encode($produkty, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    ob_start();
    require __DIR__ . '/views/' . ($view === 'photos' ? 'photos' : 'screen') . '.php';
    return (string)ob_get_clean();
}
