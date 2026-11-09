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
    // EXPLAIN after the rendered response, on the same owned connection. Compare
    // the rejected free join order with the current bounded order; retain no binds.
    $connection->profiling = false;
    $plans = [];
    foreach ($connection->profile as $query) {
        if (!str_contains($query['sql'], 'tm.trenink_datum')) {
            continue;
        }
        $templates = ['current' => $query['sql']];
        $rejectedPath = dirname(__DIR__, 2) . '/docs/verification/athlete-profile-measurement-performance-20261109/'
            . 'rejected-bounded/native-10.3/diagnostic-after.json';
        $rejected = json_decode(file_get_contents($rejectedPath), true, 512, JSON_THROW_ON_ERROR);
        foreach ($rejected['queries'] as $old) {
            if (str_contains($old['sql'], 'tm.trenink_datum') && substr_count($old['sql'], '?') === count($query['parameters'])) {
                $templates['rejected_bounded'] = $old['sql'];
            }
        }
        foreach ($templates as $label => $sql) {
            $statement = $connection->prepare('EXPLAIN FORMAT=JSON ' . $sql);
            $statement->execute($query['parameters']);
            $rawPlan = $statement->fetchColumn();
            $plans[$label] = ['raw_json' => $rawPlan, 'parsed' => json_decode($rawPlan, true, 512, JSON_THROW_ON_ERROR)];
        }
    }
    $schema = [];
    foreach (['treninky', 'trenink_mereni', 'mereni_zaznamy'] as $table) {
        $schema[$table] = [
            'columns' => $connection->query('SHOW COLUMNS FROM ' . $table)->fetchAll(PDO::FETCH_ASSOC),
            'indexes' => $connection->query('SHOW INDEX FROM ' . $table)->fetchAll(PDO::FETCH_ASSOC),
        ];
    }
    $reader = (new ReflectionClass(AthleteProfileRead::class))->getFileName();
    file_put_contents($diagnosticDirectory . '/' . $diagnosticId . '.json', json_encode([
        'status' => http_response_code(), 'bootstrap_preloaded' => false, 'timing_instrumented' => true,
        'peak_php_bytes' => memory_get_peak_usage(), 'queries' => $safe,
        'executed_reader_sha256' => hash_file('sha256', $reader),
        'opcache_enable_cli' => ini_get('opcache.enable_cli'),
        'explain' => $plans,
        'schema_metadata' => $schema,
        'emulated_prepares' => $connection->getAttribute(PDO::ATTR_EMULATE_PREPARES),
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
});
