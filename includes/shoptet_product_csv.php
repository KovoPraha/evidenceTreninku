<?php
declare(strict_types=1);

final class ShoptetProductCsvException extends RuntimeException
{
}

final class ShoptetProductCsv
{
    public const MAX_BYTES = 10 * 1024 * 1024;
    public const MAX_ROWS = 10000;
    public const MAX_COLUMNS = 200;
    public const MAX_FIELD_BYTES = 65536;

    /**
     * @return array{
     *   source:array{filename:string,sha256:string,encoding:string,delimiter:string,rows:int,columns:int},
     *   headers:list<string>,
     *   rows:list<array{row:int,values:array<string,string>}>,
     *   issues:list<array{severity:string,code:string,message:string,row:?int,field:?string}>
     * }
     */
    public static function read(string $input): array
    {
        if ($input === '' || str_contains($input, '://')) {
            throw new ShoptetProductCsvException('Vstup musi byt cesta k lokalnimu CSV souboru.');
        }
        if (strtolower(pathinfo($input, PATHINFO_EXTENSION)) !== 'csv') {
            throw new ShoptetProductCsvException('Podporovan je pouze soubor s priponou .csv.');
        }
        if (is_link($input) || !is_file($input) || !is_readable($input)) {
            throw new ShoptetProductCsvException('Vstup neni citelny lokalni regularni soubor.');
        }

        $size = filesize($input);
        if ($size === false || $size > self::MAX_BYTES) {
            throw new ShoptetProductCsvException('CSV soubor prekrocil limit 10 MiB.');
        }

        $sample = file_get_contents($input);
        if ($sample === false) {
            throw new ShoptetProductCsvException('CSV soubor nelze nacist.');
        }
        if (str_contains($sample, "\0")) {
            throw new ShoptetProductCsvException('CSV soubor obsahuje nepovolene nulove bajty.');
        }

        $encoding = mb_check_encoding($sample, 'UTF-8') ? 'UTF-8' : 'Windows-1250';
        if ($encoding === 'Windows-1250') {
            $converted = iconv('WINDOWS-1250', 'UTF-8', $sample);
            if ($converted === false || !mb_check_encoding($converted, 'UTF-8')) {
                throw new ShoptetProductCsvException('CSV soubor neni platny UTF-8 ani Windows-1250.');
            }
        }

        $delimiter = self::detectDelimiter($sample);
        $handle = fopen($input, 'rb');
        if ($handle === false) {
            throw new ShoptetProductCsvException('CSV soubor nelze otevrit.');
        }

        try {
            $headerRow = fgetcsv($handle, 0, $delimiter, '"', '');
            if ($headerRow === false) {
                throw new ShoptetProductCsvException('CSV soubor nema hlavicku.');
            }
            $headerRow = self::convertRow($headerRow, $encoding);
            if (isset($headerRow[0])) {
                $headerRow[0] = preg_replace('/^\x{FEFF}/u', '', $headerRow[0]) ?? $headerRow[0];
            }
            $headers = array_map(static fn (string $header): string => trim($header), $headerRow);

            if ($headers === [] || count($headers) > self::MAX_COLUMNS) {
                throw new ShoptetProductCsvException('CSV hlavicka ma neplatny pocet sloupcu.');
            }
            if (in_array('', $headers, true)) {
                throw new ShoptetProductCsvException('CSV hlavicka obsahuje prazdny nazev sloupce.');
            }
            if (count(array_unique($headers, SORT_STRING)) !== count($headers)) {
                throw new ShoptetProductCsvException('CSV hlavicka obsahuje duplicitni nazvy sloupcu.');
            }
            foreach ($headers as $header) {
                self::assertFieldLimit($header);
            }

            $rows = [];
            $physicalRow = 1;
            while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
                $physicalRow++;
                $row = self::convertRow($row, $encoding);
                if (count($row) > self::MAX_COLUMNS) {
                    throw new ShoptetProductCsvException('Radek ' . $physicalRow . ' prekrocil limit sloupcu.');
                }
                foreach ($row as $field) {
                    self::assertFieldLimit($field, $physicalRow);
                }
                if (self::isBlankRow($row)) {
                    continue;
                }
                if (count($row) !== count($headers)) {
                    throw new ShoptetProductCsvException(
                        'Radek ' . $physicalRow . ' nema stejny pocet sloupcu jako hlavicka.'
                    );
                }
                if (count($rows) >= self::MAX_ROWS) {
                    throw new ShoptetProductCsvException('CSV soubor prekrocil limit 10000 datovych radku.');
                }

                /** @var array<string,string> $values */
                $values = array_combine($headers, $row);
                $rows[] = ['row' => $physicalRow, 'values' => $values];
            }
        } finally {
            fclose($handle);
        }

        return [
            'source' => [
                'filename' => basename($input),
                'sha256' => hash_file('sha256', $input) ?: '',
                'encoding' => $encoding,
                'delimiter' => $delimiter === ';' ? 'semicolon' : 'comma',
                'rows' => count($rows),
                'columns' => count($headers),
            ],
            'headers' => array_values($headers),
            'rows' => $rows,
            'issues' => [],
        ];
    }

    private static function detectDelimiter(string $sample): string
    {
        $firstLine = strtok($sample, "\r\n");
        if ($firstLine === false) {
            return ';';
        }

        $semicolon = count(str_getcsv($firstLine, ';', '"', ''));
        $comma = count(str_getcsv($firstLine, ',', '"', ''));
        if ($semicolon === 1 && $comma === 1) {
            throw new ShoptetProductCsvException('Nelze rozpoznat CSV oddelovac.');
        }

        return $semicolon >= $comma ? ';' : ',';
    }

    /** @param list<string|null> $row @return list<string> */
    private static function convertRow(array $row, string $encoding): array
    {
        $converted = [];
        foreach ($row as $field) {
            $value = (string)($field ?? '');
            if ($encoding === 'Windows-1250') {
                $convertedValue = iconv('WINDOWS-1250', 'UTF-8', $value);
                if ($convertedValue === false) {
                    throw new ShoptetProductCsvException('CSV obsahuje neplatny Windows-1250 retezec.');
                }
                $value = $convertedValue;
            }
            if (!mb_check_encoding($value, 'UTF-8')) {
                throw new ShoptetProductCsvException('CSV obsahuje neplatny textovy retezec.');
            }
            $converted[] = $value;
        }
        return $converted;
    }

    /** @param list<string> $row */
    private static function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim($value) !== '') {
                return false;
            }
        }
        return true;
    }

    private static function assertFieldLimit(string $value, ?int $row = null): void
    {
        if (strlen($value) <= self::MAX_FIELD_BYTES) {
            return;
        }
        $where = $row === null ? 'Hlavicka' : 'Radek ' . $row;
        throw new ShoptetProductCsvException($where . ' obsahuje pole vetsi nez 64 KiB.');
    }
}
