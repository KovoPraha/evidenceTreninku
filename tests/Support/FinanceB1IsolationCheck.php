<?php
declare(strict_types=1);

// Explicit opt-in CLI check; never an HTTP fixture or production route.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__, 2);
require $root . '/config.php';
require $root . '/includes/local_message_outbox.php';
require $root . '/includes/private_storage.php';
$runtime = getenv('LOCALAPPDATA') . '/EvidencePavelB1/finance-b1-20261022';
$assert = static function (bool $condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
};
$assert(DB_HOST === '127.0.0.1;port=33426' && DB_NAME === 'evidence_b1' && DB_USER === 'b1_app', 'Not the dedicated B1 database');
$assert(APP_BASE_URL === 'http://127.0.0.1:18086', 'Not the dedicated loopback HTTP URL');
$assert(FIO_IMPORT_ENABLED === false && FIO_AUTO_CONFIRM_ENABLED === false && SUMUP_ENABLED === false && STRIPE_ENABLED === false && VELOCOTA_INTEGRATION === false, 'An external integration is enabled');
$assert(!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN), 'Remote URL streams must be disabled');
foreach (['mail', 'curl_exec', 'curl_multi_exec', 'fsockopen', 'pfsockopen', 'stream_socket_client', 'socket_connect', 'exec', 'shell_exec', 'system', 'passthru', 'popen', 'proc_open'] as $function) {
    $assert(!function_exists($function), 'External transport/process function remains callable: ' . $function);
}
$pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$identity = $pdo->query('SELECT DATABASE() AS db_name, @@port AS port, @@datadir AS data_directory, VERSION() AS version')->fetch(PDO::FETCH_ASSOC);
$expectedData = strtolower(str_replace('\\', '/', $runtime . '/mysql/'));
$assert(strtolower(str_replace('\\', '/', (string)$identity['data_directory'])) === $expectedData, 'Server data directory is not isolated');
$assert((int)$identity['port'] === 33426, 'Wrong server port');
$storage = privateStorageRoot();
$assert(strtolower(str_replace('\\', '/', $storage)) === strtolower(str_replace('\\', '/', $runtime . '/private')), 'Wrong private storage');
$outbox = $runtime . '/outbox';
$before = glob($outbox . '/*.json') ?: [];
$sender = localMessageOutboxSender('127.0.0.1', 'finance-b1-isolation-v1', $outbox);
$assert($sender('synthetic-neplatit@example.invalid', 'B1 LOCALHOST NEPLATIT', 'Synthetic isolation proof; no external delivery.'), 'Local sink refused synthetic message');
$after = glob($outbox . '/*.json') ?: [];
$assert(count($after) === count($before) + 1, 'Message was not captured in local sink');
$report = [
    'ok' => true,
    'checked_at' => date(DATE_ATOM),
    'database' => $identity,
    'private_storage' => $storage,
    'session_storage' => session_save_path(),
    'external_transports_disabled' => true,
    'integration_flags_disabled' => true,
    'mail_sink_verified' => true,
    'application_root' => $root,
    'note' => 'Dedicated synthetic server; no source database copied. Use this PHP ini only for web/isolated app execution; HTTP client and race harness need normal CLI process/cURL functions.',
];
file_put_contents($runtime . '/isolation-check.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
