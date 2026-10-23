<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/training_planner_view.php';

final class TrainingPlannerViewTest extends TestCase
{
    public function testPendingDestinationUsesActualFilteredDatesRatherThanPreviousWeek(): void
    {
        self::assertSame('2026-01-05', trainingPlannerOldestPendingDate([
            ['id' => 3, 'datum' => '2026-10-02'],
            ['id' => 1, 'datum' => '2026-01-05'],
            ['id' => 2, 'datum' => '2026-02-09'],
        ]));
        self::assertSame('2026-02-09', trainingPlannerOldestPendingDate([
            ['id' => 2, 'datum' => '2026-02-09'],
        ]));
        self::assertNull(trainingPlannerOldestPendingDate([]));
    }

    public function testExcerptDecodesCzechTextBeforeCharacterLimit(): void
    {
        self::assertSame('testovací oznámení', trainingPlannerAnnouncementExcerpt('<p>testovac&iacute; ozn&aacute;men&iacute;</p>'));
        self::assertSame(str_repeat('á', 160), trainingPlannerAnnouncementExcerpt(str_repeat('&aacute;', 170)));
        self::assertSame('První řádek', trainingPlannerAnnouncementExcerpt("<b>První řádek</b>\nDruhý řádek"));
    }

    public function testEncodedMarkupRemainsInertAfterOutputEscaping(): void
    {
        $excerpt = trainingPlannerAnnouncementExcerpt('&lt;img src=x onerror=alert(1)&gt;');
        self::assertStringNotContainsString('<img', htmlspecialchars($excerpt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        $page = (string)file_get_contents(dirname(__DIR__, 2) . '/planovac.php');
        self::assertStringContainsString('h(trainingPlannerAnnouncementExcerpt(', $page);
        self::assertStringContainsString('trainingPlannerOldestPendingDate($warningPlans)', $page);
    }
}
