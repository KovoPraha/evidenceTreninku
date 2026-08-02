<?php
declare(strict_types=1);

require_once __DIR__ . '/shop_offer_classifier.php';

final class ShopCatalogContract
{
    public const VERSION = 'evidence.shop.catalog-candidate.v4';

    /** @var list<string> */
    private const REQUIRED_HEADERS = ['code', 'pairCode', 'name', 'price'];

    /** @var list<string> */
    private const SUPPORTED_HEADERS = [
        'code', 'pairCode', 'name', 'price', 'priceRatio', 'currency',
        'includingVat', 'percentVat', 'ean', 'stock', 'decimalCount',
        'negativeAmount', 'productVisibility', 'variantVisibility',
        'defaultCategory', 'shortDescription', 'description', 'itemType',
        'standardPrice', 'unit', 'availabilityInStock', 'availabilityOutOfStock',
        'freeShipping', 'freeBilling',
    ];

    /** @var list<string> */
    private const PRODUCT_VISIBILITIES = [
        'hidden', 'visible', 'blocked', 'showRegistered', 'blockUnregistered',
        'cashDeskOnly', 'detailOnly',
    ];

    /**
     * @param array{
     *   source:array{filename:string,sha256:string,encoding:string,delimiter:string,rows:int,columns:int},
     *   headers:list<string>,
     *   rows:list<array{row:int,values:array<string,string>}>,
     *   issues:list<array{severity:string,code:string,message:string,row:?int,field:?string}>
     * } $parsed
     * @return array<string,mixed>
     */
    public static function build(array $parsed): array
    {
        $issues = $parsed['issues'];
        $headers = $parsed['headers'];
        foreach (self::REQUIRED_HEADERS as $required) {
            if (!in_array($required, $headers, true)) {
                $issues[] = self::issue('error', 'missing_required_header', 'Chybi povinny sloupec.', null, $required);
            }
        }

        $variantHeaders = array_values(array_filter(
            $headers,
            static fn (string $header): bool => str_starts_with($header, 'variant:') && strlen($header) > 8
        ));
        if (count($variantHeaders) > 3) {
            $issues[] = self::issue(
                'error',
                'too_many_variant_parameters',
                'Shoptet podporuje nejvyse tri parametry varianty.',
                null,
                null
            );
        }

        $unsupportedHeaders = [];
        foreach ($headers as $header) {
            if (!self::isSupportedHeader($header)) {
                $unsupportedHeaders[] = $header;
            }
        }
        foreach ($unsupportedHeaders as $header) {
            $nonEmpty = 0;
            foreach ($parsed['rows'] as $row) {
                if (trim((string)($row['values'][$header] ?? '')) !== '') {
                    $nonEmpty++;
                }
            }
            if ($nonEmpty > 0) {
                $issues[] = self::issue(
                    'error',
                    'unsupported_nonempty_header',
                    'Nepodporovany sloupec obsahuje data v ' . $nonEmpty . ' radcich.',
                    null,
                    $header
                );
            }
        }

        /** @var array<string,array<string,mixed>> $products */
        $products = [];
        /** @var array<string,int> $seenSkus */
        $seenSkus = [];
        $normalizations = [];

        foreach ($parsed['rows'] as $row) {
            $line = $row['row'];
            $values = $row['values'];
            foreach ($values as $field => $value) {
                if (self::looksLikeFormula($value)) {
                    $issues[] = self::issue(
                        'warning',
                        'formula_like_value',
                        'Hodnota vypada jako tabulkova formule; zustava pouze inertnim textem.',
                        $line,
                        $field
                    );
                }
            }

            $rawSku = trim((string)($values['code'] ?? ''));
            [$sku, $strippedDollar] = self::normalizeSku($rawSku);
            if ($sku === '') {
                $issues[] = self::issue('error', 'empty_sku', 'SKU nesmi byt prazdne.', $line, 'code');
                continue;
            }
            if ($strippedDollar) {
                $normalizations[] = ['row' => $line, 'field' => 'code', 'rule' => 'strip_shoptet_excel_prefix'];
                $issues[] = self::issue(
                    'warning',
                    'sku_excel_prefix_removed',
                    'Ochranny prefix $ byl odstranen; uvodni nuly zustaly zachovany.',
                    $line,
                    'code'
                );
            }
            if (!preg_match('/^[A-Z0-9_\/\-. ]{1,64}$/D', $sku)) {
                $issues[] = self::issue(
                    'error',
                    'invalid_sku',
                    'SKU musi mit nejvyse 64 znaku a smi obsahovat A-Z, 0-9, mezeru a _ / - .',
                    $line,
                    'code'
                );
                continue;
            }
            if (isset($seenSkus[$sku])) {
                $issues[] = self::issue(
                    'error',
                    'duplicate_sku',
                    'SKU je po normalizaci duplicitni; prvni vyskyt je na radku ' . $seenSkus[$sku] . '.',
                    $line,
                    'code'
                );
                continue;
            }
            $seenSkus[$sku] = $line;

            $pairCode = trim((string)($values['pairCode'] ?? ''));
            if ($pairCode !== '' && !preg_match('/^[A-Z0-9]+$/D', $pairCode)) {
                $issues[] = self::issue(
                    'error',
                    'invalid_pair_code',
                    'pairCode smi obsahovat pouze A-Z a 0-9.',
                    $line,
                    'pairCode'
                );
                continue;
            }

            $name = trim((string)($values['name'] ?? ''));
            if ($name === '') {
                $issues[] = self::issue('error', 'empty_name', 'Nazev produktu nesmi byt prazdny.', $line, 'name');
            }

            $categories = self::readNumberedValues($values, 'categoryText');
            $itemType = trim((string)($values['itemType'] ?? ''));
            $classificationProbe = ShopOfferClassifier::classify([
                'name' => $name,
                'default_category_path' => self::nullIfEmpty((string)($values['defaultCategory'] ?? '')),
                'additional_category_paths' => $categories,
                'item_type' => self::nullIfEmpty($itemType),
            ]);

            $price = self::parseMoney((string)($values['price'] ?? ''));
            if ($price === null) {
                $issues[] = self::issue(
                    'error',
                    'invalid_price',
                    'Cena musi byt nezaporne cislo s nejvyse dvema desetinnymi misty.',
                    $line,
                    'price'
                );
            }
            $priceRatio = trim((string)($values['priceRatio'] ?? ''));
            $priceMode = 'fixed';
            $zeroPriceRatio = $priceRatio !== '' && (bool)preg_match('/^0(?:[.,]0+)?$/D', $priceRatio);
            if ($zeroPriceRatio
                && $classificationProbe['type'] === ShopOfferClassifier::RENTAL
                && !$classificationProbe['needs_manual_review']
            ) {
                $price = 0;
                $priceMode = 'free';
                $normalizations[] = [
                    'row' => $line,
                    'field' => 'priceRatio',
                    'rule' => 'interpret_zero_ratio_as_free_rental',
                ];
            } elseif ($priceRatio !== '' && !preg_match('/^1(?:[.,]0+)?$/D', $priceRatio)) {
                $issues[] = self::issue(
                    'error',
                    'unsupported_price_ratio',
                    'Nulovy koeficient je povolen pouze u jednoznacne klasifikovane bezplatne pujcovny.',
                    $line,
                    'priceRatio'
                );
                $price = null;
                $priceMode = 'unsupported';
            }

            $standardPriceRaw = trim((string)($values['standardPrice'] ?? ''));
            $standardPrice = $standardPriceRaw === '' ? null : self::parseMoney($standardPriceRaw);
            if ($standardPriceRaw !== '' && $standardPrice === null) {
                $issues[] = self::issue(
                    'error',
                    'invalid_standard_price',
                    'Standardni cena musi byt nezaporne cislo s nejvyse dvema desetinnymi misty.',
                    $line,
                    'standardPrice'
                );
            }

            $currency = strtoupper(trim((string)($values['currency'] ?? '')));
            $currencyHasKnownMinorUnit = false;
            if ($currency === '') {
                $issues[] = self::issue(
                    'error',
                    'missing_currency',
                    'Mena musi byt v prvnim kontraktu uvedena explicitne.',
                    $line,
                    'currency'
                );
            } elseif (!preg_match('/^[A-Z]{3}$/D', $currency)) {
                $issues[] = self::issue('error', 'invalid_currency', 'Mena musi byt tri velka pismena.', $line, 'currency');
            } elseif ($currency !== 'CZK') {
                $issues[] = self::issue(
                    'error',
                    'unsupported_currency_minor_unit',
                    'Prozatimni kontrakt umi bezpecne prevest na minor units pouze CZK.',
                    $line,
                    'currency'
                );
            } else {
                $currencyHasKnownMinorUnit = true;
            }
            if (!$currencyHasKnownMinorUnit) {
                $price = null;
                $standardPrice = null;
            }

            $includingVat = self::parseBoolean((string)($values['includingVat'] ?? ''));
            if (array_key_exists('includingVat', $values) && trim($values['includingVat']) !== '' && $includingVat === null) {
                $issues[] = self::issue('error', 'invalid_including_vat', 'includingVat smi byt jen 0 nebo 1.', $line, 'includingVat');
            } elseif (!array_key_exists('includingVat', $values) || trim($values['includingVat']) === '') {
                $issues[] = self::issue(
                    'warning',
                    'vat_basis_unknown',
                    'Export neurcuje, zda cena zahrnuje DPH.',
                    $line,
                    'includingVat'
                );
            }

            $vatBasisPoints = self::parsePercentBasisPoints((string)($values['percentVat'] ?? ''));
            if (array_key_exists('percentVat', $values) && trim($values['percentVat']) !== '' && $vatBasisPoints === null) {
                $issues[] = self::issue('error', 'invalid_vat_rate', 'Sazba DPH ma neplatny format.', $line, 'percentVat');
            }

            $visibility = trim((string)($values['productVisibility'] ?? ''));
            if ($visibility !== '' && !in_array($visibility, self::PRODUCT_VISIBILITIES, true)) {
                $issues[] = self::issue(
                    'error',
                    'invalid_product_visibility',
                    'Viditelnost produktu nema podporovanou hodnotu.',
                    $line,
                    'productVisibility'
                );
            }

            if ($itemType !== '' && !in_array($itemType, ['product', 'service'], true)) {
                $issues[] = self::issue(
                    'error',
                    'unsupported_item_type',
                    'Dry-run zatim podporuje pouze bezny produkt nebo sluzbu.',
                    $line,
                    'itemType'
                );
            }

            $ean = trim((string)($values['ean'] ?? ''));
            if ($ean !== '' && !preg_match('/^\d{1,14}$/D', $ean)) {
                $issues[] = self::issue(
                    'error',
                    'invalid_ean',
                    'EAN smi obsahovat nejvyse 14 cislic.',
                    $line,
                    'ean'
                );
            }

            $variantVisible = self::parseBoolean((string)($values['variantVisibility'] ?? ''));
            if (array_key_exists('variantVisibility', $values)
                && trim($values['variantVisibility']) !== ''
                && $variantVisible === null
            ) {
                $issues[] = self::issue(
                    'error',
                    'invalid_variant_visibility',
                    'variantVisibility smi byt jen 0 nebo 1.',
                    $line,
                    'variantVisibility'
                );
            }

            $stock = self::parseDecimalString((string)($values['stock'] ?? ''), 3, true);
            if (array_key_exists('stock', $values) && trim($values['stock']) !== '' && $stock === null) {
                $issues[] = self::issue('error', 'invalid_stock', 'Sklad ma neplatny ciselny format.', $line, 'stock');
            }
            $decimalCount = self::parseDecimalCount((string)($values['decimalCount'] ?? ''));
            if (array_key_exists('decimalCount', $values)
                && trim($values['decimalCount']) !== ''
                && $decimalCount === null
            ) {
                $issues[] = self::issue(
                    'error',
                    'invalid_decimal_count',
                    'decimalCount smi byt pouze 0, 1, 2 nebo 3.',
                    $line,
                    'decimalCount'
                );
            }
            $allowNegative = self::parseBoolean((string)($values['negativeAmount'] ?? ''));
            if (array_key_exists('negativeAmount', $values)
                && trim($values['negativeAmount']) !== ''
                && $allowNegative === null
            ) {
                $issues[] = self::issue('error', 'invalid_negative_amount', 'negativeAmount smi byt jen 0 nebo 1.', $line, 'negativeAmount');
            }

            $unit = trim((string)($values['unit'] ?? ''));
            $unitCode = self::canonicalUnit($unit);
            if ($unit !== '' && $unitCode === 'other') {
                $issues[] = self::issue(
                    'warning',
                    'nonstandard_unit_preserved',
                    'Nestandardni jednotka zustava zachovana pro rucni kontrolu.',
                    $line,
                    'unit'
                );
            }
            $availabilityInStock = self::nullIfEmpty((string)($values['availabilityInStock'] ?? ''));
            $availabilityOutOfStock = self::nullIfEmpty((string)($values['availabilityOutOfStock'] ?? ''));

            $freeShipping = self::optionalBoolean($values, 'freeShipping', $line, $issues);
            $freeBilling = self::optionalBoolean($values, 'freeBilling', $line, $issues);

            $description = (string)($values['description'] ?? '');
            if ($description !== '' && preg_match('/<[^>]+>/', $description)) {
                $issues[] = self::issue(
                    'warning',
                    'html_kept_untrusted',
                    'HTML popis je zachovan pouze jako neduveryhodna data.',
                    $line,
                    'description'
                );
            }

            $attributes = [];
            foreach ($variantHeaders as $header) {
                $value = trim((string)($values[$header] ?? ''));
                if ($value !== '') {
                    $attributes[substr($header, 8)] = $value;
                }
            }
            ksort($attributes, SORT_STRING);

            $images = self::readNumberedValues($values, 'image');
            foreach ($images as $image) {
                $scheme = strtolower((string)parse_url($image, PHP_URL_SCHEME));
                if ($scheme === 'http') {
                    $issues[] = self::issue(
                        'warning',
                        'insecure_image_url',
                        'Obrazek pouziva HTTP; dry-run jej nestahuje.',
                        $line,
                        'image'
                    );
                } elseif ($scheme !== 'https') {
                    $issues[] = self::issue(
                        'error',
                        'invalid_image_url',
                        'Obrazek musi mit absolutni HTTP nebo HTTPS URL; dry-run jej nestahuje.',
                        $line,
                        'image'
                    );
                }
            }
            $key = $pairCode !== '' ? 'shoptet:pair:' . $pairCode : 'shoptet:sku:' . $sku;
            $productFields = [
                'name' => $name,
                'short_description' => self::nullIfEmpty((string)($values['shortDescription'] ?? '')),
                'description_html_untrusted' => self::nullIfEmpty($description),
                'default_category_path' => self::nullIfEmpty((string)($values['defaultCategory'] ?? '')),
                'visibility' => self::nullIfEmpty($visibility),
                'item_type' => self::nullIfEmpty($itemType),
            ];

            if (!isset($products[$key])) {
                $products[$key] = [
                    'external_product_key' => $key,
                    'source_pair_code' => $pairCode === '' ? null : $pairCode,
                    ...$productFields,
                    'additional_category_paths' => $categories,
                    'images' => $images,
                    'variants' => [],
                    '_first_row' => $line,
                ];
            } else {
                foreach ($productFields as $field => $value) {
                    $existing = $products[$key][$field];
                    if ($existing !== null && $existing !== '' && $value !== null && $value !== '' && $existing !== $value) {
                        $issues[] = self::issue(
                            'error',
                            'conflicting_product_field',
                            'Varianty se stejnym pairCode maji rozdilne produktove udaje.',
                            $line,
                            $field
                        );
                    } elseif (($existing === null || $existing === '') && $value !== null && $value !== '') {
                        $products[$key][$field] = $value;
                    }
                }
                $products[$key]['additional_category_paths'] = self::mergeUnique(
                    $products[$key]['additional_category_paths'],
                    $categories
                );
                $products[$key]['images'] = self::mergeUnique($products[$key]['images'], $images);
            }

            $products[$key]['variants'][] = [
                'sku' => $sku,
                'ean' => self::nullIfEmpty($ean),
                'attributes' => $attributes,
                'price' => [
                    'amount_minor' => $price,
                    'compare_at_amount_minor' => $standardPrice,
                    'mode' => $priceMode,
                    'currency' => $currency === '' ? null : $currency,
                    'includes_vat' => $includingVat,
                    'vat_rate_basis_points' => $vatBasisPoints,
                    'source_price_decimal' => self::nullIfEmpty((string)($values['price'] ?? '')),
                    'source_price_ratio_decimal' => self::nullIfEmpty($priceRatio),
                ],
                'stock' => [
                    'quantity_decimal' => $stock,
                    'decimal_count' => $decimalCount,
                    'allow_negative' => $allowNegative,
                    'availability_in_stock' => $availabilityInStock,
                    'availability_out_of_stock' => $availabilityOutOfStock,
                ],
                'unit' => [
                    'code' => $unitCode,
                    'source' => self::nullIfEmpty($unit),
                ],
                'fulfillment' => [
                    'free_shipping' => $freeShipping,
                    'free_billing' => $freeBilling,
                ],
                'visible' => $variantVisible,
            ];
        }

        ksort($products, SORT_STRING);
        foreach ($products as &$product) {
            unset($product['_first_row']);
            sort($product['additional_category_paths'], SORT_STRING);
            sort($product['images'], SORT_STRING);
            usort(
                $product['variants'],
                static fn (array $left, array $right): int => strcmp($left['sku'], $right['sku'])
            );
            $product['offer_classification'] = ShopOfferClassifier::classify($product);
        }
        unset($product);

        usort(
            $issues,
            static function (array $left, array $right): int {
                return [$left['severity'], $left['row'] ?? 0, $left['field'] ?? '', $left['code']]
                    <=> [$right['severity'], $right['row'] ?? 0, $right['field'] ?? '', $right['code']];
            }
        );
        $errors = count(array_filter($issues, static fn (array $issue): bool => $issue['severity'] === 'error'));
        $warnings = count($issues) - $errors;
        $variantCount = array_sum(array_map(
            static fn (array $product): int => count($product['variants']),
            $products
        ));
        $offerTypeCounts = array_fill_keys(ShopOfferClassifier::TYPES, 0);
        $manualReviewProducts = 0;
        foreach ($products as $product) {
            $classification = $product['offer_classification'];
            $offerTypeCounts[$classification['type']]++;
            if ($classification['needs_manual_review']) {
                $manualReviewProducts++;
            }
        }

        return [
            'contract_version' => self::VERSION,
            'provisional' => true,
            'source' => $parsed['source'],
            'summary' => [
                'products' => count($products),
                'variants' => $variantCount,
                'errors' => $errors,
                'warnings' => $warnings,
                'contract_ready' => $errors === 0,
                'database_writes' => 0,
                'offer_type_counts' => $offerTypeCounts,
                'manual_review_products' => $manualReviewProducts,
            ],
            'normalizations' => $normalizations,
            'issues' => $issues,
            'products' => array_values($products),
        ];
    }

    private static function isSupportedHeader(string $header): bool
    {
        if (in_array($header, self::SUPPORTED_HEADERS, true)) {
            return true;
        }
        return (bool)preg_match('/^(variant:[^:]+|image\d*|categoryText\d*)$/D', $header);
    }

    /** @return array{string,bool} */
    private static function normalizeSku(string $sku): array
    {
        if (str_starts_with($sku, '$')) {
            return [substr($sku, 1), true];
        }
        return [$sku, false];
    }

    private static function parseMoney(string $value): ?int
    {
        $value = trim($value);
        $value = preg_replace('/(?<=\d)[ \x{00A0}\x{202F}](?=\d)/u', '', $value) ?? $value;
        if (!preg_match('/^(\d{1,12})(?:[.,](\d{1,2}))?$/D', $value, $matches)) {
            return null;
        }
        $fraction = str_pad($matches[2] ?? '', 2, '0');
        return ((int)$matches[1] * 100) + (int)$fraction;
    }

    private static function parsePercentBasisPoints(string $value): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (!preg_match('/^(\d{1,3})(?:[.,](\d{1,2}))?$/D', $value, $matches)) {
            return null;
        }
        $basisPoints = ((int)$matches[1] * 100) + (int)str_pad($matches[2] ?? '', 2, '0');
        return $basisPoints <= 10000 ? $basisPoints : null;
    }

    private static function parseBoolean(string $value): ?bool
    {
        $value = trim($value);
        return match ($value) {
            '0' => false,
            '1' => true,
            default => null,
        };
    }

    /**
     * @param array<string,string> $values
     * @param list<array{severity:string,code:string,message:string,row:?int,field:?string}> $issues
     */
    private static function optionalBoolean(array $values, string $field, int $line, array &$issues): ?bool
    {
        if (!array_key_exists($field, $values) || trim($values[$field]) === '') {
            return null;
        }
        $value = self::parseBoolean($values[$field]);
        if ($value === null) {
            $codeField = strtolower((string)preg_replace('/(?<!^)[A-Z]/', '_$0', $field));
            $issues[] = self::issue(
                'error',
                'invalid_' . $codeField,
                $field . ' smi byt jen 0 nebo 1.',
                $line,
                $field
            );
        }
        return $value;
    }

    private static function canonicalUnit(string $unit): ?string
    {
        return match (mb_strtolower(trim($unit), 'UTF-8')) {
            '' => null,
            'ks' => 'piece',
            'sada' => 'set',
            default => 'other',
        };
    }

    private static function parseDecimalCount(string $value): ?int
    {
        $value = trim($value);
        return in_array($value, ['0', '1', '2', '3'], true) ? (int)$value : null;
    }

    private static function parseDecimalString(string $value, int $scale, bool $signed): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $sign = $signed ? '-?' : '';
        if (!preg_match('/^' . $sign . '\d+(?:[.,]\d{1,' . $scale . '})?$/D', $value)) {
            return null;
        }
        return str_replace(',', '.', $value);
    }

    private static function looksLikeFormula(string $value): bool
    {
        $value = ltrim($value);
        if ($value === '') {
            return false;
        }
        if (in_array($value[0], ['=', '+', '@'], true)) {
            return true;
        }
        return $value[0] === '-' && !preg_match('/^-\d+(?:[.,]\d+)?$/D', $value);
    }

    /** @param array<string,string> $values @return list<string> */
    private static function readNumberedValues(array $values, string $prefix): array
    {
        $result = [];
        foreach ($values as $header => $value) {
            if (preg_match('/^' . preg_quote($prefix, '/') . '\d*$/D', $header) && trim($value) !== '') {
                $result[] = trim($value);
            }
        }
        return array_values(array_unique($result, SORT_STRING));
    }

    private static function nullIfEmpty(string $value): ?string
    {
        $value = trim($value);
        return $value === '' ? null : $value;
    }

    /** @param list<string> $left @param list<string> $right @return list<string> */
    private static function mergeUnique(array $left, array $right): array
    {
        return array_values(array_unique([...$left, ...$right], SORT_STRING));
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
