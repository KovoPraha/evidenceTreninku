<?php

declare(strict_types=1);

// Diagnostic clones only: preserve the normal bootstrap/authorization sequence.
if (PHP_SAPI !== 'cli-server' || getenv('EVIDENCE_PERFORMANCE_CONFIRM') !== 'SYNTHETIC-ONLY'
    || ($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1') {
    return;
}
$diagnosticId = (string) ($_SERVER['HTTP_X_P11_MEASURE'] ?? '');
$diagnosticDirectory = (string) getenv('P11_HTTP_METRICS');
if (!preg_match('/^measurement-(before|after)$/D', $diagnosticId) || !is_dir($diagnosticDirectory)) {
    return;
}
require_once __DIR__ . '/QueryProfilePdo.php';
register_shutdown_function(static function () use ($diagnosticId, $diagnosticDirectory): void {
    $connection = $GLOBALS['pdo'] ?? null;
    if (!$connection instanceof Tests\Support\QueryProfilePdo) {
        throw new RuntimeException('Expected owned diagnostic connection.');
    }
    $safe = [];
    foreach ($connection->profile as $query) {
        $sql = preg_replace('/\s+/', ' ', trim($query['sql']));
        $safe[] = ['sql' => $sql, 'execute_ms' => $query['execute_ms'], 'fetch_ms' => $query['fetch_ms'],
            'fetched_rows' => $query['fetched_rows'], 'caller' => $query['caller'] ?? ''];
    }
    $reader = (new ReflectionClass(AthleteProfileRead::class))->getFileName();
    file_put_contents($diagnosticDirectory . '/' . $diagnosticId . '.json', json_encode([
        'status' => http_response_code(), 'bootstrap_preloaded' => false, 'timing_instrumented' => true,
        'peak_php_bytes' => memory_get_peak_usage(), 'queries' => $safe,
        'executed_reader_sha256' => hash_file('sha256', $reader),
        'opcache_enable_cli' => ini_get('opcache.enable_cli'),
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
});
