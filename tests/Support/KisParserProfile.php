<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once __DIR__ . '/KisReadRowsBaseline.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$directory = sys_get_temp_dir() . '/w12-parser-' . bin2hex(random_bytes(8));
if (!mkdir($directory, 0700)) {
    throw new RuntimeException('Cannot create owned parser directory.');
}
try {
    $results = [];
    foreach ([100, 1000, 10000] as $size) {
        $path = $directory . '/synthetic-' . $size . '.xlsx';
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Synthetic');
        $sheet->fromArray(['Jméno', 'Příjmení', 'Narození', 'Email', 'Částka'], null, 'A1');
        for ($i = 1; $i <= $size; $i++) {
            $sheet->fromArray(['Synthetic', 'Person ' . $i, '2010-01-02', 'person' . $i . '@example.test', 123.45], null, 'A' . ($i + 1));
        }
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();
        unset($book, $sheet);
        gc_collect_cycles();
        $expected = null;
        $expectedMeta = null;
        foreach (['baseline' => 'w12ReadRowsBaseline', 'current' => 'kis_read_rows'] as $mode => $call) {
            gc_collect_cycles();
            memory_reset_peak_usage();
            $memory = memory_get_usage();
            $start = hrtime(true);
            $meta = [];
            $rows = $call($path, ['jmeno', 'prijmeni'], $meta);
            $ms = (hrtime(true) - $start) / 1e6;
            $peak = memory_get_peak_usage() - $memory;
            $retained = memory_get_usage() - $memory;
            if (count($rows) !== $size || $rows[0]['_row'] !== 2 || $rows[$size - 1]['_row'] !== $size + 1) {
                throw new RuntimeException('Parser row identity changed.');
            }
            $hash = hash('sha256', serialize($rows));
            if ($expected !== null && ($expected !== $hash || $expectedMeta !== $meta)) {
                throw new RuntimeException('Whole parser result or metadata changed.');
            }
            $expected = $hash;
            $expectedMeta = $meta;
            unset($rows);
            if ($mode === 'current' && memory_get_usage() - $memory > 8 * 1024 * 1024) {
                throw new RuntimeException('Parser retains more than 8 MiB after releasing its result.');
            }
            $results[] = ['rows' => $size, 'mode' => $mode, 'ms' => $ms, 'incremental_peak_bytes' => $peak,
                'retained_with_result_bytes' => $retained, 'retained_after_result_release_bytes' => memory_get_usage() - $memory,
                'xlsx_bytes' => filesize($path), 'result_sha256' => $hash];
        }
    }
    gc_collect_cycles();
    $memory = memory_get_usage();
    try {
        $meta = [];
        kis_read_rows($path, ['header_that_does_not_exist'], $meta);
        throw new LogicException('Missing-header failure did not occur.');
    } catch (RuntimeException $exception) {
        if (!str_starts_with($exception->getMessage(), 'Nelze najit hlavicku')) {
            throw $exception;
        }
    }
    $failureRetained = memory_get_usage() - $memory;
    if ($failureRetained > 8 * 1024 * 1024) {
        throw new RuntimeException('Failed parser retains more than 8 MiB.');
    }
    echo json_encode(['samples' => 1, 'comparison' => 'complete result hash and exact metadata',
        'missing_header_retained_bytes' => $failureRetained, 'results' => $results], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
} finally {
    foreach (glob($directory . '/*') ?: [] as $file) {
        unlink($file);
    }
    rmdir($directory);
}
