<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/includes/legacy_club_catalog.php';

final class LegacyClubCatalogTest extends TestCase
{
    public function testReviewedSnapshotContainsAllPublishedGroupsAndSafeLinks():void
    {
        $groups=\legacyClubCatalog();self::assertCount(26,$groups);
        foreach($groups as$group){
            self::assertNotSame('',trim($group['name']));self::assertNotSame('',trim($group['age']));self::assertNotSame('',trim($group['schedule']));self::assertNotSame([], $group['offers']);
            foreach($group['offers']as$offer){self::assertGreaterThan(0,$offer['price']);self::assertStringStartsWith('https://shop.kovopraha.cz/',$offer['url']);}
        }
    }

    public function testCanonicalKisProgramReplacesLegacyFallbackByNormalizedName():void
    {
        $fallback=\legacyClubCatalogFallback([['public_name'=>'ALIGATORI']],new \DateTimeImmutable('2026-09-23'));
        self::assertCount(25,$fallback);self::assertNotContains('Aligátoři',array_column($fallback,'public_name'));
        self::assertContains('Kamzíci',array_column($fallback,'public_name'));
    }

    public function testLegacyFallbackExpiresAfterSchoolYear():void
    {
        self::assertSame([],\legacyClubCatalogFallback([],new \DateTimeImmutable('2027-07-01')));
    }

    public function testMostTwoOptionGroupsOfferDiscountedFullYear():void
    {
        $checked=0;
        foreach(\legacyClubCatalog()as$group){if(count($group['offers'])!==2)continue;$first=$group['offers'][0];$year=$group['offers'][1];if(!str_contains($year['label'],'rok')&&!str_contains($year['label'],'červen'))continue;$checked++;self::assertLessThan($first['price']*2,$year['price'],$group['name']);}
        self::assertGreaterThanOrEqual(20,$checked);
    }
}
