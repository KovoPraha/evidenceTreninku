<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/includes/club_landing_filter.php';

final class ClubLandingFilterTest extends TestCase
{
    public function testAgeRangeIsNumericAndInclusive(): void
    {
        self::assertSame([4,6],\clubLandingAgeRange('4–6 let'));
        self::assertSame([11,15],\clubLandingAgeRange('11-15 let'));
        self::assertNull(\clubLandingAgeRange('školní děti'));
        self::assertNull(\clubLandingAgeRange('15–11 let'));
    }

    public function testOnlySaleableOfferMayLinkToProductDetail(): void
    {
        self::assertSame(0, \clubLandingFirstSaleableProductId(['offers'=>[
            ['product_id'=>247,'saleable'=>false],
        ]]));
        self::assertSame(249, \clubLandingFirstSaleableProductId(['offers'=>[
            ['product_id'=>247,'saleable'=>false],
            ['product_id'=>249,'saleable'=>true],
        ]]));
        self::assertSame(0, \clubLandingFirstSaleableProductId(['offers'=>[]]));
    }

    public function testCompactOfferSummaryUsesLowestPriceAndRealYearSaving(): void
    {
        $offers = [
            ['purchase_option'=>'full_year','amount_minor'=>720000,'currency'=>'CZK'],
            ['purchase_option'=>'first_half','amount_minor'=>410000,'currency'=>'CZK'],
            ['purchase_option'=>'second_half','amount_minor'=>410000,'currency'=>'CZK'],
        ];
        self::assertSame(410000, \clubLandingLowestOffer($offers)['amount_minor']);
        self::assertSame(100000, \clubLandingYearSaving($offers));
        self::assertNull(\clubLandingLowestOffer([]));
        self::assertSame(0, \clubLandingYearSaving([$offers[0], $offers[1]]));
        $offers[2]['currency'] = 'EUR';
        self::assertSame(0, \clubLandingYearSaving($offers));
    }
}
