<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/BarNativeHarness.php';
require_once dirname(__DIR__, 2) . '/bin/bar-cutover-prepare.php';
$harness = new BarNativeHarness(__FILE__);
$runtime = sys_get_temp_dir() . '/bar-cutover-native-' . bin2hex(random_bytes(6));
$repository = dirname(__DIR__, 2);
$port = (int)(getenv('BAR_SMOKE_DB_PORT') ?: 3306);
$replacePassword = getenv('BAR_NATIVE_REPLACE_PASSWORD') === '1';
foreach (['app/includes/bar', 'app/var', 'bar.kovopraha.cz/includes', 'bar.kovopraha.cz/img', 'data/.kis-backups/files', 'operation'] as $part) {
    mkdir($runtime . '/' . $part, 0700, true);
}
try {
    $pdo = $harness->pdo;
    $database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    $pdo->exec('DELETE FROM kis_bar_products');
    $pdo->exec('DELETE FROM kis_bar_categories');
    $pdo->exec("DELETE FROM staff_user_positions WHERE trainer_id IN (2,3)");
    $pdo->exec("INSERT INTO staff_user_positions(trainer_id,position_code,is_default) VALUES(2,'system_admin',1)");
    $pdo->exec("UPDATE treneri SET jmeno='LOCALHOST Cutover Owner' WHERE id=2");
    $pdo->exec('CREATE TABLE verejni_uzivatele(id INT PRIMARY KEY,email VARCHAR(255),trener_id INT) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE jidlo_kategorie(id INT PRIMARY KEY,nazev VARCHAR(100)) ENGINE=InnoDB');
    $pdo->exec("INSERT INTO jidlo_kategorie VALUES(41,'LOCALHOST Drinks')");
    $pdo->exec('CREATE TABLE jidlo_produkty(id INT PRIMARY KEY,kategorie_id INT,nazev VARCHAR(100),cena DECIMAL(10,2),nakupni_cena DECIMAL(10,2),'
        . 'skladovy_rezim VARCHAR(20),sklad INT,predsklad INT,obrazek VARCHAR(255)) ENGINE=InnoDB');
    $pdo->prepare("INSERT INTO jidlo_produkty VALUES(91,41,'LOCALHOST Tea',35.00,11.01,'sledovat',23,47,?)")
        ->execute(['img/Čaj 1 (malý).png']);
    $pdo->exec('CREATE TABLE jidlo_produkty_slozeni(produkt_id INT,slozka_id INT,mnozstvi INT) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE bar_uzivatele(id INT PRIMARY KEY,jmeno VARCHAR(100),heslo_hash VARCHAR(255),role VARCHAR(20),schvalen INT) ENGINE=InnoDB');
    $originalHash = $replacePassword ? 'LOCALHOST-unsupported-legacy' : password_hash('LOCALHOST-Lucka-Only', PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO bar_uzivatele VALUES(77,'LuCkA',?,'admin',1)")->execute([$originalHash]);
    $image = imagecreatetruecolor(8, 4);
    imagepng($image, $runtime . '/bar.kovopraha.cz/img/Čaj 1 (malý).png');
    imagedestroy($image);
    $appConfig = ['DB_HOST' => '127.0.0.1;port=' . $port, 'DB_NAME' => $database, 'DB_USER' => 'root', 'DB_PASS' => '',
        'JE_LOKALNE' => false, 'APP_BASE_URL' => 'https://kis.kovopraha.cz', 'APP_PRIVATE_STORAGE_ROOT' => $runtime . '/private'];
    $configPhp = "<?php\n// LOCALHOST synthetic production-operation fixture; never deploy.\n";
    foreach ($appConfig as $key => $value) {
        $configPhp .= 'define(' . var_export($key, true) . ',' . var_export($value, true) . ");\n";
    }
    file_put_contents($runtime . '/app/config.php', $configPhp);
    file_put_contents($runtime . '/app/includes/bar/transfer_package.php', '<?php require_once ' . var_export($repository . '/includes/bar/transfer_package.php', true) . ';');
    $sourceConfig = ['db' => ['host' => $appConfig['DB_HOST'], 'nazev' => $database, 'uzivatel' => 'root', 'heslo' => '']];
    file_put_contents($runtime . '/bar.kovopraha.cz/includes/config.php', '<?php return ' . var_export($sourceConfig, true) . ';');
    file_put_contents($runtime . '/app/var/deployment.json', json_encode(['sha' => str_repeat('a', 40)], JSON_THROW_ON_ERROR));
    foreach (['bin/bar-cutover-operation.php' => 'bar-cutover-operation.php', 'bin/bar-cutover-prepare.php' => 'bar-cutover-prepare.php',
        'includes/bar/transfer_verification.php' => 'bar-transfer-verification.php', 'includes/bar/cutover_permissions.php' => 'bar-cutover-permissions.php'] as $source => $target) {
        copy($repository . '/' . $source, $runtime . '/operation/' . $target);
    }
    $environment = array_merge(getenv(), ['APP_HOST' => 'kis.kovopraha.cz', 'APP_ROOT' => $runtime . '/app',
        'BAR_LEGACY_ROOT' => $runtime . '/bar.kovopraha.cz', 'BAR_PACKAGE_PARENT' => $runtime . '/data/.kis-backups', 'BAR_PREPARE_RUN' => '123',
        'BAR_LUCKA_REPLACE_PASSWORD' => $replacePassword ? '1' : '0',
        'APP_PRIVATE_STORAGE_ROOT' => $runtime . '/private', 'BAR_CUTOVER_PLAN' => $runtime . '/operation/plan.json']);
    $run = static function (string $code, array $env) use ($runtime): array {
        file_put_contents($runtime . '/operation/run.php', '<?php ' . $code);
        $process = proc_open([PHP_BINARY, $runtime . '/operation/run.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
        if (!is_resource($process)) {
            throw new RuntimeException('Cannot start isolated cutover worker.');
        }
        fclose($pipes[0]);
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        if ($exit !== 0 || $err !== '') {
            throw new RuntimeException('Synthetic cutover worker failed: ' . $out . $err);
        }
        return json_decode(trim((string)$out), true, 32, JSON_THROW_ON_ERROR);
    };
    $prepare = $run('require __DIR__ . "/bar-cutover-prepare.php"; barCutoverPrepareMain();', $environment);
    if (!$prepare['ready_for_import'] || !$prepare['shared_database'] || $prepare['products'] !== 1 || $prepare['images'] !== 1) {
        throw new RuntimeException('Native preparation or shared-database source handling failed.');
    }
    if ($prepare['lucka']['password_replaced'] !== $replacePassword || $prepare['lucka']['source_password_preserved'] === $replacePassword) {
        throw new RuntimeException('Native credential-origin report differs.');
    }
    if (!$replacePassword) {
        $cached = $run('require __DIR__ . "/bar-cutover-prepare.php"; barCutoverPrepareMain();', array_merge($environment, [
            'BAR_PREPARE_RUN' => '124', 'BAR_IMAGE_CACHE_RUN' => '123', 'BAR_IMAGE_CACHE_SHA256' => $prepare['bundle_sha256']]));
        if ($cached['bundle_sha256'] !== $prepare['bundle_sha256'] || $cached['source_image_map_sha256'] !== $prepare['source_image_map_sha256']) {
            throw new RuntimeException('Fresh export using pinned image metadata differs.');
        }
    }
    $replacement = null;
    if ($replacePassword) {
        $delivery = $runtime . '/data/.kis-backups/bar-transfer-credential-123.delivery.json';
        $replacement = json_decode((string)file_get_contents($delivery), true, 8, JSON_THROW_ON_ERROR)['password'];
        // The real preparation deletes plaintext after encrypted delivery; future source checks need only the private hash state.
        unlink($delivery);
    }
    $plan = ['prepare_run' => '123', 'bundle_sha256' => $prepare['bundle_sha256'], 'release_sha' => str_repeat('a', 40),
        'administrator_name' => 'LOCALHOST Cutover Owner'];
    file_put_contents($runtime . '/operation/plan.json', json_encode($plan, JSON_THROW_ON_ERROR));
    $operation = 'require __DIR__ . "/bar-cutover-operation.php"; barCutoverOperationMain();';
    $inspect = $run($operation, array_merge($environment, ['BAR_CUTOVER_MODE' => 'inspect', 'BAR_CUTOVER_CONFIRM' => 'OVERIT']));
    if (!$inspect['ok'] || $inspect['catalog_committed'] || (int)$pdo->query('SELECT COUNT(*) FROM kis_bar_products')->fetchColumn() !== 0) {
        throw new RuntimeException('Inspect performed a database write.');
    }
    // Only this synthetic backend test substitutes proof; the protected workflow verifies the real full restore run.
    $sql = 'evidence_2026-11-05_localhost.sql.gz';
    file_put_contents($runtime . '/data/.kis-backups/' . $sql, gzencode('CREATE TABLE LOCALHOST_only(id INT);'));
    $zip = new ZipArchive();
    $zip->open($runtime . '/data/.kis-backups/files/kis-files-123.zip', ZipArchive::CREATE | ZipArchive::EXCL);
    $zip->addFromString('manifest.json', '{}');
    $zip->close();
    $backup = ['restore_run' => '123', 'sql_file' => $sql, 'sql_sha256' => hash_file('sha256', $runtime . '/data/.kis-backups/' . $sql),
        'files_archive' => 'kis-files-123.zip', 'files_sha256' => hash_file('sha256', $runtime . '/data/.kis-backups/files/kis-files-123.zip')];
    file_put_contents($runtime . '/data/.kis-backups/evidence_2026-11-05_localhost.manifest.json', json_encode(['application' => 'evidence',
        'sql_file' => $sql, 'sha256' => $backup['sql_sha256'], 'tables' => ['kis_bar_products' => 0, 'kis_bar_import_runs' => 0]], JSON_THROW_ON_ERROR));
    $plan['backup'] = $backup;
    file_put_contents($runtime . '/operation/plan.json', json_encode($plan, JSON_THROW_ON_ERROR));
    $applied = $run($operation, array_merge($environment, ['BAR_CUTOVER_MODE' => 'apply', 'BAR_CUTOVER_CONFIRM' => 'PREVEST_BAR']));
    $status = $run($operation, array_merge($environment, ['BAR_CUTOVER_MODE' => 'status', 'BAR_CUTOVER_CONFIRM' => 'OVERIT']));
    if (!$applied['ok'] || !$status['catalog_committed'] || !$status['permissions_committed']
        || !$status['verification']['images_exact'] || !$status['verification']['zero_stock']
        || (int)$pdo->query('SELECT sklad+predsklad FROM jidlo_produkty')->fetchColumn() !== 70) {
        throw new RuntimeException('Applied cutover, permission scope or source immutability differs.');
    }
    $targetHash = (string)$pdo->query("SELECT heslo FROM treneri WHERE jmeno='lucka'")->fetchColumn();
    if (($replacePassword && !password_verify((string)$replacement, $targetHash))
        || (!$replacePassword && $targetHash !== $originalHash)
        || $pdo->query('SELECT heslo_hash FROM bar_uzivatele WHERE id=77')->fetchColumn() !== $originalHash) {
        throw new RuntimeException('Credential preservation/replacement or source immutability failed.');
    }
    $repeat = $run($operation, array_merge($environment, ['BAR_CUTOVER_MODE' => 'apply', 'BAR_CUTOVER_CONFIRM' => 'PREVEST_BAR']));
    if (!$repeat['import']['repeated'] || $repeat['permissions']['admin_added'] || $repeat['permissions']['consumption_added']) {
        throw new RuntimeException('Repeated cutover duplicated catalogue or permissions.');
    }
    echo json_encode(['ok' => true, 'source' => 'synthetic', 'shared_database' => true, 'unicode_image_reference' => true,
        'inspect_without_database_writes' => true, 'catalog_exact' => true, 'zero_stock' => true,
        'operator_only' => true, 'audited_admin_and_consumption' => true, 'repeated_without_duplicates' => true,
        'password_replacement' => $replacePassword, 'source_credentials_unchanged' => true, 'production_mutations' => false], JSON_THROW_ON_ERROR) . PHP_EOL;
} finally {
    $harness->close();
    $children = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($runtime, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($children as $file) {
        if (!str_starts_with($file->getPathname(), $runtime . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Unsafe synthetic cutover cleanup.');
        }
        if ($file->isLink() || $file->isFile()) {
            unlink($file->getPathname());
        } else {
            rmdir($file->getPathname());
        }
    }
    rmdir($runtime);
}
