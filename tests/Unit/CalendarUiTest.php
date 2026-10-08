<?php
declare(strict_types=1);

namespace Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/calendar_ui.php';

final class CalendarUiTest extends TestCase
{
    public function testMonthGridStartsOnMondayAndCoversWholeMonth(): void
    {
        $weeks = \calendarUiWeeks(new DateTimeImmutable('2026-10-01'));
        self::assertSame('2026-09-28', $weeks[0][0]->format('Y-m-d'));
        self::assertSame('2026-11-01', $weeks[array_key_last($weeks)][6]->format('Y-m-d'));
        self::assertContains('2026-10-31', array_map(
            static fn(DateTimeImmutable $date): string => $date->format('Y-m-d'),
            array_merge(...$weeks)
        ));
    }

    public function testCalendarEscapesContentAndKeepsPopoverSupplemental(): void
    {
        $html = \calendarUiRender(new DateTimeImmutable('2026-10-01'), [[
            'date' => '2026-10-08',
            'title' => '<Test>',
            'time' => '16:00–17:00',
            'popover' => 'Dráha & venku',
        ]]);
        self::assertStringContainsString('&lt;Test&gt;', $html);
        self::assertStringContainsString('16:00–17:00', $html);
        self::assertStringContainsString('data-bs-toggle="popover"', $html);
        self::assertStringContainsString('Dráha &amp; venku', $html);
        self::assertStringNotContainsString('<Test>', $html);
    }

    public function testCzechDateLabelsAreStable(): void
    {
        self::assertSame('říjen 2026', \uiFormatMonthName(new DateTimeImmutable('2026-10-08')));
        self::assertSame('8. 10. 2026 16:00–17:30', \uiFormatDateTimeRange('2026-10-08 16:00:00', '2026-10-08 17:30:00'));
    }
}
