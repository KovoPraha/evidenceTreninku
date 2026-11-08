<?php

declare(strict_types=1);

// Owned diagnostic worker only; excluded from uninstrumented timing.
if (PHP_SAPI !== 'cli-server' || getenv('EVIDENCE_PERFORMANCE_CONFIRM') !== 'SYNTHETIC-ONLY') {
    return;
}
$id = (string) ($_SERVER['HTTP_X_P11_MEASURE'] ?? '');
$directory = (string) getenv('P11_HTTP_METRICS');
if (!preg_match('/^profile-(before|after)$/D', $id) || !is_dir($directory)) {
    return;
}
register_shutdown_function(static function () use ($id, $directory): void {
    file_put_contents($directory . '/' . $id . '.json', json_encode([
        'session_active_at_shutdown' => session_status() === PHP_SESSION_ACTIVE,
        'handler' => ini_get('session.save_handler'),
        'identity_present' => isset($_SESSION['trener_id']),
        'csrf_present' => !empty($_SESSION['csrf_token']),
        'worker_pid' => getmypid(),
    ], JSON_THROW_ON_ERROR));
});
