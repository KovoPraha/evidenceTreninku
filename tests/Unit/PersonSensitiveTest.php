<?php
declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/person_sensitive.php';

final class PersonSensitiveTest extends TestCase
{
    private array $sessionBackup;

    protected function setUp(): void
    {
        $this->sessionBackup = $_SESSION ?? [];
        $_SESSION = ['trener_id' => 7, 'role' => 'admin'];
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->sessionBackup;
    }

    public function testValidatesLengthsOffsetsChecksumAndBirthDateExactly(): void
    {
        $standard = $this->syntheticBirthNumber('2010-01-02', 50);
        $plusTwenty = $this->syntheticBirthNumber('2004-01-02', 20);
        $plusSeventy = $this->syntheticBirthNumber('2004-01-02', 70);

        self::assertSame($standard, \personSensitiveValidateBirthNumber($this->withSlash($standard), '2010-01-02'));
        self::assertSame($plusTwenty, \personSensitiveValidateBirthNumber($plusTwenty, '2004-01-02'));
        self::assertSame($plusSeventy, \personSensitiveValidateBirthNumber($plusSeventy, '2004-01-02'));
        self::assertSame('1953-01-01', \personSensitiveBirthDate('530101123'));
        self::assertSame('530101123', \personSensitiveValidateBirthNumber('530101/123', '1953-01-01'));

        $this->expectException(\InvalidArgumentException::class);
        \personSensitiveValidateBirthNumber($standard, '2010-01-03');
    }

    public function testRejectsInvalidChecksumDateAndIllegalLegacyLength(): void
    {
        $valid = $this->syntheticBirthNumber('2012-03-04', 0);
        $invalidChecksum = substr($valid, 0, 9) . (((int)$valid[9] + 1) % 10);
        foreach ([
            [$invalidChecksum, '2012-03-04'],
            ['1263320000', '2012-03-04'],
            ['540101123', '1954-01-01'],
        ] as [$value, $birthDate]) {
            try {
                \personSensitiveValidateBirthNumber($value, $birthDate);
                self::fail('Invalid synthetic birth number was accepted.');
            } catch (\InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testForeignerWithoutAssignedNumberUsesExplicitEmptyBranch(): void
    {
        self::assertNull(\personSensitiveValidateBirthNumber('', '2012-03-04', false));
        $this->expectException(\InvalidArgumentException::class);
        \personSensitiveValidateBirthNumber('LOCALHOST-NO-SUBSTITUTE', '2012-03-04', false);
    }

    public function testEncryptionAdminAuditRotationAndCryptographicEraseAreFailClosed(): void
    {
        $pdo = $this->database();
        $configV1 = $this->config('v1');
        $birthDate = '2010-01-02';
        $digits = $this->syntheticBirthNumber($birthDate, 50);
        $recordId = \personSensitiveStoreBirthNumber($pdo, 101, $digits, $birthDate, $configV1);

        $stored = $pdo->query('SELECT * FROM osoba_citlive_udaje')->fetch(PDO::FETCH_ASSOC);
        self::assertSame('v1', $stored['rc_key_version']);
        self::assertStringNotContainsString($digits, (string)$stored['rc_ciphertext']);
        self::assertSame(substr($digits, 0, 6) . '/****', \personSensitiveAdminMaskedView($pdo, $recordId, $configV1, '127.0.0.1'));
        self::assertSame($this->withSlash($digits), \personSensitiveAdminReveal($pdo, $recordId, 'LOCALHOST audit reveal', $configV1, '127.0.0.1'));
        self::assertSame(['masked_view', 'reveal'], $pdo->query('SELECT action FROM osoba_citlive_pristupy ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));

        $configV2 = $this->config('v2', true);
        \personSensitiveAdminRotate($pdo, $recordId, 'LOCALHOST key rotation', $configV2, '127.0.0.1');
        self::assertSame('v2', $pdo->query('SELECT rc_key_version FROM osoba_citlive_udaje')->fetchColumn());
        self::assertSame($this->withSlash($digits), \personSensitiveAdminReveal($pdo, $recordId, 'LOCALHOST after rotation', $configV2));

        \personSensitiveAdminErase($pdo, $recordId, 'LOCALHOST retention erase', '127.0.0.1');
        self::assertSame('erased', $pdo->query('SELECT status FROM osoba_citlive_udaje')->fetchColumn());
        try {
            \personSensitiveAdminReveal($pdo, $recordId, 'LOCALHOST after erase', $configV2);
            self::fail('Erased data was revealed.');
        } catch (\PersonSensitiveException) {
            self::addToAssertionCount(1);
        }
        self::assertSame(1, (int)$pdo->query("SELECT COUNT(*) FROM osoba_citlive_pristupy WHERE action='erase'")->fetchColumn());
    }

    public function testTamperMissingKeysNonAdminAndDuplicateBlindIndexFailClosed(): void
    {
        $birthDate = '2012-03-04';
        $digits = $this->syntheticBirthNumber($birthDate, 0);

        try {
            \personSensitiveConfig(['keys' => [], 'active_version' => '', 'index_key' => '']);
            self::fail('Missing key configuration was accepted.');
        } catch (\PersonSensitiveException) {
            self::addToAssertionCount(1);
        }

        $pdo = $this->database();
        $config = $this->config('v1');
        $recordId = \personSensitiveStoreBirthNumber($pdo, 201, $digits, $birthDate, $config);
        try {
            \personSensitiveStoreBirthNumber($pdo, 202, $digits, $birthDate, $config);
            self::fail('Duplicate blind index was accepted.');
        } catch (\PersonSensitiveException) {
            self::addToAssertionCount(1);
        }

        $_SESSION['role'] = 'hlavni';
        try {
            \personSensitiveAdminMaskedView($pdo, $recordId, $config);
            self::fail('Main trainer read sensitive data.');
        } catch (\PersonSensitiveException) {
            self::addToAssertionCount(1);
        }

        $_SESSION['role'] = 'admin';
        $pdo->exec("UPDATE osoba_citlive_udaje SET rc_ciphertext=randomblob(length(rc_ciphertext)) WHERE id={$recordId}");
        try {
            \personSensitiveAdminReveal($pdo, $recordId, 'LOCALHOST tamper test', $config);
            self::fail('Tampered ciphertext was accepted.');
        } catch (\PersonSensitiveException) {
            self::addToAssertionCount(1);
        }
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM osoba_citlive_pristupy')->fetchColumn());
    }

    /** @return array{keys:array<string,string>,active_version:string,index_key:string} */
    private function config(string $active, bool $includeV2 = false): array
    {
        $keys = ['v1' => str_repeat("\x11", 32)];
        if ($includeV2 || $active === 'v2') $keys['v2'] = str_repeat("\x22", 32);
        return ['keys' => $keys, 'active_version' => $active, 'index_key' => str_repeat("\x33", 32)];
    }

    private function syntheticBirthNumber(string $birthDate, int $monthOffset): string
    {
        $date = new \DateTimeImmutable($birthDate);
        $prefix = $date->format('y')
            . str_pad((string)((int)$date->format('m') + $monthOffset), 2, '0', STR_PAD_LEFT)
            . $date->format('d');
        for ($suffix = 0; $suffix <= 9999; $suffix++) {
            $candidate = $prefix . str_pad((string)$suffix, 4, '0', STR_PAD_LEFT);
            $remainder = 0;
            foreach (str_split($candidate) as $digit) $remainder = (($remainder * 10) + (int)$digit) % 11;
            if ($remainder === 0) return $candidate;
        }
        self::fail('Cannot create synthetic checksum fixture.');
    }

    private function withSlash(string $digits): string
    {
        return substr($digits, 0, 6) . '/' . substr($digits, 6);
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec(<<<'SQL'
            CREATE TABLE osoba_citlive_udaje(
                id INTEGER PRIMARY KEY AUTOINCREMENT,record_token TEXT UNIQUE,request_id INTEGER UNIQUE,
                sportovec_id INTEGER NULL UNIQUE,rc_ciphertext BLOB,rc_nonce BLOB,rc_key_version TEXT,
                rc_blind_index BLOB UNIQUE,contract_version TEXT,status TEXT,retention_reason TEXT NULL,
                retention_until TEXT NULL,erased_at TEXT NULL,created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE osoba_citlive_pristupy(
                id INTEGER PRIMARY KEY AUTOINCREMENT,sensitive_record_id INTEGER NULL,private_file_id INTEGER NULL,
                sportovec_id INTEGER NULL,request_id INTEGER NULL,actor_trainer_id INTEGER,action TEXT,
                reason TEXT,ip_address TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
            SQL);
        return $pdo;
    }
}
