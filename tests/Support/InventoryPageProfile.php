<?php

declare(strict_types=1);

require_once __DIR__ . '/FamilyOverviewPageFixture.php';

$worker = ($argv[1] ?? '') === '--worker';
$extended = in_array('--extended', $argv, true);
$schema = $worker ? ($argv[2] ?? '') : 'w91_inventory_' . bin2hex(random_bytes(8));
if (!preg_match('/^w91_inventory_[a-f0-9]{16}$/D', $schema)) {
    throw new RuntimeException('Invalid owned inventory schema.');
}
if ($worker) {
    define('APP_BASE_URL', 'http://localhost');
    $mode = $argv[3];
    $role = $argv[4];
    $query = $argv[5];
    $instrument = $argv[6] === 'profile';
    $pdo = w72Connection($schema, $instrument);
    $root = dirname(__DIR__, 2);
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['HTTP_HOST'] = 'localhost';
    $_SERVER['SERVER_NAME'] = 'localhost';
    $_SERVER['SCRIPT_NAME'] = '/eshop_inventory_admin.php';
    require_once $root . '/includes/session_security.php';
    session_id('w91' . bin2hex(random_bytes(8)));
    app_session_start();
    $_SESSION = ['trener_id' => 7, 'trener_session_version' => 1, 'staff_positions' => ['catalog_manager', 'finance_manager'],
        'staff_active_position' => $role, 'csrf_token' => str_repeat('a', 64)];
    require_once $root . '/includes/funkce.php';
    require_once $root . '/includes/ui_shell.php';
    require_once $root . '/includes/auth_session.php';
    if (!auth_session_validate($pdo)) {
        throw new RuntimeException('Synthetic staff session invalid.');
    }
    $_GET = ['q' => $query];
    $_POST = [];
    $source = (string) file_get_contents($mode === 'baseline' ? __DIR__ . '/InventoryPageBaseline.txt' : $root . '/eshop_inventory_admin.php');
    if (substr_count($source, "require_once __DIR__.'/includes/init.php';") !== 1
        || substr_count($source, "require __DIR__.'/hlavicka.php';") !== 1) {
        throw new RuntimeException('Inventory bootstrap/header contract changed.');
    }
    // Actual controller and full page body; only authenticated PDO bootstrap and unchanged shared header are excluded.
    $source = str_replace(["require_once __DIR__.'/includes/init.php';", "require __DIR__.'/hlavicka.php';"], '', $source);
    $source = str_replace('__DIR__', var_export($root, true), $source);
    if ($instrument) {
        $pdo->profile = [];
        $pdo->profiling = true;
    }
    set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
        throw new ErrorException($message, 0, $severity, $file, $line);
    });
    memory_reset_peak_usage();
    $memory = memory_get_usage();
    $start = hrtime(true);
    ob_start();
    eval(substr($source, 5));
    $html = (string) ob_get_clean();
    $elapsed = (hrtime(true) - $start) / 1e6;
    $peak = memory_get_peak_usage() - $memory;
    restore_error_handler();
    if ($error !== null || $finance !== ($role === 'finance_manager')) {
        throw new RuntimeException('Inventory controller/role failed.');
    }
    $html = str_replace(app_csp_nonce(), 'SYNTHETIC_NONCE', $html);
    $html = preg_replace('/stock-ui:[a-f0-9]{36}/', 'stock-ui:SYNTHETIC_REQUEST', $html);
    $balanceQueries = $instrument ? array_filter($pdo->profile, static fn (array $q): bool => str_contains($q['sql'], 'FROM shop_stock_balances')) : [];
    $result = ['mode' => $mode, 'role' => $role, 'query' => $query, 'variants' => count($variants), 'selected_variant' => $selectedVariant,
        'html_sha256' => hash('sha256', $html), 'html_bytes' => strlen($html),
        'data_sha256' => hash('sha256', json_encode([$policy, $variants, $selectedVariant, $openingItems, $counts, $moves, $lots], JSON_THROW_ON_ERROR)),
        'ms' => $elapsed, 'incremental_peak_bytes' => $peak,
        'sql' => $instrument ? count($pdo->profile) : null, 'balance_sql' => count($balanceQueries),
        'fetched_rows' => $instrument ? array_sum(array_column($pdo->profile, 'fetched_rows')) : null];
    session_destroy();
    echo json_encode($result, JSON_THROW_ON_ERROR) . PHP_EOL;
    exit;
}

$admin = w72Connection();
$admin->exec('CREATE DATABASE `' . $schema . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$directory = sys_get_temp_dir() . '/' . $schema;
mkdir($directory, 0700);
try {
    $pdo = w72Connection($schema);
    w72Seed($pdo);
    $pdo->exec('CREATE TABLE IF NOT EXISTS opravneni(klic VARCHAR(100) PRIMARY KEY,min_role VARCHAR(30)) ENGINE=InnoDB');
    $pdo->exec("INSERT IGNORE INTO staff_user_positions(trainer_id,position_code) VALUES(7,'catalog_manager'),(7,'finance_manager')");
    $pdo->exec("INSERT INTO shop_products(id,origin,created_by_trainer_id,external_product_key,name,offer_type,catalog_status) "
        . "VALUES(10000,'manual',7,'W91-SYNTHETIC','Žluťoučký 100%_!','goods','active')");
    $variant = $pdo->prepare("INSERT INTO shop_variants(id,product_id,origin,created_by_trainer_id,sku,attributes_json,price_mode,amount_minor,currency,stock_quantity_decimal,catalog_status) "
        . "VALUES(?,10000,'manual',7,?,'{}','fixed',100,'CZK',?,'active')");
    $balance = $pdo->prepare("INSERT INTO shop_stock_balances(variant_id,mode,physical_quantity,reserved_quantity,blocked_quantity,revision,opening_evidence,actor_trainer_id) "
        . "VALUES(?,'tracked',10,3,2,1,?,7)");
    $inserted = 0;
    $counter = 0;
    $results = [];
    foreach ($extended ? [10000] : [0, 100, 1000, 10000] as $size) {
        $pdo->beginTransaction();
        for ($id = $inserted + 1; $id <= $size; $id++) {
            $variant->execute([10000 + $id, sprintf('SKU-%05d', $id), $id % 2 ? null : '0.000000']);
            if ($id % 3 !== 0) {
                $balance->execute([10000 + $id, str_repeat('Synthetic opening evidence. ', 40)]);
            }
        }
        $pdo->commit();
        $inserted = $size;
        foreach (['catalog_manager', 'finance_manager'] as $role) {
            foreach (['', 'SKU-00001', 'missing'] as $query) {
                $measurements = ['baseline' => [], 'current' => []];
                $counts = [];
                $hash = null;
                $dataHash = null;
                for ($iteration = $extended ? -5 : -1; $iteration < ($extended ? 30 : 5); $iteration++) {
                    foreach ($iteration % 2 === 0 ? ['baseline', 'current'] : ['current', 'baseline'] as $mode) {
                        $path = $directory . '/sample-' . (++$counter);
                        $process = proc_open(
                            [PHP_BINARY, __FILE__, '--worker', $schema, $mode, $role, $query, $iteration === -1 ? 'profile' : 'plain'],
                            [0 => ['pipe', 'r'], 1 => ['file', $path . '.out', 'w'], 2 => ['file', $path . '.err', 'w']],
                            $pipes
                        );
                        if (!is_resource($process)) {
                            throw new RuntimeException('Inventory worker failed to start.');
                        }
                        fclose($pipes[0]);
                        $deadline = microtime(true) + 120;
                        do {
                            $status = proc_get_status($process);
                            if (!$status['running']) {
                                break;
                            }
                            usleep(20000);
                        } while (microtime(true) < $deadline);
                        if ($status['running']) {
                            proc_terminate($process);
                            proc_close($process);
                            throw new RuntimeException('Inventory worker timeout.');
                        }
                        proc_close($process);
                        $stderr = trim((string) file_get_contents($path . '.err'));
                        if ($status['exitcode'] !== 0 || $stderr !== '') {
                            throw new RuntimeException('Inventory worker failed: ' . $stderr);
                        }
                        $value = json_decode((string) file_get_contents($path . '.out'), true, 512, JSON_THROW_ON_ERROR);
                        $hash ??= $value['html_sha256'];
                        $dataHash ??= $value['data_sha256'];
                        if ($hash !== $value['html_sha256'] || $dataHash !== $value['data_sha256']) {
                            throw new RuntimeException('Inventory data/HTML changed: ' . json_encode([$size, $role, $query, $mode]));
                        }
                        if ($iteration === -1) {
                            $counts[$mode] = $value;
                        } elseif ($iteration >= 0) {
                            $measurements[$mode][] = $value;
                        }
                    }
                }
                $median = static function (array $values): float {
                    sort($values);
                    return $values[intdiv(count($values), 2)];
                };
                $peaks = array_map(static fn (array $values): float => $median(array_column($values, 'incremental_peak_bytes')), $measurements);
                if ($counts['current']['balance_sql'] > ($value['variants'] === 0 ? 0 : 1 + (int) ceil($value['variants'] / 400))
                    || $counts['current']['sql'] > $counts['baseline']['sql'] || $peaks['current'] > $peaks['baseline'] + 1048576) {
                    throw new RuntimeException('Inventory query/memory budget failed: ' . json_encode([$size, $role, $query, $peaks]));
                }
                $results[] = ['size' => $size, 'role' => $role, 'query' => $query, 'same_html_and_data' => true,
                    'counts' => $counts, 'samples' => $measurements, 'median_peak_bytes' => $peaks,
                    'median_ms' => array_map(static fn (array $values): float => $median(array_column($values, 'ms')), $measurements)];
            }
        }
    }
    echo json_encode(['database_version' => $pdo->query('SELECT VERSION()')->fetchColumn(), 'migrated_schema' => true,
        'foreign_keys' => (int) $pdo->query('SELECT @@foreign_key_checks')->fetchColumn(), 'production_mutations' => false,
        'timing_samples' => $extended ? 30 : 5, 'warmups' => $extended ? 5 : 1,
        'results' => $results], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
} finally {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $admin->exec('DROP DATABASE `' . $schema . '`');
    foreach (glob($directory . '/*') ?: [] as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
    rmdir($directory);
}
