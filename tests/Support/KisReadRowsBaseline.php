<?php

declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\IOFactory;

require_once dirname(__DIR__, 2) . '/includes/kis_sync_lib.php';

function w12ReadRowsBaseline(string $path, array $requiredKeys, array &$meta): array
{
    $spreadsheet = IOFactory::load($path);
    foreach ($spreadsheet->getAllSheets() as $sheet) {
        $found = kis_find_header_row($sheet, $requiredKeys);
        if ($found === null) {
            continue;
        }

        [$headerRow, $headers] = $found;
        $highestRow = (int)$sheet->getHighestRow();
        $highestCol = (string)$sheet->getHighestColumn();
        $rows = [];

        for ($r = $headerRow + 1; $r <= $highestRow; $r++) {
            $data = $sheet->rangeToArray("A{$r}:{$highestCol}{$r}", null, true, true, false)[0] ?? [];
            $nonEmpty = false;
            foreach ($data as $v) {
                if (trim((string)$v) !== '') {
                    $nonEmpty = true;
                    break;
                }
            }
            if (!$nonEmpty) {
                continue;
            }

            $row = ['_row' => $r];
            foreach ($headers as $key => $ci) {
                $row[$key] = $data[$ci] ?? null;
            }
            $rows[] = $row;
        }

        $meta = [
            'sheet' => $sheet->getTitle(),
            'header_row' => $headerRow,
            'rows' => count($rows),
            'headers' => array_values(array_keys($headers)),
        ];
        return $rows;
    }

    throw new RuntimeException('Nelze najit hlavicku v souboru ' . basename($path));
}
