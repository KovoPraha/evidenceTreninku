<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/password_reset.php';

final class ExistingEmailTrustTest extends TestCase
{
    public function testHistoricalImportGetsFirstPasswordWithoutApprovingFamilyRelation(): void
    {
        $pdo = $this->database();
        $migration = require dirname(__DIR__, 2) . '/migrations/20261124160000_existing_email_trust.php';
        $migration['up']($pdo);
        self::assertTrue($migration['verify']($pdo));
        self::assertSame(1, (int)$pdo->query('SELECT email_overeno FROM verejni_uzivatele WHERE id=1')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT email_overeno FROM verejni_uzivatele WHERE id=2')->fetchColumn());
        self::assertSame('undeliverable', $pdo->query('SELECT state FROM existing_email_trust WHERE account_id=3')->fetchColumn());
        self::assertSame('trusted', $pdo->query('SELECT state FROM existing_email_trust WHERE account_id=1')->fetchColumn());

        $delivered = [];
        $result = \passwordResetRequest($pdo, 'import@example.test', static function ($email, $token) use (&$delivered): bool {
            $delivered = [$email, $token];
            return true;
        });
        self::assertTrue($result['issued']);
        self::assertSame('import@example.test', $delivered[0]);
        self::assertSame('public', \passwordResetConsume($pdo, $delivered[1], 'First import password 123!')['target_type']);
        self::assertSame(1, (int)$pdo->query('SELECT aktivni FROM verejni_uzivatele WHERE id=1')->fetchColumn());
        self::assertSame('pending', $pdo->query('SELECT status FROM account_person_roles WHERE account_id=1')->fetchColumn());
        self::assertSame('legacy_first_password', $pdo->query('SELECT action FROM public_account_activation_events WHERE account_id=1')->fetchColumn());
        self::assertNull(\passwordResetConsume($pdo, $delivered[1], 'Another import password 456!'));

        $migration['up']($pdo);
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM existing_email_trust WHERE account_id=1')->fetchColumn());
    }

    public function testChangedAddressAndOtherDisabledAccountCannotSelfActivate(): void
    {
        $pdo = $this->database();
        (require dirname(__DIR__, 2) . '/migrations/20261124160000_existing_email_trust.php')['up']($pdo);
        $token = '';
        \passwordResetRequest($pdo, 'import@example.test', static function ($email, $issued) use (&$token): bool {
            $token = $issued;
            return true;
        });
        $pdo->exec("UPDATE verejni_uzivatele SET email='changed@example.test' WHERE id=1");
        self::assertNull(\passwordResetLookup($pdo, $token));
        self::assertFalse(\passwordResetRequest($pdo, 'changed@example.test', static fn (): bool => true)['issued']);
        self::assertFalse(\passwordResetRequest($pdo, 'disabled@example.test', static fn (): bool => true)['issued']);
        self::assertFalse(\passwordResetRequest($pdo, 'future@example.test', static fn (): bool => true)['issued']);
    }

    public function testFirstPasswordMailCanLeaveQueueBeforeAccountIsActive(): void
    {
        $pdo = $this->database();
        (require dirname(__DIR__, 2) . '/migrations/20261124160000_existing_email_trust.php')['up']($pdo);
        (require dirname(__DIR__, 2) . '/migrations/20261019100000_auth_mail_outbox.php')['up']($pdo);
        $keys = ['keys' => ['v1' => random_bytes(32)], 'active_version' => 'v1', 'index_key' => random_bytes(32)];
        self::assertTrue(\passwordResetQueueRequest($pdo, 'import@example.test', 'https://example.test/reset.php', $keys)['issued']);
        $mail = $pdo->query('SELECT * FROM auth_mail_outbox')->fetch(PDO::FETCH_ASSOC);
        self::assertTrue(\authMailTokenIsCurrent($pdo, $mail));
        $sent = [];
        self::assertSame('sent', \authMailProcessOne($pdo, static function (string $recipient, string $subject, string $body) use (&$sent): bool {
            $sent = [$recipient, $subject, $body];
            return true;
        }, $keys));
        self::assertSame('import@example.test', $sent[0]);
        self::assertSame(0, (int)$pdo->query('SELECT aktivni FROM verejni_uzivatele WHERE id=1')->fetchColumn());
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE verejni_uzivatele('
            . 'id INTEGER PRIMARY KEY,email TEXT,heslo_hash TEXT,aktivni INTEGER,email_overeno INTEGER,'
            . 'session_version INTEGER,trener_id INTEGER,registrovan TEXT,verifikacni_token TEXT,'
            . 'verifikacni_token_expires_at TEXT)');
        $pdo->exec("INSERT INTO verejni_uzivatele VALUES
            (1,'import@example.test','old',0,0,1,NULL,'2026-11-01 12:00:00',NULL,NULL),
            (2,'future@example.test','old',1,0,1,NULL,'2026-11-25 12:00:00',NULL,NULL),
            (3,'bad-address','old',0,0,1,NULL,'2026-11-01 12:00:00',NULL,NULL),
            (4,'disabled@example.test','old',0,0,1,NULL,'2026-11-01 12:00:00',NULL,NULL)");
        $pdo->exec('CREATE TABLE kis_person_contacts(account_id INTEGER,account_state TEXT)');
        $pdo->exec("INSERT INTO kis_person_contacts VALUES(1,'disabled_reset_required')");
        $pdo->exec('CREATE TABLE account_person_roles(account_id INTEGER,sportovec_id INTEGER,status TEXT,relation_role TEXT,valid_from TEXT,valid_to TEXT)');
        $pdo->exec("INSERT INTO account_person_roles VALUES(1,10,'pending','guardian','2020-01-01',NULL)");
        $pdo->exec('CREATE TABLE child_access_accounts(id INTEGER PRIMARY KEY,sportovec_id INTEGER,login_key TEXT,active INTEGER)');
        $pdo->exec('CREATE TABLE password_reset_tokens('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT,target_type TEXT,target_id INTEGER,'
            . 'delivery_account_id INTEGER,token_hash TEXT,expires_at TEXT,consumed_at TEXT,delivery_email TEXT)');
        $pdo->exec('CREATE TABLE public_account_activation_events(id INTEGER PRIMARY KEY AUTOINCREMENT,account_id INTEGER,actor_trainer_id INTEGER,action TEXT,reason TEXT)');
        return $pdo;
    }
}
