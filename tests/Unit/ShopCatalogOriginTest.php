<?php
declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/shop_catalog_origin.php';

final class ShopCatalogOriginTest extends TestCase
{
    public function testValidImportAndManualProductsAreAccepted(): void
    {
        \shopCatalogAssertProductOrigin(\ShopCatalogOrigin::IMPORT, 11, 12, null);
        \shopCatalogAssertProductOrigin(\ShopCatalogOrigin::MANUAL, null, null, 7);
        self::addToAssertionCount(2);
    }

    /** @return iterable<string,array{string,?int,?int,?int}> */
    public static function invalidProducts(): iterable
    {
        yield 'import bez produktu' => [\ShopCatalogOrigin::IMPORT, null, 12, null];
        yield 'import bez běhu' => [\ShopCatalogOrigin::IMPORT, 11, null, null];
        yield 'manual se zdrojem' => [\ShopCatalogOrigin::MANUAL, 11, null, 7];
        yield 'manual bez autora' => [\ShopCatalogOrigin::MANUAL, null, null, null];
        yield 'neznámý původ' => ['legacy', 11, 12, null];
    }

    #[DataProvider('invalidProducts')]
    public function testInvalidProductOriginIsRejected(
        string $origin,
        ?int $sourceCandidateId,
        ?int $sourceRunId,
        ?int $createdByTrainerId
    ): void {
        $this->expectException(InvalidArgumentException::class);
        \shopCatalogAssertProductOrigin(
            $origin,
            $sourceCandidateId,
            $sourceRunId,
            $createdByTrainerId
        );
    }

    public function testValidImportAndManualVariantsAreAccepted(): void
    {
        \shopCatalogAssertVariantOrigin(
            \ShopCatalogOrigin::IMPORT,
            21,
            null,
            \ShopCatalogOrigin::IMPORT
        );
        \shopCatalogAssertVariantOrigin(
            \ShopCatalogOrigin::MANUAL,
            null,
            7,
            \ShopCatalogOrigin::MANUAL
        );
        self::addToAssertionCount(2);
    }

    /** @return iterable<string,array{string,?int,?int,string}> */
    public static function invalidVariants(): iterable
    {
        yield 'import bez zdroje' => [\ShopCatalogOrigin::IMPORT, null, null, \ShopCatalogOrigin::IMPORT];
        yield 'manual se zdrojem' => [\ShopCatalogOrigin::MANUAL, 21, 7, \ShopCatalogOrigin::MANUAL];
        yield 'manual bez autora' => [\ShopCatalogOrigin::MANUAL, null, null, \ShopCatalogOrigin::MANUAL];
        yield 'jiný původ než produkt' => [\ShopCatalogOrigin::MANUAL, null, 7, \ShopCatalogOrigin::IMPORT];
        yield 'neznámý původ' => ['legacy', 21, null, \ShopCatalogOrigin::IMPORT];
    }

    #[DataProvider('invalidVariants')]
    public function testInvalidVariantOriginIsRejected(
        string $origin,
        ?int $sourceCandidateId,
        ?int $createdByTrainerId,
        string $productOrigin
    ): void {
        $this->expectException(InvalidArgumentException::class);
        \shopCatalogAssertVariantOrigin(
            $origin,
            $sourceCandidateId,
            $createdByTrainerId,
            $productOrigin
        );
    }
}
