<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class TrainingRosterBridgeWiringTest extends TestCase
{
    public function testPlannerKeepsLegacyGroupsAndUsesProtectedRosterBridge(): void
    {
        $root = dirname(__DIR__, 2);
        $planner = (string)file_get_contents($root . '/planovany_trenink_form.php');
        self::assertStringContainsString("canAccess('planovac')", $planner);
        self::assertStringContainsString('csrf_verify', $planner);
        self::assertStringContainsString('podskupiny_ids[]', $planner);
        self::assertStringContainsString('team_ids[]', $planner);
        self::assertStringContainsString('trainingRosterBridgeReplacePlanTeams', $planner);
    }

    public function testEvidenceFormOnlyPrefillsRosterExpectation(): void
    {
        $root = dirname(__DIR__, 2);
        $form = (string)file_get_contents($root . '/formular.php');
        $save = (string)file_get_contents($root . '/ulozit_trenink.php');
        $guide = (string)file_get_contents($root . '/kis_training_a07_admin.php');
        $service = (string)file_get_contents($root . '/includes/training_roster_bridge.php');
        self::assertStringContainsString('trainingRosterBridgeExpectedForPlan', $form);
        self::assertStringContainsString('pt.trener_id = ?', $form);
        self::assertStringContainsString('rosterExpected.forEach', $form);
        self::assertStringContainsString('trainingRosterBridgeCopyPlanToTraining', $save);
        self::assertStringContainsString('trainingRosterBridgePlanAttendanceComparison', $guide);
        self::assertStringNotContainsString('INSERT INTO trenink_sportovec', $service);
        self::assertStringNotContainsString('UPDATE trenink_sportovec', $service);
    }
}
