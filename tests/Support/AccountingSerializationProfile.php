<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (($argv[1] ?? '') !== '--worker') {
    $directory = sys_get_temp_dir() . '/w102-serialization-' . bin2hex(random_bytes(12));
    if (!mkdir($directory, 0700)) {
        throw new RuntimeException('Cannot create profile directory.');
    }
    $results = [];
    try {
        foreach ([1000, 10000, 100000] as $size) {
            $path = $directory . '/' . $size . '.xlsx';
            $process = proc_open(
                [PHP_BINARY, '-d', 'memory_limit=256M', __FILE__, '--worker', (string) $size, $path],
                [0 => ['pipe', 'r'], 1 => ['file', $path . '.out', 'w'], 2 => ['file', $path . '.err', 'w']],
                $pipes
            );
            if (!is_resource($process)) {
                throw new RuntimeException('Cannot start serialization worker.');
            }
            fclose($pipes[0]);
            $deadline = microtime(true) + 180;
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
                throw new RuntimeException('Serialization exceeded 180 seconds.');
            }
            proc_close($process);
            $error = trim((string) file_get_contents($path . '.err'));
            if ($status['exitcode'] !== 0 || $error !== '') {
                throw new RuntimeException('Serialization worker failed: ' . $error);
            }
            $result = json_decode((string) file_get_contents($path . '.out'), true, 512, JSON_THROW_ON_ERROR);
            if ($result['incremental_peak_bytes'] > 64 * 1024 * 1024 || $result['xlsx_bytes'] > 20 * 1024 * 1024) {
                throw new RuntimeException('Serialization memory or file-size budget exceeded.');
            }
            if (glob($directory . '/.accounting-xlsx-*')) {
                throw new RuntimeException('Private spool leaked.');
            }
            $results[] = $result;
        }
        echo json_encode(['production_mutations' => false, 'php_memory_limit_bytes' => 256 * 1024 * 1024,
            'incremental_budget_bytes' => 64 * 1024 * 1024, 'timeout_seconds' => 180, 'samples_per_size' => 1,
            'results' => $results], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
    } finally {
        // Only this invocation's random private directory is ever removed.
        $root = realpath($directory);
        if ($root === false || !str_starts_with(basename($root), 'w102-serialization-')
            || realpath(dirname($root)) !== realpath(sys_get_temp_dir())) {
            throw new RuntimeException('Unsafe cleanup target.');
        }
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            if ($file->isLink()) {
                throw new RuntimeException('Unexpected cleanup symlink.');
            }
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($root);
    }
    exit;
}

require dirname(__DIR__, 2) . '/includes/accounting_export.php';
$size = (int) ($argv[2] ?? 0);
$path = $argv[3] ?? '';
if (!in_array($size, [1000,10000,100000], true) || !str_contains($path, 'w102-serialization-')) {
    throw new RuntimeException('Owned synthetic output required.');
}
$s = ['version' => ACCOUNTING_EXPORT_VERSION,'period_from' => '2026-01-01','period_to' => '2026-01-31','timezone' => 'Europe/Prague',
    'snapshot_at' => '2026-02-01 00:00:00','snapshot_timezone' => 'UTC','recorded_database_timezone' => ['session_zone' => 'UTC','server_zone' => 'UTC'],
    'scope' => 'Synthetic serialization','status' => 'SYNTHETIC',
    'limitations' => [],'exceptions' => [],'pending_sources' => [],'documents' => [],'refund_decisions' => [],'refund_payments' => [],
    'adjustment_events' => [],'accounts' => [['id' => 1,'name' => 'Synthetic cash','opening_minor' => 100,'opened_on' => '2025-01-01','currency' => 'CZK']],
    'movements' => [],'allocations' => [],'classifications' => []];
file_put_contents($path . '.stage', 'fixture');
for ($id = 1;$id <= $size;$id++) {
    $s['movements'][] = ['id' => $id,'account_id' => 1,'kind' => 'income','amount_minor' => 10001,'currency' => 'CZK','effective_on' => '2026-01-01',
        'recorded_at' => '2026-01-01 12:00:00','source_context' => 'synthetic','source_reference' => '000'.$id,'reversal_of_id' => null,'transfer_key' => null];
    $s['allocations'][] = ['id' => $id,'movement_id' => $id,'case_type' => 'member_charge','case_id' => $id,'amount_minor' => 1,
        'effective_on' => '2026-01-01','recorded_at' => '2026-01-01 12:00:00','reverses_id' => null];
    $s['classifications'][] = ['id' => $id,'movement_id' => $id,'splits_json' => '[{"category_code":"SYN","activity":"TEST","amount_minor":10001}]'];
}
file_put_contents($path . '.stage', 'workbook');
memory_reset_peak_usage();
$before = memory_get_usage();
$start = hrtime(true);
accountingExportWorkbook($s, $path);
$ms = (hrtime(true) - $start) / 1e6;
$peak = memory_get_peak_usage();
unset($s);
file_put_contents($path . '.stage', 'validation');
$zip = new ZipArchive();
if ($zip->open($path) !== true) {
    throw new RuntimeException('Invalid XLSX archive.');
}
$counts = [];
foreach ([4 => $size + 1,5 => $size + 1,6 => $size + 1,8 => $size + 2] as $sheet => $expected) {

    $reader = new XMLReader();
    $reader->open('zip://' . $path . '#xl/worksheets/sheet' . $sheet . '.xml');
    $count = 0;
    while ($reader->read()) {
        if ($reader->nodeType === XMLReader::ELEMENT &&
           $reader->localName === 'row') {
            $count++;
        }
    }
    $reader->close();
    if ($count !== $expected) {
        throw new RuntimeException('Sheet row count differs.');
    }$counts[$sheet] = $count;
}
$zip->close();
echo json_encode(['size' => $size,'completed' => true,'ms' => $ms,'peak_bytes' => $peak,'incremental_peak_bytes' => $peak - $before,
 'xlsx_bytes' => filesize($path),'sheet_rows' => $counts,'production_mutations' => false], JSON_THROW_ON_ERROR);
