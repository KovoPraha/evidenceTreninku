<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/family_roster_status.php';

final class FamilyRosterDisplayStatusTest extends TestCase
{
    public static function periods(): array
    {
        return [
            'future active' => ['active', '2026-11-04', '2026-11-08', 'Budoucí', 'text-bg-info'],
            'first day active' => ['active', '2026-11-05', '2026-11-08', 'Aktivní', 'text-bg-success'],
            'last day active' => ['active', '2026-11-08', '2026-11-08', 'Aktivní', 'text-bg-success'],
            'past active row' => ['active', '2026-11-09', '2026-11-08', 'Ukončeno', 'text-bg-secondary'],
            'future scheduled end' => ['removed', '2026-11-04', '2026-11-08', 'Budoucí', 'text-bg-info'],
            'scheduled last day' => ['removed', '2026-11-08', '2026-11-08', 'Aktivní', 'text-bg-success'],
            'scheduled end passed' => ['removed', '2026-11-09', '2026-11-08', 'Ukončeno', 'text-bg-secondary'],
            'legacy removed without end' => ['removed', '2026-11-05', null, 'Ukončeno', 'text-bg-secondary'],
            'cancelled future' => ['cancelled', '2026-11-04', '2026-11-05', 'Zrušeno', 'text-bg-secondary'],
            'cancelled on start' => ['cancelled', '2026-11-05', '2026-11-05', 'Zrušeno', 'text-bg-secondary'],
            'open membership' => ['active', '2027-01-01', null, 'Aktivní', 'text-bg-success'],
        ];
    }

    #[DataProvider('periods')]
    public function testStatusMatchesInclusiveMembershipDates(string $status, string $on, ?string $until, string $label, string $class): void
    {
        self::assertSame(['label' => $label, 'class' => $class], \familyPortalRosterDisplayStatus([
            'status' => $status, 'valid_from' => '2026-11-05', 'valid_to' => $until,
        ], $on));
    }
}
