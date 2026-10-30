<?php

declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\StringHelper;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** Bounded worksheet serialization; PhpSpreadsheet still owns the workbook and its styles. */
final class AccountingXlsxStream
{
    private Spreadsheet $book;
    private string $directory;
    private XMLWriter $strings;
    private int $stringCount = 0;
    private array $sheets = [];
    private array $files = [];

    public function __construct(string $output)
    {
        $this->book = new Spreadsheet();
        $this->directory = dirname($output) . '/.accounting-xlsx-' . bin2hex(random_bytes(16));
        if (!mkdir($this->directory, 0700)) {
            throw new RuntimeException('Cannot create private export workspace.');
        }
        try {
            $this->book->removeSheetByIndex(0);
            $this->strings = $this->xml('strings.xml');
            $this->strings->startDocument('1.0', 'UTF-8', 'yes');
            $this->strings->startElement('sst');
            $this->strings->writeAttribute('xmlns', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        } catch (Throwable $error) {
            $this->close();
            throw $error;
        }
    }

    private function file(string $name): string
    {
        $path = $this->directory . '/' . $name;
        $this->files[$path] = true;
        return $path;
    }

    private function xml(string $name): XMLWriter
    {
        $writer = new XMLWriter();
        if (!$writer->openUri($this->file($name))) {
            throw new RuntimeException('Cannot spool export XML.');
        }
        return $writer;
    }

    public function sheet(string $name, array $headers, iterable $rows, array $numeric = []): void
    {
        $iterator = (static function () use ($rows): Generator {
            yield from $rows;
        })();
        $iterator->rewind();
        $hasRows = $iterator->valid();
        $sheet = $this->book->createSheet()->setTitle($name);
        $last = Coordinate::stringFromColumnIndex(count($headers));
        foreach ($headers as $column => $header) {
            $letter = Coordinate::stringFromColumnIndex($column + 1);
            $sheet->setCellValueExplicit($letter . '1', $header, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit($letter . '2', '', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit($letter . '3', '=style', DataType::TYPE_STRING);
            $sheet->getColumnDimension($letter)->setWidth($name === 'Manifest' ? ($column === 0 ? 28 : 95) : 24);
        }
        $sheet->getStyle('A1:' . $last . '1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:' . $last . '1')->getFill()->setFillType('solid')->getStartColor()->setARGB('FF23465C');
        $sheet->getStyle('A1:' . $last . ($hasRows ? '3' : '1'))->getAlignment()->setVertical('top')->setWrapText(true);
        foreach ($numeric as $column) {
            $letter = Coordinate::stringFromColumnIndex($column + 1);
            $sheet->getStyle($letter . '2:' . $letter . '3')->getNumberFormat()->setFormatCode('#,##0.00;[Red]-#,##0.00');
        }
        $sheet->freezePane('A2');
        // Each previous template retains its style references, so collection never renumbers emitted styles.
        $this->book->garbageCollect();
        $styles = [];
        foreach ($headers as $column => $header) {
            $letter = Coordinate::stringFromColumnIndex($column + 1);
            $styles[$column] = [
                $sheet->getCell($letter . '1')->getXfIndex(),
                $sheet->getCell($letter . '2')->getXfIndex(),
                $sheet->getCell($letter . '3')->getXfIndex(),
            ];
        }
        $number = count($this->sheets) + 1;
        $xml = $this->xml('data' . $number . '.xml');
        $xml->startElement('sheetData');
        $this->row($xml, $headers, 1, $numeric, $styles);
        $rowNumber = 1;
        while ($iterator->valid()) {
            if (++$rowNumber > 1048576) {
                throw new DomainException('Export exceeds the XLSX worksheet row limit.');
            }
            $this->row($xml, array_values($iterator->current()), $rowNumber, $numeric, $styles);
            $iterator->next();
        }
        if (!$hasRows && $numeric !== []) {
            // The original writer emits styled null cells on row two of an empty numeric sheet.
            $xml->startElement('row');
            $xml->writeAttribute('r', '2');
            foreach ($numeric as $column) {
                $xml->startElement('c');
                $xml->writeAttribute('r', Coordinate::stringFromColumnIndex($column + 1) . '2');
                $xml->writeAttribute('s', (string) $styles[$column][1]);
                $xml->endElement();
            }
            $xml->endElement();
        }
        $xml->endElement();
        $xml->flush();
        unset($xml);
        $sheet->setAutoFilter('A1:' . $last . $rowNumber);
        $this->sheets[] = ['last' => $last, 'rows' => max($rowNumber, !$hasRows && $numeric !== [] ? 2 : 1)];
    }

    private function row(XMLWriter $xml, array $row, int $number, array $numeric, array $styles): void
    {
        $xml->startElement('row');
        $xml->writeAttribute('r', (string) $number);
        foreach ($row as $column => $value) {
            $isNumeric = $value !== null && $number > 1 && in_array($column, $numeric, true);
            $text = $value === null ? 'NEZNÁMÉ' : (string) $value;
            $quote = !$isNumeric && strlen($text) > 1 && $text[0] === '=';
            $xml->startElement('c');
            $xml->writeAttribute('r', Coordinate::stringFromColumnIndex($column + 1) . $number);
            $xml->writeAttribute('s', (string) $styles[$column][$number === 1 ? 0 : ($quote ? 2 : 1)]);
            if ($isNumeric) {
                $numberValue = StringHelper::convertToString((float) $value);
                $xml->writeElement('v', str_contains($numberValue, '.') ? $numberValue : $numberValue . '.0');
            } elseif ($text !== '') {
                $xml->writeAttribute('t', 's');
                $xml->writeElement('v', (string) $this->stringCount++);
                $this->strings->startElement('si');
                $this->strings->startElement('t');
                $text = StringHelper::controlCharacterPHP2OOXML((string) DataType::checkString($text));
                if ($text !== trim($text)) {
                    $this->strings->writeAttribute('xml:space', 'preserve');
                }
                $this->strings->text($text);
                $this->strings->endElement();
                $this->strings->endElement();
            }
            $xml->endElement();
        }
        $xml->endElement();
    }

    public function save(string $path): void
    {
        $this->strings->endElement();
        $this->strings->endDocument();
        $this->strings->flush();
        unset($this->strings);
        $this->book->setActiveSheetIndex(0);
        $writer = new Xlsx($this->book);
        $writer->setPreCalculateFormulas(false);
        $writer->save($path);
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Cannot reopen export workbook.');
        }
        try {
            foreach ($this->sheets as $index => $sheet) {
                $name = 'xl/worksheets/sheet' . ($index + 1) . '.xml';
                $template = $zip->getFromName($name);
                if (!is_string($template) || !preg_match('/<sheetData>.*?<\/sheetData>/s', $template, $match, PREG_OFFSET_CAPTURE)) {
                    throw new RuntimeException('Unsupported workbook worksheet template.');
                }
                $prefix = substr($template, 0, $match[0][1]);
                $prefix = preg_replace('/<dimension ref="[^"]*"\/>/', '<dimension ref="A1:' . $sheet['last'] . $sheet['rows'] . '"/>', $prefix);
                $output = $this->file('sheet' . ($index + 1) . '.xml');
                $target = fopen($output, 'wb');
                $source = fopen($this->file('data' . ($index + 1) . '.xml'), 'rb');
                if ($target === false || $source === false) {
                    if (is_resource($target)) {
                        fclose($target);
                    }
                    if (is_resource($source)) {
                        fclose($source);
                    }
                    throw new RuntimeException('Cannot assemble streamed worksheet.');
                }
                try {
                    $suffix = substr($template, $match[0][1] + strlen($match[0][0]));
                    if (fwrite($target, (string) $prefix) !== strlen((string) $prefix)
                        || stream_copy_to_stream($source, $target) !== filesize($this->file('data' . ($index + 1) . '.xml'))
                        || fwrite($target, $suffix) !== strlen($suffix)) {
                        throw new RuntimeException('Cannot write streamed worksheet.');
                    }
                } finally {
                    fclose($target);
                    fclose($source);
                }
                if (!$zip->addFile($output, $name)) {
                    throw new RuntimeException('Cannot archive streamed worksheet.');
                }
            }
            if (!$zip->addFile($this->file('strings.xml'), 'xl/sharedStrings.xml')) {
                throw new RuntimeException('Cannot archive export strings.');
            }
        } finally {
            if (!$zip->close()) {
                throw new RuntimeException('Cannot finish export workbook.');
            }
        }
    }

    public function close(): void
    {
        unset($this->strings);
        $this->book->disconnectWorksheets();
        foreach (array_keys($this->files) as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }
}
