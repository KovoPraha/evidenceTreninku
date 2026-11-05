<?php

declare(strict_types=1);

/** Source DECIMAL values are strings. Never round or truncate a malformed monetary input. */
function barSourceMoney(mixed $value): int
{
    if ((!is_string($value) && !is_int($value)) || !preg_match('/^(0|[1-9][0-9]{0,7})(?:\.([0-9]{1,2}))?$/D', (string)$value, $match)) {
        throw new InvalidArgumentException('Cena musí být nezáporná částka s nejvýše dvěma desetinnými místy.');
    }
    return (int)$match[1] * 100 + (int)str_pad($match[2] ?? '', 2, '0');
}

function barSourceInteger(mixed $value, int $minimum = 0, int $maximum = 1000000): int
{
    if ((!is_string($value) && !is_int($value)) || !preg_match('/^-?(?:0|[1-9][0-9]*)$/D', (string)$value)
        || strlen((string)$value) > 11 || (int)$value < $minimum || (int)$value > $maximum) {
        throw new InvalidArgumentException('Neplatné celé číslo v katalogu.');
    }
    return (int)$value;
}

function barSourceText(mixed $value, int $limit, bool $required = false): string
{
    if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || mb_strlen($value, 'UTF-8') > $limit
        || ($required && trim($value) === '') || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $value)) {
        throw new InvalidArgumentException('Neplatný text v katalogu.');
    }
    return $value;
}

/**
 * Pure preflight shared by the future importer and tests. It never touches a DB or files.
 * Only catalogue configuration crosses this boundary; old quantities and histories cannot.
 */
function barPrepareSourceCatalog(array $source): array
{
    foreach (['categories', 'products', 'recipes'] as $key) {
        if (!isset($source[$key]) || !is_array($source[$key]) || !array_is_list($source[$key]) || count($source[$key]) > 10000) {
            throw new InvalidArgumentException('Chybí seznam katalogu nebo překračuje povolený rozsah.');
        }
    }
    $categories = [];
    foreach ($source['categories'] as $row) {
        if (!is_array($row)) {
            throw new InvalidArgumentException('Neplatná kategorie.');
        }
        $id = barSourceInteger($row['id'] ?? null, 1, 2147483647);
        if (isset($categories[$id])) {
            throw new InvalidArgumentException('Duplicitní ID kategorie.');
        }
        $categories[$id] = [
            'source_id' => $id, 'name' => barSourceText($row['nazev'] ?? null, 255, true),
            'sort_order' => barSourceInteger($row['poradi'] ?? 0, -2147483648, 2147483647),
            'color_index' => barSourceInteger($row['barva'] ?? 0, 0, 2147483647),
        ];
    }
    $products = [];
    $images = [];
    foreach ($source['products'] as $row) {
        if (!is_array($row)) {
            throw new InvalidArgumentException('Neplatný produkt.');
        }
        $id = barSourceInteger($row['id'] ?? null, 1, 2147483647);
        $category = barSourceInteger($row['kategorie_id'] ?? null, 1, 2147483647);
        if (isset($products[$id]) || !isset($categories[$category])) {
            throw new InvalidArgumentException('Duplicitní produkt nebo chybějící kategorie.');
        }
        $image = $row['obrazek'] ?? null;
        if ($image === '') {
            $image = null;
        }
        if ($image !== null) {
            if (!is_string($image) || !preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.-]{0,249}\.(?:jpg|jpeg|png|webp)$/iD', $image)) {
                throw new InvalidArgumentException('Fotografie musí být lokální soubor bez cesty.');
            }
            $images[$image] = $image;
        }
        $mode = $row['skladovy_rezim'] ?? null;
        if (!in_array($mode, ['sledovat', 'nesledovat'], true)) {
            throw new InvalidArgumentException('Neplatný skladový režim.');
        }
        $product = [
            'source_id' => $id, 'category_source_id' => $category,
            'name' => barSourceText($row['nazev'] ?? null, 255, true),
            'description' => barSourceText($row['popis'] ?? '', 65535),
            'price_minor' => barSourceMoney($row['cena'] ?? null),
            'purchase_minor' => isset($row['nakupni_cena']) ? barSourceMoney($row['nakupni_cena']) : null,
            'image_name' => $image, 'sort_order' => barSourceInteger($row['poradi'] ?? 0, -2147483648, 2147483647),
            'stock_mode' => $mode, 'stock_stall' => 0, 'stock_office' => 0,
            'minimum_stock' => barSourceInteger($row['minimalni_zasoba'] ?? 0),
            'package_name' => barSourceText($row['baleni_nazev'] ?? '', 100),
            'package_yield' => barSourceInteger($row['baleni_vytez'] ?? 1, 1),
        ];
        foreach (['on_terminal' => 'na_terminalu', 'sold_out' => 'vyprodano', 'favorite' => 'oblibeny', 'archived' => 'archivovan'] as $target => $field) {
            $product[$target] = barSourceInteger($row[$field] ?? ($field === 'na_terminalu' ? 1 : 0), 0, 1);
        }
        $products[$id] = $product;
    }
    $recipes = [];
    $composites = [];
    foreach ($source['recipes'] as $row) {
        if (!is_array($row)) {
            throw new InvalidArgumentException('Neplatná receptura.');
        }
        $parent = barSourceInteger($row['produkt_id'] ?? null, 1, 2147483647);
        $ingredient = barSourceInteger($row['slozka_id'] ?? null, 1, 2147483647);
        $key = $parent . ':' . $ingredient;
        if ($parent === $ingredient || !isset($products[$parent], $products[$ingredient]) || isset($recipes[$key])
            || $products[$ingredient]['stock_mode'] !== 'sledovat') {
            throw new InvalidArgumentException('Receptura obsahuje chybějící, duplicitní nebo nesledovanou surovinu.');
        }
        $recipes[$key] = ['product_source_id' => $parent, 'ingredient_source_id' => $ingredient, 'quantity' => barSourceInteger($row['mnozstvi'] ?? null, 1)];
        $composites[$parent] = true;
    }
    foreach ($recipes as $recipe) {
        if (isset($composites[$recipe['ingredient_source_id']])) {
            throw new InvalidArgumentException('Vnořená receptura vyžaduje ruční kontrolu.');
        }
    }
    ksort($categories, SORT_NUMERIC);
    ksort($products, SORT_NUMERIC);
    ksort($recipes, SORT_NATURAL);
    sort($images, SORT_STRING);
    $catalog = ['version' => 1, 'categories' => array_values($categories), 'products' => array_values($products),
        'recipes' => array_values($recipes), 'required_images' => $images];
    $catalog['sha256'] = hash('sha256', json_encode($catalog, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    return $catalog;
}
