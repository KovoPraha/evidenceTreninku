<?php

declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/bar/catalog_transfer.php';

final class BarCatalogTransferTest extends TestCase
{
    public function testCataloguePreservesConfigurationAndNeverCopiesTestQuantitiesOrHistories(): void
    {
        $source = $this->source();
        $source['orders'] = [['amount' => 9999]];
        $catalog = \barPrepareSourceCatalog($source);
        self::assertSame(3500, $catalog['products'][0]['price_minor']);
        self::assertSame(1101, $catalog['products'][0]['purchase_minor']);
        self::assertNull($catalog['products'][1]['purchase_minor']);
        self::assertSame('LOCALHOST čaj', $catalog['products'][0]['name']);
        self::assertSame('Tea package', $catalog['products'][0]['package_name']);
        self::assertSame(20, $catalog['products'][0]['package_yield']);
        self::assertSame(2, $catalog['categories'][0]['color_index']);
        self::assertSame(['tea.png'], $catalog['required_images']);
        self::assertSame(0, $catalog['products'][0]['stock_stall']);
        self::assertSame(0, $catalog['products'][0]['stock_office']);
        self::assertSame(1, $catalog['products'][1]['archived']);
        self::assertSame(0, $catalog['products'][1]['on_terminal']);
        self::assertSame([['product_source_id' => 2, 'ingredient_source_id' => 1, 'quantity' => 2]], $catalog['recipes']);
        self::assertArrayNotHasKey('orders', $catalog);
        $source['products'][0]['sklad'] = 5555;
        $source['products'][0]['predsklad'] = 1111;
        $source['products'] = array_reverse($source['products']);
        self::assertSame($catalog, \barPrepareSourceCatalog($source));
        $source['products'][0]['nazev'] = 'Changed product';
        self::assertNotSame($catalog['sha256'], \barPrepareSourceCatalog($source)['sha256']);
    }

    public static function invalidPrices(): array
    {
        return array_map(static fn ($value): array => [$value], [null, [], 1.1, '-1', '1e3', '1,23', '1.001', '999999999', '01', '35 Kč']);
    }

    #[DataProvider('invalidPrices')]
    public function testInvalidMoneyIsNeverRoundedOrCoerced(mixed $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        \barSourceMoney($value);
    }

    public function testExactHalereAndZeroArePreserved(): void
    {
        self::assertSame(0, \barSourceMoney('0.00'));
        self::assertSame(1, \barSourceMoney('0.01'));
        self::assertSame(3500, \barSourceMoney(35));
        self::assertSame(3510, \barSourceMoney('35.1'));
        self::assertSame(9999999999, \barSourceMoney('99999999.99'));
    }

    public static function invalidCatalogues(): array
    {
        return [
            'duplicate product' => ['duplicate'], 'missing category' => ['category'], 'unsafe image' => ['image'],
            'unknown stock mode' => ['mode'], 'missing ingredient' => ['ingredient'], 'self recipe' => ['self'],
            'nested recipe' => ['nested'], 'untracked ingredient' => ['untracked'], 'duplicate recipe' => ['recipe'],
        ];
    }

    #[DataProvider('invalidCatalogues')]
    public function testInvalidLinksOrImagesStopThePreflight(string $case): void
    {
        $source = $this->source();
        switch ($case) {
            case 'duplicate':
                $source['products'][] = $source['products'][0];
                break;
            case 'category':
                $source['products'][0]['kategorie_id'] = 99;
                break;
            case 'image':
                $source['products'][0]['obrazek'] = '../config.php';
                break;
            case 'mode':
                $source['products'][0]['skladovy_rezim'] = 'unknown';
                break;
            case 'ingredient':
                $source['recipes'][0]['slozka_id'] = 99;
                break;
            case 'self':
                $source['recipes'][0]['slozka_id'] = 2;
                break;
            case 'nested':
                $source['recipes'][] = ['produkt_id' => 1, 'slozka_id' => 2, 'mnozstvi' => 1];
                break;
            case 'untracked':
                $source['products'][0]['skladovy_rezim'] = 'nesledovat';
                break;
            case 'recipe':
                $source['recipes'][] = $source['recipes'][0];
                break;
        }
        $this->expectException(InvalidArgumentException::class);
        \barPrepareSourceCatalog($source);
    }

    private function source(): array
    {
        $product = ['id' => 1, 'kategorie_id' => 4, 'nazev' => 'LOCALHOST čaj', 'popis' => 'Description',
            'cena' => '35.00', 'nakupni_cena' => '11.01', 'obrazek' => 'tea.png', 'skladovy_rezim' => 'sledovat',
            'sklad' => 100, 'predsklad' => 200, 'baleni_nazev' => 'Tea package', 'baleni_vytez' => 20];
        $second = array_replace($product, ['id' => 2, 'nakupni_cena' => null, 'archivovan' => 1, 'na_terminalu' => 0]);
        return ['categories' => [['id' => 4, 'nazev' => 'Drinks', 'poradi' => 2, 'barva' => 2]],
            'products' => [$product, $second], 'recipes' => [['produkt_id' => 2, 'slozka_id' => 1, 'mnozstvi' => 2]]];
    }
}
