<?php
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;

$spreadsheet = new Spreadsheet();
$spreadsheet->getActiveSheet()->setCellValue('A1', 'Composer is working!');
echo "PhpSpreadsheet OK!";
