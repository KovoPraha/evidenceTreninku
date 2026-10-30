<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/accounting_export_read.php';
require_once __DIR__ . '/AccountingBalanceBaseline.php';

if (($argv[1] ?? '') === '--worker') {
    $size = (int) $argv[2];
    $mode = $argv[3];
    if (!in_array($size, [1000, 10000, 100000], true) || !in_array($mode, ['baseline', 'current'], true)) {
        throw new RuntimeException('Invalid synthetic profile.');
    }
    $snapshot = ['period_from' => '2026-01-01', 'accounts' => [['id' => 1, 'code' => 'SYN', 'name' => 'Synthetic cash',
        'opening_minor' => 100, 'opened_on' => '2025-01-01', 'currency' => 'CZK']], 'movements' => [], 'allocations' => [],
        'classifications' => [], 'refund_decisions' => [], 'adjustment_events' => [], 'refund_payments' => [],
        'reconciliations' => [['account_id' => 1, 'as_of' => '2026-01-31', 'is_closed' => 1, 'difference_minor' => 0]]];
    for ($id = 1; $id <= $size; $id++) {
        $snapshot['movements'][] = ['id' => $id, 'account_id' => 1, 'kind' => 'income', 'amount_minor' => 10001,
            'currency' => 'CZK', 'effective_on' => '2026-01-01'];
        $snapshot['allocations'][] = ['movement_id' => $id, 'amount_minor' => 1];
        $snapshot['classifications'][] = ['movement_id' => $id];
    }
    $call = $mode === 'baseline' ? 'w10BalanceRowsBaseline' : 'accountingExportFinancialBalanceRows';
    $exceptions = $mode === 'baseline' ? 'w10FinancialExceptionsBaseline' : 'accountingExportFinancialExceptions';
    $samples = [];
    $hash = null;
    $iterations = $mode === 'baseline' && $size >= 10000 ? 1 : 30;
    $warmups = $iterations === 1 ? 0 : 5;
    for ($sample = -$warmups; $sample < $iterations; $sample++) {
        memory_reset_peak_usage();
        $memory = memory_get_usage();
        $start = hrtime(true);
        $rows = $call($snapshot);
        $errors = $exceptions($snapshot, '2026-01-31');
        $ms = (hrtime(true) - $start) / 1e6;
        $peak = memory_get_peak_usage() - $memory;
        if ($errors !== [] || count($rows) !== $size + 1
            || $rows[0] !== ['Účet', '1', 'Synthetic cash', 1, $size * 10001 / 100, (100 + $size * 10001) / 100, 'CZK']) {
            throw new RuntimeException('Synthetic financial totals changed.');
        }
        for ($id = 1; $id <= $size; $id++) {
            if ($rows[$id] !== ['Nepřiděleno', (string) $id, 'Příjem po přídělech a skutečných vratkách', null, null, 100, 'CZK']) {
                throw new RuntimeException('Synthetic allocation meaning/order changed.');
            }
        }
        $currentHash = hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
        if ($hash !== null && $hash !== $currentHash) {
            throw new RuntimeException('Unstable pure projection.');
        }
        $hash = $currentHash;
        unset($rows, $errors);
        if ($sample >= 0) {
            $samples[] = ['ms' => $ms, 'peak_bytes' => $peak];
        }
    }
    $median = static function (array $values): float {
        sort($values);
        $middle = intdiv(count($values), 2);
        return count($values) % 2 ? $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2;
    };
    echo json_encode(['size' => $size, 'mode' => $mode, 'rows' => $size + 1, 'sha256' => $hash, 'warmups' => $warmups,
        'samples' => $samples, 'median_ms' => $median(array_column($samples, 'ms')),
        'median_peak_bytes' => $median(array_column($samples, 'peak_bytes')), 'analytical_financial_contract' => true], JSON_THROW_ON_ERROR);
    exit;
}

$directory = sys_get_temp_dir() . '/w10-read-' . bin2hex(random_bytes(8));
mkdir($directory, 0700);
$results = [];
try {
    foreach ([1000, 10000, 100000] as $size) {
        $hash = null;
        foreach (['baseline', 'current'] as $mode) {
            $path = $directory . '/' . $size . '-' . $mode;
            $process = proc_open(
                [PHP_BINARY, '-d', 'memory_limit=512M', __FILE__, '--worker', (string) $size, $mode],
                [0 => ['pipe', 'r'], 1 => ['file', $path . '.out', 'w'], 2 => ['file', $path . '.err', 'w']],
                $pipes
            );
            if (!is_resource($process)) {
                throw new RuntimeException('Profile worker failed to start.');
            }
            fclose($pipes[0]);
            $timeout = $size === 100000 && $mode === 'baseline' ? 15 : 300;
            $deadline = microtime(true) + $timeout;
            do {
                $status = proc_get_status($process);
                if (!$status['running']) {
                    break;
                }
                usleep(50000);
            } while (microtime(true) < $deadline);
            if ($status['running']) {
                proc_terminate($process);
                proc_close($process);
                if ($size !== 100000 || $mode !== 'baseline') {
                    throw new RuntimeException('Unexpected profile timeout.');
                }
                $results[] = ['size' => $size, 'mode' => $mode, 'timeout_seconds' => $timeout,
                    'measurement_complete' => false, 'extrapolated' => false];
                continue;
            }
            proc_close($process);
            $stderr = trim((string) file_get_contents($path . '.err'));
            if ($status['exitcode'] !== 0 || $stderr !== '') {
                throw new RuntimeException('Profile worker failed: ' . $stderr);
            }
            $result = json_decode((string) file_get_contents($path . '.out'), true, 512, JSON_THROW_ON_ERROR);
            if ($hash !== null && $hash !== $result['sha256']) {
                throw new RuntimeException('Frozen baseline differs.');
            }
            $hash = $result['sha256'];
            if ($mode === 'current' && $result['median_peak_bytes'] > 128 * 1024 * 1024) {
                throw new RuntimeException('Pure projection exceeds 128 MiB additional memory.');
            }
            $results[] = $result;
        }
    }
    echo json_encode(['production_mutations' => false, 'scope' => 'pure financial rows and exceptions; not SQL or XLSX serialization',
        'results' => $results], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
} finally {
    foreach (glob($directory . '/*') ?: [] as $path) {
        unlink($path);
    }
    rmdir($directory);
}
