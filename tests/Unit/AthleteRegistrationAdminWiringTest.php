<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AthleteRegistrationAdminWiringTest extends TestCase
{
    public function testSingleAdminQueueUsesSharedMatcherAndExistingF7Creator(): void
    {
        $root = dirname(__DIR__, 2);
        $page = (string)file_get_contents($root . '/eshop_identity_admin.php');
        $service = (string)file_get_contents($root . '/includes/athlete_registration_admin.php');

        self::assertStringContainsString("staffActivePositionIs('registrar')", $page);
        self::assertStringContainsString('Cache-Control: no-store', $page);
        self::assertStringContainsString('athleteRegistrationAdminReview', $page);
        self::assertStringContainsString('approve_registration_existing', $page);
        self::assertStringContainsString('create_registration_person', $page);
        self::assertStringContainsString('reject_registration', $page);
        self::assertStringContainsString('personMatchV1($pdo', $service);
        self::assertStringContainsString('personMatchV1CreateManual($pdo', $service);
        self::assertStringNotContainsString('function personMatchV2', $service . $page);
        self::assertStringContainsString('shodný bezpečný otisk RČ', $page);
        self::assertStringContainsString('athlete_sensitive_admin.php', $page);
        self::assertStringContainsString('private_download.php?kind=athlete-photo', $page);
        self::assertStringContainsString("file_kind='profile_photo'", $service);
        self::assertStringContainsString("VALUES (?,'profile_photo'", (string)file_get_contents($root . '/includes/athlete_registration.php'));
        self::assertStringContainsString("file_kind='profile_photo'", (string)file_get_contents($root . '/private_download.php'));
    }

    public function testApprovalAssignmentAndChargeRemainSeparateExplicitActions(): void
    {
        $service = (string)file_get_contents(dirname(__DIR__, 2) . '/includes/athlete_registration_admin.php');
        self::assertStringContainsString('beginTransaction()', $service);
        self::assertStringContainsString('accountPersonClaimApprove', $service);
        self::assertStringContainsString('athleteRegistrationAdminApplyToPerson', $service);
        self::assertStringContainsString("kis_external_id) ", (string)file_get_contents(dirname(__DIR__, 2) . '/includes/person_match.php'));
        self::assertStringContainsString('athleteRegistrationAdminAssign', $service);
        self::assertStringContainsString('sportovec_skupina', $service);
        self::assertStringContainsString('sportovec_podskupina', $service);
        self::assertStringContainsString('club_roster_members', $service);
        self::assertStringContainsString('athlete_registration_assign', $service);
        self::assertStringContainsString('athleteRegistrationAdminChargeContext', $service);
        self::assertStringContainsString('athleteRegistrationAdminCreateCharge', $service);
        self::assertStringContainsString('club_member_charges', $service);
        self::assertStringContainsString('club_member_charge_events', $service);
        self::assertStringContainsString("'source_system' => 'membership'", $service);
        self::assertStringContainsString('create_registration_charge', (string)file_get_contents(dirname(__DIR__, 2) . '/eshop_identity_admin.php'));
        self::assertStringContainsString('name="confirmed"', (string)file_get_contents(dirname(__DIR__, 2) . '/eshop_identity_admin.php'));
        self::assertStringNotContainsString('athleteRegistrationAdminCreateCharge(', (string)file_get_contents(dirname(__DIR__, 2) . '/includes/athlete_registration.php'));
    }
}
