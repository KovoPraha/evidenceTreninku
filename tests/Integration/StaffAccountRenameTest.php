<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/staff_account_lifecycle.php';

final class StaffAccountRenameTest extends TestCase
{
    public function testRenamePreservesAccountAndRelationsAndRecordsAudit(): void
    {
        $pdo = $this->database();
        $before = $pdo->query('SELECT * FROM treneri WHERE id=2')->fetch();
        $result = \staffAccountRename($pdo, 2, 1, "  Jiří   O'Connor  ", 'Oprava jména trenéra.');
        self::assertSame(['id' => 2, 'changed' => true, 'name' => "Jiří O'Connor"], $result);
        $after = $pdo->query('SELECT * FROM treneri WHERE id=2')->fetch();
        self::assertSame(array_replace($before, ['jmeno' => "Jiří O'Connor"]), $after);
        self::assertSame(2, (int)$pdo->query('SELECT trener_id FROM training_assignment')->fetchColumn());
        $event = $pdo->query('SELECT * FROM staff_account_events')->fetch();
        self::assertSame('rename', $event['action']);
        self::assertSame(1, (int)$event['actor_trainer_id']);
        self::assertSame(2, (int)$event['trainer_id']);
        self::assertSame('Oprava jména trenéra.', $event['reason']);
        self::assertSame('Původní jméno', json_decode($event['before_json'], true)['jmeno']);
        self::assertSame("Jiří O'Connor", json_decode($event['after_json'], true)['jmeno']);
        self::assertArrayNotHasKey('heslo', json_decode($event['after_json'], true));
    }

    public function testUnchangedNameDoesNotCreateAuditEvent(): void
    {
        $pdo = $this->database();
        self::assertFalse(\staffAccountRename($pdo, 2, 1, 'Původní jméno', 'Kontrola stejného jména.')['changed']);
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM staff_account_events')->fetchColumn());
    }

    #[DataProvider('invalidInput')]
    public function testInvalidInputDoesNotChangeAccount(string $name, string $reason): void
    {
        $pdo = $this->database();
        try {
            \staffAccountRename($pdo, 2, 1, $name, $reason);
            self::fail('Invalid input must be rejected.');
        } catch (\InvalidArgumentException) {
            self::assertSame('Původní jméno', $pdo->query('SELECT jmeno FROM treneri WHERE id=2')->fetchColumn());
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM staff_account_events')->fetchColumn());
        }
    }

    public static function invalidInput(): array
    {
        return [
            'empty' => ['', 'Oprava jména.'],
            'whitespace' => [" \t\n ", 'Oprava jména.'],
            'over column limit' => [str_repeat('Ř', 101), 'Oprava jména.'],
            'invalid encoding' => ["\xFF", 'Oprava jména.'],
            'missing reason' => ['Nové jméno', ''],
            'long reason' => ['Nové jméno', str_repeat('x', 1001)],
        ];
    }

    public function testAuditFailureRollsBackRename(): void
    {
        $pdo = $this->database();
        try {
            \staffAccountRename($pdo, 2, 999, 'Nové jméno', 'Oprava jména.');
            self::fail('Missing audit actor must fail.');
        } catch (\StaffAccountLifecycleException) {
            self::assertFalse($pdo->inTransaction());
            self::assertSame('Původní jméno', $pdo->query('SELECT jmeno FROM treneri WHERE id=2')->fetchColumn());
        }
    }

    public function testMissingAccountIsRejected(): void
    {
        $pdo = $this->database();
        $this->expectException(\StaffAccountLifecycleException::class);
        \staffAccountRename($pdo, 999, 1, 'Nové jméno', 'Oprava jména.');
    }

    public function testInactiveAndOwnAccountsCanBeRenamed(): void
    {
        $pdo = $this->database();
        $pdo->exec('UPDATE treneri SET aktivni=0 WHERE id=2');
        self::assertTrue(\staffAccountRename($pdo, 2, 1, str_repeat('Ř', 100), 'Oprava jména.')['changed']);
        self::assertSame(0, (int)$pdo->query('SELECT aktivni FROM treneri WHERE id=2')->fetchColumn());
        self::assertTrue(\staffAccountRename($pdo, 1, 1, 'Nové vlastní jméno', 'Oprava vlastního jména.')['changed']);
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT,email TEXT,heslo TEXT,role TEXT,aktivni INTEGER,session_version INTEGER)');
        $pdo->exec("INSERT INTO treneri VALUES(1,'Admin','admin@test','hash1','admin',1,1),(2,'Původní jméno','worker@test','hash2','trener',1,1)");
        $pdo->exec('CREATE TABLE training_assignment(trener_id INTEGER REFERENCES treneri(id))');
        $pdo->exec('INSERT INTO training_assignment VALUES(2)');
        $migration = require dirname(__DIR__, 2) . '/migrations/20260822180000_staff_account_events.php';
        $migration['up']($pdo);
        return $pdo;
    }
}
