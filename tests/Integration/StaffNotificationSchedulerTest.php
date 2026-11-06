<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/staff_notification_scheduler.php';

final class StaffNotificationSchedulerTest extends TestCase
{
    private string $directory;
    private array $paths;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/staff-notification-scheduler-' . bin2hex(random_bytes(8));
        foreach (['kis.kovopraha.cz', 'data/.kis-deploy', 'CRON.1minuta'] as $path) {
            mkdir($this->directory . '/' . $path, 0700, true);
        }
        $this->paths = \staffNotificationSchedulerPaths($this->directory . '/kis.kovopraha.cz', $this->directory . '/data/.kis-deploy');
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

    public function testInstallIsIdempotentAndRequiresActualHostingHeartbeat(): void
    {
        self::assertFalse(\staffNotificationSchedulerStatus($this->paths)['installed_expected_source']);
        self::assertSame(['installed' => true, 'changed' => true], \staffNotificationSchedulerInstall($this->paths));
        self::assertSame(['installed' => true, 'changed' => false], \staffNotificationSchedulerInstall($this->paths));
        if (PHP_OS_FAMILY !== 'Windows') {
            self::assertSame(0700, fileperms($this->paths['cron']) & 0777);
            self::assertTrue(\staffNotificationSchedulerStatus($this->paths)['executable']);
        }
        $source = (string)file_get_contents($this->paths['cron']);
        self::assertStringContainsString('STAFF_NOTIFICATION_TRIGGER=hosting_cron', $source);
        self::assertStringContainsString("PHP_SAPI !== 'cli'", $source);
        self::assertStringContainsString('STAFF_NOTIFICATION_ACTION=process', $source);
        self::assertFalse(\staffNotificationSchedulerStatus($this->paths)['recent_successful_hosting_run']);
        \staffNotificationSchedulerHeartbeat($this->paths, ['processed' => 0, 'sent' => 0], true);
        self::assertTrue(\staffNotificationSchedulerStatus($this->paths)['recent_successful_hosting_run']);
        \staffNotificationSchedulerHeartbeat($this->paths, ['error' => 'worker_failed'], false);
        self::assertFalse(\staffNotificationSchedulerStatus($this->paths)['recent_successful_hosting_run']);
    }

    public function testUnrelatedCronIsNeverOverwritten(): void
    {
        file_put_contents($this->paths['cron'], 'Existing unrelated job');
        try {
            \staffNotificationSchedulerInstall($this->paths);
            self::fail('Existing different job must be preserved');
        } catch (\RuntimeException) {
            self::assertSame('Existing unrelated job', file_get_contents($this->paths['cron']));
        }
    }

    public function testStatusDoesNotWriteAndOldHeartbeatIsNotCurrent(): void
    {
        $before = scandir($this->paths['operations']);
        \staffNotificationSchedulerStatus($this->paths);
        self::assertSame($before, scandir($this->paths['operations']));
        \staffNotificationSchedulerInstall($this->paths);
        file_put_contents($this->paths['heartbeat'], json_encode([
            'trigger' => 'hosting_cron', 'ok' => true, 'completed_at' => gmdate(DATE_ATOM, time() - 600),
        ], JSON_THROW_ON_ERROR));
        self::assertFalse(\staffNotificationSchedulerStatus($this->paths)['recent_successful_hosting_run']);
        file_put_contents($this->paths['heartbeat'], json_encode([
            'trigger' => 'github', 'ok' => true, 'completed_at' => gmdate(DATE_ATOM),
        ], JSON_THROW_ON_ERROR));
        self::assertFalse(\staffNotificationSchedulerStatus($this->paths)['recent_successful_hosting_run']);
    }

    public function testInstallerDoesNotCreateMissingHostingDirectory(): void
    {
        rmdir(dirname($this->paths['cron']));
        $this->expectException(\RuntimeException::class);
        \staffNotificationSchedulerInstall($this->paths);
    }

    public function testOperationsMustStayInTheSameHostingAccount(): void
    {
        self::assertTrue(\staffNotificationSchedulerIdentity('kis.kovopraha.cz', 'https://kis.kovopraha.cz'));
        self::assertFalse(\staffNotificationSchedulerIdentity('kis.kovopraha.cz', 'http://localhost'));
        self::assertFalse(\staffNotificationSchedulerIdentity('velocota.com', 'https://kis.kovopraha.cz'));
        $this->expectException(\RuntimeException::class);
        \staffNotificationSchedulerPaths($this->paths['root'], sys_get_temp_dir());
    }
}
