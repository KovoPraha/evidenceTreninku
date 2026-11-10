<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function barStockOperationMain(): void
{
    $result = ['ok' => false, 'committed' => false];
    try {
        $mode = (string)getenv('BAR_CUTOVER_MODE');
        $result['mode'] = $mode;
        $input = json_decode((string)file_get_contents(__DIR__ . '/plan.json'), true, 16, JSON_THROW_ON_ERROR);
        if (!in_array($mode, ['inspect', 'apply', 'status'], true)
            || !is_array($input) || array_diff(array_keys($input), ['administrator_name', 'inspect_run', 'snapshot_sha256', 'backup']) !== []) {
            throw new InvalidArgumentException('Neplatný režim nebo vstup převodu zásob.');
        }
        $root = realpath((string)getenv('APP_ROOT'));
        $legacy = realpath((string)getenv('BAR_LEGACY_ROOT'));
        $parent = realpath((string)getenv('BAR_PACKAGE_PARENT'));
        $run = (string)getenv('BAR_OPERATION_RUN');
        if (getenv('APP_HOST') !== 'kis.kovopraha.cz' || !$root || !$legacy || !$parent
            || basename($legacy) !== 'bar.kovopraha.cz' || $root === $legacy || !preg_match('/^[1-9][0-9]{0,19}$/D', $run)) {
            throw new RuntimeException('Neplatné cílové prostředí.');
        }
        $_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = 'kis.kovopraha.cz';
        ob_start();
        (static function (string $path): void {
            require_once $path;
        })($root . '/config.php');
        $output = ob_get_clean();
        $config = get_defined_constants();
        if ($output !== '' || ($config['JE_LOKALNE'] ?? null) !== false || ($config['APP_BASE_URL'] ?? null) !== 'https://kis.kovopraha.cz') {
            throw new RuntimeException('Neplatná produkční konfigurace.');
        }
        require_once $root . '/includes/bar/requests.php';
        require_once $root . '/includes/bar/transfer_package.php';
        require_once $root . '/includes/bar/cutover_permissions.php';
        require_once __DIR__ . '/bar-cutover-prepare.php';
        require_once __DIR__ . '/bar-cutover-operation.php';
        require_once is_file(__DIR__ . '/bar-stock-transfer.php')
            ? __DIR__ . '/bar-stock-transfer.php' : dirname(__DIR__) . '/includes/bar/stock_transfer.php';
        $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_TIMEOUT => 15];
        $target = new PDO('mysql:host=' . $config['DB_HOST'] . ';dbname=' . $config['DB_NAME'] . ';charset=utf8mb4', $config['DB_USER'], $config['DB_PASS'], $options);
        $legacyConfig = barCutoverReadSourceConfig($legacy);
        $sourceConfig = $legacyConfig['db'];
        $source = new PDO(
            'mysql:host=' . $sourceConfig['host'] . ';dbname=' . $sourceConfig['nazev'] . ';charset=utf8mb4',
            $sourceConfig['uzivatel'],
            $sourceConfig['heslo'],
            $options
        );
        $source->exec('SET SESSION innodb_lock_wait_timeout=20');
        $target->exec('SET SESSION innodb_lock_wait_timeout=20');
        // Hold a shared lock on the exact source quantities until the target transaction has committed.
        $source->beginTransaction();
        $rows = $source->query('SELECT id,sklad,predsklad FROM jidlo_produkty ORDER BY id LOCK IN SHARE MODE')->fetchAll();
        $settings = $source->query("SELECT klic,hodnota FROM bar_nastaveni WHERE klic IN
            ('obrazovka_nadpis','obrazovka_podtitul','obrazovka_obnova','obrazovka_vzhled','obrazovka_vyprodane') ORDER BY klic LOCK IN SHARE MODE")
            ->fetchAll(PDO::FETCH_KEY_PAIR);
        if (trim((string)($settings['obrazovka_nadpis'] ?? '')) === '') {
            $settings['obrazovka_nadpis'] = trim((string)($legacyConfig['mail']['nazev'] ?? '')) ?: 'KOVO BAR';
        }
        $sourceHash = hash('sha256', json_encode([$rows, $settings], JSON_THROW_ON_ERROR));
        $actor = barCutoverResolveAdministrator($target, (string)($input['administrator_name'] ?? ''));
        $_SESSION = ['trener_id' => $actor['id'], 'trener_session_version' => $actor['session_version'], 'staff_active_position' => 'bar_admin'];
        barActor($target, 'stock_manage');
        $deployment = json_decode((string)file_get_contents($root . '/var/deployment.json'), true, 16, JSON_THROW_ON_ERROR);
        if ($mode === 'inspect') {
            $plan = barOpeningStockPlan($target, $rows);
            $snapshot = ['created_at' => time(), 'source_sha256' => $sourceHash, 'release_sha' => $deployment['sha'],
                'actor_id' => $actor['id'], 'plan' => $plan, 'display_settings' => $settings];
            $directory = $parent . '/bar-stock-' . $run;
            if (file_exists($directory) || !mkdir($directory, 0700)) {
                throw new RuntimeException('Adresář nového snímku již existuje.');
            }
            $json = json_encode($snapshot, JSON_THROW_ON_ERROR);
            barWritePrivateFile($directory . '/snapshot.json', $json);
            $result += ['inspect_run' => $run, 'snapshot_sha256' => hash('sha256', $json), 'products' => count($plan),
                'source_stall_total' => array_sum(array_column($plan, 'stall')), 'source_office_total' => array_sum(array_column($plan, 'office')),
                'target_stall_total' => array_sum(array_column($plan, 'before_stall')), 'target_office_total' => array_sum(array_column($plan, 'before_office'))];
        } else {
            $inspectRun = (string)($input['inspect_run'] ?? '');
            $hash = (string)($input['snapshot_sha256'] ?? '');
            if (!preg_match('/^[1-9][0-9]{0,19}$/D', $inspectRun) || !preg_match('/^[a-f0-9]{64}$/D', $hash)) {
                throw new InvalidArgumentException('Chybí přesný schválený snímek.');
            }
            $directory = $parent . '/bar-stock-' . $inspectRun;
            $file = $directory . '/snapshot.json';
            if (is_link($file) || !is_file($file) || !hash_equals($hash, (string)hash_file('sha256', $file))) {
                throw new RuntimeException('Snímek chybí nebo má jiný otisk.');
            }
            $snapshot = json_decode((string)file_get_contents($file), true, 16, JSON_THROW_ON_ERROR);
            if (!hash_equals($snapshot['source_sha256'], $sourceHash) || $snapshot['actor_id'] !== $actor['id']) {
                throw new RuntimeException('Zdrojová zásoba nebo správce se změnili.');
            }
            if ($mode === 'apply') {
                if (getenv('BAR_CUTOVER_CONFIRM') !== 'PREVEST_ZASOBY' || $snapshot['created_at'] < time() - 3600
                    || $snapshot['release_sha'] !== $deployment['sha']) {
                    throw new RuntimeException('Chybí potvrzení nebo aktuální snímek stejného vydání.');
                }
                $result['backup'] = barCutoverVerifyBackup($input['backup'] ?? [], $parent, count($snapshot['plan']));
                $result += barApplyOpeningStocks(
                    $target,
                    $snapshot['plan'],
                    $hash,
                    'Převod původních zásob BAR schválený vlastníkem 10. 11. 2026',
                    $snapshot['display_settings']
                );
                $result['committed'] = true;
            }
            $current = barOpeningStockPlan($target, $rows);
            $mismatches = 0;
            foreach ($current as $row) {
                $mismatches += (int)($row['before_stall'] !== $row['stall'] || $row['before_office'] !== $row['office']);
            }
            $result += ['products' => count($current), 'mismatches' => $mismatches, 'inspect_run' => $inspectRun];
            if ($mismatches !== 0) {
                throw new RuntimeException('Současné zásoby nejsou shodné se zdrojem.');
            }
        }
        $source->commit();
        $result['ok'] = true;
    } catch (Throwable $error) {
        if (isset($source) && $source->inTransaction()) {
            $source->rollBack();
        }
        // Never expose configuration, credentials or SQL parameters through exception text.
        $result['error_class'] = get_class($error);
    }
    $json = json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    file_put_contents(__DIR__ . '/result.json', $json . PHP_EOL, LOCK_EX);
    chmod(__DIR__ . '/result.json', 0600);
    echo $json . PHP_EOL;
    exit($result['ok'] ? 0 : 1);
}
