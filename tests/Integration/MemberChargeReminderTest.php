<?php
declare(strict_types=1);

namespace Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/member_charge_reminder.php';

final class MemberChargeReminderTest extends TestCase
{
    public function testPreferenceIsExplicitAuditedAndOptOutCancelsUnsentRows(): void
    {
        $pdo = $this->database();
        self::assertSame(['enabled' => false, 'days_before' => 7], \memberChargeReminderPreference($pdo, 1));
        $saved = \memberChargeReminderSavePreference($pdo, 1, true, 7);
        self::assertTrue($saved['changed']);
        self::assertSame(['enabled' => true, 'days_before' => 7], \memberChargeReminderPreference($pdo, 1));
        self::assertFalse(\memberChargeReminderSavePreference($pdo, 1, true, 7)['changed']);
        \memberChargeReminderGenerate($pdo, $this->today());
        self::assertSame(2, (int)$pdo->query("SELECT COUNT(*) FROM member_charge_reminders WHERE status='pending'")->fetchColumn());

        \memberChargeReminderSavePreference($pdo, 1, false, 7);
        self::assertSame(2, (int)$pdo->query("SELECT COUNT(*) FROM member_charge_reminders WHERE status='cancelled'")->fetchColumn());
        self::assertSame(
            ['preference_change', 'enqueue', 'enqueue', 'preference_change', 'cancel_opt_out', 'cancel_opt_out'],
            $pdo->query('SELECT action FROM member_charge_reminder_events ORDER BY id')->fetchAll(PDO::FETCH_COLUMN)
        );
    }

    public function testGenerationIsIdempotentIsolatedAndUsesSafeAccountLink(): void
    {
        $pdo = $this->database();
        \memberChargeReminderSavePreference($pdo, 1, true, 7);
        $first = \memberChargeReminderGenerate($pdo, $this->today());
        $second = \memberChargeReminderGenerate($pdo, $this->today());
        self::assertSame(['queued' => 2, 'existing' => 0, 'skipped' => 0], $first);
        self::assertSame(['queued' => 0, 'existing' => 2, 'skipped' => 0], $second);

        $rows = $pdo->query('SELECT * FROM member_charge_reminders ORDER BY charge_id')->fetchAll(PDO::FETCH_ASSOC);
        self::assertSame([100, 101], array_map('intval', array_column($rows, 'charge_id')));
        self::assertSame([1, 1], array_map('intval', array_column($rows, 'account_id')));
        foreach ($rows as $row) {
            self::assertStringContainsString('sportovni_prehled.php', (string)$row['body_plain']);
            self::assertStringNotContainsString('?', (string)$row['body_plain']);
            self::assertStringNotContainsString('CH-', (string)$row['body_plain']);
        }
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM member_charge_reminders WHERE account_id=2')->fetchColumn());
    }

    public function testWorkerRechecksChargeAndLimitsOneMessagePerAccountForTwentyHours(): void
    {
        $pdo = $this->database();
        \memberChargeReminderSavePreference($pdo, 1, true, 7);
        \memberChargeReminderGenerate($pdo, $this->today());
        $sent = [];
        $sender = static function (string $email, string $subject, string $body) use (&$sent): bool {
            $sent[] = [$email, $subject, $body];
            return true;
        };
        self::assertTrue(\memberChargeReminderProcessOne($pdo, $sender));
        self::assertNull(\memberChargeReminderProcessOne($pdo, $sender));
        self::assertCount(1, $sent);

        $pdo->exec("UPDATE member_charge_reminders SET sent_at='2000-01-01 00:00:00' WHERE status='sent'");
        self::assertTrue(\memberChargeReminderProcessOne($pdo, $sender));
        self::assertCount(2, $sent);

        $pdo->exec("UPDATE club_member_charges SET status='paid' WHERE id=103");
        $pdo->exec("INSERT INTO member_charge_reminders(charge_id,account_id,recipient_email,recipient_name,subject_plain,body_plain) VALUES(103,1,'rodic@example.test','Rodič','x','x')");
        $pdo->exec("UPDATE member_charge_reminders SET sent_at='2000-01-01 00:00:00' WHERE status='sent'");
        self::assertNull(\memberChargeReminderClaim($pdo));
    }

    public function testFailureRetriesAndBecomesTerminalAfterFifthAttempt(): void
    {
        $pdo = $this->database();
        \memberChargeReminderSavePreference($pdo, 1, true, 7);
        \memberChargeReminderGenerate($pdo, $this->today());
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $pdo->exec("UPDATE member_charge_reminders SET available_at='2000-01-01 00:00:00' WHERE id=1");
            self::assertFalse(\memberChargeReminderProcessOne($pdo, static fn (): bool => false));
            self::assertSame($attempt, (int)$pdo->query('SELECT attempts FROM member_charge_reminders WHERE id=1')->fetchColumn());
        }
        self::assertSame('failed', $pdo->query('SELECT status FROM member_charge_reminders WHERE id=1')->fetchColumn());
        self::assertSame(5, (int)$pdo->query("SELECT COUNT(*) FROM member_charge_reminder_events WHERE reminder_id=1 AND action='send_failed'")->fetchColumn());
    }

    public function testSecondWorkerCannotClaimAnotherReminderForSameAccount(): void
    {
        $pdo = $this->database();
        \memberChargeReminderSavePreference($pdo, 1, true, 7);
        \memberChargeReminderGenerate($pdo, $this->today());
        $first = \memberChargeReminderClaim($pdo);
        self::assertNotNull($first);
        self::assertNull(\memberChargeReminderClaim($pdo));
        self::assertSame(1, (int)$pdo->query("SELECT COUNT(*) FROM member_charge_reminders WHERE status='processing'")->fetchColumn());
        self::assertSame(1, (int)$pdo->query("SELECT COUNT(*) FROM member_charge_reminders WHERE status='pending'")->fetchColumn());
    }

    public function testAdminCanInspectAndAuditRetryOfFailedReminder(): void
    {
        $pdo = $this->database();
        \memberChargeReminderSavePreference($pdo, 1, true, 7);
        \memberChargeReminderGenerate($pdo, $this->today());
        $pdo->exec("UPDATE member_charge_reminders SET status='failed',attempts=5,last_error='Transport unavailable' WHERE id=1");

        self::assertSame(1, \memberChargeReminderAdminSummary($pdo)['failed']);
        $rows = \memberChargeReminderAdminList($pdo, 'failed');
        self::assertCount(1, $rows);
        self::assertSame('Anna', $rows[0]['child_first_name']);
        self::assertSame('CH-100', $rows[0]['public_code']);

        $result = \memberChargeReminderAdminRetry($pdo, 1, 77, 'Ověřena dočasná chyba transportu.', true);
        self::assertSame(['id' => 1, 'changed' => true, 'status' => 'pending'], $result);
        $reminder = $pdo->query('SELECT status,attempts,last_error FROM member_charge_reminders WHERE id=1')->fetch(PDO::FETCH_ASSOC);
        self::assertSame(['status' => 'pending', 'attempts' => 0, 'last_error' => null], $reminder);
        $audit = $pdo->query("SELECT action,actor_type,actor_id FROM member_charge_reminder_events WHERE action='manual_retry'")->fetch(PDO::FETCH_ASSOC);
        self::assertSame(['action' => 'manual_retry', 'actor_type' => 'trainer', 'actor_id' => 77], $audit);
    }

    public function testAdminRetryCannotBypassConfirmationOptOutOrFinalState(): void
    {
        $pdo = $this->database();
        \memberChargeReminderSavePreference($pdo, 1, true, 7);
        \memberChargeReminderGenerate($pdo, $this->today());
        $pdo->exec("UPDATE member_charge_reminders SET status='failed',attempts=5 WHERE id=1");

        try {
            \memberChargeReminderAdminRetry($pdo, 1, 77, 'Důvod', false);
            self::fail('Missing confirmation must fail.');
        } catch (\InvalidArgumentException) {
            self::assertTrue(true);
        }
        \memberChargeReminderSavePreference($pdo, 1, false, 7);
        try {
            \memberChargeReminderAdminRetry($pdo, 1, 77, 'Důvod', true);
            self::fail('Cancelled reminder must fail.');
        } catch (\MemberChargeReminderException) {
            self::assertTrue(true);
        }
        self::assertSame('cancelled', $pdo->query('SELECT status FROM member_charge_reminders WHERE id=1')->fetchColumn());
    }

    private function today(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-08-05', new DateTimeZone('Europe/Prague'));
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,email TEXT,jmeno TEXT,prijmeni TEXT,aktivni INTEGER,email_overeno INTEGER)');
        $pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT)');
        $pdo->exec('CREATE TABLE account_person_roles(id INTEGER PRIMARY KEY,account_id INTEGER,sportovec_id INTEGER,relation_role TEXT,status TEXT,valid_from TEXT,valid_to TEXT)');
        $pdo->exec('CREATE TABLE club_member_charges(id INTEGER PRIMARY KEY,sportovec_id INTEGER,payer_account_id INTEGER,public_code TEXT,title_snapshot TEXT,amount_minor INTEGER,currency TEXT,due_on TEXT,status TEXT)');
        $migration = require dirname(__DIR__, 2) . '/migrations/20260805020000_member_charge_reminders.php';
        $migration['up']($pdo);
        $migration['up']($pdo);
        self::assertTrue($migration['verify']($pdo));
        $adminMigration = require dirname(__DIR__, 2) . '/migrations/20260805030000_member_charge_reminder_admin.php';
        $adminMigration['up']($pdo);
        $adminMigration['up']($pdo);
        self::assertTrue($adminMigration['verify']($pdo));

        $pdo->exec("INSERT INTO verejni_uzivatele VALUES(1,'rodic@example.test','Testovací','Rodič',1,1),(2,'cizi@example.test','Cizí','Rodič',1,1)");
        $pdo->exec("INSERT INTO sportovci VALUES(10,'Anna','První'),(11,'Bára','Cizí')");
        $pdo->exec("INSERT INTO account_person_roles VALUES(1,1,10,'guardian','approved','2020-01-01',NULL),(2,2,11,'guardian','approved','2020-01-01',NULL)");
        $pdo->exec("INSERT INTO club_member_charges VALUES"
            . "(100,10,1,'CH-100','Příspěvek srpen',50000,'CZK','2026-08-10','pending'),"
            . "(101,10,1,'CH-101','Soustředění',120000,'CZK','2026-08-12','pending'),"
            . "(102,11,2,'CH-102','Cizí příspěvek',60000,'CZK','2026-08-10','pending'),"
            . "(103,10,1,'CH-103','Uhrazený předpis',10000,'CZK','2026-08-08','paid')");
        return $pdo;
    }
}
