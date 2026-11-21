<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2).'/includes/password_reset.php';
require_once dirname(__DIR__, 2).'/includes/parent_reset_reissue.php';

final class AuthMailOutboxTest extends TestCase
{
    private array $keys;

    protected function setUp(): void
    {
        $this->keys = ['keys' => ['v1' => random_bytes(32)],'active_version' => 'v1','index_key' => random_bytes(32)];
    }

    public function testVerificationIsEncryptedAndStaleLinksAreNeverSent(): void
    {
        $pdo = $this->database();
        $first = \one_time_token_issue(\ONE_TIME_TOKEN_EMAIL_VERIFICATION, 3600);
        $pdo->prepare('UPDATE verejni_uzivatele SET verifikacni_token=?,verifikacni_token_expires_at=? WHERE id=1')->execute([$first['hash'],$first['expires_at']]);
        $pdo->beginTransaction();
        \authMailEnqueue($pdo, 'verify', 'public', 1, $first['hash'], 'parent@example.test', 'Ověření', 'Tajný odkaz '.$first['token'], $this->keys);
        $pdo->commit();
        $raw = (string)$pdo->query('SELECT body_ciphertext FROM auth_mail_outbox WHERE id=1')->fetchColumn();
        self::assertStringNotContainsString($first['token'], $raw);
        self::assertSame('Tajný odkaz '.$first['token'], \authMailDecrypt($pdo->query('SELECT * FROM auth_mail_outbox WHERE id=1')->fetch(PDO::FETCH_ASSOC), $this->keys));

        $sent = [];
        self::assertSame('sent', \authMailProcessOne($pdo, static function ($email, $subject, $body) use (&$sent): bool {
            $sent[] = [$email,$body];
            return true;
        }, $this->keys));
        self::assertCount(1, $sent);
        self::assertNull($pdo->query('SELECT body_ciphertext FROM auth_mail_outbox WHERE id=1')->fetchColumn());

        $stale = \one_time_token_issue(\ONE_TIME_TOKEN_EMAIL_VERIFICATION, 3600);
        $pdo->beginTransaction();
        \authMailEnqueue($pdo, 'verify', 'public', 1, $stale['hash'], 'parent@example.test', 'Ověření', 'Nikdy neodeslat', $this->keys);
        $pdo->commit();
        self::assertSame('cancelled', \authMailProcessOne($pdo, static function (): bool {
            throw new \LogicException('Stale token was sent');
        }, $this->keys));
    }

    public function testResetQueueKeepsPreviousLinkAndRetriesOnlyCurrentTarget(): void
    {
        $pdo = $this->database();
        $pdo->exec('UPDATE verejni_uzivatele SET email_overeno=1 WHERE id=1');
        $first = \passwordResetQueueRequest($pdo, 'parent@example.test', 'https://example.test/reset.php', $this->keys);
        $firstBody = \authMailDecrypt($pdo->query('SELECT * FROM auth_mail_outbox WHERE id=1')->fetch(PDO::FETCH_ASSOC), $this->keys);
        self::assertStringContainsString('Odkaz je platný 3 dny.', $firstBody);
        $firstExpiry = strtotime((string)$pdo->query('SELECT expires_at FROM password_reset_tokens WHERE id=1')->fetchColumn() . ' UTC');
        self::assertGreaterThanOrEqual(time() + 259190, $firstExpiry);
        $second = \passwordResetQueueRequest($pdo, 'parent@example.test', 'https://example.test/reset.php', $this->keys);
        self::assertTrue($first['issued']);
        self::assertTrue($second['issued']);
        self::assertSame(2, (int)$pdo->query('SELECT COUNT(*) FROM password_reset_tokens WHERE consumed_at IS NULL')->fetchColumn());
        $pdo->exec("UPDATE auth_mail_outbox SET status='failed' WHERE id=1");
        self::assertTrue(\authMailAdminRetry($pdo, 1, 7, 'Transport je znovu funkční', true));
        $pdo->exec("UPDATE verejni_uzivatele SET email='changed@example.test' WHERE id=1");
        self::assertSame('cancelled', \authMailProcessOne($pdo, static function (): bool {
            throw new \LogicException('Changed recipient was sent');
        }, $this->keys));
        self::assertSame('cancelled', \authMailProcessOne($pdo, static function (): bool {
            throw new \LogicException('Changed recipient was sent');
        }, $this->keys));
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM auth_mail_outbox_events')->fetchColumn());
    }

    public function testTwoCancelledParentResetsAreReissuedOnceForThreeDays(): void
    {
        $pdo = $this->database();
        $oldIds = $this->cancelledParentResets($pdo);
        self::assertSame(['ready' => true, 'old_ids' => $oldIds, 'reason' => null], \parentResetReissueInspect($pdo));
        $before = time();
        $issued = \parentResetReissueTwo($pdo, $oldIds, 'https://example.test/reset.php', $this->keys);
        self::assertSame(2, $issued['queued']);
        self::assertSame($oldIds, $issued['old_ids']);
        self::assertCount(2, $issued['new_ids']);
        self::assertSame(2, (int)$pdo->query('SELECT COUNT(*) FROM password_reset_tokens')->fetchColumn());
        self::assertFalse(\parentResetReissueInspect($pdo)['ready']);
        $rows = $pdo->query("SELECT * FROM auth_mail_outbox WHERE status='pending' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
        self::assertCount(2, $rows);
        foreach ($rows as $row) {
            $body = \authMailDecrypt($row, $this->keys);
            self::assertStringContainsString('Odkaz je platný 3 dny.', $body);
            self::assertSame(1, preg_match('~#token=([a-f0-9]{64})~', $body, $match));
            $tokenHash = \one_time_token_hash(\ONE_TIME_TOKEN_PASSWORD_RESET, $match[1]);
            $query = $pdo->prepare('SELECT expires_at FROM password_reset_tokens WHERE token_hash=?');
            $query->execute([$tokenHash]);
            $expires = strtotime((string)$query->fetchColumn() . ' UTC');
            self::assertGreaterThanOrEqual($before + 259200, $expires);
            self::assertLessThanOrEqual(time() + 259200, $expires);
        }
        $sent = [];
        self::assertSame('sent', \authMailProcessOne($pdo, static function (string $email) use (&$sent): bool {
            $sent[] = $email;
            return true;
        }, $this->keys, false, true));
        self::assertSame('sent', \authMailProcessOne($pdo, static function (string $email) use (&$sent): bool {
            $sent[] = $email;
            return true;
        }, $this->keys, false, true));
        self::assertSame(['parent@example.test', 'first@example.test'], $sent);
        $this->expectException(\RuntimeException::class);
        \parentResetReissueTwo($pdo, $oldIds, 'https://example.test/reset.php', $this->keys);
    }

    public function testReissueRefusesNewerRequest(): void
    {
        $pdo = $this->database();
        $oldIds = $this->cancelledParentResets($pdo);
        \passwordResetQueueRequest($pdo, 'parent@example.test', 'https://example.test/reset.php', $this->keys);
        self::assertSame('newer_request_exists', \parentResetReissueInspect($pdo)['reason']);
        $this->expectException(\RuntimeException::class);
        \parentResetReissueTwo($pdo, $oldIds, 'https://example.test/reset.php', $this->keys);
    }

    public function testReissueRefusesChangedRecipient(): void
    {
        $pdo = $this->database();
        $oldIds = $this->cancelledParentResets($pdo);
        $pdo->exec("UPDATE verejni_uzivatele SET email='changed@example.test' WHERE id=2");
        self::assertSame('target_changed', \parentResetReissueInspect($pdo)['reason']);
        $this->expectException(\RuntimeException::class);
        \parentResetReissueTwo($pdo, $oldIds, 'https://example.test/reset.php', $this->keys);
    }

    private function cancelledParentResets(PDO $pdo): array
    {
        $pdo->exec("UPDATE verejni_uzivatele SET email_overeno=1 WHERE id=1");
        $pdo->exec("INSERT INTO verejni_uzivatele(id,jmeno,email,heslo_hash,aktivni,email_overeno) VALUES(2,'Druhý','first@example.test','old',1,1)");
        $pdo->beginTransaction();
        \authMailEnqueue($pdo, 'reset', 'public', 1, str_repeat('a', 64), 'parent@example.test', 'Reset', 'Expired', $this->keys);
        \authMailEnqueue($pdo, 'reset', 'public', 2, str_repeat('b', 64), 'first@example.test', 'Reset', 'Expired', $this->keys);
        $pdo->commit();
        $statement = $pdo->prepare("UPDATE auth_mail_outbox SET status='cancelled',attempts=1,body_ciphertext=NULL,last_error=?");
        $statement->execute([\PARENT_RESET_INCIDENT_CANCEL_REASON]);
        return [1, 2];
    }

    public function testFailedTransportIsRetryableAndClaimRespectsBackoff(): void
    {
        $pdo = $this->database();
        $pdo->exec('UPDATE verejni_uzivatele SET email_overeno=1 WHERE id=1');
        \passwordResetQueueRequest($pdo, 'parent@example.test', 'https://example.test/reset.php', $this->keys);
        self::assertSame('failed', \authMailProcessOne($pdo, static fn (): bool => false, $this->keys));
        self::assertSame('pending', $pdo->query('SELECT status FROM auth_mail_outbox')->fetchColumn());
        self::assertSame(1, (int)$pdo->query('SELECT attempts FROM auth_mail_outbox')->fetchColumn());
        self::assertNull(\authMailProcessOne($pdo, static fn (): bool => true, $this->keys));
    }

    public function testParentWorkerClaimsVerifyAndBothResetTypesWithoutTrainerMail(): void
    {
        $pdo = $this->database();
        $trainerMigration = require dirname(__DIR__, 2) . '/migrations/20261031090000_trainer_password_reset.php';
        $trainerMigration['up']($pdo);
        $pdo->beginTransaction();
        \authMailEnqueue($pdo, 'reset', 'trainer', 9, str_repeat('a', 64), 'coach@example.test', 'Trainer', 'Trainer', $this->keys);
        \authMailEnqueue($pdo, 'verify', 'public', 1, str_repeat('b', 64), 'parent@example.test', 'Verify', 'Verify', $this->keys);
        \authMailEnqueue($pdo, 'reset', 'public', 1, str_repeat('c', 64), 'parent@example.test', 'Reset', 'Reset', $this->keys);
        \authMailEnqueue($pdo, 'reset', 'child', 2, str_repeat('d', 64), 'parent@example.test', 'Child reset', 'Child reset', $this->keys);
        $pdo->commit();

        self::assertSame('verify', \authMailClaim($pdo, false, true)['kind']);
        self::assertSame('reset', \authMailClaim($pdo, false, true)['kind']);
        self::assertSame('child', \authMailClaim($pdo, false, true)['target_type']);
        self::assertNull(\authMailClaim($pdo, false, true));
        self::assertSame('pending', $pdo->query('SELECT status FROM auth_mail_outbox WHERE id=1')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT attempts FROM auth_mail_outbox WHERE id=1')->fetchColumn());
        $pdo->exec("UPDATE auth_mail_outbox SET status='processing',claimed_at=datetime('now','-1 hour') WHERE id=1");
        self::assertNull(\authMailClaim($pdo, false, true));
        self::assertSame('processing', $pdo->query('SELECT status FROM auth_mail_outbox WHERE id=1')->fetchColumn());
    }

    public function testResendReplacesOnlyUnverifiedAccountLinkWithoutEnumeratingAddress(): void
    {
        $pdo = $this->database();
        $first = \authMailResendVerification($pdo, 'parent@example.test', 'https://example.test/verify.php', $this->keys);
        self::assertSame(['accepted' => true,'queued' => true], $first);
        $oldHash = (string)$pdo->query('SELECT verifikacni_token FROM verejni_uzivatele WHERE id=1')->fetchColumn();
        $again = \authMailResendVerification($pdo, 'parent@example.test', 'https://example.test/verify.php', $this->keys);
        self::assertSame($first, $again);
        self::assertNotSame($oldHash, (string)$pdo->query('SELECT verifikacni_token FROM verejni_uzivatele WHERE id=1')->fetchColumn());
        self::assertSame('cancelled', (string)$pdo->query('SELECT status FROM auth_mail_outbox WHERE id=1')->fetchColumn());
        self::assertNull($pdo->query('SELECT body_ciphertext FROM auth_mail_outbox WHERE id=1')->fetchColumn());
        self::assertSame('pending', (string)$pdo->query('SELECT status FROM auth_mail_outbox WHERE id=2')->fetchColumn());
        self::assertSame(['accepted' => true,'queued' => false], \authMailResendVerification($pdo, 'nobody@example.test', 'https://example.test/verify.php', $this->keys));
    }

    public function testResendPreservesVariantThroughEncryptedMailAndRejectsExternalReturn(): void
    {
        $pdo = $this->database();
        $purchase = 'produkt.php?id=143&variant_id=671#varianta-671';
        $destination = 'registrace_sportovce.php?return='.rawurlencode($purchase);
        foreach ([$destination, 'https://example.test/evil'] as $requested) {
            \authMailResendVerification($pdo, 'parent@example.test', 'https://example.test/overeni.php', $this->keys, $requested);
            $row = $pdo->query('SELECT * FROM auth_mail_outbox ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
            $body = \authMailDecrypt($row, $this->keys);
            self::assertSame(1, preg_match('~https://example.test/overeni.php#([^\s]+)~', $body, $match));
            parse_str($match[1], $fragment);
            self::assertSame($requested === $destination ? $destination : 'moje_osoby.php', $fragment['redirect']);
            self::assertSame($row['token_hash'], \one_time_token_hash(\ONE_TIME_TOKEN_EMAIL_VERIFICATION, $fragment['token']));
            self::assertStringNotContainsString($fragment['token'], (string)$row['body_ciphertext']);
        }
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,jmeno TEXT,email TEXT,heslo_hash TEXT,'
            . 'aktivni INTEGER,email_overeno INTEGER,verifikacni_token TEXT,verifikacni_token_expires_at TEXT,trener_id INTEGER)');
        $pdo->exec("INSERT INTO verejni_uzivatele VALUES(1,'Rodič','parent@example.test','old',1,0,NULL,NULL,NULL)");
        $pdo->exec('CREATE TABLE password_reset_tokens(id INTEGER PRIMARY KEY AUTOINCREMENT,target_type TEXT,'
            . 'target_id INTEGER,delivery_account_id INTEGER,token_hash TEXT,expires_at TEXT,consumed_at TEXT)');
        $migration = require dirname(__DIR__, 2).'/migrations/20261019100000_auth_mail_outbox.php';
        $migration['up']($pdo);
        self::assertTrue($migration['verify']($pdo));
        return $pdo;
    }
}
