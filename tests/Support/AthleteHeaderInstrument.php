<?php

declare(strict_types=1);

// Separate owned diagnostic workers only. Never loaded by the plain timing workers.
if (PHP_SAPI !== 'cli-server' || getenv('EVIDENCE_PERFORMANCE_CONFIRM') !== 'SYNTHETIC-ONLY'
    || ($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1') {
    return;
}
$diagnosticId = (string) ($_SERVER['HTTP_X_P11_MEASURE'] ?? '');
$diagnosticDirectory = (string) getenv('P11_HTTP_METRICS');
if (!preg_match('/^parent-(before|after)$/D', $diagnosticId) || !is_dir($diagnosticDirectory)) {
    return;
}
require_once dirname(__DIR__, 2) . '/includes/session_security.php';
app_session_start();
require_once dirname(__DIR__, 2) . '/db.php';
if (!defined('DB_NAME') || !preg_match('/^p11_read_[a-f0-9]{16}$/D', DB_NAME)
    || !str_starts_with(DB_HOST, '127.0.0.1;port=')) {
    throw new RuntimeException('Owned synthetic database required.');
}
require_once __DIR__ . '/QueryProfilePdo.php';
$pdo = new Tests\Support\QueryProfilePdo('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS);
$pdo->traceCallers = true;
$pdo->profiling = true;
register_shutdown_function(static function () use ($pdo, $diagnosticId, $diagnosticDirectory): void {
    $safe = [];
    foreach ($pdo->profile as $query) {
        $sql = preg_replace('/\s+/', ' ', trim($query['sql']));
        $safe[] = ['sql' => $sql, 'execute_ms' => $query['execute_ms'], 'fetch_ms' => $query['fetch_ms'],
            'fetched_rows' => $query['fetched_rows'], 'caller' => $query['caller'] ?? ''];
    }
    file_put_contents($diagnosticDirectory . '/' . $diagnosticId . '.json', json_encode([
        'status' => http_response_code(), 'bootstrap_preloaded' => true, 'timing_instrumented' => true,
        'peak_php_bytes' => memory_get_peak_usage(), 'queries' => $safe,
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
});
