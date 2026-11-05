<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/** Private, read-only production export. No application bootstrap or writes to either DB. */
function barCutoverPrepare(): array
{
    umask(0077);
    $GLOBALS['bar_cutover_prepare_stage'] = 'paths';
    $root = realpath((string)getenv('APP_ROOT'));
    $legacy = realpath((string)getenv('BAR_LEGACY_ROOT'));
    $parent = realpath((string)getenv('BAR_PACKAGE_PARENT'));
    $run = (string)getenv('BAR_PREPARE_RUN');
    if (getenv('APP_HOST') !== 'kis.kovopraha.cz' || $root === false || $legacy === false || $parent === false
        || basename($legacy) !== 'bar.kovopraha.cz' || $root === $legacy || !preg_match('/^[1-9][0-9]{0,19}$/D', $run)) {
        throw new RuntimeException('Unexpected export paths.');
    }
    foreach ([$root, $legacy] as $webroot) {
        if ($parent === $webroot || str_starts_with($parent . '/', $webroot . '/')) {
            throw new RuntimeException('The private package must be outside both applications.');
        }
    }
    $_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = 'kis.kovopraha.cz';
    $GLOBALS['bar_cutover_prepare_stage'] = 'target_configuration';
    ob_start();
    try {
        require $root . '/config.php';
    } finally {
        $output = ob_get_clean();
    }
    $config = get_defined_constants();
    if ($output !== '' || ($config['JE_LOKALNE'] ?? null) !== false
        || ($config['APP_BASE_URL'] ?? null) !== 'https://kis.kovopraha.cz') {
        throw new RuntimeException('Unexpected target configuration.');
    }
    require_once $root . '/includes/bar/transfer_package.php';
    $GLOBALS['bar_cutover_prepare_stage'] = 'source_configuration';
    $_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = 'bar.kovopraha.cz';
    ob_start();
    try {
        $sourceConfig = require $legacy . '/includes/config.php';
    } finally {
        $output = ob_get_clean();
    }
    $_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = 'kis.kovopraha.cz';
    if ($output !== '' || !is_array($sourceConfig) || ($sourceConfig['lokalne'] ?? null) !== false
        || !is_array($sourceConfig['db'] ?? null)) {
        throw new RuntimeException('Unexpected source configuration.');
    }
    $sourceDb = $sourceConfig['db'];
    $GLOBALS['bar_cutover_prepare_stage'] = 'connections';
    $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
    $source = new PDO(
        'mysql:host=' . $sourceDb['host'] . ';dbname=' . $sourceDb['nazev'] . ';charset=utf8mb4',
        $sourceDb['uzivatel'],
        $sourceDb['heslo'],
        $options
    );
    $target = new PDO(
        'mysql:host=' . $config['DB_HOST'] . ';dbname=' . $config['DB_NAME'] . ';charset=utf8mb4',
        $config['DB_USER'],
        $config['DB_PASS'],
        $options
    );
    // Legacy and KIS may share a hosting database; the fixed table namespaces differ.
    $sharedDatabase = $sourceDb['host'] === $config['DB_HOST'] && $sourceDb['nazev'] === $config['DB_NAME'];
    $GLOBALS['bar_cutover_prepare_stage'] = 'source_snapshot';
    $engines = $source->query("SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()
        AND TABLE_NAME IN ('jidlo_kategorie','jidlo_produkty','jidlo_produkty_slozeni','bar_uzivatele')")->fetchAll();
    if (count($engines) !== 4 || array_unique(array_column($engines, 'ENGINE')) !== ['InnoDB']) {
        throw new RuntimeException('A transactional source snapshot is required.');
    }
    $GLOBALS['bar_cutover_prepare_stage'] = 'source_catalog_and_images';
    $bundle = barBuildTransferBundle(barReadLegacyTransferSource($source), $legacy . '/img');
    if (array_sum(array_column($bundle['images'], 'byte_size')) > 256 * 1024 * 1024) {
        throw new RuntimeException('The package exceeds the reviewed size limit.');
    }
    $directory = $parent . '/bar-transfer-' . $run;
    $GLOBALS['bar_cutover_prepare_stage'] = 'private_package';
    if (file_exists($directory)) {
        $existing = json_decode((string)file_get_contents($directory . '/bundle.json'), true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($existing) || barValidateTransferBundle($existing, $directory . '/images') !== $bundle) {
            throw new RuntimeException('An existing package differs or is incomplete.');
        }
    } else {
        barWriteTransferPackage($bundle, $legacy . '/img', $directory);
    }
    // Detect changes to rows or image bytes while the package was being copied.
    $GLOBALS['bar_cutover_prepare_stage'] = 'source_stability';
    if (barBuildTransferBundle(barReadLegacyTransferSource($source), $legacy . '/img') !== $bundle) {
        throw new RuntimeException('Source changed during export; prepare a fresh snapshot.');
    }
    $target->exec('START TRANSACTION READ ONLY');
    $GLOBALS['bar_cutover_prepare_stage'] = 'target_inventory';
    try {
        $counts = [];
        foreach (['categories', 'products', 'recipes', 'assets', 'import_runs', 'sales', 'cashboxes', 'stock_movements',
            'invoices', 'offers', 'accounting_exports'] as $suffix) {
            $table = 'kis_bar_' . $suffix;
            $counts[$table] = (int)$target->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
        }
        $q = $target->prepare('SELECT COUNT(*) FROM treneri WHERE LOWER(TRIM(jmeno))=? OR LOWER(TRIM(email))=?');
        $q->execute(['lucka', 'info1@kovopraha.cz']);
        $staffCollisions = (int)$q->fetchColumn();
        $q = $target->prepare('SELECT COUNT(*) FROM verejni_uzivatele WHERE LOWER(TRIM(email))=?');
        $q->execute(['info1@kovopraha.cz']);
        $publicCollisions = (int)$q->fetchColumn();
        $clock = (string)$target->query('SELECT clock_contract FROM kis_bar_state WHERE id=1')->fetchColumn();
        $target->commit();
    } catch (Throwable $error) {
        if ($target->inTransaction()) {
            $target->rollBack();
        }
        throw $error;
    }
    return ['ok' => true, 'read_only_databases' => true, 'shared_database' => $sharedDatabase, 'prepare_run' => $run, 'bundle_sha256' => $bundle['sha256'],
        'catalog_sha256' => $bundle['catalog']['sha256'], 'products' => count($bundle['catalog']['products']),
        'categories' => count($bundle['catalog']['categories']), 'recipes' => count($bundle['catalog']['recipes']),
        'images' => count($bundle['images']), 'image_bytes' => array_sum(array_column($bundle['images'], 'byte_size')),
        'lucka' => ['accounts' => 1, 'password_supported' => true, 'technical_email' => true, 'position' => 'bar_operator'],
        'target_counts' => $counts, 'staff_collisions' => $staffCollisions, 'public_collisions' => $publicCollisions,
        'target_empty' => array_sum($counts) === 0, 'clock_contract' => $clock, 'ready_for_rehearsal' => true,
        'ready_for_import' => array_sum($counts) === 0 && $staffCollisions === 0 && $publicCollisions === 0 && $clock === 'utc-v1',
        'source_stable' => true, 'source_writes' => false, 'target_writes' => false];
}

function barCutoverPrepareMain(): void
{
    try {
        echo json_encode(barCutoverPrepare(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } catch (Throwable $error) {
        // Configuration, source rows and the credential hash must never reach an Actions log.
        fwrite(STDERR, json_encode(
            ['ok' => false, 'stage' => $GLOBALS['bar_cutover_prepare_stage'] ?? 'unknown',
            'message' => $error instanceof InvalidArgumentException ? $error->getMessage() : 'bar_cutover_prepare_failed_without_database_writes'],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
        ) . PHP_EOL);
        exit(1);
    }
}
