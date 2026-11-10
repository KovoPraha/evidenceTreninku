<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__, 2) . '/includes/bar/menu_view.php';
$directory = dirname(__DIR__, 2) . '/output/bar-display-preview';
if (!is_dir($directory)) {
    mkdir($directory, 0700, true);
}
$products = [];
foreach (range(1, 45) as $id) {
    $products[] = ['id' => $id, 'name' => 'LOCALHOST Produkt ' . $id,
        'description' => 'Syntetický popis pro ověření původního rozvržení obrazovky.',
        'price_minor' => 3500 + $id * 100, 'sold_out' => $id === 7 ? 1 : 0,
        'category' => 'Kategorie ' . (int)ceil($id / 9), 'color_index' => (int)floor(($id - 1) / 9), 'image_id' => null];
}
$menu = ['offer' => ['title' => 'LOCALHOST — syntetická nabídka', 'layout' => 'photos'], 'products' => $products];
foreach (['text', 'photos'] as $view) {
    file_put_contents($directory . '/' . $view . '.html', str_replace('../assets/', '/assets/', barMenuHtml($menu, false, $view)));
}
echo "Rendered two synthetic screens, 45 products, no database or production writes.\n";
