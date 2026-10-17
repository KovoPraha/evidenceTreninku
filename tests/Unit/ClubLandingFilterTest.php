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
}
