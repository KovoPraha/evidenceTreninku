<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/** Configuration variables cannot overwrite the exporter's state. Diagnostics contain no values. */
function barCutoverReadSourceConfig(string $legacy): array
{
    $oldHost = $_SERVER['HTTP_HOST'] ?? null;
    $oldName = $_SERVER['SERVER_NAME'] ?? null;
    $GLOBALS['bar_source_config_meta'] = [
        'loader_readable' => is_readable($legacy . '/includes/config.php'),
        'private_present' => is_file($legacy . '/includes/.config.php'),
        'private_readable' => is_readable($legacy . '/includes/.config.php'),
        'local_present' => is_file($legacy . '/includes/.config.local.php'),
        'local_readable' => is_readable($legacy . '/includes/.config.local.php'),
    ];
    $_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = 'bar.kovopraha.cz';
    ob_start();
    try {
        $value = (static function (string $file): mixed {
            return require $file;
        })($legacy . '/includes/config.php');
        $output = ob_get_contents();
    } finally {
        ob_end_clean();
        if ($oldHost === null) {
            unset($_SERVER['HTTP_HOST']);
        } else {
            $_SERVER['HTTP_HOST'] = $oldHost;
        }
        if ($oldName === null) {
            unset($_SERVER['SERVER_NAME']);
        } else {
            $_SERVER['SERVER_NAME'] = $oldName;
        }
    }
    $GLOBALS['bar_source_config_meta'] += ['return_type' => get_debug_type($value),
        'has_database_array' => is_array($value) && is_array($value['db'] ?? null),
        'local_flag_type' => is_array($value) ? get_debug_type($value['lokalne'] ?? null) : 'missing',
        'production_mode' => is_array($value) && !(bool)($value['lokalne'] ?? false),
        'unexpected_output' => $output !== ''];
    // Match the legacy application's je_lokalni_prostredi(): an omitted flag means production.
    if ($output !== '' || !is_array($value) || (bool)($value['lokalne'] ?? false) || !is_array($value['db'] ?? null)) {
        throw new RuntimeException('Unexpected source configuration.');
    }
    return $value;
}

/** Private, read-only production export. No application bootstrap or writes to either DB. */
function barCutoverPrepareSourceLucka(array $user, string $parent, string $run): array
{
    $name = $user['jmeno'] ?? null;
    $hash = $user['heslo_hash'] ?? null;
    $supported = is_string($hash) && strlen($hash) <= 100 && trainer_password_is_modern_hash($hash);
    $GLOBALS['bar_source_account_meta'] = ['source_id' => (int)($user['id'] ?? 0),
        'name_exact' => $name === 'lucka', 'name_casefold_matches' => is_string($name) && mb_strtolower(trim($name), 'UTF-8') === 'lucka',
        'source_role' => in_array($user['role'] ?? null, ['admin', 'prodejce'], true) ? $user['role'] : 'unknown',
        'approved' => (int)($user['schvalen'] ?? 0) === 1, 'source_password_supported' => $supported,
        'password_replaced' => false, 'source_password_preserved' => $supported];
    $meta = $GLOBALS['bar_source_account_meta'];
    if (!$meta['name_casefold_matches'] || !$meta['approved'] || $meta['source_role'] === 'unknown' || $meta['source_id'] < 1) {
        throw new InvalidArgumentException('Účet lucka není jednoznačný, schválený nebo nemá známou původní roli.');
    }
    // The owner retained only this identity as an operator; source administrator rights never cross.
    $user['jmeno'] = 'lucka';
    $user['role'] = 'prodejce';
    if (!$supported) {
        $state = $parent . '/bar-transfer-credential-' . $run . '.json';
        $sourceFingerprint = hash('sha256', is_string($hash) ? $hash : json_encode($hash, JSON_THROW_ON_ERROR));
        if (file_exists($state)) {
            if (is_link($state) || !is_file($state) || filesize($state) > 16384) {
                throw new RuntimeException('Invalid private credential state.');
            }
            $record = json_decode((string)file_get_contents($state), true, 8, JSON_THROW_ON_ERROR);
            if (($record['source_id'] ?? null) !== $meta['source_id'] || ($record['source_fingerprint'] ?? null) !== $sourceFingerprint
                || !is_string($record['heslo_hash'] ?? null) || !trainer_password_is_modern_hash($record['heslo_hash'])) {
                throw new RuntimeException('Source credential changed after preparation.');
            }
            $user['heslo_hash'] = $record['heslo_hash'];
        } else {
            if (getenv('BAR_LUCKA_REPLACE_PASSWORD') !== '1') {
                throw new InvalidArgumentException('Heslo lucky nemá podporovaný formát. Použijte výslovně povolenou přípravu nového hesla se šifrovaným předáním.');
            }
            $password = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
            $user['heslo_hash'] = trainer_password_hash($password);
            barWritePrivateFile($parent . '/bar-transfer-credential-' . $run . '.delivery.json', json_encode([
                'kind' => 'bar-lucka-transfer-v1', 'run_id' => $run, 'source_id' => $meta['source_id'], 'login' => 'lucka',
                'email' => 'info1@kovopraha.cz', 'password' => $password], JSON_THROW_ON_ERROR));
            barWritePrivateFile($state, json_encode(['source_id' => $meta['source_id'], 'source_fingerprint' => $sourceFingerprint,
                'heslo_hash' => $user['heslo_hash']], JSON_THROW_ON_ERROR));
            if (function_exists('sodium_memzero')) {
                sodium_memzero($password);
            }
            unset($password);
        }
        $GLOBALS['bar_source_account_meta']['password_replaced'] = true;
        $GLOBALS['bar_source_account_meta']['source_password_preserved'] = false;
    }
    return $user;
}

function barCutoverSnapshotImages(array $source, string $directory, string $snapshot): array
{
    $root = realpath($directory);
    if ($root === false || !is_dir($root) || is_link($directory)) {
        throw new RuntimeException('Invalid source image directory.');
    }
    if (!is_dir($snapshot) && !mkdir($snapshot, 0700)) {
        throw new RuntimeException('Cannot create private image snapshot.');
    }
    if (is_link($snapshot)) {
        throw new RuntimeException('A snapshot cannot be a symlink.');
    }
    $map = [];
    $GLOBALS['bar_source_image_meta'] = [];
    foreach ($source['products'] as &$product) {
        $reference = $product['obrazek'] ?? null;
        if ($reference === null || $reference === '' || (is_string($reference) && trim($reference) === '')) {
            $product['obrazek'] = null;
            continue;
        }
        $name = is_string($reference) ? trim($reference) : '';
        $shape = str_contains($name, '://') ? 'url' : (str_contains($name, '/') || str_contains($name, '\\') ? 'path' : 'basename');
        $GLOBALS['bar_source_image_meta'] = ['product_source_id' => (int)$product['id'], 'reference_shape' => $shape,
            'reference_type' => get_debug_type($reference), 'reference_bytes' => strlen($name)];
        if ($shape === 'url') {
            $url = parse_url($name);
            if (!is_array($url) || !in_array($url['scheme'] ?? '', ['http', 'https'], true)
                || ($url['host'] ?? '') !== 'bar.kovopraha.cz' || isset($url['user']) || isset($url['pass'])
                || isset($url['query']) || isset($url['fragment']) || isset($url['port'])) {
                throw new InvalidArgumentException('Fotografie odkazuje mimo ověřený místní zdroj.');
            }
            $name = rawurldecode((string)($url['path'] ?? ''));
        }
        foreach (['../img/', './img/', '/img/', 'img/'] as $prefix) {
            if (str_starts_with($name, $prefix)) {
                $name = substr($name, strlen($prefix));
                break;
            }
        }
        if ($name === '' || !mb_check_encoding($name, 'UTF-8') || strlen($name) > 255
            || preg_match('~[\x00-\x1f\x7f/\\\\:<>|"?*]~', $name)
            || !preg_match('/\.(?:jpg|jpeg|png|webp)$/iD', $name)) {
            throw new InvalidArgumentException('Zdrojová fotografie nemá bezpečně převoditelný místní název.');
        }
        $path = $root . DIRECTORY_SEPARATOR . $name;
        $resolved = realpath($path);
        $GLOBALS['bar_source_image_meta']['basename'] = $name;
        if ($resolved === false || dirname($resolved) !== $root || !is_file($path) || is_link($path)) {
            throw new InvalidArgumentException('Zdrojová fotografie chybí nebo není běžný soubor v img.');
        }
        $image = barReadImage($resolved);
        $extension = match ($image['meta']['mime_type']) {
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
            default => throw new InvalidArgumentException('Nepodporovaná zdrojová fotografie.'),
        };
        $canonical = 'source-' . $image['meta']['sha256'] . '.' . $extension;
        $destination = $snapshot . '/' . $canonical;
        if (file_exists($destination)) {
            if (is_link($destination) || !is_file($destination) || hash_file('sha256', $destination) !== $image['meta']['sha256']) {
                throw new RuntimeException('Private image snapshot differs.');
            }
        } else {
            barWritePrivateFile($destination, $image['bytes']);
        }
        $map[] = ['product_source_id' => (int)$product['id'], 'source_reference' => $reference,
            'source_basename' => $name, 'package_name' => $canonical, 'sha256' => $image['meta']['sha256']];
        $product['obrazek'] = $canonical;
    }
    unset($product);
    $GLOBALS['bar_source_image_meta'] = [];
    return ['source' => $source, 'map' => $map];
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
        require_once $root . '/config.php';
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
    $sourceConfig = barCutoverReadSourceConfig($legacy);
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
    $snapshot = $parent . '/bar-transfer-images-' . $run;
    $prepared = barCutoverSnapshotImages(barReadLegacyTransferSource($source), $legacy . '/img', $snapshot);
    $GLOBALS['bar_source_catalog_meta'] = ['categories' => count($prepared['source']['categories']),
        'products' => count($prepared['source']['products']), 'recipes' => count($prepared['source']['recipes']),
        'image_references' => count($prepared['map']), 'unique_images' => count(array_unique(array_column($prepared['map'], 'package_name')))];
    $prepared['source']['lucka'] = barCutoverPrepareSourceLucka($prepared['source']['lucka'], $parent, $run);
    $bundle = barBuildTransferBundle($prepared['source'], $snapshot);
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
        barWriteTransferPackage($bundle, $snapshot, $directory);
    }
    // Detect changes to rows or image bytes while the package was being copied.
    $GLOBALS['bar_cutover_prepare_stage'] = 'source_stability';
    $fresh = barCutoverSnapshotImages(barReadLegacyTransferSource($source), $legacy . '/img', $snapshot);
    $fresh['source']['lucka'] = barCutoverPrepareSourceLucka($fresh['source']['lucka'], $parent, $run);
    if (barBuildTransferBundle($fresh['source'], $snapshot) !== $bundle || $fresh['map'] !== $prepared['map']) {
        throw new RuntimeException('Source changed during export; prepare a fresh snapshot.');
    }
    $mapJson = json_encode($prepared['map'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $mapPath = $directory . '/source-image-map.json';
    if (file_exists($mapPath)) {
        if (is_link($mapPath) || file_get_contents($mapPath) !== $mapJson) {
            throw new RuntimeException('Source image provenance differs.');
        }
    } else {
        barWritePrivateFile($mapPath, $mapJson);
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
        'catalog_sha256' => $bundle['catalog']['sha256'], 'source_image_map_sha256' => hash('sha256', $mapJson),
        'products' => count($bundle['catalog']['products']),
        'categories' => count($bundle['catalog']['categories']), 'recipes' => count($bundle['catalog']['recipes']),
        'images' => count($bundle['images']), 'image_bytes' => array_sum(array_column($bundle['images'], 'byte_size')),
        'lucka' => ['accounts' => 1, 'password_supported' => true, 'technical_email' => true, 'position' => 'bar_operator'] + $GLOBALS['bar_source_account_meta'],
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
            'source_configuration' => $GLOBALS['bar_source_config_meta'] ?? [],
            'source_image' => $GLOBALS['bar_source_image_meta'] ?? [],
            'source_account' => $GLOBALS['bar_source_account_meta'] ?? [], 'source_catalog' => $GLOBALS['bar_source_catalog_meta'] ?? [],
            'error_type' => get_debug_type($error), 'error_file' => basename($error->getFile()), 'error_line' => $error->getLine(),
            'message' => ($GLOBALS['bar_cutover_prepare_stage'] ?? '') === 'source_catalog_and_images' && $error instanceof InvalidArgumentException
                ? $error->getMessage() : 'bar_cutover_prepare_failed_without_database_writes'],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
        ) . PHP_EOL);
        exit(1);
    }
}
