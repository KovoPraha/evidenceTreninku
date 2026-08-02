<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/shoptet_product_csv.php';
require_once dirname(__DIR__, 2) . '/includes/shop_catalog_contract.php';

final class ShopCatalogContractTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../fixtures/shoptet';

    public function testBuildsTwoProductsAndThreeVariantsWithoutDatabase(): void
    {
        $result = \ShopCatalogContract::build(
            \ShoptetProductCsv::read(self::FIXTURES . '/products-valid.csv')
        );

        self::assertTrue($result['provisional']);
        self::assertTrue($result['summary']['contract_ready']);
        self::assertSame(2, $result['summary']['products']);
        self::assertSame(3, $result['summary']['variants']);
        self::assertSame(0, $result['summary']['errors']);
        self::assertSame(0, $result['summary']['database_writes']);

        $dres = $this->product($result, 'shoptet:pair:DRES2026');
        self::assertCount(2, $dres['variants']);
        self::assertSame('000123', $dres['variants'][0]['sku']);
        self::assertSame(129000, $dres['variants'][0]['price']['amount_minor']);
        self::assertSame('CZK', $dres['variants'][0]['price']['currency']);
        self::assertSame(['Velikost' => 'S'], $dres['variants'][0]['attributes']);
        self::assertSame('<p>Fiktivní testovací dres.</p>', $dres['description_html_untrusted']);
        self::assertContains('html_kept_untrusted', array_column($result['issues'], 'code'));
        self::assertContains('sku_excel_prefix_removed', array_column($result['issues'], 'code'));
    }

    public function testParsesLocalizedMoneyExactlyToMinorUnits(): void
    {
        $regularSpace = $this->resultForPrice('1 234,50');
        $nbsp = $this->resultForPrice("1\u{00A0}234,50");
        $narrowNbsp = $this->resultForPrice("1\u{202F}234,50");

        self::assertSame(123450, $regularSpace['products'][0]['variants'][0]['price']['amount_minor']);
        self::assertSame(123450, $nbsp['products'][0]['variants'][0]['price']['amount_minor']);
        self::assertSame(123450, $narrowNbsp['products'][0]['variants'][0]['price']['amount_minor']);
    }

    public function testDuplicateSkuAfterDollarNormalizationIsBlocking(): void
    {
        $result = \ShopCatalogContract::build(
            \ShoptetProductCsv::read(self::FIXTURES . '/products-invalid.csv')
        );

        self::assertFalse($result['summary']['contract_ready']);
        self::assertContains('duplicate_sku', array_column($result['issues'], 'code'));
        self::assertContains('empty_sku', array_column($result['issues'], 'code'));
        self::assertContains('unsupported_nonempty_header', array_column($result['issues'], 'code'));
    }

    public function testFormulaLikeValuesRemainInertAndAreReported(): void
    {
        $parsed = $this->parsedRow([
            'code' => 'TEST-1',
            'pairCode' => '',
            'name' => '=2+2',
            'price' => '100',
            'currency' => 'CZK',
            'includingVat' => '1',
        ]);

        $result = \ShopCatalogContract::build($parsed);

        self::assertTrue($result['summary']['contract_ready']);
        self::assertSame('=2+2', $result['products'][0]['name']);
        self::assertContains('formula_like_value', array_column($result['issues'], 'code'));
    }

    public function testMissingCurrencyIsAnExplicitProvisionalBlocker(): void
    {
        $parsed = $this->parsedRow([
            'code' => 'TEST-1',
            'pairCode' => '',
            'name' => 'Test',
            'price' => '100',
        ]);

        $result = \ShopCatalogContract::build($parsed);

        self::assertFalse($result['summary']['contract_ready']);
        self::assertContains('missing_currency', array_column($result['issues'], 'code'));
    }

    public function testSyntheticVariantMatrixIsGroupedAndSortedDeterministically(): void
    {
        $parsed = \ShoptetProductCsv::read(self::FIXTURES . '/products-variant-matrix.csv');
        $first = \ShopCatalogContract::build($parsed);
        $second = \ShopCatalogContract::build($parsed);

        self::assertTrue($first['summary']['contract_ready']);
        self::assertSame(2, $first['summary']['products']);
        self::assertSame(4, $first['summary']['variants']);
        self::assertSame(0, $first['summary']['errors']);
        self::assertSame($first, $second);

        $dres = $this->product($first, 'shoptet:pair:DRESMATRIX');
        self::assertSame(['0100', 'DRES-M-C', 'DRES-S-M'], array_column($dres['variants'], 'sku'));
        self::assertSame(
            ['Barva' => 'Červená', 'Velikost' => 'S'],
            $dres['variants'][0]['attributes']
        );
        self::assertSame(123450, $dres['variants'][0]['price']['amount_minor']);
    }

    public function testDuplicateSkuFixtureReportsEveryCollisionWithoutOverwrite(): void
    {
        $result = \ShopCatalogContract::build(
            \ShoptetProductCsv::read(self::FIXTURES . '/products-duplicate-sku.csv')
        );
        $duplicates = array_values(array_filter(
            $result['issues'],
            static fn (array $issue): bool => $issue['code'] === 'duplicate_sku'
        ));

        self::assertFalse($result['summary']['contract_ready']);
        self::assertCount(2, $duplicates);
        self::assertSame([3, 5], array_column($duplicates, 'row'));
        self::assertStringContainsString('radku 2', $duplicates[0]['message']);
        self::assertStringContainsString('radku 4', $duplicates[1]['message']);
        self::assertSame(2, $result['summary']['products']);
        self::assertSame(2, $result['summary']['variants']);
    }

    public function testCatalogMoneyAndVatFixtureKeepsExactTypedValues(): void
    {
        $result = \ShopCatalogContract::build(
            \ShoptetProductCsv::read(self::FIXTURES . '/products-money-vat.csv')
        );

        self::assertTrue($result['summary']['contract_ready']);
        self::assertSame(4, $result['summary']['products']);
        self::assertSame(4, $result['summary']['variants']);
        self::assertSame(0, $result['summary']['errors']);

        $zero = $this->product($result, 'shoptet:sku:ZERO')['variants'][0]['price'];
        self::assertSame(0, $zero['amount_minor']);
        self::assertTrue($zero['includes_vat']);
        self::assertSame(0, $zero['vat_rate_basis_points']);

        $gross = $this->product($result, 'shoptet:sku:GROSS')['variants'][0]['price'];
        self::assertSame(123450, $gross['amount_minor']);
        self::assertSame('CZK', $gross['currency']);
        self::assertTrue($gross['includes_vat']);
        self::assertSame(2100, $gross['vat_rate_basis_points']);

        $net = $this->product($result, 'shoptet:sku:NET')['variants'][0]['price'];
        self::assertSame(999999, $net['amount_minor']);
        self::assertFalse($net['includes_vat']);
        self::assertSame(1250, $net['vat_rate_basis_points']);

        $unknown = $this->product($result, 'shoptet:sku:VATUNKNOWN')['variants'][0]['price'];
        self::assertNull($unknown['includes_vat']);
        self::assertNull($unknown['vat_rate_basis_points']);
        self::assertSame(1, count(array_filter(
            $result['issues'],
            static fn (array $issue): bool => $issue['code'] === 'vat_basis_unknown'
        )));
    }

    public function testUnsupportedCurrencyDoesNotProduceMisleadingMinorAmount(): void
    {
        $result = \ShopCatalogContract::build($this->parsedRow([
            'code' => 'EUR-PRICE',
            'pairCode' => '',
            'name' => 'Fiktivní cizí měna',
            'price' => '10,00',
            'currency' => 'EUR',
            'includingVat' => '1',
        ]));
        $price = $result['products'][0]['variants'][0]['price'];

        self::assertFalse($result['summary']['contract_ready']);
        self::assertContains('unsupported_currency_minor_unit', array_column($result['issues'], 'code'));
        self::assertNull($price['amount_minor']);
        self::assertSame('EUR', $price['currency']);
        self::assertSame('10,00', $price['source_price_decimal']);
    }

    public function testMalformedMoneyCurrencyAndVatFailClosed(): void
    {
        $cases = [
            ['price', '-1', 'invalid_price'],
            ['price', '0.001', 'invalid_price'],
            ['price', '1.234,50', 'invalid_price'],
            ['currency', '', 'missing_currency'],
            ['currency', 'CZ', 'invalid_currency'],
            ['includingVat', '2', 'invalid_including_vat'],
            ['percentVat', '-1', 'invalid_vat_rate'],
            ['percentVat', '100.01', 'invalid_vat_rate'],
        ];

        foreach ($cases as [$field, $value, $expectedCode]) {
            $row = [
                'code' => 'MALFORMED-1',
                'pairCode' => '',
                'name' => 'Fiktivní neplatná hodnota',
                'price' => '100',
                'currency' => 'CZK',
                'includingVat' => '1',
                'percentVat' => '21',
            ];
            $row[$field] = $value;
            $result = \ShopCatalogContract::build($this->parsedRow($row));

            self::assertFalse($result['summary']['contract_ready'], $field . '=' . $value);
            self::assertContains($expectedCode, array_column($result['issues'], 'code'), $field . '=' . $value);
            if ($field === 'currency') {
                self::assertNull($result['products'][0]['variants'][0]['price']['amount_minor']);
            }
        }
    }

    public function testOrderPaymentWalletAndDeliveryColumnsRemainOutsideCatalogContract(): void
    {
        $result = \ShopCatalogContract::build(
            \ShoptetProductCsv::read(self::FIXTURES . '/products-catalog-scope-boundary.csv')
        );
        $unsupported = array_values(array_filter(
            $result['issues'],
            static fn (array $issue): bool => $issue['code'] === 'unsupported_nonempty_header'
        ));
        $fields = array_column($unsupported, 'field');
        sort($fields, SORT_STRING);

        self::assertFalse($result['summary']['contract_ready']);
        self::assertSame(4, $result['summary']['errors']);
        self::assertSame(
            ['deliveryMethod', 'orderStatus', 'paymentMethod', 'walletCredit'],
            $fields
        );
        self::assertSame(1, $result['summary']['products']);
        self::assertSame(1, $result['summary']['variants']);
    }

    public function testOfferTypeMatrixClassifiesRealShopShapesDeterministically(): void
    {
        $parsed = \ShoptetProductCsv::read(self::FIXTURES . '/products-offer-types.csv');
        $first = \ShopCatalogContract::build($parsed);
        $second = \ShopCatalogContract::build($parsed);

        self::assertSame($first, $second);
        self::assertTrue($first['summary']['contract_ready']);
        self::assertSame([
            'goods' => 2,
            'club_event' => 1,
            'camp' => 1,
            'bookable_service' => 2,
            'rental' => 1,
            'custom_quote' => 1,
            'unclassified' => 1,
        ], $first['summary']['offer_type_counts']);
        self::assertSame(1, $first['summary']['manual_review_products']);

        self::assertSame(
            'club_event',
            $this->product($first, 'shoptet:sku:CLUB-ZEBRY')['offer_classification']['type']
        );
        self::assertSame(
            'rental',
            $this->product($first, 'shoptet:sku:RENTAL-BIKE')['offer_classification']['type']
        );
        $ambiguous = $this->product($first, 'shoptet:sku:AMBIGUOUS-1')['offer_classification'];
        self::assertSame('unclassified', $ambiguous['type']);
        self::assertTrue($ambiguous['needs_manual_review']);
        self::assertContains('candidate:club_event', $ambiguous['signals']);
        self::assertContains('candidate:rental', $ambiguous['signals']);
    }

    public function testClubShirtCategoryRemainsPhysicalGoods(): void
    {
        $result = \ShopCatalogContract::build($this->parsedRow([
            'code' => 'CLUB-SHIRT',
            'pairCode' => '',
            'name' => 'Cyklo kroužek - tričko',
            'price' => '250',
            'currency' => 'CZK',
            'includingVat' => '1',
            'defaultCategory' => 'Volnočasové oblečení > Cyklo kroužek - trička',
            'itemType' => 'product',
        ]));
        $classification = $result['products'][0]['offer_classification'];

        self::assertSame('goods', $classification['type']);
        self::assertSame('high', $classification['confidence']);
        self::assertFalse($classification['needs_manual_review']);
        self::assertNotContains('category:club_event', $classification['signals']);
    }

    public function testUnknownServiceFailsSafeToManualScheduleReview(): void
    {
        $result = \ShopCatalogContract::build($this->parsedRow([
            'code' => 'SERVICE-UNKNOWN',
            'pairCode' => '',
            'name' => 'Fiktivní služba',
            'price' => '500',
            'currency' => 'CZK',
            'includingVat' => '1',
            'itemType' => 'service',
        ]));
        $classification = $result['products'][0]['offer_classification'];

        self::assertSame('bookable_service', $classification['type']);
        self::assertSame('low', $classification['confidence']);
        self::assertTrue($classification['needs_manual_review']);
        self::assertSame(1, $result['summary']['manual_review_products']);
    }

    public function testSpacedSaleCategoryIsRecognizedAsGoods(): void
    {
        $result = \ShopCatalogContract::build($this->parsedRow([
            'code' => 'SALE-JERSEY',
            'pairCode' => '',
            'name' => 'Fiktivní výprodejový dres',
            'price' => '500',
            'currency' => 'CZK',
            'includingVat' => '1',
            'defaultCategory' => 'V Ý P R O D E J > Dresy',
            'itemType' => 'product',
        ]));
        $classification = $result['products'][0]['offer_classification'];

        self::assertSame('goods', $classification['type']);
        self::assertSame('high', $classification['confidence']);
        self::assertFalse($classification['needs_manual_review']);
    }

    public function testZeroPriceRatioIsAcceptedOnlyAsExplicitFreeRental(): void
    {
        $result = \ShopCatalogContract::build($this->parsedRow([
            'code' => 'FREE-BIKE',
            'pairCode' => '',
            'name' => 'Půjčovna kola zdarma',
            'price' => '1',
            'priceRatio' => '0',
            'standardPrice' => '',
            'currency' => 'CZK',
            'includingVat' => '0',
            'defaultCategory' => 'Půjčovna kol - praktické půjčování',
            'itemType' => 'product',
            'unit' => 'ks',
            'availabilityInStock' => 'Skladem',
            'availabilityOutOfStock' => 'Momentálně nedostupné',
            'freeShipping' => '0',
            'freeBilling' => '1',
        ]));
        $variant = $result['products'][0]['variants'][0];

        self::assertTrue($result['summary']['contract_ready']);
        self::assertSame('rental', $result['products'][0]['offer_classification']['type']);
        self::assertSame(0, $variant['price']['amount_minor']);
        self::assertSame('free', $variant['price']['mode']);
        self::assertSame('1', $variant['price']['source_price_decimal']);
        self::assertSame('0', $variant['price']['source_price_ratio_decimal']);
        self::assertSame(['code' => 'piece', 'source' => 'ks'], $variant['unit']);
        self::assertSame('Skladem', $variant['stock']['availability_in_stock']);
        self::assertTrue($variant['fulfillment']['free_billing']);
        self::assertContains(
            'interpret_zero_ratio_as_free_rental',
            array_column($result['normalizations'], 'rule')
        );
    }

    public function testZeroPriceRatioOutsideRentalRemainsBlocking(): void
    {
        $result = \ShopCatalogContract::build($this->parsedRow([
            'code' => 'FREE-JERSEY',
            'pairCode' => '',
            'name' => 'Fiktivní dres',
            'price' => '1',
            'priceRatio' => '0',
            'currency' => 'CZK',
            'includingVat' => '1',
            'defaultCategory' => 'CYKLISTICKÉ OBLEČENÍ > DRESY',
            'itemType' => 'product',
        ]));
        $price = $result['products'][0]['variants'][0]['price'];

        self::assertFalse($result['summary']['contract_ready']);
        self::assertContains('unsupported_price_ratio', array_column($result['issues'], 'code'));
        self::assertNull($price['amount_minor']);
        self::assertSame('unsupported', $price['mode']);
    }

    /** @return array<string,mixed> */
    private function resultForPrice(string $price): array
    {
        return \ShopCatalogContract::build($this->parsedRow([
            'code' => 'PRICE-1',
            'pairCode' => '',
            'name' => 'Cena',
            'price' => $price,
            'currency' => 'CZK',
            'includingVat' => '1',
        ]));
    }

    /** @param array<string,string> $values @return array<string,mixed> */
    private function parsedRow(array $values): array
    {
        return [
            'source' => [
                'filename' => 'synthetic.csv',
                'sha256' => str_repeat('a', 64),
                'encoding' => 'UTF-8',
                'delimiter' => 'semicolon',
                'rows' => 1,
                'columns' => count($values),
            ],
            'headers' => array_keys($values),
            'rows' => [['row' => 2, 'values' => $values]],
            'issues' => [],
        ];
    }

    /** @param array<string,mixed> $result @return array<string,mixed> */
    private function product(array $result, string $key): array
    {
        foreach ($result['products'] as $product) {
            if ($product['external_product_key'] === $key) {
                return $product;
            }
        }
        self::fail('Product not found: ' . $key);
    }
}
