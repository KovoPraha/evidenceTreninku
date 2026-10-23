<?php
declare(strict_types=1);
// Read-only local readiness probe. Run with the isolated php-web.ini.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
try {
    $app = dirname(__DIR__);
    $runtime = getenv('LOCALAPPDATA') . '/EvidencePavelB1/finance-b1-20261022';
    $assert = static function (bool $valid, string $message): void { if (!$valid) throw new RuntimeException($message); };
    $normalize = static fn(string $path): string => strtolower(rtrim(str_replace('\\', '/', $path), '/'));
    $assert(trim((string)file_get_contents($runtime . '/owner.txt')) === $app, 'Unexpected sandbox owner.');
    $descriptor = json_decode((string)file_get_contents($runtime . '/environment.json'), true, 512, JSON_THROW_ON_ERROR);
    $assert(($descriptor['synthetic_only'] ?? false) === true && ($descriptor['external_sends_disabled'] ?? false) === true, 'Sandbox descriptor is not isolated.');
    require $app . '/config.php';
    $assert(DB_HOST === '127.0.0.1;port=33426' && DB_NAME === 'evidence_b1' && DB_USER === 'b1_app' && APP_BASE_URL === 'http://127.0.0.1:18086', 'Unexpected application endpoint.');
    $assert($normalize((string)php_ini_loaded_file()) === $normalize($runtime . '/php-web.ini'), 'Run this probe with the isolated php-web.ini.');
    $assert(!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN) && !filter_var(ini_get('allow_url_include'), FILTER_VALIDATE_BOOLEAN), 'URL streams remain enabled.');
    foreach (['mail','curl_exec','curl_multi_exec','fsockopen','pfsockopen','stream_socket_client','stream_socket_server','socket_connect','exec','shell_exec','system','passthru','popen','proc_open','pcntl_exec'] as $function) {
        $assert(!function_exists($function), 'External transport/process function remains callable: ' . $function);
    }
    foreach (['FIO_IMPORT_ENABLED','FIO_AUTO_CONFIRM_ENABLED','SUMUP_ENABLED','STRIPE_ENABLED','VELOCOTA_INTEGRATION'] as $flag) $assert(defined($flag) && constant($flag) === false, 'External integration is not disabled: ' . $flag);
    foreach (['private' => APP_PRIVATE_STORAGE_ROOT, 'sessions' => session_save_path(), 'upload-tmp' => ini_get('upload_tmp_dir')] as $name => $path) {
        $real = realpath((string)$path);
        $assert($real !== false && $normalize($real) === $normalize($runtime . '/' . $name), 'Unexpected private runtime path: ' . $name);
        $assert(!str_starts_with($normalize($real) . '/', $normalize($app) . '/'), 'Private runtime is inside the web root.');
    }
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $identity = $pdo->query('SELECT DATABASE() db_name, @@port port, @@datadir path')->fetch(PDO::FETCH_ASSOC);
    $assert($identity['db_name'] === 'evidence_b1' && (int)$identity['port'] === 33426 && $normalize($identity['path']) === $normalize($runtime . '/mysql'), 'Unexpected database identity.');
    require_once $app . '/bin/finance-b1-readiness.php';
    // These three optional legacy integrations were absent in the verified B1 baseline.
    $tables = financeB1TableReadiness($pdo, financeB1OwnedTables($app), ['ucto_gs_kategorie','ucto_gs_link_targets','ucto_gs_linky']);
    echo json_encode(['status' => $tables['status'], 'read_only' => true, 'checked_at' => date(DATE_ATOM), 'database_identity' => true, 'table_readiness' => $tables, 'external_transports_disabled' => true, 'private_paths_outside_web_root' => true, 'production_authorized' => false], JSON_THROW_ON_ERROR) . PHP_EOL;
    if ($tables['status'] !== 'PASS') exit(1);
} catch (Throwable $error) {
    // Deliberately omit exception details that might include a database DSN or input.
    fwrite(STDERR, "Isolated readiness probe FAILED; verify owner, local config, restricted ini, private paths and database identity.\n");
    exit(1);
}
