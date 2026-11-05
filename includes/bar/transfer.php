<?php

declare(strict_types=1);

require_once __DIR__ . '/catalog_transfer.php';
require_once __DIR__ . '/transfer_assets.php';
require_once __DIR__ . '/access.php';
require_once dirname(__DIR__) . '/password_security.php';

function barTransferHash(array $bundle): string
{
    unset($bundle['sha256']);
    return hash('sha256', json_encode($bundle, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function barPrepareLucka(array $user): array
{
    $hash = $user['heslo_hash'] ?? '';
    if (($user['jmeno'] ?? null) !== 'lucka' || ($user['role'] ?? null) !== 'prodejce'
        || (int)($user['schvalen'] ?? 0) !== 1 || !is_string($hash) || strlen($hash) > 100 || !trainer_password_is_modern_hash($hash)) {
        throw new InvalidArgumentException('Zdroj neobsahuje jednoznačný schválený účet lucka s podporovaným hashem hesla.');
    }
    return ['source_id' => barSourceInteger($user['id'] ?? null, 1, 2147483647),
        'jmeno' => 'lucka', 'email' => 'info1@kovopraha.cz', 'heslo_hash' => $hash];
}

/** A dedicated connection is used only for a consistent read-only source snapshot. */
function barReadLegacyTransferSource(PDO $source): array
{
    $mysql = $source->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    if ($mysql) {
        $source->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $source->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
    } else {
        $source->beginTransaction();
    }
    try {
        $data = ['categories' => $source->query('SELECT * FROM jidlo_kategorie ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
            'products' => $source->query('SELECT * FROM jidlo_produkty ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
            'recipes' => $source->query('SELECT produkt_id,slozka_id,mnozstvi FROM jidlo_produkty_slozeni ORDER BY produkt_id,slozka_id')->fetchAll(PDO::FETCH_ASSOC)];
        $query = $source->prepare('SELECT id,jmeno,heslo_hash,role,schvalen FROM bar_uzivatele WHERE jmeno=?');
        $query->execute(['lucka']);
        $users = $query->fetchAll(PDO::FETCH_ASSOC);
        if (count($users) !== 1) {
            throw new InvalidArgumentException('Zdrojový účet lucka není jednoznačný.');
        }
        $data['lucka'] = $users[0];
        $source->commit();
        return $data;
    } catch (Throwable $error) {
        if ($source->inTransaction()) {
            $source->rollBack();
        }
        throw $error;
    }
}

function barBuildTransferBundle(array $source, string $imagesDirectory): array
{
    $catalog = barPrepareSourceCatalog($source);
    if ($catalog['products'] === []) {
        throw new InvalidArgumentException('Prázdný katalog nelze převést.');
    }
    $images = [];
    foreach ($catalog['required_images'] as $name) {
        $image = barReadImage(barSourceImagePath($imagesDirectory, $name));
        $images[] = ['name' => $name] + $image['meta'];
    }
    $bundle = ['version' => 1, 'catalog' => $catalog, 'lucka' => barPrepareLucka($source['lucka'] ?? []), 'images' => $images];
    $bundle['sha256'] = barTransferHash($bundle);
    return $bundle;
}

function barTransferRequireFields(mixed $row, array $fields): void
{
    if (!is_array($row) || array_diff($fields, array_keys($row)) !== []) {
        throw new InvalidArgumentException('Převodní balíček nemá všechna povinná pole.');
    }
}

/** Re-run source validation instead of trusting a hand-edited normalized JSON document. */
function barValidateTransferBundle(array $bundle, string $imagesDirectory): array
{
    if (($bundle['version'] ?? null) !== 1 || !is_array($bundle['catalog'] ?? null) || !is_array($bundle['lucka'] ?? null)
        || !is_array($bundle['images'] ?? null) || !is_string($bundle['sha256'] ?? null)) {
        throw new InvalidArgumentException('Neplatný převodní balíček.');
    }
    $source = ['categories' => [], 'products' => [], 'recipes' => [], 'lucka' => [
        'id' => $bundle['lucka']['source_id'] ?? null, 'jmeno' => $bundle['lucka']['jmeno'] ?? null,
        'heslo_hash' => $bundle['lucka']['heslo_hash'] ?? null, 'role' => 'prodejce', 'schvalen' => 1,
    ]];
    foreach (['categories', 'products', 'recipes'] as $key) {
        if (!is_array($bundle['catalog'][$key] ?? null) || !array_is_list($bundle['catalog'][$key])) {
            throw new InvalidArgumentException('Neplatná část katalogu.');
        }
    }
    foreach ($bundle['catalog']['categories'] as $row) {
        barTransferRequireFields($row, ['source_id', 'name', 'sort_order', 'color_index']);
        $source['categories'][] = ['id' => $row['source_id'], 'nazev' => $row['name'], 'poradi' => $row['sort_order'], 'barva' => $row['color_index']];
    }
    foreach ($bundle['catalog']['products'] as $row) {
        barTransferRequireFields($row, ['source_id', 'category_source_id', 'name', 'description', 'price_minor', 'purchase_minor',
            'image_name', 'sort_order', 'stock_mode', 'minimum_stock', 'package_name', 'package_yield', 'on_terminal', 'sold_out', 'favorite', 'archived']);
        if (!is_int($row['price_minor'] ?? null) || (($row['purchase_minor'] ?? null) !== null && !is_int($row['purchase_minor']))) {
            throw new InvalidArgumentException('Neplatná cena v balíčku.');
        }
        $source['products'][] = ['id' => $row['source_id'], 'kategorie_id' => $row['category_source_id'], 'nazev' => $row['name'],
            'popis' => $row['description'], 'cena' => barMinorDecimal($row['price_minor']),
            'nakupni_cena' => $row['purchase_minor'] === null ? null : barMinorDecimal($row['purchase_minor']),
            'obrazek' => $row['image_name'], 'poradi' => $row['sort_order'], 'skladovy_rezim' => $row['stock_mode'],
            'minimalni_zasoba' => $row['minimum_stock'], 'baleni_nazev' => $row['package_name'], 'baleni_vytez' => $row['package_yield'],
            'na_terminalu' => $row['on_terminal'], 'vyprodano' => $row['sold_out'], 'oblibeny' => $row['favorite'], 'archivovan' => $row['archived']];
    }
    foreach ($bundle['catalog']['recipes'] as $row) {
        barTransferRequireFields($row, ['product_source_id', 'ingredient_source_id', 'quantity']);
        $source['recipes'][] = ['produkt_id' => $row['product_source_id'], 'slozka_id' => $row['ingredient_source_id'], 'mnozstvi' => $row['quantity']];
    }
    $canonical = barBuildTransferBundle($source, $imagesDirectory);
    if ($canonical !== $bundle) {
        throw new InvalidArgumentException('Obsah, fotografie nebo otisk balíčku nesouhlasí. Vytvořte nový balíček.');
    }
    return $canonical;
}

/** All catalogue rows, the staff-only account, provenance and audit commit together. */
function barImportTransfer(PDO $pdo, array $bundle, string $imagesDirectory): array
{
    barActor($pdo, 'transfer');
    $bundle = barValidateTransferBundle($bundle, $imagesDirectory);
    $pdo->beginTransaction();
    try {
        $suffix = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $state = $pdo->query('SELECT import_run_id FROM kis_bar_state WHERE id=1' . $suffix)->fetch(PDO::FETCH_ASSOC);
        if (!$state) {
            throw new RuntimeException('Chybí převodní zámek.');
        }
        $actor = barActor($pdo, 'transfer', true);
        $query = $pdo->prepare('SELECT id,summary_json FROM kis_bar_import_runs WHERE bundle_sha256=?' . $suffix);
        $query->execute([$bundle['sha256']]);
        $old = $query->fetch(PDO::FETCH_ASSOC);
        if ($old) {
            $result = json_decode($old['summary_json'], true, 16, JSON_THROW_ON_ERROR);
            $pdo->commit();
            return $result + ['repeated' => true];
        }
        foreach (['kis_bar_categories', 'kis_bar_products', 'kis_bar_recipes', 'kis_bar_assets', 'kis_bar_import_runs'] as $table) {
            if ((int)$pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn() !== 0) {
                throw new DomainException('Cílový katalog už obsahuje data. Převod je nikdy nepřepíše.');
            }
        }
        $collision = $pdo->prepare('SELECT id FROM treneri WHERE LOWER(TRIM(jmeno))=? OR LOWER(TRIM(email))=?' . $suffix);
        $collision->execute(['lucka', 'info1@kovopraha.cz']);
        if ($collision->fetchColumn()) {
            throw new DomainException('Jméno nebo technický e-mail již používá pracovní účet.');
        }
        $collision = $pdo->prepare('SELECT id FROM verejni_uzivatele WHERE LOWER(TRIM(email))=?' . $suffix);
        $collision->execute(['info1@kovopraha.cz']);
        if ($collision->fetchColumn()) {
            throw new DomainException('Technický e-mail již používá zákaznický účet.');
        }
        $categories = [];
        $insert = $pdo->prepare('INSERT INTO kis_bar_categories(source_id,name,sort_order,color_index) VALUES(?,?,?,?)');
        foreach ($bundle['catalog']['categories'] as $row) {
            $insert->execute(array_values($row));
            $categories[$row['source_id']] = (int)$pdo->lastInsertId();
        }
        $products = [];
        $columns = ['source_id', 'category_id', 'name', 'description', 'price_minor', 'purchase_minor', 'image_name', 'sort_order',
            'stock_mode', 'stock_stall', 'stock_office', 'minimum_stock', 'package_name', 'package_yield', 'on_terminal', 'sold_out', 'favorite', 'archived'];
        $insert = $pdo->prepare('INSERT INTO kis_bar_products(' . implode(',', $columns) . ') VALUES(' . implode(',', array_fill(0, count($columns), '?')) . ')');
        foreach ($bundle['catalog']['products'] as $row) {
            $row['category_id'] = $categories[$row['category_source_id']];
            $insert->execute(array_map(static fn (string $key): mixed => $row[$key], $columns));
            $products[$row['source_id']] = (int)$pdo->lastInsertId();
        }
        $insert = $pdo->prepare('INSERT INTO kis_bar_recipes(product_id,ingredient_id,quantity) VALUES(?,?,?)');
        foreach ($bundle['catalog']['recipes'] as $row) {
            $insert->execute([$products[$row['product_source_id']], $products[$row['ingredient_source_id']], $row['quantity']]);
        }
        $insert = $pdo->prepare('INSERT INTO kis_bar_assets(source_name,storage_key,sha256_hex,mime_type,byte_size,width_px,height_px) VALUES(?,?,?,?,?,?,?)');
        foreach ($bundle['images'] as $row) {
            $image = barReadImage(barSourceImagePath($imagesDirectory, $row['name']));
            if ($image['meta'] !== array_diff_key($row, ['name' => true])) {
                throw new DomainException('Fotografie se během převodu změnila.');
            }
            $key = barStoreTransferImage($image);
            $insert->execute([$row['name'], $key, $row['sha256'], $row['mime_type'], $row['byte_size'], $row['width_px'], $row['height_px']]);
        }
        $pdo->prepare("INSERT INTO treneri(jmeno,email,heslo,role,aktivni,session_version,technical_email) VALUES('lucka','info1@kovopraha.cz',?,'trener',1,1,1)")
            ->execute([$bundle['lucka']['heslo_hash']]);
        $trainerId = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO staff_user_positions(trainer_id,position_code,is_default,assigned_by_trainer_id) VALUES(?,'bar_operator',1,?)")
            ->execute([$trainerId, $actor['id']]);
        $pdo->prepare('INSERT INTO staff_position_assignment_events(trainer_id,actor_trainer_id,action,before_json,after_json,reason) VALUES(?,?,?,?,?,?)')
            ->execute([$trainerId, $actor['id'], 'bar_import_lucka', '{}', '{"positions":["bar_operator"],"technical_email":true}', 'Převod jediného schváleného barového účtu.']);
        $result = ['categories' => count($categories), 'products' => count($products), 'recipes' => count($bundle['catalog']['recipes']),
            'images' => count($bundle['images']), 'trainer_id' => $trainerId];
        $pdo->prepare('INSERT INTO kis_bar_import_runs(bundle_sha256,trainer_id,summary_json,created_by) VALUES(?,?,?,?)')
            ->execute([$bundle['sha256'], $trainerId, json_encode($result, JSON_THROW_ON_ERROR), $actor['id']]);
        $runId = (int)$pdo->lastInsertId();
        $pdo->prepare('UPDATE kis_bar_state SET import_run_id=? WHERE id=1')->execute([$runId]);
        barEvent($pdo, $actor['id'], 'import', $runId, 'catalog_and_lucka_imported', $result);
        $pdo->commit();
        return $result + ['repeated' => false];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
