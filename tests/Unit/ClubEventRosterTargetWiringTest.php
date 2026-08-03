<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ClubEventRosterTargetWiringTest extends TestCase
{
    public function testMigrationServiceAndEntryPointsAreWired(): void
    {
        $root = dirname(__DIR__, 2);
        $migration = (string)file_get_contents(
            $root . '/migrations/20260804150000_club_event_roster_targets.php'
        );
        $service = (string)file_get_contents($root . '/includes/club_event_roster_target.php');
        $registration = (string)file_get_contents($root . '/includes/club_event_registration.php');
        $admin = (string)file_get_contents($root . '/eshop_events_admin.php');
        $portal = (string)file_get_contents($root . '/booking/krouzky.php');

        self::assertStringContainsString('club_event_roster_targets', $migration);
        self::assertStringContainsString('PRIMARY KEY (event_id,team_id)', $migration);
        self::assertStringContainsString('eligibility_team_ids_snapshot', $migration);
        self::assertStringContainsString('eligibility_reason_snapshot', $migration);
        self::assertStringContainsString('function clubEventRosterReplaceTargets', $service);
        self::assertStringContainsString("status'] !== 'draft'", $service);
        self::assertStringContainsString('function clubEventRosterEligibility', $service);
        self::assertStringContainsString('clubEventRosterEligibilityJson($eligibility)', $registration);
        self::assertStringContainsString("\$action==='set_roster_targets'", $admin);
        self::assertStringContainsString("name=\"team_ids[]\"", $admin);
        self::assertStringContainsString('clubEventRosterEligibility($pdo', $portal);
    }
}
