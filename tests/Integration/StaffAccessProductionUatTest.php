<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/BarFoundationFixture.php';
require_once dirname(__DIR__, 2) . '/includes/staff_account_lifecycle.php';
require_once dirname(__DIR__, 2) . '/includes/password_security.php';
require_once dirname(__DIR__, 2) . '/bin/staff-access-production-uat.php';

final class StaffAccessProductionUatTest extends TestCase
{
    public function testSnapshotDetectsRoleAndDefaultChangesWhileIgnoringNewAccounts(): void
    {
        $pdo = \barTestDatabase();
        $before = \staffAccessUatSnapshot($pdo);
        $pdo->exec("INSERT INTO treneri(id,jmeno,role,aktivni,session_version) VALUES(9,'TEST New','trener',1,1)");
        $pdo->exec("INSERT INTO staff_user_positions(trainer_id,position_code,is_default) VALUES(9,'coach',1)");
        self::assertSame($before, \staffAccessUatSnapshot($pdo, $before['ids']));
        $pdo->exec("UPDATE staff_user_positions SET is_default=0 WHERE trainer_id=4");
        self::assertNotSame($before['sha256'], \staffAccessUatSnapshot($pdo, $before['ids'])['sha256']);
    }

    public function testExistingAccountCannotBeUsedAsFixtureEvenWithMatchingId(): void
    {
        $pdo = \barTestDatabase();
        $this->expectException(\RuntimeException::class);
        \staffAccessUatIdentity($pdo, 1, '12345678900', 'ADMIN');
    }

    public function testDedicatedFixtureCreationAndCleanupPreserveAllOriginalAccounts(): void
    {
        $pdo = \barTestDatabase();
        $audit = require dirname(__DIR__, 2) . '/migrations/20260822180000_staff_account_events.php';
        $audit['up']($pdo);
        $dir = sys_get_temp_dir() . '/staff-access-test-' . bin2hex(random_bytes(5));
        mkdir($dir, 0700);
        $file = $dir . '/state.json';
        $state = ['run_id' => '12345678900', 'expires_at' => time() + 600,
            'baseline' => \staffAccessUatSnapshot($pdo), 'actors' => []];
        try {
            $credentials = \staffAccessUatCreate($pdo, $state, $file);
            self::assertCount(2, $credentials);
            self::assertNotContains($credentials['ADMIN']['id'], $state['baseline']['ids']);
            $result = \staffAccessUatCleanup($pdo, $state, $file);
            self::assertTrue($result['original_roles_unchanged']);
            self::assertSame(2, $result['accounts_deactivated']);
            foreach ($state['actors'] as $id) {
                self::assertSame(0, (int)$pdo->query('SELECT aktivni FROM treneri WHERE id=' . (int)$id)->fetchColumn());
                self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM staff_user_positions WHERE trainer_id=' . (int)$id)->fetchColumn());
            }
            self::assertTrue(\staffAccessUatCleanup($pdo, $state, $file)['original_roles_unchanged']);
            self::assertStringNotContainsString($credentials['ADMIN']['password'], (string)file_get_contents($file));
        } finally {
            if (is_file($file)) {
                unlink($file);
            }
            rmdir($dir);
        }
    }

    public function testFormParserSelectsOnlyTheRequestedActionAndTarget(): void
    {
        $html = '<form><input type="hidden" name="action" value="save_positions"><input type="hidden" name="trainer_id" value="73">'
            . '<input type="hidden" name="csrf_token" value="token-a"></form><form><input type="hidden" name="action" value="set_consumption">'
            . '<input type="hidden" name="csrf_token" value="token-b"></form>';
        self::assertSame('73', \staffAccessUatForm($html, 'save_positions')['trainer_id']);
        self::assertSame('token-b', \staffAccessUatForm($html, 'set_consumption')['csrf_token']);
    }

    public function testCleanupRejectsAnOriginalAccountBeforeAnyMutation(): void
    {
        $pdo = \barTestDatabase();
        $state = ['run_id' => '12345678900', 'baseline' => \staffAccessUatSnapshot($pdo), 'actors' => ['ADMIN' => 1]];
        try {
            \staffAccessUatCleanup($pdo, $state, 'unused-recovery-path');
            self::fail('Original account was accepted as a disposable fixture.');
        } catch (\RuntimeException $error) {
            self::assertSame('Refusing to clean up an original account.', $error->getMessage());
            self::assertSame($state['baseline'], \staffAccessUatSnapshot($pdo));
        }
    }
}
