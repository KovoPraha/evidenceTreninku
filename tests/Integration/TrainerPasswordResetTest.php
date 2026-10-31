<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/trainer_password_reset.php';
require_once dirname(__DIR__, 2) . '/includes/trainer_access_readiness.php';

final class TrainerPasswordResetTest extends TestCase
{
    private array $keys;

    protected function setUp(): void
    {
        $this->keys = ['keys' => ['v1' => random_bytes(32)], 'active_version' => 'v1', 'index_key' => random_bytes(32)];
    }

    public function testStandaloneTrainerReceivesEncryptedSingleUseResetAndRevokesSessions(): void
    {
        $pdo = $this->database();
        $first = $this->request($pdo);
        $second = $this->request($pdo);
        self::assertTrue($first['issued']);
        $row = $pdo->query('SELECT * FROM auth_mail_outbox ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        self::assertSame('trainer', $row['target_type']);
        self::assertStringNotContainsString($first['token'], $row['body_ciphertext']);
        self::assertStringContainsString('#token=' . $first['token'], \authMailDecrypt($row, $this->keys));
        $sent = [];
        self::assertSame('sent', \authMailProcessOne($pdo, static function ($email, $subject, $body) use (&$sent): bool {
            $sent[] = [$email, $body];
            return true;
        }, $this->keys));
        self::assertSame('coach@example.test', $sent[0][0]);
        self::assertNotNull(\trainerPasswordResetConsume($pdo, $first['token'], 'New coach password 123!'));
        self::assertTrue(password_verify('New coach password 123!', $pdo->query('SELECT heslo FROM treneri WHERE id=1')->fetchColumn()));
        self::assertSame(2, (int)$pdo->query('SELECT session_version FROM treneri WHERE id=1')->fetchColumn());
        self::assertNull(\trainerPasswordResetConsume($pdo, $first['token'], 'Another password 123!'));
        self::assertNull(\trainerPasswordResetConsume($pdo, $second['token'], 'Another password 123!'));
        self::assertSame('cancelled', \authMailProcessOne($pdo, static fn (): bool => throw new \LogicException('Stale mail'), $this->keys));
        self::assertSame('unrelated', $pdo->query('SELECT heslo_hash FROM verejni_uzivatele WHERE id=1')->fetchColumn());
    }

    public function testExplicitLinkedAccountStaysUnifiedButEmailAloneDoesNotLink(): void
    {
        $pdo = $this->database();
        $pdo->exec('UPDATE verejni_uzivatele SET trener_id=1 WHERE id=1');
        $pdo->exec("INSERT INTO password_reset_tokens VALUES('public',1,NULL)");
        $request = $this->request($pdo);
        self::assertTrue($request['issued']);
        self::assertNotNull(\trainerPasswordResetConsume($pdo, $request['token'], 'Linked password 123!'));
        self::assertSame($pdo->query('SELECT heslo FROM treneri WHERE id=1')->fetchColumn(), $pdo->query('SELECT heslo_hash FROM verejni_uzivatele WHERE id=1')->fetchColumn());
        self::assertSame(2, (int)$pdo->query('SELECT session_version FROM verejni_uzivatele WHERE id=1')->fetchColumn());
        self::assertNotNull($pdo->query('SELECT consumed_at FROM password_reset_tokens')->fetchColumn());
    }

    public function testTrainerWorkerLeavesPublicPendingAndStaleRowsUntouched(): void
    {
        $pdo = $this->database();
        $pdo->beginTransaction();
        \authMailEnqueue($pdo, 'reset', 'public', 1, str_repeat('a', 64), 'coach@example.test', 'Public', 'Do not send', $this->keys);
        $pdo->commit();
        $this->request($pdo);
        self::assertSame('sent', \authMailProcessOne($pdo, static fn (): bool => true, $this->keys, true));
        self::assertSame('pending', $pdo->query('SELECT status FROM auth_mail_outbox WHERE id=1')->fetchColumn());
        $pdo->exec("UPDATE auth_mail_outbox SET status='processing',claimed_at=datetime('now','-1 hour') WHERE id=1");
        self::assertNull(\authMailProcessOne($pdo, static fn (): bool => throw new \LogicException('Other audience'), $this->keys, true));
        self::assertSame('processing', $pdo->query('SELECT status FROM auth_mail_outbox WHERE id=1')->fetchColumn());
    }

    public function testLinkedAccountChangeAndUpdateFailureNeverPartiallyChangePasswords(): void
    {
        foreach ([
            "UPDATE verejni_uzivatele SET email='new@example.test'",
            'UPDATE verejni_uzivatele SET aktivni=0',
            'UPDATE verejni_uzivatele SET session_version=2',
            'UPDATE verejni_uzivatele SET trener_id=NULL',
        ] as $change) {
            $pdo = $this->database();
            $pdo->exec('UPDATE verejni_uzivatele SET trener_id=1');
            $request = $this->request($pdo);
            $pdo->exec($change);
            self::assertNull(\trainerPasswordResetConsume($pdo, $request['token'], 'Never applied password 123!'));
            self::assertSame('old', $pdo->query('SELECT heslo FROM treneri')->fetchColumn());
        }
        $pdo = $this->database();
        $pdo->exec('UPDATE verejni_uzivatele SET trener_id=1');
        $request = $this->request($pdo);
        $pdo->exec("CREATE TRIGGER reject_password BEFORE UPDATE OF heslo_hash ON verejni_uzivatele BEGIN SELECT RAISE(ABORT,'test'); END");
        try {
            \trainerPasswordResetConsume($pdo, $request['token'], 'Never applied password 123!');
            self::fail('Expected rollback');
        } catch (\PDOException) {
            self::assertSame('old', $pdo->query('SELECT heslo FROM treneri')->fetchColumn());
            self::assertSame(1, (int)$pdo->query('SELECT session_version FROM treneri')->fetchColumn());
            self::assertNull($pdo->query('SELECT consumed_at FROM trainer_password_reset_tokens')->fetchColumn());
        }
    }

    public function testMigrationPreservesEncryptedQueueAndItsAuditForeignKeys(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $old = require dirname(__DIR__, 2) . '/migrations/20261019100000_auth_mail_outbox.php';
        $old['up']($pdo);
        $pdo->beginTransaction();
        $id = \authMailEnqueue($pdo, 'reset', 'public', 1, str_repeat('a', 64), 'parent@example.test', 'Preserved', 'Encrypted old body', $this->keys);
        $pdo->exec("INSERT INTO auth_mail_outbox_events(outbox_id,actor_trainer_id,action,reason) VALUES($id,1,'retry','Test')");
        $pdo->commit();
        $before = $pdo->query('SELECT * FROM auth_mail_outbox')->fetchAll(PDO::FETCH_ASSOC);
        $migration = require dirname(__DIR__, 2) . '/migrations/20261031090000_trainer_password_reset.php';
        $migration['up']($pdo);
        $migration['up']($pdo);
        self::assertSame($before, $pdo->query('SELECT * FROM auth_mail_outbox')->fetchAll(PDO::FETCH_ASSOC));
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM auth_mail_outbox_events')->fetchColumn());
        self::assertSame([], $pdo->query('PRAGMA foreign_key_check')->fetchAll());
        self::assertSame(1, (int)$pdo->query('PRAGMA foreign_keys')->fetchColumn());
    }

    public function testUnknownDisabledDuplicateAndAmbiguousLinksDoNotQueue(): void
    {
        $pdo = $this->database();
        self::assertFalse(\trainerPasswordResetQueueRequest($pdo, 'unknown@example.test', 'https://example.test/reset', $this->keys)['issued']);
        self::assertFalse(\trainerPasswordResetQueueRequest($pdo, 'coach', 'https://example.test/reset', $this->keys)['issued']);
        $pdo->exec('UPDATE treneri SET aktivni=0');
        self::assertFalse($this->request($pdo)['issued']);
        $pdo->exec('UPDATE treneri SET aktivni=1');
        $pdo->exec("INSERT INTO treneri VALUES(2,' COACH@example.test ','old',1,1)");
        self::assertFalse($this->request($pdo)['issued']);
        $pdo->exec('DELETE FROM treneri WHERE id=2');
        $pdo->exec('UPDATE verejni_uzivatele SET trener_id=1,email_overeno=0');
        self::assertFalse($this->request($pdo)['issued']);
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM auth_mail_outbox')->fetchColumn());
    }

    public function testAccountChangesInvalidateQueuedAndDeliveredLinks(): void
    {
        foreach ([
            "UPDATE treneri SET email='changed@example.test'",
            'UPDATE treneri SET aktivni=0',
            'UPDATE treneri SET session_version=session_version+1',
            'UPDATE verejni_uzivatele SET trener_id=1',
            "INSERT INTO treneri VALUES(2,'coach@example.test','old',1,1)",
        ] as $change) {
            $pdo = $this->database();
            $request = $this->request($pdo);
            $pdo->exec($change);
            self::assertNull(\trainerPasswordResetConsume($pdo, $request['token'], 'Never applied password 123!'), $change);
            self::assertSame('old', $pdo->query('SELECT heslo FROM treneri WHERE id=1')->fetchColumn());
            self::assertSame('cancelled', \authMailProcessOne($pdo, static fn (): bool => throw new \LogicException('Invalid mail'), $this->keys));
        }
    }

    public function testExpiryWrongPurposeAndTransactionRollback(): void
    {
        $pdo = $this->database();
        $request = \trainerPasswordResetQueueRequest($pdo, 'coach@example.test', 'https://example.test/reset', $this->keys, 1700000000);
        self::assertNull(\trainerPasswordResetConsume($pdo, $request['token'], 'Expired password 123!', 1700003601));
        self::assertNotSame(\one_time_token_hash(\ONE_TIME_TOKEN_PASSWORD_RESET, $request['token']), $pdo->query('SELECT token_hash FROM trainer_password_reset_tokens')->fetchColumn());
        $pdo->exec("CREATE TRIGGER reject_mail BEFORE INSERT ON auth_mail_outbox BEGIN SELECT RAISE(ABORT,'test'); END");
        try {
            $this->request($pdo);
            self::fail('Queue failure must roll back token');
        } catch (\PDOException) {
            self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM trainer_password_reset_tokens')->fetchColumn());
            self::assertFalse($pdo->inTransaction());
        }
    }

    private function request(PDO $pdo): array
    {
        return \trainerPasswordResetQueueRequest($pdo, ' COACH@example.test ', 'https://example.test/reset?pro=trener', $this->keys);
    }

    public function testReadinessIsReadOnlyAndDoesNotExposeIdentities(): void
    {
        $pdo = $this->database();
        $pdo->exec("CREATE TABLE staff_user_positions(trainer_id INTEGER,position_code TEXT)");
        $pdo->exec("INSERT INTO staff_user_positions VALUES(1,'coach')");
        $pdo->exec('CREATE TABLE coach_scope_grants(trainer_id INTEGER,revoked_at TEXT,valid_from TEXT,valid_to TEXT)');
        $migration = require dirname(__DIR__, 2) . '/migrations/20260803190000_club_event_notifications.php';
        $migration['up']($pdo);
        $pdo->exec('PRAGMA query_only=ON');
        $status = \trainerAccessReadiness($pdo, 'example.test', $this->keys);
        self::assertTrue($status['read_only']);
        self::assertSame(1, $status['accounts']['reset_eligible']);
        self::assertSame(1, $status['accounts']['coaches_without_current_grant']);
        self::assertFalse($status['full_trainer_uat_verified']);
        self::assertTrue($status['mail']['ready']);
        self::assertStringNotContainsString('coach@example.test', json_encode($status, JSON_THROW_ON_ERROR));
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,email TEXT,heslo TEXT,aktivni INTEGER,session_version INTEGER)');
        $pdo->exec("INSERT INTO treneri VALUES(1,'coach@example.test','old',1,1)");
        $pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,email TEXT,heslo_hash TEXT,aktivni INTEGER,email_overeno INTEGER,session_version INTEGER,trener_id INTEGER NULL)');
        $pdo->exec("INSERT INTO verejni_uzivatele VALUES(1,'coach@example.test','unrelated',1,1,1,NULL)");
        $pdo->exec('CREATE TABLE password_reset_tokens(target_type TEXT,target_id INTEGER,consumed_at TEXT)');
        $old = require dirname(__DIR__, 2) . '/migrations/20261019100000_auth_mail_outbox.php';
        $old['up']($pdo);
        $migration = require dirname(__DIR__, 2) . '/migrations/20261031090000_trainer_password_reset.php';
        $migration['up']($pdo);
        $migration['up']($pdo);
        self::assertTrue($migration['verify']($pdo));
        return $pdo;
    }
}
