<?php
require 'C:/xampp/htdocs/evidencePavel/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$file = 'C:/Users/Marek/Desktop/downloads/2026 Enrollment form - ENG.xlsx';

echo "=== EXCEL FILE ANALYSIS ===\n";
echo "File: $file\n\n";

$spreadsheet = IOFactory::load($file);
$sheetNames = $spreadsheet->getSheetNames();

echo "=== SHEET NAMES ===\n";
foreach ($sheetNames as $i => $name) {
    echo "  Sheet $i: \"$name\"\n";
}
echo "\n";

foreach ($sheetNames as $sheetIndex => $sheetName) {
    $sheet = $spreadsheet->getSheet($sheetIndex);
    $highestRow = $sheet->getHighestRow();
    $highestCol = $sheet->getHighestColumn();
    $highestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);

    echo "============================================================\n";
    echo "SHEET $sheetIndex: \"$sheetName\"\n";
    echo "============================================================\n";
    echo "Used range: A1:{$highestCol}{$highestRow} ($highestRow rows x $highestColIndex columns)\n\n";

    // Merged cells
    $mergedCells = $sheet->getMergeCells();
    if (count($mergedCells) > 0) {
        echo "--- MERGED CELL RANGES ---\n";
        foreach ($mergedCells as $range) {
            echo "  $range\n";
        }
        echo "\n";
    } else {
        echo "--- No merged cells ---\n\n";
    }

    // Dump all cells with content
    echo "--- ALL CELLS WITH CONTENT ---\n";
    $cellCount = 0;
    for ($row = 1; $row <= $highestRow; $row++) {
        for ($col = 1; $col <= $highestColIndex; $col++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            $coord = $colLetter . $row;
            $cell = $sheet->getCell($coord);
            $value = $cell->getValue();

            if ($value !== null && $value !== '') {
                // If it's a formula, also show calculated value
                if (is_string($value) && str_starts_with($value, '=')) {
                    $calculated = $cell->getCalculatedValue();
                    echo "  Row $row, Col $colLetter ($coord): FORMULA $value => $calculated\n";
                } else {
                    $displayVal = $value;
                    if (is_bool($value)) {
                        $displayVal = $value ? 'TRUE' : 'FALSE';
                    }
                    echo "  Row $row, Col $colLetter ($coord): $displayVal\n";
                }
                $cellCount++;
            }
        }
    }
    echo "\n  Total cells with content: $cellCount\n\n";
}

echo "=== ANALYSIS COMPLETE ===\n";
