<?php

declare(strict_types=1);

require_once __DIR__ . '/transfer_package.php';

/** Exact initial-state comparison; hashes and source rows never appear in the returned report. */
function barVerifyInitialTransfer(PDO $pdo, array $bundle, string $imagesDirectory): array
{
    $bundle = barValidateTransferBundle($bundle, $imagesDirectory);
    $source = ['categories' => [], 'products' => [], 'recipes' => []];
    $categories = [];
    foreach ($pdo->query('SELECT * FROM kis_bar_categories ORDER BY source_id')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $categories[(int)$row['id']] = (int)$row['source_id'];
        $source['categories'][] = ['id' => $row['source_id'], 'nazev' => $row['name'], 'poradi' => $row['sort_order'], 'barva' => $row['color_index']];
    }
    $products = [];
    foreach ($pdo->query('SELECT * FROM kis_bar_products ORDER BY source_id')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ((int)$row['stock_stall'] !== 0 || (int)$row['stock_office'] !== 0 || !isset($categories[(int)$row['category_id']])) {
            throw new RuntimeException('Initial stock or category mapping differs.');
        }
        $products[(int)$row['id']] = (int)$row['source_id'];
        $source['products'][] = ['id' => $row['source_id'], 'kategorie_id' => $categories[(int)$row['category_id']],
            'nazev' => $row['name'], 'popis' => $row['description'], 'cena' => barMinorDecimal((int)$row['price_minor']),
            'nakupni_cena' => $row['purchase_minor'] === null ? null : barMinorDecimal((int)$row['purchase_minor']),
            'obrazek' => $row['image_name'], 'poradi' => $row['sort_order'], 'skladovy_rezim' => $row['stock_mode'],
            'minimalni_zasoba' => $row['minimum_stock'], 'baleni_nazev' => $row['package_name'], 'baleni_vytez' => $row['package_yield'],
            'na_terminalu' => $row['on_terminal'], 'vyprodano' => $row['sold_out'], 'oblibeny' => $row['favorite'], 'archivovan' => $row['archived']];
    }
    foreach ($pdo->query('SELECT * FROM kis_bar_recipes ORDER BY product_id,ingredient_id')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (!isset($products[(int)$row['product_id']], $products[(int)$row['ingredient_id']])) {
            throw new RuntimeException('Recipe mapping differs.');
        }
        $source['recipes'][] = ['produkt_id' => $products[(int)$row['product_id']],
            'slozka_id' => $products[(int)$row['ingredient_id']], 'mnozstvi' => $row['quantity']];
    }
    if (barPrepareSourceCatalog($source) !== $bundle['catalog']) {
        throw new RuntimeException('Transferred catalogue differs.');
    }
    $assets = $pdo->query('SELECT * FROM kis_bar_assets ORDER BY source_name')->fetchAll(PDO::FETCH_ASSOC);
    $manifest = [];
    foreach ($assets as $asset) {
        $image = barReadImage(barTransferAssetPath($asset));
        $meta = ['sha256' => $asset['sha256_hex'], 'mime_type' => $asset['mime_type'], 'byte_size' => (int)$asset['byte_size'],
            'width_px' => (int)$asset['width_px'], 'height_px' => (int)$asset['height_px']];
        if ($image['meta'] !== $meta) {
            throw new RuntimeException('Stored image metadata differs.');
        }
        $manifest[] = ['name' => $asset['source_name']] + $meta;
    }
    if ($manifest !== $bundle['images']) {
        throw new RuntimeException('Transferred image manifest differs.');
    }
    $query = $pdo->prepare('SELECT * FROM treneri WHERE LOWER(TRIM(jmeno))=? OR LOWER(TRIM(email))=?');
    $query->execute(['lucka', 'info1@kovopraha.cz']);
    $users = $query->fetchAll(PDO::FETCH_ASSOC);
    if (count($users) !== 1 || $users[0]['jmeno'] !== 'lucka' || $users[0]['email'] !== 'info1@kovopraha.cz'
        || (int)$users[0]['technical_email'] !== 1 || (int)$users[0]['aktivni'] !== 1 || $users[0]['role'] !== 'trener'
        || !hash_equals($bundle['lucka']['heslo_hash'], (string)$users[0]['heslo'])) {
        throw new RuntimeException('Transferred account differs.');
    }
    $id = (int)$users[0]['id'];
    $query = $pdo->prepare('SELECT position_code,is_default FROM staff_user_positions WHERE trainer_id=? ORDER BY position_code');
    $query->execute([$id]);
    $positions = $query->fetchAll(PDO::FETCH_ASSOC);
    if (count($positions) !== 1 || $positions[0]['position_code'] !== 'bar_operator' || (int)$positions[0]['is_default'] !== 1) {
        throw new RuntimeException('Transferred account positions differ.');
    }
    foreach (['SELECT COUNT(*) FROM kis_bar_permissions WHERE trainer_id=?',
        'SELECT COUNT(*) FROM staff_superadmins WHERE trainer_id=?',
        'SELECT COUNT(*) FROM verejni_uzivatele WHERE trener_id=? OR LOWER(TRIM(email))=\'info1@kovopraha.cz\''] as $sql) {
        $query = $pdo->prepare($sql);
        $query->execute([$id]);
        if ((int)$query->fetchColumn() !== 0) {
            throw new RuntimeException('Unexpected transferred account permissions or customer link.');
        }
    }
    $history = 0;
    foreach (['sales', 'sale_lines', 'sale_components', 'sale_payments', 'cashboxes', 'cash_movements', 'cup_returns', 'voids',
        'stock_operations', 'stock_movements', 'invoices', 'invoice_receipts', 'invoice_receipt_items', 'supplier_mappings',
        'offers', 'offer_publications', 'accounting_exports', 'accounting_export_items', 'requests'] as $suffix) {
        $history += (int)$pdo->query('SELECT COUNT(*) FROM kis_bar_' . $suffix)->fetchColumn();
    }
    if ($history !== 0) {
        throw new RuntimeException('Initial operational history is not empty.');
    }
    $query = $pdo->prepare('SELECT COUNT(*) FROM kis_bar_import_runs WHERE bundle_sha256=? AND trainer_id=?');
    $query->execute([$bundle['sha256'], $id]);
    if ((int)$query->fetchColumn() !== 1 || (int)$pdo->query('SELECT COUNT(*) FROM kis_bar_import_runs')->fetchColumn() !== 1) {
        throw new RuntimeException('Transfer provenance differs.');
    }
    return ['ok' => true, 'catalog_exact' => true, 'images_exact' => true, 'password_preserved' => true,
        'operator_only' => true, 'technical_email' => true, 'zero_stock' => true, 'zero_operational_history' => true,
        'trainer_id' => $id, 'products' => count($products), 'categories' => count($categories),
        'recipes' => count($source['recipes']), 'images' => count($assets)];
}
