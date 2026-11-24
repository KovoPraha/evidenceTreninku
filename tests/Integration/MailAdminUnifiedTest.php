<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/funkce.php';
require_once dirname(__DIR__, 2) . '/includes/mail_admin_unified.php';
require_once dirname(__DIR__, 2) . '/includes/auth_mail_outbox.php';

final class MailAdminUnifiedTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION['trener_id'] = 7;
        $_SESSION['role'] = 'admin';
    }

    protected function tearDown(): void
    {
        unset($_SESSION['trener_id'], $_SESSION['role']);
    }

    public function testMigrationIsRepeatableAndFeedIncludesQueuedAndRejectedRequests(): void
    {
        $pdo = $this->database();
        $migration = require dirname(__DIR__, 2) . '/migrations/20261124120000_mail_admin_unified.php';
        $migration['up']($pdo);
        self::assertTrue($migration['verify']($pdo));
        $pdo->exec("INSERT INTO auth_mail_outbox VALUES(1,'verify','parent@example.test','sent',1,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP,NULL,CURRENT_TIMESTAMP)");
        $pdo->exec("INSERT INTO club_event_notifications VALUES(2,'registration','club@example.test','pending',0,CURRENT_TIMESTAMP,NULL,NULL,CURRENT_TIMESTAMP,NULL,NULL)");
        $request = str_repeat('a', 32);
        \mailRequestAuditStart($pdo, $request, 'registration', 'unlisted@example.test');
        \mailRequestAuditFinish($pdo, $request, 'rejected', 'duplicate_email');
        $feed = \mailAdminUnifiedList($pdo);
        self::assertSame(3, $feed['total']);
        self::assertCount(3, $feed['rows']);
        self::assertSame(1, \mailAdminUnifiedList($pdo, ['email' => 'unlisted@example.test'])['total']);
        self::assertSame($request, \mailAdminUnifiedList($pdo, ['reference' => $request])['rows'][0]['reference']);
        self::assertSame('auth-1', \mailAdminUnifiedList($pdo, ['reference' => 'auth-1'])['rows'][0]['reference']);
        $_SESSION['role'] = 'trener';
        $this->expectException(\MailAdminException::class);
        \mailAdminUnifiedList($pdo);
    }

    public function testBccIsAdminOnlyTimeLimitedAndResendIsAudited(): void
    {
        $pdo = $this->database();
        $pdo->exec("INSERT INTO club_event_notifications VALUES(2,'registration','club@example.test','pending',0,CURRENT_TIMESTAMP,NULL,NULL,CURRENT_TIMESTAMP,NULL,NULL)");
        self::assertFalse(\mailAdminBccSettings($pdo)['active']);
        \mailAdminBccToggle($pdo, 7, 1, 'Kontrolní odeslání');
        \mailAdminBccAttach($pdo, 'club', 2, 'audit@example.test', 7, 'Kontrolní schránka');
        self::assertSame('audit@example.test', \mailAdminBccFor($pdo, 'club', 2));
        self::assertSame("Bcc: audit@example.test\r\n", \mailAdminBccHeader('audit@example.test'));
        $pdo->exec("UPDATE club_event_notifications SET status='sent' WHERE id=2");
        \mailAdminResend($pdo, 'club', 2, 7, 'Příjemce požádal znovu', true);
        self::assertSame('pending', $pdo->query('SELECT status FROM club_event_notifications WHERE id=2')->fetchColumn());
        self::assertSame(3, (int)$pdo->query('SELECT COUNT(*) FROM mail_admin_events')->fetchColumn());
        \mailAdminBccToggle($pdo, 7, 0, 'Test skončil');
        self::assertNull(\mailAdminBccFor($pdo, 'club', 2));
        self::assertNotNull($pdo->query('SELECT skipped_at FROM mail_admin_bcc WHERE source_id=2')->fetchColumn());
        $_SESSION['role'] = 'trener';
        $this->expectException(\MailAdminException::class);
        \mailAdminBccToggle($pdo, 7, 1, 'Nepovolený pokus');
    }

    public function testSentVerificationGetsOneFreshThreeDayLinkWithoutNewAccount(): void
    {
        $keys = ['keys' => ['v1' => random_bytes(32)], 'active_version' => 'v1', 'index_key' => random_bytes(32)];
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,jmeno TEXT,email TEXT,aktivni INT,email_overeno INT,verifikacni_token TEXT,verifikacni_token_expires_at TEXT)');
        $pdo->exec("INSERT INTO verejni_uzivatele VALUES(1,'Rodič','parent@example.test',1,0,NULL,NULL)");
        $pdo->exec('CREATE TABLE password_reset_tokens(id INTEGER PRIMARY KEY AUTOINCREMENT,target_type TEXT,target_id INT,delivery_account_id INT,token_hash TEXT,expires_at TEXT,consumed_at TEXT)');
        foreach (['20261019100000_auth_mail_outbox','20261122120000_password_reset_request_audit','20261124120000_mail_admin_unified'] as $name) {
            $migration = require dirname(__DIR__, 2) . '/migrations/' . $name . '.php';
            $migration['up']($pdo);
        }
        $pdo->beginTransaction();
        \authMailEnqueue($pdo, 'verify', 'public', 1, str_repeat('a', 64), 'parent@example.test', 'Ověření', 'Starý odkaz', $keys);
        $pdo->commit();
        $pdo->exec("UPDATE auth_mail_outbox SET status='sent',body_ciphertext=NULL WHERE id=1");
        \mailAdminAuthResend($pdo, 1, 7, 'Rodič nedostal první zprávu', true, $keys, 'https://example.test/overeni.php');
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM verejni_uzivatele')->fetchColumn());
        self::assertSame(2, (int)$pdo->query('SELECT COUNT(*) FROM auth_mail_outbox')->fetchColumn());
        self::assertSame('pending', $pdo->query('SELECT status FROM auth_mail_outbox WHERE id=2')->fetchColumn());
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM mail_admin_reissues')->fetchColumn());
        $expiry = strtotime((string)$pdo->query('SELECT verifikacni_token_expires_at FROM verejni_uzivatele WHERE id=1')->fetchColumn() . ' UTC');
        self::assertGreaterThan(time() + 259190, $expiry);
        try {
            \mailAdminAuthResend($pdo, 1, 7, 'Druhé kliknutí', true, $keys, 'https://example.test/overeni.php');
            self::fail('Second link was issued from the same message.');
        } catch (\MailAdminException) {
            self::assertSame(2, (int)$pdo->query('SELECT COUNT(*) FROM auth_mail_outbox')->fetchColumn());
        }
        $pdo->exec('UPDATE verejni_uzivatele SET email_overeno=1 WHERE id=1');
        $pdo->beginTransaction();
        \authMailEnqueue($pdo, 'reset', 'public', 1, str_repeat('b', 64), 'parent@example.test', 'Obnova', 'Starý reset', $keys);
        $pdo->commit();
        $pdo->exec("UPDATE auth_mail_outbox SET status='sent',body_ciphertext=NULL WHERE id=3");
        \mailAdminAuthResend($pdo, 3, 7, 'Nový obnovovací odkaz', true, $keys, null, 'https://example.test/reset.php');
        self::assertSame(4, (int)$pdo->query('SELECT COUNT(*) FROM auth_mail_outbox')->fetchColumn());
        self::assertSame('pending', $pdo->query('SELECT status FROM auth_mail_outbox WHERE id=4')->fetchColumn());
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM password_reset_tokens')->fetchColumn());
        $resetExpiry = strtotime((string)$pdo->query('SELECT expires_at FROM password_reset_tokens')->fetchColumn() . ' UTC');
        self::assertGreaterThan(time() + 259190, $resetExpiry);
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $reset = require dirname(__DIR__, 2) . '/migrations/20261122120000_password_reset_request_audit.php';
        $reset['up']($pdo);
        $migration = require dirname(__DIR__, 2) . '/migrations/20261124120000_mail_admin_unified.php';
        $migration['up']($pdo);
        $pdo->exec('CREATE TABLE auth_mail_outbox(id INTEGER PRIMARY KEY,kind TEXT,recipient_email TEXT,status TEXT,attempts INT,available_at TEXT,sent_at TEXT,last_error TEXT,created_at TEXT)');
        $pdo->exec('CREATE TABLE club_event_notifications(id INTEGER PRIMARY KEY,notification_type TEXT,'
            . 'recipient_email TEXT,status TEXT,attempts INT,available_at TEXT,sent_at TEXT,last_error TEXT,'
            . 'created_at TEXT,claim_token TEXT,claimed_at TEXT)');
        $pdo->exec('CREATE TABLE roster_message_recipients(id INTEGER PRIMARY KEY,recipient_email TEXT,status TEXT,attempts INT,available_at TEXT,sent_at TEXT,last_error TEXT,created_at TEXT)');
        $pdo->exec('CREATE TABLE family_weekly_summaries(id INTEGER PRIMARY KEY,recipient_email TEXT,status TEXT,attempts INT,available_at TEXT,sent_at TEXT,last_error TEXT,created_at TEXT)');
        $pdo->exec('CREATE TABLE member_charge_reminders(id INTEGER PRIMARY KEY,reminder_type TEXT,'
            . 'recipient_email TEXT,status TEXT,attempts INT,available_at TEXT,sent_at TEXT,last_error TEXT,created_at TEXT)');
        $pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,email TEXT)');
        $pdo->exec('CREATE TABLE monthly_membership_messages(id INTEGER PRIMARY KEY,kind TEXT,account_id INT,status TEXT,attempts INT,sent_at TEXT,last_error TEXT,created_at TEXT)');
        return $pdo;
    }
}
