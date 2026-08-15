<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/bin/provision-production-test-admin.php';

final class ProvisionProductionTestAdminTest extends TestCase
{
    public function testValidationAllowsOnlyDedicatedEmailAndStrongPassword(): void
    {
        $settings = kisProductionTestAdminValidate([
            'email' => ' KIS@VELOCOTA.COM ',
            'name' => 'KIS testovací administrátor',
            'password' => 'Strong-Test-123!',
        ]);
        self::assertSame('kis@velocota.com', $settings['email']);
        self::assertSame('KIS testovací administrátor', $settings['name']);

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
            'email' => 'kis@velocota.com',
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

        $first = kisProductionTestAdminUpsert($pdo, [
            'email' => 'kis@velocota.com',
            'name' => 'KIS testovací administrátor',
            'password' => 'Strong-Test-123!',
        ]);
        self::assertTrue($first['created']);

        $second = kisProductionTestAdminUpsert($pdo, [
            'email' => 'kis@velocota.com',
            'name' => 'KIS testovací administrátor',
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
    }
}
