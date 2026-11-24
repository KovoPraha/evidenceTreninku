<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/password_reset_request_audit.php';

final class PasswordResetRequestAuditTest extends TestCase
{
    private array $keys;

    protected function setUp(): void
    {
        $this->keys = ['keys' => ['v1' => random_bytes(32)], 'active_version' => 'v1', 'index_key' => random_bytes(32)];
    }

    public function testEligiblePublicRequestAtomicallyLinksAuditTokenAndOutbox(): void
    {
        $pdo = $this->database();
        $result = $this->request($pdo, 'PARENT@EXAMPLE.TEST');
        self::assertTrue($result['issued']);
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM password_reset_tokens')->fetchColumn());
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM auth_mail_outbox')->fetchColumn());
        $audit = passwordResetAuditAdminList($pdo, $result['request_id'])[0];
        self::assertSame('queued', $audit['outcome']);
        self::assertSame('queued', $audit['reason_code']);
        self::assertSame('pending', $audit['outbox_status']);
        self::assertSame('1', (string)$audit['outbox_id']);
        self::assertArrayNotHasKey('recipient_email', $audit);
        $pdo->exec("UPDATE auth_mail_outbox SET status='sent' WHERE id=1");
        self::assertSame('sent', passwordResetAuditAdminList($pdo, $result['request_id'])[0]['outbox_status']);
    }

    public function testRejectedBranchesAreRecordedWithoutTokenOrOutbox(): void
    {
        $pdo = $this->database();
        $cases = [
            [$this->request($pdo, 'parent@example.test', false), 'csrf_invalid'],
            [$this->request($pdo, str_repeat('x', 161)), 'invalid_identifier'],
            [$this->request($pdo, 'inactive@example.test'), 'not_eligible'],
        ];
        foreach ($cases as [$result, $reason]) {
            self::assertFalse($result['issued']);
            $audit = passwordResetAuditAdminList($pdo, $result['request_id'])[0];
            self::assertSame('rejected', $audit['outcome']);
            self::assertSame($reason, $audit['reason_code']);
            self::assertNull($audit['outbox_id']);
        }
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM password_reset_tokens')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM auth_mail_outbox')->fetchColumn());
    }

    public function testRateLimitRejectionHasReasonWithoutQueue(): void
    {
        $pdo = $this->database();
        for ($index = 0; $index < 5; $index++) {
            self::assertTrue($this->request($pdo, 'parent@example.test')['issued']);
        }
        $rejected = $this->request($pdo, 'parent@example.test');
        self::assertFalse($rejected['issued']);
        self::assertSame('rate_limited', passwordResetAuditAdminList($pdo, $rejected['request_id'])[0]['reason_code']);
        self::assertSame(5, (int)$pdo->query('SELECT COUNT(*) FROM auth_mail_outbox')->fetchColumn());
    }

    public function testQueueFailureRollsBackTokenAndKeepsFailedAudit(): void
    {
        $pdo = $this->database();
        $failed = passwordResetRequestHandle(
            $pdo,
            false,
            'parent@example.test',
            true,
            '192.0.2.10',
            'https://example.test/reset',
            ['keys' => [], 'active_version' => 'v1', 'index_key' => random_bytes(32)]
        );
        self::assertFalse($failed['issued']);
        $audit = passwordResetAuditAdminList($pdo, $failed['request_id'])[0];
        self::assertSame('failed', $audit['outcome']);
        self::assertSame('mail_enqueue_error', $audit['reason_code']);
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM password_reset_tokens')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM auth_mail_outbox')->fetchColumn());
    }

    public function testOutboxInsertFailureIsAuditedAndTokenIsRolledBack(): void
    {
        $pdo = $this->database();
        $pdo->exec("CREATE TRIGGER reject_reset_mail BEFORE INSERT ON auth_mail_outbox BEGIN SELECT RAISE(ABORT,'synthetic_insert_failure'); END");
        $failed = $this->request($pdo, 'parent@example.test');
        self::assertFalse($failed['issued']);
        self::assertSame('mail_enqueue_error', passwordResetAuditAdminList($pdo, $failed['request_id'])[0]['reason_code']);
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM password_reset_tokens')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM auth_mail_outbox')->fetchColumn());
    }

    public function testLookupFailureIsDistinguishedFromMailFailure(): void
    {
        $pdo = $this->database();
        $pdo->exec('DROP TABLE verejni_uzivatele');
        $failed = $this->request($pdo, 'parent@example.test');
        self::assertFalse($failed['issued']);
        self::assertSame('lookup_error', passwordResetAuditAdminList($pdo, $failed['request_id'])[0]['reason_code']);
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM auth_mail_outbox')->fetchColumn());
    }

    public function testTrainerRequestHasTheSameAuditGuarantee(): void
    {
        $pdo = $this->database();
        $result = passwordResetRequestHandle(
            $pdo,
            true,
            'coach@example.test',
            true,
            '192.0.2.11',
            'https://example.test/reset?pro=trener',
            $this->keys
        );
        self::assertTrue($result['issued']);
        $audit = passwordResetAuditAdminList($pdo, $result['request_id'])[0];
        self::assertSame('trainer', $audit['audience']);
        self::assertSame('queued', $audit['outcome']);
        self::assertSame('pending', $audit['outbox_status']);
    }

    private function request(PDO $pdo, string $identifier, bool $csrfValid = true): array
    {
        return passwordResetRequestHandle($pdo, false, $identifier, $csrfValid, '192.0.2.10', 'https://example.test/reset', $this->keys);
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,email TEXT,heslo_hash TEXT,aktivni INTEGER,email_overeno INTEGER,trener_id INTEGER,session_version INTEGER)');
        $pdo->exec("INSERT INTO verejni_uzivatele VALUES(1,'Parent@Example.Test','old',1,1,NULL,1),(2,'inactive@example.test','old',0,1,NULL,1)");
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,email TEXT,heslo TEXT,aktivni INTEGER,session_version INTEGER)');
        $pdo->exec("INSERT INTO treneri VALUES(1,'coach@example.test','old',1,1)");
        $pdo->exec('CREATE TABLE child_access_accounts(id INTEGER PRIMARY KEY,sportovec_id INTEGER,login_key TEXT,active INTEGER)');
        $pdo->exec('CREATE TABLE account_person_roles(id INTEGER PRIMARY KEY,account_id INTEGER,sportovec_id INTEGER,relation_role TEXT,status TEXT,valid_from TEXT,valid_to TEXT)');
        $pdo->exec('CREATE TABLE auth_login_limits(scope TEXT,key_hash TEXT,window_started_at INTEGER,attempts INTEGER,blocked_until INTEGER,updated_at INTEGER,PRIMARY KEY(scope,key_hash))');
        $pdo->exec('CREATE TABLE password_reset_tokens(id INTEGER PRIMARY KEY AUTOINCREMENT,target_type TEXT,'
            . 'target_id INTEGER,delivery_account_id INTEGER,token_hash TEXT,expires_at TEXT,consumed_at TEXT)');
        foreach (['20261019100000_auth_mail_outbox', '20261122120000_password_reset_request_audit', '20261124120000_mail_admin_unified'] as $name) {
            $migration = require dirname(__DIR__, 2) . '/migrations/' . $name . '.php';
            $migration['up']($pdo);
            self::assertTrue($migration['verify']($pdo));
        }
        $trainerMigration = require dirname(__DIR__, 2) . '/migrations/20261031090000_trainer_password_reset.php';
        $trainerMigration['up']($pdo);
        return $pdo;
    }
}
