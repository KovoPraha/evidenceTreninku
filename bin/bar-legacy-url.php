<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/** Fixed destinations deliberately discard all legacy IDs, tokens and query parameters. */
function barLegacyUrlTarget(string $path): string
{
    if (!preg_match('~^(?:index\.php|(?:admin|terminal|menu|public)/[a-z0-9_]+\.php)$~D', $path)) {
        throw new InvalidArgumentException('Unexpected legacy entry point.');
    }
    $target = '/bar/checkout.php';
    if (str_starts_with($path, 'public/') || str_starts_with($path, 'menu/')) {
        $target = '/bar/menu.php';
    } elseif ($path === 'terminal/sklad.php') {
        $target = '/bar/catalog.php';
    } elseif ($path === 'terminal/uzavrit.php') {
        $target = '/bar/cashbox.php';
    } elseif (str_starts_with($path, 'admin/')) {
        $name = basename($path, '.php');
        $target = match ($name) {
            'sklad', 'sklad_pohyby' => '/bar/stock.php',
            'faktury', 'faktura_pdf', 'faktury_api' => '/bar/invoices.php',
            'finance', 'grafy', 'trzby', 'audit' => '/bar/operations.php',
            'export' => '/bar/accounting.php',
            'spotreba' => '/bar/consumption.php',
            'nastaveni' => '/bar/settings.php',
            'prodejci' => '/sprava_pracovnich_pozic.php',
            'edit_kategorie', 'uloz_kategorii' => '/bar/categories.php',
            'prehled_listku', 'uloz_listek', 'vytvor_listky', 'nahled_obrazovky' => '/bar/offers.php',
            default => '/bar/catalog.php',
        };
    }
    return 'https://kis.kovopraha.cz' . $target;
}

function barLegacyUrlWrapper(string $path): string
{
    $target = var_export(barLegacyUrlTarget($path), true);
    $api = in_array($path, ['terminal/api.php', 'admin/faktury_api.php'], true) ? 'true' : 'false';
    return "<?php\ndeclare(strict_types=1);\n"
        . "// KIS BAR cutover: no legacy bootstrap, sessions, database access or request replay.\n"
        . "header('Cache-Control: no-store');\nheader('Referrer-Policy: no-referrer');\n"
        . "header('X-Content-Type-Options: nosniff');\n"
        . "\$target = $target;\n"
        . "if ($api || !in_array(\$_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'HEAD'], true)) {\n"
        . "    http_response_code(410);\n    header('Content-Type: application/json; charset=utf-8');\n"
        . "    echo json_encode(['error' => 'bar_moved_to_kis', 'url' => \$target], JSON_UNESCAPED_SLASHES);\n    exit;\n}\n"
        . "header('Location: ' . \$target, true, 302);\nexit;\n";
}

/** Only entry points change. Includes, configuration, images and the database stay in place. */
function barLegacyUrlInventory(string $root): array
{
    $root = realpath($root);
    if ($root === false || basename($root) !== 'bar.kovopraha.cz' || !is_file($root . '/includes/config.php')) {
        throw new RuntimeException('Unexpected legacy root.');
    }
    foreach (glob($root . '/*.php') ?: [] as $file) {
        if (!in_array(basename($file), ['index.php', 'config.php'], true)) {
            throw new RuntimeException('Unreviewed legacy root entry point.');
        }
    }
    $files = ['index.php' => null];
    foreach (['admin', 'terminal', 'menu', 'public'] as $directory) {
        if (!is_dir($root . '/' . $directory) || is_link($root . '/' . $directory)) {
            throw new RuntimeException('Unexpected legacy entry point directory.');
        }
        foreach (new DirectoryIterator($root . '/' . $directory) as $file) {
            if ($file->isDot()) {
                continue;
            }
            if ($file->isLink() || $file->isDir()) {
                throw new RuntimeException('Unreviewed nested legacy entry point.');
            }
            if (strtolower($file->getExtension()) === 'php') {
                $files[$directory . '/' . $file->getFilename()] = null;
            }
        }
    }
    if (count($files) < 20 || count($files) > 100 || !array_key_exists('terminal/api.php', $files)) {
        throw new RuntimeException('Unexpected legacy entry point count.');
    }
    ksort($files);
    // Root is switched last; every former writer is already disabled by then.
    unset($files['index.php']);
    $files['index.php'] = null;
    foreach ($files as $path => &$meta) {
        barLegacyUrlTarget($path);
        $absolute = $root . '/' . $path;
        if (is_link($absolute) || file_exists($absolute) && !is_file($absolute)) {
            throw new RuntimeException('Unsafe legacy entry point.');
        }
        $meta = ['before' => is_file($absolute) ? hash_file('sha256', $absolute) : null,
            'after' => hash('sha256', barLegacyUrlWrapper($path)),
            'mode' => is_file($absolute) ? fileperms($absolute) & 0777 : 0644];
    }
    unset($meta);
    return $files;
}

function barLegacyUrlApply(string $root, array $files): void
{
    // Validate the complete set before replacing the first entry point.
    if (barLegacyUrlInventory($root) !== $files) {
        throw new RuntimeException('Legacy files changed after the restored snapshot.');
    }
    $staged = [];
    try {
        foreach ($files as $path => $meta) {
            $temporary = dirname($root . '/' . $path) . '/.kis-bar-' . bin2hex(random_bytes(12)) . '.tmp';
            $handle = fopen($temporary, 'xb');
            if ($handle === false) {
                throw new RuntimeException('Cannot stage redirect.');
            }
            $staged[$path] = $temporary;
            $body = barLegacyUrlWrapper($path);
            $written = fwrite($handle, $body);
            fclose($handle);
            if ($written !== strlen($body) || !chmod($temporary, $meta['mode'])
                || hash_file('sha256', $temporary) !== $meta['after']) {
                throw new RuntimeException('Cannot verify staged redirect.');
            }
        }
        foreach ($files as $path => $meta) {
            $absolute = $root . '/' . $path;
            $current = is_file($absolute) ? hash_file('sha256', $absolute) : null;
            if (is_link($absolute) || $current !== $meta['before'] || !rename($staged[$path], $absolute)) {
                throw new RuntimeException('Partial URL cutover; inspect the private receipt before any retry.');
            }
            unset($staged[$path]);
        }
    } finally {
        foreach ($staged as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}

function barLegacyUrlMain(): void
{
    umask(0077);
    $result = ['ok' => false, 'phase' => 'identity', 'database_writes' => false];
    try {
        $mode = (string)getenv('BAR_URL_MODE');
        if (getenv('APP_HOST') !== 'kis.kovopraha.cz' || !in_array($mode, ['prepare', 'apply', 'status'], true)
            || getenv('BAR_URL_CONFIRM') !== ($mode === 'apply' ? 'PREPNOUT_BAR' : 'OVERIT')) {
            throw new RuntimeException('Unexpected operation.');
        }
        $root = realpath((string)getenv('BAR_LEGACY_ROOT'));
        $private = realpath((string)getenv('BAR_URL_PRIVATE'));
        if ($root === false || $private === false || basename($root) !== 'bar.kovopraha.cz'
            || basename(dirname($private)) !== '.kis-backups' || basename(dirname(dirname($private))) !== 'data'
            || !preg_match('/^bar-url-[1-9][0-9]{0,19}$/D', basename($private)) || is_link((string)getenv('BAR_URL_PRIVATE'))
            || str_starts_with($private . '/', $root . '/')) {
            throw new RuntimeException('Unexpected private paths.');
        }
        if ($mode === 'prepare') {
            $result['phase'] = 'webroot';
            $context = stream_context_create(['http' => ['timeout' => 30, 'follow_location' => 0, 'ignore_errors' => true],
                'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
            $served = file_get_contents('https://bar.kovopraha.cz/terminal/sw.js', false, $context);
            if (!is_string($served) || !is_file($root . '/terminal/sw.js')
                || !hash_equals((string)hash_file('sha256', $root . '/terminal/sw.js'), hash('sha256', $served))) {
                throw new RuntimeException('Legacy webroot is not proven.');
            }
            $result['phase'] = 'snapshot';
            $files = barLegacyUrlInventory($root);
            require_once __DIR__ . '/production-file-backup.php';
            $backup = productionFileBackup(['app' => $root], $private . '/source.zip');
            if (barLegacyUrlInventory($root) !== $files) {
                throw new RuntimeException('Legacy entry points changed while archiving.');
            }
            file_put_contents($private . '/plan.json', json_encode(['files' => $files, 'backup' => $backup], JSON_THROW_ON_ERROR));
            $result['backup'] = $backup;
        } else {
            $plan = json_decode((string)file_get_contents($private . '/plan.json'), true, 32, JSON_THROW_ON_ERROR);
            if ($mode === 'apply') {
                $result['phase'] = 'restore_proof';
                $proof = json_decode((string)file_get_contents($private . '/restored.json'), true, 8, JSON_THROW_ON_ERROR);
                if (($proof['ok'] ?? null) !== true || ($proof['sha256'] ?? null) !== $plan['backup']['sha256']
                    || ($proof['files'] ?? null) !== $plan['backup']['files'] || filemtime($private . '/source.zip') < time() - 3600
                    || !hash_equals($plan['backup']['sha256'], (string)hash_file('sha256', $private . '/source.zip'))) {
                    throw new RuntimeException('Complete fresh source restore is not proven.');
                }
                $result['phase'] = 'replace';
                barLegacyUrlApply($root, $plan['files']);
            }
            $result['phase'] = 'verify';
            $files = barLegacyUrlInventory($root);
            if (array_keys($files) !== array_keys($plan['files'])) {
                throw new RuntimeException('Legacy entry point set differs.');
            }
            foreach ($files as $path => $meta) {
                if ($meta['before'] !== $plan['files'][$path]['after']) {
                    throw new RuntimeException('Legacy redirect content differs.');
                }
            }
            $result['http'] = barLegacyUrlVerifyHttp(array_keys($files));
        }
        $result += ['mode' => $mode, 'entry_points' => count($files), 'no_request_replay' => true,
            'query_parameters_discarded' => true, 'temporary_redirects' => true];
        $result['ok'] = true;
        $result['phase'] = 'complete';
    } catch (Throwable $error) {
        $result['error_type'] = get_debug_type($error);
        $result['message'] = 'bar_url_failed_inspect_before_retry';
    }
    $json = json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    file_put_contents(__DIR__ . '/result.json', $json . PHP_EOL, LOCK_EX);
    echo $json, PHP_EOL;
    exit($result['ok'] ? 0 : 1);
}

/** Read requests only, after every served PHP file has the exact reviewed wrapper. */
function barLegacyUrlVerifyHttp(array $paths): array
{
    $checked = 0;
    foreach ($paths as $path) {
        $target = barLegacyUrlTarget($path);
        $context = stream_context_create(['http' => ['timeout' => 15, 'follow_location' => 0, 'ignore_errors' => true,
            'header' => "Cache-Control: no-cache\r\n"], 'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $http_response_header = [];
        $body = file_get_contents('https://bar.kovopraha.cz/' . $path . '?id=999999&cutover_check=1', false, $context);
        $headers = $http_response_header;
        $api = in_array($path, ['terminal/api.php', 'admin/faktury_api.php'], true);
        if (!is_string($body) || !preg_match('~^HTTP/\S+ ' . ($api ? '410' : '302') . '\b~', $headers[0] ?? '')
            || !in_array('cache-control: no-store', array_map('strtolower', $headers), true)) {
            throw new RuntimeException('Legacy HTTP response differs.');
        }
        if ($api) {
            $data = json_decode($body, true, 4, JSON_THROW_ON_ERROR);
            if (($data['error'] ?? null) !== 'bar_moved_to_kis' || ($data['url'] ?? null) !== $target) {
                throw new RuntimeException('Legacy API was not retired.');
            }
        } elseif (!in_array('location: ' . $target, array_map('strtolower', $headers), true)) {
            throw new RuntimeException('Legacy redirect destination differs.');
        }
        $checked++;
    }
    return ['ok' => true, 'get_requests' => $checked, 'business_writes' => false];
}
