<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class TrainingCalendarWiringTest extends TestCase
{
    public function testVenueCalendarsUseSharedDeduplicatedPlansAndRecordedBadge(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (['kalendar_sportovist.php', 'ajax_denny_rozvrh.php'] as $file) {
            $source = (string)file_get_contents($root . '/' . $file);
            self::assertStringContainsString('venueCalendarUnreservedPlans', $source);
            self::assertStringContainsString('Zaevidováno', $source);
            self::assertStringContainsString('edit_trenink.php?id=', $source);
        }
        $service = (string)file_get_contents($root . '/includes/venue_calendar.php');
        self::assertStringContainsString("pt.stav IN ('planovany','evidovany')", $service);
        self::assertStringContainsString('vr.trenink_id=pt.trenink_id', $service);
    }

    public function testPlanPrefillsExpandedReservationAndSaveLinksIt(): void
    {
        $root = dirname(__DIR__, 2);
        $form = (string)file_get_contents($root . '/formular.php');
        self::assertStringContainsString("'sportoviste_id'", $form);
        self::assertStringContainsString("'cas_od'", $form);
        self::assertStringContainsString("'cas_do'", $form);
        self::assertStringContainsString("\$reservationFromPlan ? 'show'", $form);

        $save = (string)file_get_contents($root . '/ulozit_trenink.php');
        self::assertStringContainsString('venueCalendarCreateTrainingReservation', $save);
        self::assertStringContainsString("'?plan_id=' . \$planId", $save);
    }

    public function testGroupOnlyCalendarAndWeekCopyVisibilityAreWired(): void
    {
        $root = dirname(__DIR__, 2);
        $calendar = (string)file_get_contents($root . '/prehled_treninku_skupiny_kalendar.php');
        self::assertStringContainsString('JOIN trenink_skupina ts', $calendar);
        self::assertStringContainsString('$filterPodskupinaId !== \'\' || $filterSkupinaId !== \'\'', $calendar);

        $planner = (string)file_get_contents($root . '/planovac.php');
        self::assertStringContainsString('popis, je_verejny, stav', $planner);
        self::assertStringContainsString("(int)\$zp['je_verejny']", $planner);
    }
}
