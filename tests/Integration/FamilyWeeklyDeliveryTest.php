<?php
declare(strict_types=1);

namespace Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/family_weekly_delivery.php';

final class FamilyWeeklyDeliveryTest extends TestCase
{
    public function testExplicitPreferenceAndOptOutCancelUnsentSummary(): void
    {
        $pdo = $this->database();
        self::assertSame(['enabled' => false], \familyWeeklyDeliveryPreference($pdo, 1));
        self::assertTrue(\familyWeeklyDeliverySavePreference($pdo, 1, true)['changed']);
        self::assertFalse(\familyWeeklyDeliverySavePreference($pdo, 1, true)['changed']);
        \familyWeeklyDeliveryGenerate($pdo, $this->today(), true, $this->previewFactory());
        self::assertSame(1, (int)$pdo->query("SELECT COUNT(*) FROM family_weekly_summaries WHERE status='pending'")->fetchColumn());

        $result = \familyWeeklyDeliverySavePreference($pdo, 1, false);
        self::assertSame(1, $result['cancelled']);
        self::assertSame('cancelled', $pdo->query('SELECT status FROM family_weekly_summaries')->fetchColumn());
        self::assertSame(
            ['preference_change', 'enqueue', 'preference_change', 'cancel_opt_out'],
            $pdo->query('SELECT action FROM family_weekly_summary_events ORDER BY id')->fetchAll(PDO::FETCH_COLUMN)
        );
    }

    public function testMondayGateIdempotencyAndAccountIsolation(): void
    {
        $pdo = $this->database();
        \familyWeeklyDeliverySavePreference($pdo, 1, true);
        $notDue = \familyWeeklyDeliveryGenerate($pdo, $this->today(), false, $this->previewFactory());
        self::assertFalse($notDue['due']);
        self::assertSame(0, $notDue['queued']);

        $first = \familyWeeklyDeliveryGenerate($pdo, $this->today(), true, $this->previewFactory());
        $second = \familyWeeklyDeliveryGenerate($pdo, $this->today(), true, $this->previewFactory());
        self::assertSame(1, $first['queued']);
        self::assertSame(1, $second['existing']);
        self::assertSame(1, (int)$pdo->query('SELECT account_id FROM family_weekly_summaries')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM family_weekly_summaries WHERE account_id=2')->fetchColumn());
    }

    public function testWorkerStoresSnapshotAndLocalOutboxNeverAcceptsProductionHost(): void
    {
        $pdo = $this->database();
        \familyWeeklyDeliverySavePreference($pdo, 1, true);
        \familyWeeklyDeliveryGenerate($pdo, $this->today(), true, $this->previewFactory());
        $captured = [];
        self::assertTrue(\familyWeeklyDeliveryProcessOne($pdo, static function (string $email, string $subject, string $body) use (&$captured): bool {
            $captured = compact('email', 'subject', 'body');
            return true;
        }));
        self::assertSame('rodic@example.test', $captured['email']);
        self::assertSame('Rodinný program 05.–11. 08. 2026', $captured['subject']);
        self::assertSame('sent', $pdo->query('SELECT status FROM family_weekly_summaries')->fetchColumn());
        self::assertNull(\familyWeeklyDeliveryProcessOne($pdo, static fn (): bool => true));

        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'weekly-summary-' . bin2hex(random_bytes(5));
        try {
            $sender = \familyWeeklyDeliveryLocalOutboxSender('localhost', $directory);
            self::assertTrue($sender('test@example.test', 'Souhrn', 'Bez síťového transportu'));
            $files = glob($directory . DIRECTORY_SEPARATOR . '*.json') ?: [];
            self::assertCount(1, $files);
            $payload = json_decode((string)file_get_contents($files[0]), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame('family-weekly-summary-local-outbox-v1', $payload['schema']);
        } finally {
            foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) unlink($file);
            if (is_dir($directory)) rmdir($directory);
        }

        $this->expectException(\FamilyWeeklyDeliveryException::class);
        \familyWeeklyDeliveryLocalOutboxSender('data.kovopraha.cz', $directory);
    }

    public function testOptOutWinsWhenItOccursAfterWorkerClaim(): void
    {
        $pdo = $this->database();
        \familyWeeklyDeliverySavePreference($pdo, 1, true);
        \familyWeeklyDeliveryGenerate($pdo, $this->today(), true, $this->previewFactory());

        $result = \familyWeeklyDeliveryProcessOne($pdo, static function () use ($pdo): bool {
            \familyWeeklyDeliverySavePreference($pdo, 1, false);
            return true;
        });

        self::assertFalse($result);
        self::assertSame('cancelled', $pdo->query('SELECT status FROM family_weekly_summaries')->fetchColumn());
        self::assertSame(1, (int)$pdo->query("SELECT COUNT(*) FROM family_weekly_summary_events WHERE action='cancel_opt_out'")->fetchColumn());
    }

    /** @return callable(PDO,int,string):array<string,mixed> */
    private function previewFactory(): callable
    {
        return static fn (PDO $pdo, int $accountId, string $from): array => [
            'to' => '2026-08-11',
            'subject' => 'Rodinný program 05.–11. 08. 2026',
            'body' => 'Bezpečný souhrn účtu ' . $accountId . ' od ' . $from,
            'items' => [['id' => 1]],
            'counts' => ['total' => 1],
        ];
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
        $migration = require dirname(__DIR__, 2) . '/migrations/20260805040000_family_weekly_summaries.php';
        $migration['up']($pdo);
        $migration['up']($pdo);
        self::assertTrue($migration['verify']($pdo));
        $pdo->exec("INSERT INTO verejni_uzivatele VALUES(1,'rodic@example.test','Testovací','Rodič',1,1),(2,'cizi@example.test','Cizí','Rodič',1,1)");
        return $pdo;
    }
}
