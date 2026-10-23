<?php
declare(strict_types=1);
namespace Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/club_program_wizard.php';

final class ClubProgramWizardSalesWindowTest extends TestCase
{
    public function testFutureCourseKeepsOriginalWindowAndExplicitWindowIsAuthoritative(): void
    {
        $now = new DateTimeImmutable('2026-10-23T12:00:00+02:00');
        $input = ['starts_on' => '2026-11-01', 'ends_on' => '2027-06-30'];
        self::assertSame(['sales_open_at' => '2026-10-23T00:00', 'sales_close_at' => '2026-11-01T23:59'], \clubProgramWizardSalesWindow($input, $now));
        $input['sales_open_at'] = '2026-08-01T10:00';
        $input['sales_close_at'] = '2026-11-15T18:30';
        $input['repeat_annually'] = '1';
        self::assertSame(['sales_open_at' => '2026-08-01T10:00', 'sales_close_at' => '2026-11-15T18:30'], \clubProgramWizardSalesWindow($input, $now));
    }

    public function testBlankFieldsUsePragueDateAndCourseLastDay(): void
    {
        $now = new DateTimeImmutable('2026-10-22T22:30:00+00:00');
        self::assertSame(['sales_open_at' => '2026-10-23T00:00', 'sales_close_at' => '2026-10-23T23:59'], \clubProgramWizardSalesWindow([
            'starts_on' => '2026-10-16', 'ends_on' => '2026-10-23', 'sales_open_at' => '', 'sales_close_at' => '',
        ], $now));
    }

    public function testFinishedCourseDoesNotReopenAutomatically(): void
    {
        $this->expectException(\ClubProgramWizardException::class);
        \clubProgramWizardSalesWindow(['starts_on' => '2026-10-01', 'ends_on' => '2026-10-22'], new DateTimeImmutable('2026-10-23T12:00:00+02:00'));
    }

    public function testExplicitReversedWindowIsNotSilentlyRepaired(): void
    {
        $this->expectException(\ClubProgramWizardException::class);
        \clubProgramWizardSalesWindow(['sales_open_at' => '2026-11-01T10:00', 'sales_close_at' => '2026-10-23T18:00']);
    }

    public function testInvalidCalendarDateIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        \clubProgramWizardSalesWindow(['sales_open_at' => '2026-11-01T10:00', 'sales_close_at' => '2026-11-31T18:00']);
    }
}
