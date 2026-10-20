<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/transactional_notification_worker.php';
require_once dirname(__DIR__, 2) . '/includes/local_message_outbox.php';

final class TransactionalNotificationWorkerTest extends TestCase
{
    private array $keys;
    private string $outbox;

    protected function setUp(): void
    {
        $this->keys = ['keys' => ['v1' => random_bytes(32)], 'active_version' => 'v1', 'index_key' => random_bytes(32)];
        $this->outbox = sys_get_temp_dir() . '/evidence-worker-test-' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->outbox . '/*.json') ?: [] as $file) unlink($file);
        if (is_dir($this->outbox)) rmdir($this->outbox);
    }

    public function testStatusIsReadOnlyAggregatedAndDoesNotCreateOutbox(): void
    {
        $pdo = $this->database();
        $token = $this->verification($pdo);
        $this->event($pdo);
        $pdo->exec("UPDATE auth_mail_outbox SET created_at=datetime('now','-1 hour')");
        $pdo->exec("UPDATE club_event_notifications SET status='failed',last_error='private@example.test secret'");
        $before = $pdo->query('SELECT * FROM auth_mail_outbox')->fetchAll();
        $pdo->exec('PRAGMA query_only=ON');
        $status = \transactionalNotificationStatus($pdo, 'local-outbox', 'localhost', $this->outbox, $this->keys);
        self::assertTrue($status['ready']);
        self::assertTrue($status['read_only']);
        self::assertTrue($status['attention_required']);
        self::assertFalse($status['scheduler_verified']);
        self::assertFalse($status['mailbox_delivery_verified']);
        self::assertSame(1, $status['queues']['auth_mail_outbox']['counts']['pending']);
        self::assertSame(1, $status['queues']['auth_mail_outbox']['due']);
        self::assertGreaterThanOrEqual(3590, $status['queues']['auth_mail_outbox']['oldest_pending_age_seconds']);
        self::assertSame(1, $status['queues']['club_event_notifications']['counts']['failed']);
        self::assertSame($before, $pdo->query('SELECT * FROM auth_mail_outbox')->fetchAll());
        self::assertDirectoryDoesNotExist($this->outbox);
        $json = json_encode($status, JSON_THROW_ON_ERROR);
        foreach (['example.test', $token['token'], $token['hash'], 'recipient_email', 'secret', 'body_ciphertext', 'last_error'] as $private) {
            self::assertStringNotContainsString($private, $json);
        }
    }

    public function testReadinessDetectsMissingSchemaAndKeysWithoutMutating(): void
    {
        $pdo = $this->database();
        $this->verification($pdo);
        $pdo->exec("UPDATE auth_mail_outbox SET key_version='removed-key'");
        $pdo->exec('PRAGMA query_only=ON');
        $status = \transactionalNotificationStatus($pdo, 'mail', 'example.test', null, $this->keys);
        self::assertFalse($status['ready']);
        self::assertSame(1, $status['missing_queued_key_versions']);
        self::assertContains('queued_encryption_keys_unavailable', $status['issues']);
        $status = \transactionalNotificationStatus($pdo, 'mail', 'example.test', null, []);
        self::assertContains('encryption_configuration_unavailable', $status['issues']);

        $empty = new PDO('sqlite::memory:');
        $empty->exec('PRAGMA query_only=ON');
        $status = \transactionalNotificationStatus($empty, 'local-outbox', 'example.test', $this->outbox, $this->keys);
        self::assertFalse($status['ready']);
        self::assertFalse($status['queues']['auth_mail_outbox']['available']);
        self::assertFalse($status['queues']['club_event_notifications']['available']);
        self::assertContains('local_outbox_requires_localhost', $status['issues']);
        self::assertSame([], $empty->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll());
    }

    public function testBoundedDrainDeliversEncryptedAuthAndEventToLocalFiles(): void
    {
        $pdo = $this->database();
        $token = $this->verification($pdo);
        $this->event($pdo);
        $this->event($pdo);
        $sender = \localMessageOutboxSender('localhost', 'evidence.transactional-notification.v1', $this->outbox);
        $result = \transactionalNotificationDrain($pdo, $sender, 2, $this->keys);
        self::assertSame(['processed' => 2, 'sent' => 2, 'failed' => 0, 'cancelled' => 0, 'auth_processed' => 1, 'event_processed' => 1], $result);
        self::assertSame(1, (int)$pdo->query("SELECT COUNT(*) FROM club_event_notifications WHERE status='pending'")->fetchColumn());
        self::assertNull($pdo->query('SELECT body_ciphertext FROM auth_mail_outbox')->fetchColumn());
        $files = glob($this->outbox . '/*.json');
        self::assertCount(2, $files);
        $bodies = [];
        foreach ($files as $file) {
            $message = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame('parent@example.test', $message['original_recipient']);
            $bodies[] = $message['body'];
        }
        self::assertContains('LOCALHOST NEPLATIT verify ' . $token['token'], $bodies);
        self::assertContains('LOCALHOST NEPLATIT enrollment ready', $bodies);
        self::assertSame(1, \transactionalNotificationDrain($pdo, $sender, 2, $this->keys)['sent']);
        self::assertSame(0, \transactionalNotificationDrain($pdo, $sender, 2, $this->keys)['processed']);
    }

    public function testFailedTransportBackoffRetryAndStaleLinkCancellation(): void
    {
        $pdo = $this->database();
        $this->verification($pdo);
        self::assertSame(1, \transactionalNotificationDrain($pdo, static fn(): bool => false, 2, $this->keys)['failed']);
        self::assertSame(0, \transactionalNotificationDrain($pdo, static fn(): bool => false, 2, $this->keys)['processed']);
        $pdo->exec("UPDATE auth_mail_outbox SET available_at=datetime('now','-1 minute')");
        $sender = \localMessageOutboxSender('localhost', 'evidence.transactional-notification.v1', $this->outbox);
        self::assertSame(1, \transactionalNotificationDrain($pdo, $sender, 2, $this->keys)['sent']);
        self::assertSame(2, (int)$pdo->query('SELECT attempts FROM auth_mail_outbox WHERE id=1')->fetchColumn());
        $this->verification($pdo);
        $pdo->exec('UPDATE verejni_uzivatele SET email_overeno=1');
        self::assertSame(1, \transactionalNotificationDrain($pdo, $sender, 2, $this->keys)['cancelled']);
        self::assertCount(1, glob($this->outbox . '/*.json'));
        self::assertNull($pdo->query('SELECT body_ciphertext FROM auth_mail_outbox WHERE id=2')->fetchColumn());
    }

    public function testMissingSecondQueueCannotPartiallyClaimOrSendFirstQueue(): void
    {
        $pdo = $this->database();
        $this->verification($pdo);
        $pdo->exec('DROP TABLE club_event_notifications');
        try {
            \transactionalNotificationDrain($pdo, static function (): bool {
                self::fail('Transport must not be invoked with missing queue schema.');
            }, 2, $this->keys);
            self::fail('Missing schema must fail closed.');
        } catch (\PDOException) {
            self::assertSame('pending', $pdo->query('SELECT status FROM auth_mail_outbox')->fetchColumn());
            self::assertSame(0, (int)$pdo->query('SELECT attempts FROM auth_mail_outbox')->fetchColumn());
        }
    }

    private function verification(PDO $pdo): array
    {
        $token = \one_time_token_issue(\ONE_TIME_TOKEN_EMAIL_VERIFICATION, 3600);
        $pdo->prepare('UPDATE verejni_uzivatele SET verifikacni_token=?,verifikacni_token_expires_at=? WHERE id=1')
            ->execute([$token['hash'], $token['expires_at']]);
        $pdo->beginTransaction();
        \authMailEnqueue($pdo, 'verify', 'public', 1, $token['hash'], 'parent@example.test', 'LOCALHOST NEPLATIT',
            'LOCALHOST NEPLATIT verify ' . $token['token'], $this->keys);
        $pdo->commit();
        return $token;
    }

    private function event(PDO $pdo): void
    {
        $pdo->exec("INSERT INTO club_event_notifications(registration_id,registration_event_id,notification_type,recipient_email,recipient_name,subject_plain,body_plain)"
            . " VALUES(1,(SELECT COUNT(*)+1 FROM club_event_notifications),'synthetic','parent@example.test','LOCALHOST','NEPLATIT','LOCALHOST NEPLATIT enrollment ready')");
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,email TEXT,aktivni INTEGER,email_overeno INTEGER,verifikacni_token TEXT,verifikacni_token_expires_at TEXT)');
        $pdo->exec("INSERT INTO verejni_uzivatele VALUES(1,'parent@example.test',1,0,NULL,NULL)");
        foreach (['20261019100000_auth_mail_outbox', '20260803190000_club_event_notifications'] as $name) {
            $migration = require dirname(__DIR__, 2) . '/migrations/' . $name . '.php';
            $migration['up']($pdo);
        }
        return $pdo;
    }
}
