<?php
declare(strict_types=1);

final class ShoptetProductXmlException extends RuntimeException
{
}

final class ShoptetProductXml
{
    public const MAX_BYTES = 10 * 1024 * 1024;
    public const MAX_ROWS = 10000;
    public const MAX_COLUMNS = 200;
    public const MAX_FIELD_BYTES = 65536;

    /**
     * Normalize a Shoptet product XML export to the same inert row contract
     * used by ShopCatalogContract. No URLs are fetched and no data is written.
     *
     * @return array{
     *   source:array{filename:string,sha256:string,encoding:string,delimiter:string,rows:int,columns:int},
     *   headers:list<string>,
     *   rows:list<array{row:int,values:array<string,string>}>,
     *   issues:list<array{severity:string,code:string,message:string,row:?int,field:?string}>
     * }
     */
    public static function read(string $input): array
    {
        self::assertLocalInput($input);

        $size = filesize($input);
        if ($size === false || $size > self::MAX_BYTES) {
            throw new ShoptetProductXmlException('XML soubor prekrocil limit 10 MiB.');
        }

        $snapshot = file_get_contents($input);
        if ($snapshot === false || strlen($snapshot) > self::MAX_BYTES) {
            throw new ShoptetProductXmlException('XML soubor nelze nacist nebo prekrocil limit 10 MiB.');
        }
        if (!mb_check_encoding($snapshot, 'UTF-8')) {
            throw new ShoptetProductXmlException('XML soubor musi byt platne UTF-8.');
        }
        if (str_contains($snapshot, "\0")) {
            throw new ShoptetProductXmlException('XML soubor obsahuje nepovolene nulove bajty.');
        }
        if (preg_match('/<!\s*(?:DOCTYPE|ENTITY)\b/i', $snapshot)) {
            throw new ShoptetProductXmlException('XML nesmi obsahovat DOCTYPE ani deklaraci entit.');
        }

        $previousInternalErrors = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($snapshot, SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
            $parseErrors = libxml_get_errors();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousInternalErrors);
        }
        if ($xml === false) {
            $detail = isset($parseErrors[0]) ? trim($parseErrors[0]->message) : 'neznamy duvod';
            throw new ShoptetProductXmlException('XML nema platnou strukturu: ' . $detail);
        }
        if ($xml->getName() !== 'SHOP') {
            throw new ShoptetProductXmlException('Korenovy element XML musi byt SHOP.');
        }

        $items = $xml->SHOPITEM;
        if (count($items) > self::MAX_ROWS) {
            throw new ShoptetProductXmlException('XML prekrocilo limit 10000 produktu.');
        }

        $rows = [];
        $issues = [];
        $headers = [];
        $headerSet = [];
        foreach ($items as $item) {
            $productId = trim((string)$item['id']);
            if ($productId === '') {
                throw new ShoptetProductXmlException('SHOPITEM nema povinny atribut id.');
            }

            $base = self::productValues($item, $productId);
            $variants = [];
            if (isset($item->VARIANTS->VARIANT)) {
                foreach ($item->VARIANTS->VARIANT as $variant) {
                    $variants[] = $variant;
                }
            }
            if ($variants === []) {
                $variants[] = $item;
            }

            foreach ($variants as $variantIndex => $variant) {
                if (count($rows) >= self::MAX_ROWS) {
                    throw new ShoptetProductXmlException('XML prekrocilo limit 10000 variant.');
                }
                $line = count($rows) + 2;
                $values = array_merge($base, self::variantValues($variant));
                if ($variantIndex > 0) {
                    // Product descriptions are identical for every XML variant.
                    // Keep them once to avoid hundreds of duplicate warnings.
                    $values['shortDescription'] = '';
                    $values['description'] = '';
                }

                $parameterNames = [];
                if (isset($variant->PARAMETERS->PARAMETER)) {
                    foreach ($variant->PARAMETERS->PARAMETER as $parameter) {
                        $name = trim((string)$parameter->NAME);
                        $value = trim((string)$parameter->VALUE);
                        if ($name === '') {
                            $issues[] = self::issue(
                                'error',
                                'empty_variant_parameter_name',
                                'Parametr varianty nema nazev.',
                                $line,
                                null
                            );
                            continue;
                        }
                        if (str_contains($name, ':')) {
                            $issues[] = self::issue(
                                'error',
                                'invalid_variant_parameter_name',
                                'Nazev parametru varianty nesmi obsahovat dvojtecku.',
                                $line,
                                $name
                            );
                            continue;
                        }
                        if (isset($parameterNames[$name])) {
                            $issues[] = self::issue(
                                'error',
                                'duplicate_variant_parameter_name',
                                'Varianta obsahuje stejny parametr vicekrat.',
                                $line,
                                $name
                            );
                            continue;
                        }
                        $parameterNames[$name] = true;
                        $values['variant:' . $name] = $value;
                    }
                }

                foreach ($values as $header => $value) {
                    self::assertFieldLimit($header, $line);
                    self::assertFieldLimit($value, $line);
                    if (!isset($headerSet[$header])) {
                        if (count($headers) >= self::MAX_COLUMNS) {
                            throw new ShoptetProductXmlException('XML prekrocilo limit 200 normalizovanych sloupcu.');
                        }
                        $headerSet[$header] = true;
                        $headers[] = $header;
                    }
                }
                $rows[] = ['row' => $line, 'values' => $values];
            }
        }

        foreach ($rows as &$row) {
            foreach ($headers as $header) {
                if (!array_key_exists($header, $row['values'])) {
                    $row['values'][$header] = '';
                }
            }
            $row['values'] = array_replace(array_fill_keys($headers, ''), $row['values']);
        }
        unset($row);

        return [
            'source' => [
                'filename' => basename($input),
                'sha256' => hash('sha256', $snapshot),
                'encoding' => 'UTF-8',
                'delimiter' => 'xml',
                'rows' => count($rows),
                'columns' => count($headers),
            ],
            'headers' => $headers,
            'rows' => $rows,
            'issues' => $issues,
        ];
    }

    private static function assertLocalInput(string $input): void
    {
        if ($input === '' || str_contains($input, '://')) {
            throw new ShoptetProductXmlException('Vstup musi byt cesta k lokalnimu XML souboru.');
        }
        if (!in_array(strtolower(pathinfo($input, PATHINFO_EXTENSION)), ['xml', 'csv'], true)) {
            throw new ShoptetProductXmlException('XML vstup musi mit priponu .xml nebo .csv.');
        }
        if (is_link($input) || !is_file($input) || !is_readable($input)) {
            throw new ShoptetProductXmlException('Vstup neni citelny lokalni regularni soubor.');
        }
    }

    /** @return array<string,string> */
    private static function productValues(SimpleXMLElement $item, string $productId): array
    {
        $values = [
            'code' => '',
            'pairCode' => $productId,
            'name' => self::text($item, 'NAME'),
            'price' => '',
            'priceRatio' => '',
            'currency' => '',
            'includingVat' => '',
            'percentVat' => '',
            'ean' => '',
            'stock' => '',
            'decimalCount' => '',
            'negativeAmount' => '',
            'productVisibility' => self::text($item, 'VISIBILITY'),
            'variantVisibility' => '',
            'defaultCategory' => isset($item->CATEGORIES->DEFAULT_CATEGORY)
                ? trim((string)$item->CATEGORIES->DEFAULT_CATEGORY)
                : '',
            'shortDescription' => self::text($item, 'SHORT_DESCRIPTION'),
            'description' => self::text($item, 'DESCRIPTION'),
            'itemType' => self::text($item, 'ITEM_TYPE'),
        ];

        $defaultCategory = $values['defaultCategory'];
        $categories = [];
        if (isset($item->CATEGORIES->CATEGORY)) {
            foreach ($item->CATEGORIES->CATEGORY as $category) {
                $value = trim((string)$category);
                if ($value !== '' && $value !== $defaultCategory) {
                    $categories[] = $value;
                }
            }
        }
        self::addNumberedValues($values, 'categoryText', $categories);

        $images = [];
        if (isset($item->IMAGES->IMAGE)) {
            foreach ($item->IMAGES->IMAGE as $image) {
                $value = trim((string)$image);
                if ($value !== '') {
                    $images[] = $value;
                }
            }
        }
        self::addNumberedValues($values, 'image', $images);

        return $values;
    }

    /** @return array<string,string> */
    private static function variantValues(SimpleXMLElement $variant): array
    {
        $priceVat = self::text($variant, 'PRICE_VAT');
        $price = $priceVat !== '' ? $priceVat : self::text($variant, 'PRICE');

        return [
            'code' => self::text($variant, 'CODE'),
            'price' => $price,
            'priceRatio' => self::text($variant, 'PRICE_RATIO'),
            'actionPrice' => self::text($variant, 'ACTION_PRICE'),
            'standardPrice' => self::text($variant, 'STANDARD_PRICE'),
            'currency' => strtoupper(self::text($variant, 'CURRENCY')),
            'includingVat' => $priceVat !== '' ? '1' : '0',
            'percentVat' => self::text($variant, 'VAT'),
            'ean' => self::text($variant, 'EAN'),
            'stock' => isset($variant->STOCK->AMOUNT) ? trim((string)$variant->STOCK->AMOUNT) : '',
            'decimalCount' => self::text($variant, 'DECIMAL_COUNT'),
            'negativeAmount' => self::text($variant, 'NEGATIVE_AMOUNT'),
            'variantVisibility' => self::text($variant, 'VISIBLE'),
            'unit' => self::text($variant, 'UNIT'),
            'availabilityInStock' => self::text($variant, 'AVAILABILITY_IN_STOCK'),
            'availabilityOutOfStock' => self::text($variant, 'AVAILABILITY_OUT_OF_STOCK'),
            'freeShipping' => self::text($variant, 'FREE_SHIPPING'),
            'freeBilling' => self::text($variant, 'FREE_BILLING'),
        ];
    }

    private static function text(SimpleXMLElement $node, string $name): string
    {
        return isset($node->{$name}) ? trim((string)$node->{$name}) : '';
    }

    /** @param array<string,string> $values @param list<string> $items */
    private static function addNumberedValues(array &$values, string $prefix, array $items): void
    {
        $items = array_values(array_unique($items, SORT_STRING));
        foreach ($items as $index => $item) {
            $values[$prefix . ($index === 0 ? '' : (string)($index + 1))] = $item;
        }
    }

    private static function assertFieldLimit(string $value, int $row): void
    {
        if (strlen($value) > self::MAX_FIELD_BYTES) {
            throw new ShoptetProductXmlException('Normalizovany radek ' . $row . ' obsahuje pole vetsi nez 64 KiB.');
        }
    }

    /** @return array{severity:string,code:string,message:string,row:?int,field:?string} */
    private static function issue(
        string $severity,
        string $code,
        string $message,
        ?int $row,
        ?string $field
    ): array {
        return compact('severity', 'code', 'message', 'row', 'field');
    }
}
