<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/shop_purchase_mode.php';

final class ShopYoungestCourseInterestOnlyTest extends TestCase
{
    public function testOnlyReviewedImportedYoungestCoursesUseInterestOnlyMode(): void
    {
        foreach ([157, 158, 159] as $productId) {
            self::assertTrue(\shopProductIsInterestOnlyYoungestCourse([
                'product_id' => $productId,
                'origin' => 'import',
                'external_product_key' => 'shoptet:pair:' . $productId,
            ]));
        }

        foreach ([156, 160] as $productId) {
            self::assertFalse(\shopProductIsInterestOnlyYoungestCourse([
                'product_id' => $productId,
                'origin' => 'import',
                'external_product_key' => 'shoptet:pair:' . $productId,
            ]));
        }
        self::assertFalse(\shopProductIsInterestOnlyYoungestCourse([
            'product_id' => 157,
            'origin' => 'manual',
            'external_product_key' => 'shoptet:pair:157',
        ]));
    }

    public function testPublicPagesDoNotPresentLegacyLessonPriceAsCoursePrice(): void
    {
        $root = dirname(__DIR__, 2);
        $listing = (string)file_get_contents($root . '/booking/eshop.php');
        $detail = (string)file_get_contents($root . '/booking/produkt.php');

        self::assertStringContainsString('Cena kurzu na dotaz', $listing);
        self::assertStringContainsString('Termín, rozsah a cenu celého kurzu', $detail);
        self::assertStringContainsString('if (!$interestOnlyCourse): ?><div class="vstack', $detail);
        self::assertStringContainsString('if ($interestOnlyCourse)', $detail);
    }
}
