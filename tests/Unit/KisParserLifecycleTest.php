<?php

declare(strict_types=1);

namespace Tests\Unit;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/KisReadRowsBaseline.php';

final class KisParserLifecycleTest extends TestCase
{
    public function testWholeResultAndMetadataSurviveWorkbookCleanup(): void
    {
        $path = sys_get_temp_dir() . '/w12-parser-contract-' . bin2hex(random_bytes(8)) . '.xlsx';
        $book = new Spreadsheet();
        $book->getActiveSheet()->setTitle('No header')->setCellValue('A1', 'Ignore me');
        $sheet = $book->createSheet()->setTitle('Český přehled');
        $sheet->fromArray(['Jméno', 'Příjmení', 'Částka', 'Datum', 'Prázdné'], null, 'A3');
        $sheet->fromArray(['Jiří', 'Žluťoučký', '=100+23.45', 46023], null, 'A4');
        $sheet->getStyle('D4')->getNumberFormat()->setFormatCode('dd.mm.yyyy');
        $sheet->fromArray(['', '', '', '', ''], null, 'A5');
        $sheet->fromArray(['Zero', 'Synthetic', 0, '2026-01-02'], null, 'A6', true);
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();
        unset($book, $sheet);
        try {
            $before = hash_file('sha256', $path);
            $baselineMeta = $currentMeta = [];
            $expected = \w12ReadRowsBaseline($path, ['jmeno', 'prijmeni'], $baselineMeta);
            self::assertSame($expected, \kis_read_rows($path, ['jmeno', 'prijmeni'], $currentMeta));
            self::assertSame($baselineMeta, $currentMeta);
            self::assertSame([4, 6], array_column($expected, '_row'));
            self::assertSame('Český přehled', $currentMeta['sheet']);
            self::assertSame($before, hash_file('sha256', $path));
            try {
                \kis_read_rows($path, ['absent'], $currentMeta);
                self::fail('Missing header must throw.');
            } catch (\RuntimeException $exception) {
                self::assertSame('Nelze najit hlavicku v souboru ' . basename($path), $exception->getMessage());
            }
            self::assertSame($expected, \kis_read_rows($path, ['jmeno', 'prijmeni'], $currentMeta));
        } finally {
            unlink($path);
        }
    }
}
