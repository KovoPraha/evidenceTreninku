<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/shoptet_product_input.php';
require_once dirname(__DIR__, 2) . '/includes/shop_catalog_contract.php';

final class ShoptetProductXmlTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../fixtures/shoptet/products-export.xml';

    public function testNormalizesXmlProductsAndVariantsIntoCatalogContract(): void
    {
        $parsed = \ShoptetProductXml::read(self::FIXTURE);
        $result = \ShopCatalogContract::build($parsed);

        self::assertSame('xml', $parsed['source']['delimiter']);
        self::assertSame(3, $parsed['source']['rows']);
        self::assertSame(hash('sha256', (string)file_get_contents(self::FIXTURE)), $parsed['source']['sha256']);
        self::assertContains('standardPrice', $parsed['headers']);
        self::assertContains('availabilityInStock', $parsed['headers']);
        self::assertContains('freeBilling', $parsed['headers']);
        self::assertTrue($result['summary']['contract_ready']);
        self::assertSame(2, $result['summary']['products']);
        self::assertSame(3, $result['summary']['variants']);
        self::assertSame(0, $result['summary']['database_writes']);

        $camp = $this->product($result, 'shoptet:pair:100');
        self::assertSame('camp', $camp['offer_classification']['type']);
        self::assertSame(100000, $camp['variants'][0]['price']['amount_minor']);
        self::assertSame(120000, $camp['variants'][0]['price']['compare_at_amount_minor']);
        self::assertSame('fixed', $camp['variants'][0]['price']['mode']);
        self::assertFalse($camp['variants'][0]['price']['includes_vat']);
        self::assertSame(2100, $camp['variants'][0]['price']['vat_rate_basis_points']);
        self::assertSame(['code' => 'piece', 'source' => 'ks'], $camp['variants'][0]['unit']);
        self::assertSame('Skladem', $camp['variants'][0]['stock']['availability_in_stock']);
        self::assertTrue($camp['variants'][0]['fulfillment']['free_billing']);
        self::assertFalse($camp['variants'][0]['fulfillment']['free_shipping']);

        $jersey = $this->product($result, 'shoptet:pair:200');
        self::assertSame('goods', $jersey['offer_classification']['type']);
        self::assertSame(['DRES-M', 'DRES-S'], array_column($jersey['variants'], 'sku'));
        self::assertSame(['Barva' => 'Červená', 'Velikost' => 'M'], $jersey['variants'][0]['attributes']);
        self::assertSame(180000, $jersey['variants'][0]['price']['compare_at_amount_minor']);
        self::assertSame(['https://example.invalid/dres.jpg'], $jersey['images']);
    }

    public function testContentDetectionAcceptsXmlSavedWithCsvExtension(): void
    {
        $base = tempnam(sys_get_temp_dir(), 'shop-xml-csv-');
        self::assertIsString($base);
        $csv = $base . '.csv';
        self::assertTrue(copy(self::FIXTURE, $csv));

        try {
            $parsed = \ShoptetProductInput::read($csv);
            self::assertSame('xml', $parsed['source']['delimiter']);
            self::assertSame(3, $parsed['source']['rows']);
        } finally {
            @unlink($csv);
            @unlink($base);
        }
    }

    public function testRejectsDoctypeAndExternalEntityDeclarations(): void
    {
        $base = tempnam(sys_get_temp_dir(), 'shop-xml-xxe-');
        self::assertIsString($base);
        $xml = $base . '.xml';
        file_put_contents(
            $xml,
            '<?xml version="1.0"?><!DOCTYPE SHOP [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>'
            . '<SHOP><SHOPITEM id="1"><NAME>&xxe;</NAME></SHOPITEM></SHOP>'
        );

        try {
            $this->expectException(\ShoptetProductXmlException::class);
            $this->expectExceptionMessage('DOCTYPE');
            \ShoptetProductXml::read($xml);
        } finally {
            @unlink($xml);
            @unlink($base);
        }
    }

    public function testInputDetectionRejectsRemoteUrlsBeforeReading(): void
    {
        $this->expectException(\ShoptetProductXmlException::class);
        $this->expectExceptionMessage('lokalni regularni soubor');
        \ShoptetProductInput::read('https://example.invalid/products.csv');
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
