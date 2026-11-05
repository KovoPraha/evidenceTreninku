<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bar/recovery.php';
require_once dirname(__DIR__, 2) . '/includes/bar/recovery_assets.php';
require_once dirname(__DIR__, 2) . '/includes/bar/photo_management.php';
require_once dirname(__DIR__, 2) . '/includes/bar/category_management.php';
require_once dirname(__DIR__, 2) . '/includes/bar/invoices.php';
require_once dirname(__DIR__, 2) . '/includes/bar/stock.php';
require_once dirname(__DIR__, 2) . '/includes/bar/accounting.php';

/** Only the isolated test harness calls this seed. It never reads config.php or contacts a payment provider. */
function barAcceptanceSeed(PDO $pdo, string $workspace): array
{
    if ((int)$pdo->query('SELECT COUNT(*) FROM kis_bar_products')->fetchColumn() !== 0) {
        throw new RuntimeException('Acceptance seed requires an empty synthetic BAR catalogue.');
    }
    $login = static function (int $id, string $position): void {
        $_SESSION = ['trener_id' => $id, 'trener_session_version' => 1, 'staff_active_position' => $position];
    };
    $token = static fn (): string => bin2hex(random_bytes(32));
    foreach ([97001 => ['System', 'system_admin'], 97002 => ['Operator', 'bar_operator'], 97003 => ['Administrator', 'bar_admin']] as $id => [$name, $position]) {
        $pdo->prepare('INSERT INTO treneri(id,jmeno,email,heslo,role,aktivni,session_version) VALUES(?,?,?,?,?,1,1)')
            ->execute([$id, 'LOCALHOST Restore ' . $name, 'bar-restore-' . $id . '@example.invalid', password_hash('LOCALHOST-Only-Not-A-Real-Account!', PASSWORD_DEFAULT), 'trener']);
        $pdo->prepare('INSERT INTO staff_user_positions(trainer_id,position_code,is_default) VALUES(?,?,1)')->execute([$id, $position]);
    }
    $login(97001, 'system_admin');
    barSetConsumptionPermission($pdo, 97002, true, 'LOCALHOST explicit restore grant');
    $login(97003, 'bar_admin');
    $category = barSaveCategory($pdo, 0, 0, ['name' => 'LOCALHOST Restore drinks', 'sort_order' => 1, 'color_index' => 2], 'LOCALHOST restore category');
    $product = barSaveProduct($pdo, 0, 0, ['name' => 'LOCALHOST Restore Tea', 'description' => 'LOCALHOST synthetic item', 'category_id' => $category,
        'price' => '35.00', 'purchase_price' => '11.01', 'stock_mode' => 'sledovat', 'on_terminal' => 1], 'LOCALHOST restore product');
    $recipe = barSaveProduct($pdo, 0, 0, ['name' => 'LOCALHOST Restore Double Tea', 'category_id' => $category,
        'price' => '70.00', 'stock_mode' => 'sledovat', 'on_terminal' => 1], 'LOCALHOST restore recipe');
    barSetRecipe($pdo, $recipe, 1, [['id' => $product, 'quantity' => 2]], 'LOCALHOST two tea units');
    $version = static function () use ($pdo, $product): int {
        return (int)$pdo->query('SELECT lock_version FROM kis_bar_products WHERE id=' . $product)->fetchColumn();
    };
    foreach ([50, 150] as $shade) {
        $image = imagecreatetruecolor(24, 16);
        imagefill($image, 0, 0, imagecolorallocate($image, $shade, 200, 100));
        $imagePath = $workspace . '/source-' . $shade . '.png';
        imagepng($image, $imagePath);
        imagedestroy($image);
        barSetProductImage($pdo, $product, $version(), $imagePath, 'LOCALHOST retained photograph ' . $shade);
    }
    $invoice = barUploadInvoice($pdo, dirname(__DIR__) . '/fixtures/bar-localhost-invoice.pdf', $token(), false)['invoice_id'];
    $draft = ['supplier' => 'LOCALHOST Restore Supplier', 'supplier_id' => '12345678', 'number' => 'LOCAL-RESTORE-001', 'date' => '2000-01-01', 'currency' => 'CZK',
        'net_minor' => 10000, 'vat_minor' => 2100, 'gross_minor' => 12100, 'rounding_minor' => 0, 'payable_minor' => 12100, 'verified' => 1,
        'rows' => [['code' => 'TEA001', 'description' => 'LOCALHOST Tea', 'quantity' => 2, 'unit' => 'bal', 'net_minor' => 10000,
            'gross_minor' => 12100, 'kind' => 'stock', 'product_id' => $product, 'multiplier' => 3, 'confirmed' => 1, 'remember' => 1,
            'cost_choice' => 'apply', 'purchase_version' => 1]]];
    barSaveInvoice($pdo, $invoice, 1, $draft, 'LOCALHOST verified supplier invoice', $token());
    barReceiveInvoice($pdo, $invoice, 2, '2000-01-01', true, 'LOCALHOST physical delivery', $token());
    barStockOperation($pdo, $product, $version(), 'to_stall', 4, $token(), 'LOCALHOST four units moved');
    barSetCupDeposit($pdo, 2000, 1, 'LOCALHOST deposit setup', $token());
    $offer = barSaveOffer($pdo, 0, 0, ['title' => 'LOCALHOST Restore menu', 'layout' => 'photos', 'products' => [$product, $recipe]], 'LOCALHOST menu draft', $token());
    barPublishOffer($pdo, $offer['offer_id'], 1, 'LOCALHOST published menu', $token());
    $login(97002, 'bar_operator');
    $box = barOpenCashbox($pdo, 100000, 'LOCALHOST restore cash drawer', $token())['cashbox_id'];
    $cart = [['product_id' => $product, 'quantity' => 1]];
    $quote = barQuote($pdo, $cart, 0, 2);
    $saleRequest = [$box, $cart, 0, 2, $quote['fingerprint'], 'cash', $token()];
    $sale = barSubmitSale($pdo, ...$saleRequest)['sale_id'];
    barReturnCups($pdo, $sale, $box, 1, 'LOCALHOST one cup returned', $token());
    barVoidSale($pdo, $sale, $box, '', 'LOCALHOST cash sale reversed', $token());
    $recipeCart = [['product_id' => $recipe, 'quantity' => 1]];
    $quote = barQuote($pdo, $recipeCart);
    $pending = barSubmitSale($pdo, $box, $recipeCart, 0, 0, $quote['fingerprint'], 'qr', $token())['sale_id'];
    $quote = barQuote($pdo, $cart, 0, 0, true);
    $consumption = barSubmitConsumption($pdo, $cart, 'stall', $quote['fingerprint'], 'LOCALHOST restore own consumption', $token())['sale_id'];
    barVoidSale($pdo, $consumption, null, '', 'LOCALHOST consumption returned', $token());
    $pdo->exec('UPDATE treneri SET aktivni=0 WHERE id=97002');
    $login(97003, 'bar_admin');
    $boxVersion = static fn (): int => (int)$pdo->query('SELECT lock_version FROM kis_bar_cashboxes WHERE id=' . $box)->fetchColumn();
    barRecoverCashbox($pdo, $box, $boxVersion(), 'cancel', $pending, 0, '', true, 'LOCALHOST returned orphan reservation', $token());
    barRecoverCashbox($pdo, $box, $boxVersion(), 'close', 0, 100000, '', true, 'LOCALHOST counted orphan cash', $token());
    $pdo->exec('UPDATE treneri SET aktivni=1 WHERE id=97002');
    $selection = array_map(static fn (array $row): string => $row['sha256'], barAccountingSources($pdo));
    $export = barCreateAccountingExport($pdo, $selection, 'LOCALHOST reviewed restored evidence', $token());
    return ['product_id' => $product, 'recipe_id' => $recipe, 'invoice_id' => $invoice, 'cashbox_id' => $box, 'sale_id' => $sale,
        'export_id' => $export['export_id'], 'export_sha256' => $export['sha256'], 'sale_request' => $saleRequest,
        'private_manifest' => barPrivateFileManifest($pdo)];
}

function barAcceptanceDatabaseSnapshot(PDO $pdo): array
{
    $tables = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
        ? $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN)
        : $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    $snapshot = [];
    foreach ($tables as $table) {
        if (!str_starts_with($table, 'kis_bar_') || !preg_match('/^[a-z_]+$/D', $table)) {
            continue;
        }
        $rows = [];
        foreach ($pdo->query('SELECT * FROM ' . $table)->fetchAll(PDO::FETCH_ASSOC) as $row) {
            ksort($row, SORT_STRING);
            $rows[] = json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }
        sort($rows, SORT_STRING);
        $snapshot[$table] = ['rows' => count($rows), 'sha256' => hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR))];
    }
    ksort($snapshot, SORT_STRING);
    return $snapshot;
}

/** Test-only copy between directories created by the harness. Never accepts a request path. */
function barAcceptanceCopyPrivateFiles(array $manifest, string $destination, ?string $bundleSource = null): void
{
    if (file_exists($destination)) {
        throw new RuntimeException('Test bundle destination must be new.');
    }
    mkdir($destination, 0700, true);
    foreach ($manifest['files'] as $file) {
        if (!preg_match('~^private://(bar-images/[a-f0-9]{64}\.(?:png|jpg|webp)|bar-invoices/[a-f0-9]{64}\.pdf)$~D', $file['key'])) {
            throw new RuntimeException('Unsafe synthetic bundle key.');
        }
        $relative = substr($file['key'], strlen('private://'));
        $source = $bundleSource === null ? barPrivateManifestFilePath($file) : $bundleSource . '/' . $relative;
        $target = $destination . '/' . $relative;
        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0700, true);
        }
        if (is_link($source) || !is_file($source) || hash_file('sha256', $source) !== $file['sha256'] || !copy($source, $target)) {
            throw new RuntimeException('Synthetic private bundle copy failed.');
        }
        chmod($target, 0600);
    }
}
