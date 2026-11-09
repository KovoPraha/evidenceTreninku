<?php

declare(strict_types=1);

// Diagnostic clones only: preserve the normal bootstrap/authorization sequence.
if (PHP_SAPI !== 'cli-server' || getenv('EVIDENCE_PERFORMANCE_CONFIRM') !== 'SYNTHETIC-ONLY'
    || ($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1') {
    return;
}
$diagnosticId = (string) ($_SERVER['HTTP_X_P11_MEASURE'] ?? '');
$diagnosticDirectory = (string) getenv('P11_HTTP_METRICS');
if (!preg_match('/^targeted-(before|after)-(parent|history|measurements)-(1001|1012|1013|1020)$/D', $diagnosticId) || !is_dir($diagnosticDirectory)) {
    return;
}
require_once __DIR__ . '/QueryProfilePdo.php';
register_shutdown_function(static function () use ($diagnosticId, $diagnosticDirectory): void {
    $applicationPeak = memory_get_peak_usage();
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
    // EXPLAIN after rendering on the same owned connection; retain no bind values.
    $connection->profiling = false;
    $plans = [];
    foreach ($connection->profile as $number => $query) {
        if (!preg_match('/^\s*SELECT\b/i', $query['sql']) || !str_contains($query['sql'], 'treninky')) {
            continue;
        }
        $statement = $connection->prepare('EXPLAIN FORMAT=JSON ' . $query['sql']);
        $statement->execute($query['parameters']);
        $rawPlan = $statement->fetchColumn();
        $plans[$number] = ['raw_json' => $rawPlan, 'parsed' => json_decode($rawPlan, true, 512, JSON_THROW_ON_ERROR)];
    }
    $schema = [];
    foreach (['treninky', 'trenink_sportovec', 'trenink_mereni', 'mereni_zaznamy', 'sportovec_poznamka'] as $table) {
        $schema[$table] = [
            'columns' => $connection->query('SHOW COLUMNS FROM ' . $table)->fetchAll(PDO::FETCH_ASSOC),
            'indexes' => $connection->query('SHOW INDEX FROM ' . $table)->fetchAll(PDO::FETCH_ASSOC),
        ];
    }
    $reader = (new ReflectionClass(AthleteProfileRead::class))->getFileName();
    file_put_contents($diagnosticDirectory . '/' . $diagnosticId . '.json', json_encode([
        'diagnostic_id' => $diagnosticId, 'history_year' => isset($_GET['rok']) ? (int) $_GET['rok'] : null,
        'status' => http_response_code(), 'bootstrap_preloaded' => false, 'timing_instrumented' => true,
        'peak_php_bytes' => $applicationPeak, 'queries' => $safe,
        'peak_includes_sql_profiler' => true, 'peak_excludes_later_explain_and_schema' => true,
        'executed_reader_sha256' => hash_file('sha256', $reader),
        'opcache_enable_cli' => ini_get('opcache.enable_cli'),
        'explain' => $plans,
        'schema_metadata' => $schema,
        'emulated_prepares' => $connection->getAttribute(PDO::ATTR_EMULATE_PREPARES),
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
});
