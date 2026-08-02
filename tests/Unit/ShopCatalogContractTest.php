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
