<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class LocalhostAcceptanceHubWiringTest extends TestCase
{
    public function testPageFailsClosedBeforeBootstrapAndDoesNotExposeDemoPasswords(): void
    {
        $root = dirname(__DIR__, 2);
        $page = (string)file_get_contents($root . '/testovaci_scenare.php');
        $helper = (string)file_get_contents($root . '/includes/localhost_acceptance_hub.php');

        $guardPosition = strpos($page, 'localhostAcceptanceRequestIsAllowed');
        $bootstrapPosition = strpos($page, "includes/init.php");
        self::assertNotFalse($guardPosition);
        self::assertNotFalse($bootstrapPosition);
        self::assertLessThan($bootstrapPosition, $guardPosition);
        self::assertStringContainsString('http_response_code(404)', $page);
        self::assertStringContainsString("roleAtLeast('admin')", $page);
        self::assertStringContainsString('csrf_verify', $page);
        self::assertStringContainsString('reset_local_demo', $page);
        self::assertStringContainsString('localhostAcceptanceRunSeedReset', $page);
        self::assertStringContainsString('reset_test_customer', $page);
        self::assertStringContainsString('localhostAcceptanceResetTestCustomer', $page);
        self::assertStringContainsString('confirm_customer_reset', $page);
        self::assertStringContainsString('localhostAcceptanceScenarios', $page);
        self::assertStringContainsString('localhostAcceptanceFeedbackSave', $page);
        self::assertStringContainsString('m2FinalizationStatus', $page);
        self::assertStringContainsString('Závěrečná brána M2', $page);
        self::assertStringContainsString("?export=markdown", $page);
        self::assertStringContainsString('maxlength="4000"', $page);
        self::assertStringContainsString('Nezadávejte hesla ani ostré osobní údaje.', $page);
        self::assertStringContainsString("['HTTP_HOST', 'SERVER_ADDR', 'REMOTE_ADDR']", $helper);
        self::assertStringContainsString("[\$cliBinary, \$root . '/bin/seed-local-demo.php']", $helper);
        self::assertStringContainsString('PHP_BINDIR', $helper);
        self::assertStringContainsString("['bypass_shell' => true]", $helper);
        self::assertStringContainsString("\$environment['APP_HOST'] = 'localhost'", $helper);
        self::assertStringContainsString('stream_get_contents($pipes[1])', $helper);
        self::assertStringNotContainsString('Localhost123!', $page . $helper);
        self::assertStringNotContainsString('LocalhostAdmin123!', $page . $helper);
    }

    public function testSeedKeepsStableDemoIdentitiesAfterSecurityTokenRotation(): void
    {
        $seed = (string)file_get_contents(dirname(__DIR__, 2) . '/bin/seed-local-demo.php');

        self::assertStringContainsString('localhost-sportovec-a@localhost.test', $seed);
        self::assertStringContainsString('localhost-sportovec-b@localhost.test', $seed);
        self::assertStringContainsString('$people=$demoPeople', $seed);
        self::assertStringContainsString("hash='localhost-demo-group'", $seed);
        self::assertStringContainsString("WHERE email='a05-transition@localhost.test' ORDER BY id DESC LIMIT 1", $seed);
        self::assertStringContainsString("email='a05-transition@localhost.test' AND id<>?", $seed);
        self::assertStringContainsString("'archived-a05-'.(int)\$duplicateId.'@localhost.invalid'", $seed);
        self::assertStringContainsString('kisRosterRemoveMember($pdo', $seed);
        self::assertStringContainsString('childAccessSetActive($pdo', $seed);
        self::assertStringContainsString('SELECT id,login_name,password_hash,active FROM child_access_accounts', $seed);
        self::assertStringContainsString("if(!password_verify(\$childPassword,(string)\$childAccess['password_hash']))childAccessResetPassword", $seed);
        self::assertStringContainsString('public_profile_token_generate()', $seed);
        self::assertStringContainsString("'shoptet:local-demo:club-event'", $seed);
        self::assertStringNotContainsString("'local-demo:club-event'", $seed);
        self::assertStringContainsString("if((int)\$programGoods['id']!==(int)\$goods['id'])", $seed);
        self::assertStringContainsString("s.email='a05-transition@localhost.test'", $seed);
        self::assertStringContainsString('accountPersonRoleRevoke(', $seed);
        self::assertStringNotContainsString("hash('sha256','localhost-demo:a05-hobby-to-race')", $seed);
        self::assertStringNotContainsString("accountPersonRoleApprove(\$pdo,\$accountId,\$a05PersonId", $seed);
    }

    public function testSeedResetsOnlyDemoParentsActiveA08RegistrationsThroughAuditedCancellation(): void
    {
        $seed = (string)file_get_contents(dirname(__DIR__, 2) . '/bin/seed-local-demo.php');

        self::assertStringContainsString("WHERE event_id=? AND account_id=? AND status IN ('confirmed','waitlisted')", $seed);
        self::assertStringContainsString('clubEventAdminCancelRegistration($pdo,(int)$a08RegistrationId,$actorId', $seed);
        self::assertStringNotContainsString('DELETE FROM club_event_registrations', $seed);
    }

    public function testSeedGrantsTheLocalAdministratorEveryWorkspaceAndSuperadminAccess(): void
    {
        $seed = (string)file_get_contents(dirname(__DIR__, 2) . '/bin/seed-local-demo.php');

        self::assertStringContainsString("require_once \$root.'/includes/staff_workspaces.php'", $seed);
        self::assertStringContainsString('staffPositionCodes()', $seed);
        self::assertStringContainsString('INSERT IGNORE INTO staff_user_positions', $seed);
        self::assertStringContainsString("position_code='system_admin'", $seed);
        self::assertStringContainsString('INSERT INTO staff_superadmins', $seed);
        self::assertStringContainsString("'admin_superadmin'=>true", $seed);
        self::assertStringContainsString("'admin_positions'=>\$positionCodes", $seed);
    }
}
