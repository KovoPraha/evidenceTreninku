<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2).'/bin/fio-production-operations.php';
require_once dirname(__DIR__, 2).'/includes/fio_readonly_import.php';

final class FioProductionOperationsTest extends TestCase
{
    private string $directory;
    private array $paths;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/fio-hosting-'.bin2hex(random_bytes(6));
        foreach (['kis.kovopraha.cz', 'data/.kis-deploy', 'CRON.1minuta'] as $path) {
            mkdir($this->directory.'/'.$path, 0700, true);
        }
        $this->paths = \kisFioHostingPaths($this->directory.'/kis.kovopraha.cz', $this->directory.'/data/.kis-deploy');
    }

    protected function tearDown(): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            if ($item->isDir() && !$item->isLink()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($this->directory);
    }

    public function testInstallPreservesUnrelatedJobsAndStatusCannotClaimARun(): void
    {
        $other = dirname($this->paths['cron']).'/evidence-trainer-password-reset.php';
        file_put_contents($other, 'LOCAL unrelated job');
        $before = scandir($this->paths['operations']);
        self::assertFalse(\kisFioHostingStatus($this->paths)['recent_hosting_tick']);
        self::assertSame($before, scandir($this->paths['operations']));
        self::assertTrue(\kisFioHostingInstall($this->paths)['changed']);
        self::assertFalse(\kisFioHostingInstall($this->paths)['changed']);
        self::assertTrue(\kisFioHostingStatus($this->paths)['installed_expected_source']);
        self::assertFalse(\kisFioHostingStatus($this->paths)['recent_successful_import']);
        self::assertSame('LOCAL unrelated job', file_get_contents($other));
        self::assertStringContainsString('FIO_HOSTING_TRIGGER=hosting_cron', (string)file_get_contents($this->paths['cron']));
        if (PHP_OS_FAMILY !== 'Windows') {
            self::assertSame(0700, fileperms($this->paths['cron']) & 0777);
            self::assertSame(0700, fileperms($this->paths['worker']) & 0777);
        }
    }

    public function testTicksRespectTenMinutesAndKeepLastActualImportSeparate(): void
    {
        \kisFioHostingInstall($this->paths);
        $calls = 0;
        $import = static function () use (&$calls): array {
            $calls++;
            return ['inserted' => 0, 'duplicates' => 4, 'automatic' => ['confirmed' => 0]];
        };
        $now = time();
        self::assertTrue(\kisFioHostingTick($this->paths, $import, $now)['ok']);
        self::assertSame(['skipped' => 'not_due'], \kisFioHostingTick($this->paths, $import, $now + 60));
        self::assertSame(['skipped' => 'not_due'], \kisFioHostingTick($this->paths, $import, $now + 599));
        $state = \kisFioHostingStatus($this->paths, $now + 599);
        self::assertSame($now, $state['state']['last_run']['completed_at']);
        self::assertSame(3, $state['state']['ticks']);
        self::assertTrue($state['recent_hosting_tick']);
        self::assertTrue($state['recent_successful_import']);
        self::assertTrue(\kisFioHostingTick($this->paths, $import, $now + 600)['ok']);
        self::assertSame(2, $calls);
        self::assertFalse(\kisFioHostingStatus($this->paths, $now + 1600)['recent_successful_import']);
        self::assertFalse(\kisFioHostingStatus($this->paths, $now + 500)['recent_hosting_tick']);
    }

    public function testFailedAttemptIsNotRetriedEachMinuteOrLeakedInHeartbeat(): void
    {
        $calls = 0;
        $import = static function () use (&$calls): array {
            $calls++;
            throw new \RuntimeException('LOCAL private provider body must not escape');
        };
        $now = time();
        self::assertFalse(\kisFioHostingTick($this->paths, $import, $now)['ok']);
        self::assertSame(['skipped' => 'not_due'], \kisFioHostingTick($this->paths, $import, $now + 60));
        self::assertSame(1, $calls);
        self::assertStringNotContainsString('private provider', (string)file_get_contents($this->paths['state']));
        self::assertFalse(\kisFioHostingStatus($this->paths, $now + 60)['recent_successful_import']);
    }

    public function testConcurrentTickCannotFetchAndDoesNotFabricateHeartbeat(): void
    {
        $lock = fopen($this->paths['lock'], 'c');
        flock($lock, LOCK_EX);
        try {
            $result = \kisFioHostingTick($this->paths, static function (): array {
                self::fail('Concurrent worker must not fetch');
            });
            self::assertSame(['skipped' => 'worker_already_running'], $result);
            self::assertFileDoesNotExist($this->paths['state']);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function testInterruptedNewAttemptCannotReusePreviousSuccessAsCurrentHealth(): void
    {
        \kisFioHostingInstall($this->paths);
        $now = time();
        \kisFioHostingTick($this->paths, static fn (): array => ['duplicates' => 1], $now);
        $state = \kisFioHostingRead($this->paths);
        $state['last_attempt_at'] = $now + 600;
        $state['last_tick_at'] = $now + 600;
        $state['last_tick_outcome'] = 'running';
        \kisFioHostingWrite($this->paths, $state);
        self::assertTrue(\kisFioHostingStatus($this->paths, $now + 600)['recent_hosting_tick']);
        self::assertFalse(\kisFioHostingStatus($this->paths, $now + 600)['recent_successful_import']);
        \kisFioHostingTick($this->paths, static function (): array {
            self::fail('Interrupted attempt retains the rate gate');
        }, $now + 660);
        self::assertFalse(\kisFioHostingStatus($this->paths, $now + 660)['recent_successful_import']);
    }

    public function testForeignFilePreventsAnyPartialInstallation(): void
    {
        file_put_contents($this->paths['cron'], 'LOCAL foreign file');
        try {
            \kisFioHostingInstall($this->paths);
            self::fail('Must refuse foreign cron');
        } catch (\RuntimeException) {
            self::assertFileDoesNotExist($this->paths['worker']);
            self::assertSame('LOCAL foreign file', file_get_contents($this->paths['cron']));
        }
    }

    public function testMissingHostingDirectoryIsNotCreated(): void
    {
        rmdir(dirname($this->paths['cron']));
        $this->expectException(\RuntimeException::class);
        \kisFioHostingInstall($this->paths);
    }

    public function testChangedSourceOrForeignTriggerCannotProveHostingHealth(): void
    {
        \kisFioHostingInstall($this->paths);
        \kisFioHostingTick($this->paths, static fn (): array => ['duplicates' => 1]);
        self::assertTrue(\kisFioHostingStatus($this->paths)['recent_successful_import']);
        file_put_contents($this->paths['worker'], 'LOCAL changed worker');
        self::assertFalse(\kisFioHostingStatus($this->paths)['recent_successful_import']);
        $state = \kisFioHostingRead($this->paths);
        $state['trigger'] = 'manual';
        \kisFioHostingWrite($this->paths, $state);
        $this->expectException(\RuntimeException::class);
        \kisFioHostingTick($this->paths, static function (): array {
            self::fail('Invalid trigger state must not fetch');
        });
    }

    public function testOtherHostingAccountIsRefused(): void
    {
        self::assertTrue(\kisFioHostingIdentity('https://kis.kovopraha.cz/', false));
        self::assertFalse(\kisFioHostingIdentity('https://kis.kovopraha.cz', true));
        self::assertFalse(\kisFioHostingIdentity('http://localhost', false));
        self::assertFalse(\kisFioHostingIdentity(null, false));
        $this->expectException(\RuntimeException::class);
        \kisFioHostingPaths($this->paths['root'], sys_get_temp_dir());
    }

    public function testReviewAuditClassifiesEveryItemWithoutChangingOrExportingPrivateFields(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE fio_import_runs(id INT,source_account_iban TEXT);'
            .'CREATE TABLE fio_account_movements(id INT,import_run_id INT,amount_minor INT,currency TEXT,variable_symbol TEXT,booked_on TEXT,match_status TEXT);'
            .'CREATE TABLE payments(id INT,payable_type TEXT,payable_id INT,method TEXT,status TEXT,amount_minor INT,currency TEXT,variable_symbol TEXT,created_at TEXT);'
            .'CREATE TABLE shop_orders(id INT,status TEXT,payment_status TEXT)');
        $pdo->exec("INSERT INTO fio_import_runs VALUES(1,'LOCALBANK'),(2,'OTHERBANK');"
            ."INSERT INTO payments VALUES(1,'shop_order',1,'bank_transfer','pending',100,'CZK','0000000789','2026-11-05 10:00:00');"
            ."INSERT INTO shop_orders VALUES(1,'placed','pending');"
            ."INSERT INTO fio_account_movements VALUES(1,1,100,'CZK',NULL,'2026-11-04','review_missing_vs'),"
            ."(2,1,100,'CZK','0000000999','2026-11-04','review_unknown_vs'),"
            ."(3,1,100,'CZK','0000000789','2026-11-04','review_state'),"
            ."(4,2,100,'CZK',NULL,'2026-11-04','review_missing_vs')");
        $before = $pdo->query('SELECT * FROM fio_account_movements ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $report = \kisFioReviewAudit($pdo, 'LOCALBANK');
        self::assertSame(3, $report['reviewed']);
        self::assertSame(['review_missing_vs' => 1, 'review_unknown_vs' => 1, 'proposed_exact' => 1], $report['classifications']);
        self::assertSame(1, $report['items'][2]['vs_payment_count']);
        self::assertSame(1, $report['items'][2]['pending_created_after_booking_day']);
        self::assertSame(1, $report['items'][0]['same_amount_pending_count']);
        self::assertSame($before, $pdo->query('SELECT * FROM fio_account_movements ORDER BY id')->fetchAll(PDO::FETCH_ASSOC));
        self::assertSame('pending', $pdo->query('SELECT status FROM payments')->fetchColumn());
        $json = json_encode($report, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('0000000789', $json);
        self::assertStringNotContainsString('LOCALBANK', $json);
        self::assertArrayNotHasKey('amount_minor', $report['items'][0]);
    }
}
