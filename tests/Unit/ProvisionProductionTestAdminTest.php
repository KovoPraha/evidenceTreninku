<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/bin/provision-production-test-admin.php';

final class ProvisionProductionTestAdminTest extends TestCase
{
    public function testValidationAllowsOnlyDedicatedEmailAndStrongPassword(): void
    {
        $settings = kisProductionTestAdminValidate([
            'email' => ' TESTER.SPRAVCE@VELOCOTA.COM ',
            'name' => 'Tester Správce',
            'password' => 'Strong-Test-123!',
        ]);
        self::assertSame('tester.spravce@velocota.com', $settings['email']);
        self::assertSame('Tester Správce', $settings['name']);

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
            'email' => 'tester.spravce@velocota.com',
            'name' => 'KIS testovací administrátor',
            'password' => 'kis',
        ]);
    }

    public function testUpsertCreatesAndThenSafelyRotatesDedicatedAdmin(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE verejni_uzivatele ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT,email TEXT NOT NULL,trener_id INTEGER NULL)'
        );
        $pdo->exec(
            'CREATE TABLE treneri ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT,jmeno TEXT NOT NULL,email TEXT NOT NULL,'
            . 'heslo TEXT NOT NULL,role TEXT NOT NULL,aktivni INTEGER NOT NULL DEFAULT 1,'
                . 'session_version INTEGER NOT NULL DEFAULT 1)'
        );
        $migration = require dirname(__DIR__, 2) . '/migrations/20260821150000_staff_workspaces.php';
        $migration['up']($pdo);

        $first = kisProductionTestAdminUpsert($pdo, [
            'email' => 'tester.spravce@velocota.com',
            'name' => 'Tester Správce',
            'password' => 'Strong-Test-123!',
        ]);
        self::assertTrue($first['created']);

        $pdo->exec(
            "INSERT INTO verejni_uzivatele(email,trener_id) VALUES "
            . "('tester.spravce@velocota.com'," . (int)$first['id'] . ')'
        );

        $second = kisProductionTestAdminUpsert($pdo, [
            'email' => 'tester.spravce@velocota.com',
            'name' => 'Tester Správce',
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

    public function testUpsertRejectsUnrelatedPublicAccountCollision(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE verejni_uzivatele ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT,email TEXT NOT NULL,trener_id INTEGER NULL)'
        );
        $pdo->exec(
            'CREATE TABLE treneri ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT,jmeno TEXT NOT NULL,email TEXT NOT NULL,'
            . 'heslo TEXT NOT NULL,role TEXT NOT NULL,aktivni INTEGER NOT NULL DEFAULT 1,'
            . 'session_version INTEGER NOT NULL DEFAULT 1)'
        );
        $migration = require dirname(__DIR__, 2) . '/migrations/20260821150000_staff_workspaces.php';
        $migration['up']($pdo);
        $pdo->exec(
            "INSERT INTO verejni_uzivatele(email,trener_id) VALUES "
            . "('tester.spravce@velocota.com',NULL)"
        );

        $this->expectException(RuntimeException::class);
        kisProductionTestAdminUpsert($pdo, [
            'email' => 'tester.spravce@velocota.com',
            'name' => 'Tester Správce',
            'password' => 'Strong-Test-123!',
        ]);
    }
}
