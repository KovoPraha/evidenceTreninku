<?php

declare(strict_types=1);

function trainerResetSchedulerIdentity(string $host, mixed $configuredBaseUrl): bool
{
    return $host === 'kis.kovopraha.cz' && $configuredBaseUrl === 'https://kis.kovopraha.cz';
}

/** Hosting-owned minute directory; never create a new scheduler directory or replace unrelated jobs. */
function trainerResetSchedulerPaths(string $root, string $operations): array
{
    $root = realpath($root);
    $operations = realpath($operations);
    if ($root === false || $operations === false || basename($root) !== 'kis.kovopraha.cz'
        || $operations !== realpath(dirname($root) . '/data/.kis-deploy')) {
        throw new RuntimeException('Unexpected trainer scheduler scope.');
    }
    return [
        'root' => $root,
        'operations' => $operations,
        'cron' => dirname($root) . '/CRON.1minuta/evidence-trainer-password-reset.php',
        'heartbeat' => $operations . '/trainer-reset-hosting-heartbeat.json',
    ];
}

function trainerResetSchedulerSource(array $paths): string
{
    return "<?php\n// EvidencePavel trainer password recovery; owned scheduler v1.\n"
        . "if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }\n"
        . "putenv('APP_HOST=kis.kovopraha.cz');\nputenv('TRAINER_RESET_ACTION=process');\n"
        . "putenv('TRAINER_RESET_TRIGGER=hosting_cron');\n"
        . 'putenv(' . var_export('TRAINER_RESET_OPERATIONS_DIR=' . $paths['operations'], true) . ");\n"
        . 'require ' . var_export($paths['root'] . '/bin/trainer-access-operations.php', true) . ";\n";
}

function trainerResetSchedulerStatus(array $paths): array
{
    $expected = trainerResetSchedulerSource($paths);
    $installed = is_file($paths['cron']) && !is_link($paths['cron'])
        && hash_equals(hash('sha256', $expected), (string)hash_file('sha256', $paths['cron']));
    $heartbeat = null;
    if (is_file($paths['heartbeat']) && !is_link($paths['heartbeat'])) {
        $value = json_decode((string)file_get_contents($paths['heartbeat']), true);
        if (is_array($value) && ($value['trigger'] ?? null) === 'hosting_cron') {
            $heartbeat = $value;
        }
    }
    $timestamp = strtotime((string)($heartbeat['completed_at'] ?? ''));
    return [
        'hosting_directory_available' => is_dir(dirname($paths['cron'])) && is_writable(dirname($paths['cron'])),
        'installed_expected_source' => $installed,
        'executable' => $installed && is_executable($paths['cron']),
        'last_hosting_run' => $heartbeat,
        'recent_successful_hosting_run' => $installed && ($heartbeat['ok'] ?? false) === true
            && $timestamp !== false && $timestamp <= time() && $timestamp >= time() - 180,
    ];
}

function trainerResetSchedulerInstall(array $paths): array
{
    $directory = dirname($paths['cron']);
    if (!is_dir($directory) || is_link($directory) || !is_writable($directory)) {
        throw new RuntimeException('Hosting minute directory unavailable.');
    }
    $lock = fopen($paths['operations'] . '/trainer-reset-install.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX)) {
        throw new RuntimeException('Scheduler installation lock unavailable.');
    }
    $temporary = null;
    try {
        if (file_exists($paths['cron']) || is_link($paths['cron'])) {
            if (!trainerResetSchedulerStatus($paths)['installed_expected_source']) {
                throw new RuntimeException('Existing scheduler file differs; refusing replacement.');
            }
            return ['installed' => true, 'changed' => false];
        }
        $source = trainerResetSchedulerSource($paths);
        $temporary = tempnam($paths['operations'], 'trainer-cron-');
        if ($temporary === false || file_put_contents($temporary, $source) !== strlen($source)
            || !chmod($temporary, 0700) || !rename($temporary, $paths['cron'])) {
            throw new RuntimeException('Cannot install trainer scheduler atomically.');
        }
        $temporary = null;
        return ['installed' => true, 'changed' => true];
    } finally {
        if (is_string($temporary) && is_file($temporary)) {
            unlink($temporary);
        }
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function trainerResetSchedulerHeartbeat(array $paths, array $result, bool $ok): void
{
    $payload = ['trigger' => 'hosting_cron', 'completed_at' => gmdate(DATE_ATOM), 'ok' => $ok, 'result' => $result];
    $temporary = tempnam($paths['operations'], 'trainer-heartbeat-');
    try {
        if ($temporary === false || file_put_contents($temporary, json_encode($payload, JSON_THROW_ON_ERROR)) === false
            || !chmod($temporary, 0600) || !rename($temporary, $paths['heartbeat'])) {
            throw new RuntimeException('Cannot persist trainer scheduler heartbeat.');
        }
        $temporary = null;
    } finally {
        if (is_string($temporary) && is_file($temporary)) {
            unlink($temporary);
        }
    }
}
