<?php
declare(strict_types=1);

require_once __DIR__ . '/shoptet_product_csv.php';
require_once __DIR__ . '/shoptet_product_xml.php';

final class ShoptetProductInput
{
    /** @return array<string,mixed> */
    public static function read(string $input): array
    {
        if ($input === ''
            || str_contains($input, '://')
            || is_link($input)
            || !is_file($input)
            || !is_readable($input)
        ) {
            throw new ShoptetProductXmlException('Vstup neni citelny lokalni regularni soubor.');
        }
        $extension = strtolower(pathinfo($input, PATHINFO_EXTENSION));
        if (!in_array($extension, ['csv', 'xml'], true)) {
            throw new ShoptetProductXmlException('Vstup musi mit priponu .csv nebo .xml.');
        }

        $prefix = file_get_contents($input, false, null, 0, 4096);
        if ($prefix === false) {
            throw new ShoptetProductXmlException('Vstupni soubor nelze nacist.');
        }
        $prefix = preg_replace('/^\xEF\xBB\xBF/', '', $prefix) ?? $prefix;
        $trimmed = ltrim($prefix);
        if (str_starts_with($trimmed, '<?xml') || preg_match('/^<SHOP(?:\s|>)/', $trimmed)) {
            return ShoptetProductXml::read($input);
        }
        if ($extension === 'xml') {
            throw new ShoptetProductXmlException('Soubor s priponou .xml nema XML hlavicku ani koren SHOP.');
        }
        return ShoptetProductCsv::read($input);
    }
}
