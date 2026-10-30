<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/AccountingClubReadFixture.php';
require_once __DIR__ . '/AccountingClubBalanceBaseline.php';
require_once dirname(__DIR__, 2) . '/includes/accounting_club_read.php';

if (($argv[1] ?? '') === '--worker') {
    $size = (int) $argv[2];
    $domain = $argv[3];
    $mode = $argv[4];
    if (!in_array($size, [1000, 10000, 100000], true) || !in_array($mode, ['baseline', 'current'], true)) {
        throw new RuntimeException('Invalid profile parameters.');
    }
    $snapshot = w103ClubFixture($size, $domain);
    $expected = ['expenses' => [90, -12, 78], 'advances' => [200, -64, 136],
        'rewards' => [50, 0, 50], 'receivables' => [null, null, null]][$domain];
    $samples = [];
    $warmups = $mode === 'baseline' ? 0 : 5;
    $iterations = $mode === 'baseline' ? 1 : 30;
    for ($sample = -$warmups; $sample < $iterations; $sample++) {
        memory_reset_peak_usage();
        $memory = memory_get_usage();
        $start = hrtime(true);
        $rows = $mode === 'baseline' ? w103ClubBalanceBaseline($snapshot) : iterator_to_array(accountingClubBalanceIterator($snapshot), false);
        $ms = (hrtime(true) - $start) / 1e6;
        $peak = memory_get_peak_usage() - $memory;
        if (count($rows) !== $size) {
            throw new RuntimeException('Financial row count changed.');
        }
        foreach ($rows as $index => $row) {
            $id = $domain === 'receivables' ? 'shop_order:' . ($index + 1) : (string) ($index + 1);
            if ($row[1] !== $id || array_slice($row, 3, 3) !== $expected || $row[6] !== 'CZK') {
                throw new RuntimeException('Financial identity/order/amount contract changed.');
            }
        }
        $hash = hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
        unset($rows);
        if ($sample >= 0) {
            $samples[] = ['ms' => $ms, 'peak_bytes' => $peak];
        }
    }
    $median = static function (array $values): float {
        sort($values);
        $middle = intdiv(count($values), 2);
        return count($values) % 2 ? $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2;
    };
    echo json_encode(['size' => $size, 'domain' => $domain, 'mode' => $mode, 'rows' => $size, 'sha256' => $hash,
        'warmups' => $warmups, 'samples' => $samples, 'median_ms' => $median(array_column($samples, 'ms')),
        'median_peak_bytes' => $median(array_column($samples, 'peak_bytes')), 'measurement_complete' => true], JSON_THROW_ON_ERROR);
    exit;
}

$directory = sys_get_temp_dir() . '/w103-club-' . bin2hex(random_bytes(12));
if (!mkdir($directory, 0700)) {
    throw new RuntimeException('Cannot create owned profile directory.');
}
$results = [];
try {
    foreach (['expenses', 'advances', 'rewards', 'receivables'] as $domain) {
        foreach ([1000, 10000, 100000] as $size) {
            $baselineHash = null;
            foreach (['baseline', 'current'] as $mode) {
                $path = $directory . '/' . $domain . '-' . $size . '-' . $mode;
                $process = proc_open(
                    [PHP_BINARY, '-d', 'memory_limit=512M', __FILE__, '--worker', (string) $size, $domain, $mode],
                    [0 => ['pipe', 'r'], 1 => ['file', $path . '.out', 'w'], 2 => ['file', $path . '.err', 'w']],
                    $pipes
                );
                if (!is_resource($process)) {
                    throw new RuntimeException('Cannot start profile worker.');
                }
                fclose($pipes[0]);
                $timeout = $mode === 'baseline' && $size >= 10000 ? 15 : 180;
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
                    if ($mode !== 'baseline' || $size < 10000) {
                        throw new RuntimeException('Unexpected profile timeout.');
                    }
                    $results[] = ['size' => $size, 'domain' => $domain, 'mode' => $mode,
                        'measurement_complete' => false, 'timeout_seconds' => $timeout, 'extrapolated' => false];
                    continue;
                }
                proc_close($process);
                $error = trim((string) file_get_contents($path . '.err'));
                if ($status['exitcode'] !== 0 || $error !== '') {
                    throw new RuntimeException('Profile worker failed: ' . $error);
                }
                $result = json_decode((string) file_get_contents($path . '.out'), true, 512, JSON_THROW_ON_ERROR);
                if ($mode === 'baseline') {
                    $baselineHash = $result['sha256'];
                } elseif (($baselineHash !== null && $baselineHash !== $result['sha256']) || $result['median_peak_bytes'] > 128 * 1024 * 1024) {
                    throw new RuntimeException('Projection equivalence or memory budget failed.');
                }
                $results[] = $result;
            }
        }
    }
    echo json_encode(['scope' => 'pure club balance rows; isolated workers; not SQL/PDF/XLSX',
        'production_mutations' => false, 'results' => $results], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
} finally {
    foreach (glob($directory . '/*') ?: [] as $path) {
        unlink($path);
    }
    rmdir($directory);
}
