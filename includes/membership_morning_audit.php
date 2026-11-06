<?php

declare(strict_types=1);

require_once __DIR__ . '/monthly_membership_scheduler.php';

function membershipMorningPaths(string $root, string $operations): array
{
    $paths = membershipSchedulerPaths($root, $operations);
    $operations = $paths['operations'];
    return $paths + [
        'audit_cron' => dirname($paths['root']) . '/CRON.1minuta/evidence-membership-audit-20261107.php',
        'audit_lock' => $operations . '/membership-morning-audit-20261107.lock',
        'heartbeat' => $operations . '/membership-morning-audit-heartbeat.json',
        'report' => $operations . '/membership-morning-audit-20261107.json',
        'note' => $operations . '/membership-morning-audit-20261107.md',
        'handoff' => $operations . '/membership-morning-handoff-20261107.md',
    ];
}

function membershipMorningSource(array $paths): string
{
    return "<?php\n// One-time read-only membership audit, 2026-11-07 05:30 UTC.\n"
        . "if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }\n"
        . "putenv('APP_HOST=kis.kovopraha.cz');\nputenv('MEMBERSHIP_AUDIT_ACTION=tick');\n"
        . "putenv('MEMBERSHIP_AUDIT_TRIGGER=hosting_cron');\n"
        . 'putenv(' . var_export('MEMBERSHIP_AUDIT_DIR=' . $paths['operations'], true) . ");\n"
        . 'require ' . var_export($paths['root'] . '/bin/membership-morning-audit.php', true) . ";\n";
}

function membershipMorningOwnCron(array $paths): bool
{
    return !is_link($paths['audit_cron']) && is_file($paths['audit_cron'])
        && hash_equals(hash('sha256', membershipMorningSource($paths)), (string)hash_file('sha256', $paths['audit_cron']));
}

function membershipMorningRead(string $path): ?array
{
    if (is_link($path)) {
        throw new RuntimeException('membership_audit_symlink');
    }
    if (!file_exists($path)) {
        return null;
    }
    $data = json_decode((string)file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($data) || ($data['version'] ?? null) !== 1 || ($data['target_day'] ?? '') !== '2026-11-07') {
        throw new RuntimeException('membership_audit_invalid_file');
    }
    return $data;
}

function membershipMorningWrite(array $paths, string $destination, string $text, int $mode = 0600): void
{
    if (is_link($destination)) {
        throw new RuntimeException('membership_audit_symlink');
    }
    $tmp = tempnam($paths['operations'], 'membership-audit-');
    try {
        if ($tmp === false || file_put_contents($tmp, $text) !== strlen($text) || !chmod($tmp, $mode) || !rename($tmp, $destination)) {
            throw new RuntimeException('membership_audit_write');
        }
        $tmp = null;
    } finally {
        if (is_string($tmp) && is_file($tmp)) {
            unlink($tmp);
        }
    }
}

function membershipMorningEvaluate(array $status, array $jobs, int $now): array
{
    $expected = strtotime('2026-11-07 05:23:00 UTC');
    $attempt = $status['state']['last_attempt_at'] ?? null;
    $success = isset($status['last_success_utc']) ? strtotime($status['last_success_utc']) : false;
    $delay = is_int($attempt) ? $attempt - $expected : null;
    $matched = false;
    $failed = 0;
    foreach ($jobs as $job) {
        $start = strtotime($job['started_at'] . ' UTC');
        if (is_int($attempt) && abs($start - $attempt) <= 2 && $job['status'] === 'success') {
            $matched = true;
        }
        $failed += $job['status'] === 'failed' ? 1 : 0;
    }
    $checks = [
        'captured_after_due' => $now >= strtotime('2026-11-07 05:30:00 UTC'),
        'captured_on_target_day' => gmdate('Y-m-d', $now) === '2026-11-07',
        'expected_worker_installed' => ($status['installed_expected_source'] ?? false) === true,
        'fresh_membership_tick' => ($status['recent_hosting_tick'] ?? false) === true,
        'hosting_provenance' => ($status['state']['trigger'] ?? '') === 'hosting_cron',
        'attempt_on_target_day' => is_int($attempt) && gmdate('Y-m-d', $attempt) === '2026-11-07' && $attempt <= $now,
        'attempt_not_before_due' => $delay !== null && $delay >= 0,
        'successful_ledger_matches_attempt' => $matched && count($jobs) <= 100,
        'new_success_after_attempt' => is_int($attempt) && $success !== false && $success >= $attempt && $success <= $now,
        'daily_completed' => ($status['completed_today'] ?? false) === true && ($status['daily_due'] ?? true) === false,
        'worker_result_without_errors' => ($status['state']['result']['apply'] ?? false) === true
            && ($status['state']['result']['errors'] ?? null) === [],
        'no_failed_jobs' => $failed === 0,
        'automation_and_mail_preserved' => ($status['automation_enabled'] ?? false) === true && ($status['mail_enabled'] ?? false) === true,
    ];
    $verdict = in_array(false, $checks, true) ? 'FAIL' : (($delay ?? PHP_INT_MAX) <= 60 ? 'PASS' : 'LATE');
    if (!$checks['captured_on_target_day'] || !$checks['captured_after_due']) {
        $verdict = 'BLOCKED';
    }
    $counts = [];
    foreach (['memberships', 'charges', 'documents', 'queued', 'sent'] as $key) {
        $value = $status['state']['result'][$key] ?? null;
        $counts[$key] = is_int($value) && $value >= 0 ? $value : null;
    }
    return ['version' => 1, 'target_day' => '2026-11-07', 'trigger' => 'hosting_cron', 'verdict' => $verdict,
        'captured_at_utc' => gmdate(DATE_ATOM, $now), 'expected_start_utc' => gmdate(DATE_ATOM, $expected),
        'actual_attempt_utc' => is_int($attempt) ? gmdate(DATE_ATOM, $attempt) : null,
        'start_delay_seconds' => $delay, 'last_success_utc' => $status['last_success_utc'] ?? null,
        'checks' => $checks, 'failed_jobs' => $failed, 'job_count' => count($jobs), 'counts' => $counts,
        'first_attempt_limitation' => ($delay ?? PHP_INT_MAX) > 60 || $failed > 0,
        'deferred' => ['P05/P07 actual membership/payment', 'P06 export']];
}

function membershipMorningMarkdown(array $report): string
{
    $actual = $report['actual_attempt_utc'] ?? 'nedoloženo';
    $delay = $report['start_delay_seconds'] === null ? 'nedoloženo' : (string)$report['start_delay_seconds'];
    return '# Ranní členství 7. 11. 2026 — ' . $report['verdict'] . "\n\n"
        . 'Zachyceno přímo hostingem: ' . $report['captured_at_utc'] . ".\n"
        . "Očekávaný start: 05:23 UTC (06:23 CET).\nSkutečný pokus UTC: " . $actual . ".\n"
        . 'Odchylka: ' . $delay . " s.\nPoslední úspěch UTC: " . ($report['last_success_utc'] ?? 'nedoloženo') . ".\n\n"
        . 'Kontroly: ' . json_encode($report['checks'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . ".\n\n"
        . "Při retry nemusí poslední pokus dokládat první start; viz first_attempt_limitation v JSON.\n"
        . "Audit pouze četl stav a ledger, nespustil účtování ani poštu.\n"
        . "Skutečné P05/P07 a export P06 zůstávají odložené.\n";
}

function membershipMorningStatus(array $paths): array
{
    return ['installed_expected_source' => membershipMorningOwnCron($paths),
        'heartbeat' => membershipMorningRead($paths['heartbeat']), 'report' => membershipMorningRead($paths['report'])];
}

function membershipMorningRemoveOwnCron(array $paths): void
{
    clearstatcache(true, $paths['audit_cron']);
    if (!membershipMorningOwnCron($paths) || !unlink($paths['audit_cron'])) {
        throw new RuntimeException('membership_audit_self_cleanup');
    }
}

function membershipMorningOperate(array $paths, bool $install, callable $capture, ?int $now = null): array
{
    $now ??= time();
    if (is_link($paths['audit_lock']) || is_link(dirname($paths['audit_cron']))) {
        throw new RuntimeException('membership_audit_symlink');
    }
    $lock = fopen($paths['audit_lock'], 'c');
    if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
        if (is_resource($lock)) {
            fclose($lock);
        }
        return ['skipped' => 'already_running'];
    }
    try {
        $report = membershipMorningRead($paths['report']);
        if ($install) {
            if ($report !== null) {
                return ['completed' => true, 'installed' => false];
            }
            if (!is_dir(dirname($paths['audit_cron']))) {
                throw new RuntimeException('membership_audit_cron_directory');
            }
            if (file_exists($paths['audit_cron']) || is_link($paths['audit_cron'])) {
                if (!membershipMorningOwnCron($paths)) {
                    throw new RuntimeException('membership_audit_foreign_cron');
                }
                return ['installed' => true, 'changed' => false];
            }
            membershipMorningWrite($paths, $paths['audit_cron'], membershipMorningSource($paths), 0700);
            return ['installed' => true, 'changed' => true];
        }
        if (!membershipMorningOwnCron($paths)) {
            throw new RuntimeException('membership_audit_tick_identity');
        }
        $heartbeat = ['version' => 1, 'target_day' => '2026-11-07', 'trigger' => 'hosting_cron', 'last_tick_at' => $now,
            'phase' => $report === null ? 'waiting' : 'captured'];
        if ($report === null && $now >= strtotime('2026-11-07 05:30:00 UTC')) {
            try {
                $report = $capture($now);
            } catch (Throwable) {
                $report = membershipMorningEvaluate([], [], $now);
                $report['capture_error'] = 'read_only_capture_failed';
            }
            membershipMorningWrite($paths, $paths['report'], json_encode($report, JSON_THROW_ON_ERROR));
        }
        if ($report !== null) {
            // Repair only derived notes after a crash, retaining the immutable JSON capture.
            $note = membershipMorningMarkdown($report);
            foreach (['note', 'handoff'] as $key) {
                membershipMorningWrite($paths, $paths[$key], $note);
            }
            membershipMorningRemoveOwnCron($paths);
            $heartbeat['phase'] = 'captured';
            $heartbeat['own_cron_removed'] = true;
        }
        membershipMorningWrite($paths, $paths['heartbeat'], json_encode($heartbeat, JSON_THROW_ON_ERROR));
        return ['phase' => $heartbeat['phase']];
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
