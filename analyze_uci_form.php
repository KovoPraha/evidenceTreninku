<?php
require 'C:\xampp\htdocs\evidencePavel\vendor\autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$file = 'C:\Users\Marek\Desktop\downloads\2026 Enrollment form - ENG.xlsx';
$spreadsheet = IOFactory::load($file);

// Get the first sheet
$sheet = $spreadsheet->getSheet(0);
$sheetTitle = $sheet->getTitle();

echo "=== Sheet: $sheetTitle ===\n\n";

// AREA 2: Rows 1-10, Columns A-G (event info on page 1)
echo "--- AREA 1: Rows 1-10, Columns A through G (Event Info) ---\n";
for ($row = 1; $row <= 10; $row++) {
    foreach (range('A', 'G') as $col) {
        $cell = $sheet->getCell($col . $row);
        $value = $cell->getValue();
        if ($value !== null && $value !== '') {
            echo "Row $row $col: [$value]\n";
        }
    }
}

echo "\n--- AREA 2: Rows 1-45, Columns I through Q (Rider Data / Page 2) ---\n";
$columns = ['I','J','K','L','M','N','O','P','Q'];
for ($row = 1; $row <= 45; $row++) {
    foreach ($columns as $col) {
        $cell = $sheet->getCell($col . $row);
        $value = $cell->getValue();
        if ($value !== null && $value !== '') {
            echo "Row $row $col: [$value]\n";
        }
    }
}

echo "\n=== Done ===\n";
