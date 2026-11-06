<?php

declare(strict_types=1);

/** Fixed UTC time preserves the existing 05:23 UTC contract across DST. */
function membershipSchedulerDue(array $state, int $now, ?int $success): bool
{
    $day = gmdate('Y-m-d', $now);
    $due = strtotime($day . ' 05:23:00 UTC');
    if ($success !== null && $success > $now) {
        throw new RuntimeException('membership_scheduler_future_success');
    }
    return $now >= $due && ($success === null || gmdate('Y-m-d', $success) !== $day)
        && $now >= (int)($state['last_attempt_at'] ?? 0) + 600;
}

function membershipSchedulerPaths(string $root, string $operations): array
{
    if (is_link($root) || is_link($operations) || is_link(dirname($root) . '/data')
        || is_link(dirname($root) . '/data/.kis-deploy')) {
        throw new RuntimeException('membership_scheduler_symlink');
    }
    $root = realpath($root);
    $operations = realpath($operations);
    if ($root === false || $operations === false || basename($root) !== 'kis.kovopraha.cz'
        || $operations !== realpath(dirname($root) . '/data/.kis-deploy')) {
        throw new RuntimeException('membership_scheduler_scope');
    }
    return ['root' => $root, 'operations' => $operations,
        'cron' => dirname($root) . '/CRON.1minuta/evidence-monthly-memberships.php',
        'state' => $operations . '/membership-scheduler-state.json',
        'lock' => $operations . '/membership-scheduler.lock'];
}

function membershipSchedulerSource(array $paths): string
{
    return "<?php\n// KIS daily memberships, fixed UTC 05:23; existing flags only.\n"
        . "if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }\n"
        . "putenv('APP_HOST=kis.kovopraha.cz');\nputenv('MEMBERSHIP_SCHEDULER_ACTION=tick');\n"
        . "putenv('MEMBERSHIP_SCHEDULER_TRIGGER=hosting_cron');\n"
        . 'putenv(' . var_export('MEMBERSHIP_SCHEDULER_DIR=' . $paths['operations'], true) . ");\n"
        . 'require ' . var_export($paths['root'] . '/bin/monthly-membership-operations.php', true) . ";\n";
}

function membershipSchedulerRead(array $paths): array
{
    if (is_link($paths['state'])) {
        throw new RuntimeException('membership_scheduler_state_symlink');
    }
    if (!is_file($paths['state'])) {
        return [];
    }
    $state = json_decode((string)file_get_contents($paths['state']), true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($state) || ($state['version'] ?? null) !== 1 || ($state['trigger'] ?? '') !== 'hosting_cron') {
        throw new RuntimeException('membership_scheduler_invalid_state');
    }
    return $state;
}

function membershipSchedulerWrite(array $paths, array $state): void
{
    if (is_link($paths['state'])) {
        throw new RuntimeException('membership_scheduler_state_symlink');
    }
    $text = json_encode($state, JSON_THROW_ON_ERROR);
    $tmp = tempnam($paths['operations'], 'membership-state-');
    try {
        if ($tmp === false || file_put_contents($tmp, $text) !== strlen($text)
            || !chmod($tmp, 0600) || !rename($tmp, $paths['state'])) {
            throw new RuntimeException('membership_scheduler_state_write');
        }
        $tmp = null;
    } finally {
        if (is_string($tmp) && is_file($tmp)) {
            unlink($tmp);
        }
    }
}

function membershipSchedulerStatus(array $paths, ?int $success, ?int $now = null): array
{
    $now ??= time();
    $installed = is_file($paths['cron']) && !is_link($paths['cron'])
        && hash_equals(hash('sha256', membershipSchedulerSource($paths)), (string)hash_file('sha256', $paths['cron']));
    $state = membershipSchedulerRead($paths);
    $tick = (int)($state['last_tick_at'] ?? 0);
    return ['schedule_utc' => '05:23', 'poll_seconds' => 60,
        'installed_expected_source' => $installed, 'last_success_utc' => $success === null ? null : gmdate(DATE_ATOM, $success),
        'recent_hosting_tick' => $installed && $tick > 0 && $tick <= $now && $now - $tick <= 180,
        'daily_due' => membershipSchedulerDue([], $now, $success),
        'completed_today' => $success !== null && gmdate('Y-m-d', $success) === gmdate('Y-m-d', $now), 'state' => $state];
}

/** Never provision a cron directory or overwrite foreign files. */
function membershipSchedulerInstall(array $paths): array
{
    if (!is_dir(dirname($paths['cron'])) || is_link(dirname($paths['cron'])) || !is_writable(dirname($paths['cron']))) {
        throw new RuntimeException('membership_scheduler_directory');
    }
    $source = membershipSchedulerSource($paths);
    if (is_link($paths['lock'])) {
        throw new RuntimeException('membership_scheduler_lock_symlink');
    }
    $lock = fopen($paths['lock'], 'c');
    if ($lock === false || !flock($lock, LOCK_EX)) {
        throw new RuntimeException('membership_scheduler_lock');
    }
    $tmp = null;
    try {
        if (file_exists($paths['cron']) || is_link($paths['cron'])) {
            if (!membershipSchedulerStatus($paths, null)['installed_expected_source']) {
                throw new RuntimeException('membership_scheduler_foreign_file');
            }
            return ['installed' => true, 'changed' => false];
        }
        $tmp = tempnam($paths['operations'], 'membership-cron-');
        if ($tmp === false || file_put_contents($tmp, $source) !== strlen($source)
            || !chmod($tmp, 0700) || !rename($tmp, $paths['cron'])) {
            throw new RuntimeException('membership_scheduler_install');
        }
        $tmp = null;
    } finally {
        if (is_string($tmp) && is_file($tmp)) {
            unlink($tmp);
        }
        flock($lock, LOCK_UN);
        fclose($lock);
    }
    return ['installed' => true, 'changed' => true];
}

/** Success is read under the scheduler lock; a status/manual call cannot invent a tick. */
function membershipSchedulerTick(array $paths, callable $lastSuccess, callable $run, ?int $now = null, bool $enabled = true): array
{
    $now ??= time();
    if (is_link($paths['lock'])) {
        throw new RuntimeException('membership_scheduler_lock_symlink');
    }
    $lock = fopen($paths['lock'], 'c');
    if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
        if (is_resource($lock)) {
            fclose($lock);
        }
        return ['skipped' => 'already_running'];
    }
    try {
        $state = membershipSchedulerRead($paths);
        $state['version'] = 1;
        $state['trigger'] = 'hosting_cron';
        $state['last_tick_at'] = $now;
        if (!$enabled) {
            $state['outcome'] = 'disabled';
        } elseif (!membershipSchedulerDue($state, $now, $lastSuccess())) {
            $state['outcome'] = 'not_due';
        } else {
            $state['last_attempt_at'] = $now;
            $state['outcome'] = 'running';
            membershipSchedulerWrite($paths, $state);
            try {
                $result = $run();
                $state['outcome'] = ($result['errors'] ?? ['invalid_result']) === [] ? 'completed' : 'failed';
                $state['result'] = $result;
            } catch (Throwable) {
                $state['outcome'] = 'failed';
                $state['result'] = ['error' => 'membership_worker_failed'];
            }
        }
        membershipSchedulerWrite($paths, $state);
        return ['outcome' => $state['outcome']];
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
