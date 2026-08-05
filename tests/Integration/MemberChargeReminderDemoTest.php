<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/member_charge_reminder_demo.php';

final class MemberChargeReminderDemoTest extends TestCase
{
    public function testLocalDemoIsConfirmedAuditedAndRepeatable(): void
    {
        $pdo = $this->database();
        $first = \memberChargeReminderSeedLocalDemo($pdo, 77, true, true);
        $second = \memberChargeReminderSeedLocalDemo($pdo, 77, true, true);

        self::assertSame($first, $second);
        self::assertSame(1, (int)$pdo->query("SELECT COUNT(*) FROM club_member_charges WHERE source_system='localhost_demo'")->fetchColumn());
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM member_charge_reminders')->fetchColumn());
        self::assertSame(1, (int)$pdo->query('SELECT enabled FROM member_charge_reminder_preferences WHERE account_id=1')->fetchColumn());
        self::assertSame('pending', $pdo->query('SELECT status FROM member_charge_reminders')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT attempts FROM member_charge_reminders')->fetchColumn());
        self::assertSame(2, (int)$pdo->query("SELECT COUNT(*) FROM member_charge_reminder_events WHERE action='localhost_demo_reset' AND actor_type='trainer' AND actor_id=77")->fetchColumn());
        self::assertSame(2, (int)$pdo->query("SELECT COUNT(*) FROM member_charge_reminder_events WHERE action='localhost_demo_opt_in' AND actor_type='trainer' AND actor_id=77")->fetchColumn());
        self::assertSame(2, (int)$pdo->query("SELECT COUNT(*) FROM club_member_charge_events WHERE action='localhost_demo_reset' AND actor_type='trainer' AND actor_id=77")->fetchColumn());

        $preview = \memberChargeReminderAdminPreview($pdo, $first['reminder_id']);
        self::assertStringContainsString('LOCALHOST', (string)$preview['subject_plain']);
        self::assertStringContainsString('Testovací Rodič', (string)$preview['body_plain']);
        self::assertStringContainsString('Anna První', (string)$preview['body_plain']);
    }

    public function testDemoRefusesMissingConfirmationAndNonLocalEnvironment(): void
    {
        $pdo = $this->database();
        try {
            \memberChargeReminderSeedLocalDemo($pdo, 77, false, true);
            self::fail('Missing confirmation must fail.');
        } catch (\InvalidArgumentException) {
            self::assertTrue(true);
        }
        $this->expectException(\MemberChargeReminderException::class);
        \memberChargeReminderSeedLocalDemo($pdo, 77, true, false);
    }

    public function testLocalDemoDeliveryUsesFileOutboxAndAuditsTrainer(): void
    {
        $pdo = $this->database();
        $seed = \memberChargeReminderSeedLocalDemo($pdo, 77, true, true);
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'member-charge-reminder-demo-' . bin2hex(random_bytes(8));

        try {
            $result = \memberChargeReminderDeliverLocalDemo($pdo, 77, true, true, $directory);
            self::assertSame(['processed' => true, 'sent' => true, 'reminder_id' => $seed['reminder_id']], $result);
            self::assertSame('sent', $pdo->query('SELECT status FROM member_charge_reminders')->fetchColumn());
            self::assertSame(1, (int)$pdo->query('SELECT attempts FROM member_charge_reminders')->fetchColumn());

            $files = glob($directory . DIRECTORY_SEPARATOR . '*.json') ?: [];
            self::assertCount(1, $files);
            $payload = json_decode((string)file_get_contents($files[0]), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame('member-charge-reminder-local-outbox-v1', $payload['schema']);
            self::assertSame('rodic@localhost.test', $payload['original_recipient']);
            self::assertStringContainsString('LOCALHOST', (string)$payload['subject']);

            foreach (['claim', 'sent'] as $action) {
                $statement = $pdo->prepare('SELECT actor_type,actor_id FROM member_charge_reminder_events WHERE reminder_id=? AND action=? ORDER BY id DESC LIMIT 1');
                $statement->execute([$seed['reminder_id'], $action]);
                self::assertSame(['actor_type' => 'trainer', 'actor_id' => 77], $statement->fetch(PDO::FETCH_ASSOC));
            }
            self::assertSame(['processed' => false, 'sent' => false, 'reminder_id' => null], \memberChargeReminderDeliverLocalDemo($pdo, 77, true, true, $directory));

            $reset = \memberChargeReminderSeedLocalDemo($pdo, 77, true, true);
            self::assertSame($seed, $reset);
            self::assertSame('pending', $pdo->query('SELECT status FROM member_charge_reminders')->fetchColumn());
            self::assertSame(0, (int)$pdo->query('SELECT attempts FROM member_charge_reminders')->fetchColumn());
        } finally {
            foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) unlink($file);
            if (is_dir($directory)) rmdir($directory);
        }
    }

    public function testLocalDemoDeliveryRefusesMissingConfirmationAndNonLocalEnvironment(): void
    {
        $pdo = $this->database();
        try {
            \memberChargeReminderDeliverLocalDemo($pdo, 77, false, true);
            self::fail('Missing confirmation must fail.');
        } catch (\InvalidArgumentException) {
            self::assertTrue(true);
        }
        $this->expectException(\MemberChargeReminderException::class);
        \memberChargeReminderDeliverLocalDemo($pdo, 77, true, false);
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,email TEXT,jmeno TEXT,prijmeni TEXT,aktivni INTEGER,email_overeno INTEGER)');
        $pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT)');
        $pdo->exec('CREATE TABLE account_person_roles(id INTEGER PRIMARY KEY,account_id INTEGER,sportovec_id INTEGER,relation_role TEXT,status TEXT,valid_from TEXT,valid_to TEXT)');
        $pdo->exec('CREATE TABLE club_member_charges(id INTEGER PRIMARY KEY AUTOINCREMENT,sportovec_id INTEGER,payer_account_id INTEGER,public_code TEXT UNIQUE,charge_type TEXT,title_snapshot TEXT,period_from TEXT,period_to TEXT,amount_minor INTEGER,currency TEXT,due_on TEXT,status TEXT,source_system TEXT,source_external_id TEXT,source_import_run_id INTEGER,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP,UNIQUE(source_system,source_external_id))');
        $pdo->exec('CREATE TABLE club_member_charge_events(id INTEGER PRIMARY KEY AUTOINCREMENT,charge_id INTEGER,action TEXT,from_status TEXT,to_status TEXT,actor_type TEXT,actor_id INTEGER,reason TEXT,snapshot_json TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $reminderMigration = require dirname(__DIR__, 2) . '/migrations/20260805020000_member_charge_reminders.php';
        $reminderMigration['up']($pdo);
        $adminMigration = require dirname(__DIR__, 2) . '/migrations/20260805030000_member_charge_reminder_admin.php';
        $adminMigration['up']($pdo);
        $pdo->exec("INSERT INTO verejni_uzivatele VALUES(1,'rodic@localhost.test','Testovací','Rodič',1,1)");
        $pdo->exec("INSERT INTO sportovci VALUES(10,'Anna','První')");
        $pdo->exec("INSERT INTO account_person_roles VALUES(1,1,10,'guardian','approved','2020-01-01',NULL)");
        return $pdo;
    }
}
