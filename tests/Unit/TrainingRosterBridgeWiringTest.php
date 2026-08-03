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
        $service = (string)file_get_contents($root . '/includes/training_roster_bridge.php');
        self::assertStringContainsString('trainingRosterBridgeExpectedForPlan', $form);
        self::assertStringContainsString('rosterExpected.forEach', $form);
        self::assertStringNotContainsString('INSERT INTO trenink_sportovec', $service);
        self::assertStringNotContainsString('UPDATE trenink_sportovec', $service);
    }
}
