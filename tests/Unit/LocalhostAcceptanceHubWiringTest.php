<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class LocalhostAcceptanceHubWiringTest extends TestCase
{
    /** Match code or decoded SQL literals independently of formatting and split strings. */
    private function seedContains(string $needle, string $source): bool
    {
        $compact = static fn (string $value): string => (string)preg_replace('/\s+/', '', $value);
        if (str_contains($compact($source), $compact($needle))) {
            return true;
        }
        $literals = [];
        $previous = null;
        foreach (token_get_all($source) as $token) {
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
                // These are literal tokens from this repository's trusted test source.
                $value = eval('return ' . $token[1] . ';');
                if ($previous === '.' && $literals !== []) {
                    $literals[array_key_last($literals)] .= $value;
                } else {
                    $literals[] = $value;
                }
            }
            $previous = $token;
        }
        foreach ($literals as $literal) {
            if (str_contains($compact($literal), $compact($needle))) {
                return true;
            }
        }
        return false;
    }

    private function assertSeedContains(string $needle, string $source): void
    {
        self::assertTrue($this->seedContains($needle, $source), 'Missing seed contract: ' . $needle);
    }

    private function assertSeedNotContains(string $needle, string $source): void
    {
        self::assertFalse($this->seedContains($needle, $source), 'Forbidden seed operation: ' . $needle);
    }

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

        $this->assertSeedContains('localhost-sportovec-a@localhost.test', $seed);
        $this->assertSeedContains('localhost-sportovec-b@localhost.test', $seed);
        $this->assertSeedContains('$people=$demoPeople', $seed);
        $this->assertSeedContains("hash='localhost-demo-group'", $seed);
        $this->assertSeedContains("WHERE email='a05-transition@localhost.test' ORDER BY id DESC LIMIT 1", $seed);
        $this->assertSeedContains("email='a05-transition@localhost.test' AND id<>?", $seed);
        $this->assertSeedContains("'archived-a05-'.(int)\$duplicateId.'@localhost.invalid'", $seed);
        $this->assertSeedContains('kisRosterRemoveMember($pdo', $seed);
        $this->assertSeedContains('childAccessSetActive($pdo', $seed);
        $this->assertSeedContains('SELECT id,login_name,password_hash,active FROM child_access_accounts', $seed);
        $this->assertSeedContains("if(!password_verify(\$childPassword,(string)\$childAccess['password_hash'])){childAccessResetPassword", $seed);
        $this->assertSeedContains('public_profile_token_generate()', $seed);
        $this->assertSeedContains("'shoptet:local-demo:club-event'", $seed);
        $this->assertSeedNotContains("'local-demo:club-event'", $seed);
        $this->assertSeedContains("if((int)\$programGoods['id']!==(int)\$goods['id'])", $seed);
        $this->assertSeedContains("s.email='a05-transition@localhost.test'", $seed);
        $this->assertSeedContains('accountPersonRoleRevoke(', $seed);
        $this->assertSeedNotContains("hash('sha256','localhost-demo:a05-hobby-to-race')", $seed);
        $this->assertSeedNotContains("accountPersonRoleApprove(\$pdo,\$accountId,\$a05PersonId", $seed);
    }

    public function testSeedResetsOnlyDemoParentsActiveA08RegistrationsThroughAuditedCancellation(): void
    {
        $seed = (string)file_get_contents(dirname(__DIR__, 2) . '/bin/seed-local-demo.php');

        $this->assertSeedContains("WHERE event_id=? AND account_id=? AND status IN ('confirmed','waitlisted')", $seed);
        $this->assertSeedContains('clubEventAdminCancelRegistration($pdo,(int)$a08RegistrationId,$actorId', $seed);
        $this->assertSeedNotContains('DELETE FROM club_event_registrations', $seed);
    }

    public function testSeedGrantsTheLocalAdministratorEveryWorkspaceAndSuperadminAccess(): void
    {
        $seed = (string)file_get_contents(dirname(__DIR__, 2) . '/bin/seed-local-demo.php');

        $this->assertSeedContains("require_once \$root.'/includes/staff_workspaces.php'", $seed);
        $this->assertSeedContains('staffPositionCodes()', $seed);
        $this->assertSeedContains('INSERT IGNORE INTO staff_user_positions', $seed);
        $this->assertSeedContains("position_code='system_admin'", $seed);
        $this->assertSeedContains('INSERT INTO staff_superadmins', $seed);
        $this->assertSeedContains("'admin_superadmin'=>true", $seed);
        $this->assertSeedContains("'admin_positions'=>\$positionCodes", $seed);
    }
}
