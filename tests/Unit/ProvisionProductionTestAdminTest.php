<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/bin/provision-production-test-admin.php';

final class ProvisionProductionTestAdminTest extends TestCase
{
    public function testValidationAllowsOnlyDedicatedEmailAndStrongPassword(): void
    {
        $settings = kisProductionTestAdminValidate([
            'email' => ' KIS-SUPERADMIN-TEST@VELOCOTA.COM ',
            'name' => 'KIS testovací superadministrátor',
            'password' => 'Strong-Test-123!',
        ]);
        self::assertSame('kis-superadmin-test@velocota.com', $settings['email']);
        self::assertSame('KIS testovací superadministrátor', $settings['name']);

        $this->expectException(RuntimeException::class);
        kisProductionTestAdminValidate([
            'email' => 'other@velocota.com',
            'name' => 'Jiný účet',
            'password' => 'Strong-Test-123!',
        ]);
    }

    public function testValidationRejectsWeakPassword(): void
    {
        $this->expectException(RuntimeException::class);
        kisProductionTestAdminValidate([
            'email' => 'kis-superadmin-test@velocota.com',
            'name' => 'KIS testovací administrátor',
            'password' => 'kis',
        ]);
    }

    public function testUpsertCreatesAndThenSafelyRotatesDedicatedAdmin(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE verejni_uzivatele (id INTEGER PRIMARY KEY AUTOINCREMENT,email TEXT NOT NULL)');
        $pdo->exec(
            'CREATE TABLE treneri ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT,jmeno TEXT NOT NULL,email TEXT NOT NULL,'
            . 'heslo TEXT NOT NULL,role TEXT NOT NULL,aktivni INTEGER NOT NULL DEFAULT 1,'
                . 'session_version INTEGER NOT NULL DEFAULT 1)'
        );
        $migration = require dirname(__DIR__, 2) . '/migrations/20260821150000_staff_workspaces.php';
        $migration['up']($pdo);

        $first = kisProductionTestAdminUpsert($pdo, [
            'email' => 'kis-superadmin-test@velocota.com',
            'name' => 'KIS testovací superadministrátor',
            'password' => 'Strong-Test-123!',
        ]);
        self::assertTrue($first['created']);

        $second = kisProductionTestAdminUpsert($pdo, [
            'email' => 'kis-superadmin-test@velocota.com',
            'name' => 'KIS testovací superadministrátor',
            'password' => 'Another-Test-456!',
        ]);
        self::assertFalse($second['created']);
        self::assertSame($first['id'], $second['id']);

        $row = $pdo->query('SELECT * FROM treneri')->fetch(PDO::FETCH_ASSOC);
        self::assertSame('admin', $row['role']);
        self::assertSame(1, (int)$row['aktivni']);
        self::assertSame(2, (int)$row['session_version']);
        self::assertTrue(password_verify('Another-Test-456!', (string)$row['heslo']));
        self::assertFalse(password_verify('Strong-Test-123!', (string)$row['heslo']));
        self::assertSame(8, $second['positions']);
        self::assertTrue($second['superadmin']);
        self::assertSame(8, (int)$pdo->query('SELECT COUNT(*) FROM staff_user_positions WHERE trainer_id=' . (int)$first['id'])->fetchColumn());
        self::assertSame('system_admin', (string)$pdo->query('SELECT position_code FROM staff_user_positions WHERE trainer_id=' . (int)$first['id'] . ' AND is_default=1')->fetchColumn());
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM staff_superadmins WHERE trainer_id=' . (int)$first['id'])->fetchColumn());
        self::assertSame(1, (int)$pdo->query("SELECT COUNT(*) FROM staff_position_assignment_events WHERE action='provision_test_superadmin'")->fetchColumn());
    }
}
