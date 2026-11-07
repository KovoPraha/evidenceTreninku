<?php

declare(strict_types=1);

// Diagnostic server only; never required by the application or the plain timing server.
(static function (): void {
    $start = hrtime(true);
    $id = (string)($_SERVER['HTTP_X_P11_MEASURE'] ?? '');
    $directory = (string)getenv('P11_HTTP_METRICS');
    if (PHP_SAPI !== 'cli-server' || getenv('EVIDENCE_PERFORMANCE_CONFIRM') !== 'SYNTHETIC-ONLY'
        || ($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1' || !is_dir($directory)
        || !preg_match('/^[a-z][a-z0-9_-]{1,100}$/D', $id)) {
        return;
    }
    register_shutdown_function(static function () use ($start, $id, $directory): void {
        $fatal = error_get_last();
        $files = array_map('basename', get_included_files());
        $report = ['id' => $id, 'php_ms' => (hrtime(true) - $start) / 1e6,
            'peak_php_bytes' => memory_get_peak_usage(), 'allocated_peak_bytes' => memory_get_peak_usage(true),
            'status' => http_response_code(), 'db_bootstrap_included' => in_array('db.php', $files, true),
            'navigation_included' => in_array('hlavicka.php', $files, true), 'included_file_count' => count($files),
            'fatal' => $fatal !== null && in_array($fatal['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)];
        file_put_contents($directory . '/' . $id . '.json', json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
    });
})();
