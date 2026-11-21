<?php

declare(strict_types=1);

/** Own only the parent access-mail cron file in the hosting minute directory. */
function parentAuthMailPaths(string $root, string $operations): array
{
    if (is_link($root) || is_link($operations) || is_link(dirname($root) . '/data')) {
        throw new RuntimeException('parent_mail_symlink_scope');
    }
    $root = realpath($root);
    $operations = realpath($operations);
    if ($root === false || $operations === false || basename($root) !== 'kis.kovopraha.cz'
        || $operations !== realpath(dirname($root) . '/data/.kis-deploy')) {
        throw new RuntimeException('parent_mail_scope');
    }
    return [
        'root' => $root,
        'operations' => $operations,
        'cron' => dirname($root) . '/CRON.1minuta/evidence-parent-auth-mail.php',
        'heartbeat' => $operations . '/parent-auth-mail-heartbeat.json',
    ];
}

function parentAuthMailCronSource(array $paths): string
{
    return "<?php\n// EvidencePavel parent verification and reset; owned scheduler v1.\n"
        . "if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }\n"
        . "putenv('APP_HOST=kis.kovopraha.cz');\n"
        . "putenv('PARENT_AUTH_MAIL_ACTION=process');\n"
        . "putenv('PARENT_AUTH_MAIL_TRIGGER=hosting_cron');\n"
        . 'putenv(' . var_export('PARENT_AUTH_MAIL_OPERATIONS_DIR=' . $paths['operations'], true) . ");\n"
        . 'require ' . var_export($paths['root'] . '/bin/parent-auth-mail-operations.php', true) . ";\n";
}

function parentAuthMailSchedulerStatus(array $paths): array
{
    $cron = $paths['cron'];
    $installed = is_file($cron) && !is_link($cron)
        && hash_equals(hash('sha256', parentAuthMailCronSource($paths)), (string)hash_file('sha256', $cron));
    $heartbeat = null;
    if (is_file($paths['heartbeat']) && !is_link($paths['heartbeat'])) {
        $value = json_decode((string)file_get_contents($paths['heartbeat']), true);
        if (is_array($value) && ($value['trigger'] ?? null) === 'hosting_cron') {
            $heartbeat = $value;
        }
    }
    $at = strtotime((string)($heartbeat['completed_at'] ?? ''));
    return [
        'hosting_directory_available' => is_dir(dirname($cron)) && !is_link(dirname($cron)) && is_writable(dirname($cron)),
        'installed_expected_source' => $installed,
        'executable' => $installed && is_executable($cron),
        'last_hosting_run' => $heartbeat,
        'recent_successful_hosting_run' => $installed && ($heartbeat['ok'] ?? false) === true
            && $at !== false && $at <= time() && $at >= time() - 180,
    ];
}

function parentAuthMailInstall(array $paths): array
{
    $directory = dirname($paths['cron']);
    if (!is_dir($directory) || is_link($directory) || !is_writable($directory)) {
        throw new RuntimeException('parent_mail_hosting_directory');
    }
    $lockPath = $paths['operations'] . '/parent-auth-mail-install.lock';
    if (is_link($lockPath)) {
        throw new RuntimeException('parent_mail_install_lock_symlink');
    }
    $lock = fopen($lockPath, 'c');
    if ($lock === false || !flock($lock, LOCK_EX)) {
        throw new RuntimeException('parent_mail_install_lock');
    }
    $temporary = null;
    try {
        if (file_exists($paths['cron']) || is_link($paths['cron'])) {
            if (!parentAuthMailSchedulerStatus($paths)['installed_expected_source']) {
                throw new RuntimeException('parent_mail_foreign_cron');
            }
            return ['installed' => true, 'changed' => false];
        }
        $source = parentAuthMailCronSource($paths);
        $temporary = tempnam($paths['operations'], 'parent-mail-cron-');
        if ($temporary === false || file_put_contents($temporary, $source) !== strlen($source)
            || !chmod($temporary, 0700) || !rename($temporary, $paths['cron'])) {
            throw new RuntimeException('parent_mail_install_failed');
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

function parentAuthMailHeartbeat(array $paths, array $result, bool $ok): void
{
    if (is_link($paths['heartbeat'])) {
        throw new RuntimeException('parent_mail_heartbeat_symlink');
    }
    $payload = ['trigger' => 'hosting_cron', 'completed_at' => gmdate(DATE_ATOM), 'ok' => $ok, 'result' => $result];
    $temporary = tempnam($paths['operations'], 'parent-mail-heartbeat-');
    try {
        if ($temporary === false || file_put_contents($temporary, json_encode($payload, JSON_THROW_ON_ERROR)) === false
            || !chmod($temporary, 0600) || !rename($temporary, $paths['heartbeat'])) {
            throw new RuntimeException('parent_mail_heartbeat_write');
        }
        $temporary = null;
    } finally {
        if (is_string($temporary) && is_file($temporary)) {
            unlink($temporary);
        }
    }
}
