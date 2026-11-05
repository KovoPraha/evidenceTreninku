<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/bar/web.php';
require_once dirname(__DIR__) . '/includes/bar/management.php';
if (!isset($pdo) || !$pdo instanceof PDO) {
    throw new RuntimeException('Databázové připojení není dostupné.');
}
barRequireWebActor($pdo, 'catalog_manage');
$error = '';
$id = (int)filter_var($_POST['id'] ?? $_GET['id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        $id = barSourceInteger($_POST['id'] ?? null, 0, 2147483647);
        if (!is_string($_POST['csrf_token'] ?? null) || !csrf_verify($_POST['csrf_token'])) {
            throw new InvalidArgumentException('Formulář vypršel. Obnovte stránku.');
        }
        $version = barSourceInteger($_POST['version'] ?? null, 0, 2147483647);
        $reason = is_string($_POST['reason'] ?? null) ? $_POST['reason'] : '';
        if (($_POST['action'] ?? null) === 'save') {
            $input = $_POST;
            foreach (['on_terminal', 'sold_out', 'favorite', 'archived'] as $key) {
                $input[$key] = isset($_POST[$key]) ? 1 : 0;
            }
            $id = barSaveProduct($pdo, $id, $version, $input, $reason);
        } elseif (($_POST['action'] ?? null) === 'recipe' && is_array($_POST['ingredients'] ?? null)) {
            $ingredients = [];
            foreach ($_POST['ingredients'] as $ingredient => $quantity) {
                $quantity = barSourceInteger($quantity);
                if ($quantity > 0) {
                    $ingredients[] = ['id' => barSourceInteger($ingredient, 1, 2147483647), 'quantity' => $quantity];
                }
            }
            barSetRecipe($pdo, $id, $version, $ingredients, $reason);
        } else {
            throw new InvalidArgumentException('Neplatná operace katalogu.');
        }
        header('Location: ' . appUiUrl('bar/catalog.php?id=' . $id), true, 303);
        exit;
    } catch (DomainException | InvalidArgumentException $exception) {
        http_response_code($exception instanceof BarConflictException ? 409 : 422);
        $error = $exception->getMessage();
    }
}
$editor = null;
if ($id > 0 || isset($_GET['new']) || $error !== '') {
    $query = $pdo->prepare('SELECT * FROM kis_bar_products WHERE id=?');
    $query->execute([$id]);
    $product = $query->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($id > 0 && $product === null) {
        http_response_code(404);
        exit('Produkt nebyl nalezen.');
    }
    $defaults = ['name' => '', 'description' => '', 'category_id' => 0, 'price' => '', 'purchase_price' => '',
        'stock_mode' => 'sledovat', 'minimum_stock' => 0, 'on_terminal' => 1, 'sold_out' => 0, 'favorite' => 0, 'archived' => 0];
    $input = $product === null ? $defaults : array_replace($defaults, $product, ['price' => barMinorDecimal((int)$product['price_minor']),
        'purchase_price' => $product['purchase_minor'] === null ? '' : barMinorDecimal((int)$product['purchase_minor'])]);
    if ($error !== '' && ($_POST['action'] ?? null) === 'save') {
        foreach (array_keys($defaults) as $key) {
            if (is_string($_POST[$key] ?? null)) {
                $input[$key] = $_POST[$key];
            }
        }
        foreach (['on_terminal', 'sold_out', 'favorite', 'archived'] as $key) {
            $input[$key] = isset($_POST[$key]) ? 1 : 0;
        }
    }
    $query = $pdo->prepare('SELECT ingredient_id,quantity FROM kis_bar_recipes WHERE product_id=?');
    $query->execute([$id]);
    $recipe = $query->fetchAll(PDO::FETCH_KEY_PAIR);
    $ingredients = $pdo->query("SELECT p.id,p.name FROM kis_bar_products p WHERE p.archived=0 AND p.stock_mode='sledovat' "
        . 'AND NOT EXISTS(SELECT 1 FROM kis_bar_recipes r WHERE r.product_id=p.id) ORDER BY p.name,p.id')->fetchAll(PDO::FETCH_ASSOC);
    $ingredients = array_values(array_filter($ingredients, static fn (array $row): bool => (int)$row['id'] !== $id));
    $editor = ['product' => $product, 'input' => $input, 'error' => $error, 'recipe' => $recipe, 'ingredients' => $ingredients,
        'categories' => $pdo->query('SELECT id,name FROM kis_bar_categories ORDER BY sort_order,id')->fetchAll(PDO::FETCH_ASSOC)];
}
barRenderCatalog($pdo, barCatalog($pdo, true), true, $editor);
