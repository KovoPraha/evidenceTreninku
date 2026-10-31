<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/trainer_reset_scheduler.php';

final class TrainerResetSchedulerTest extends TestCase
{
    private string $directory;
    private array $paths;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/trainer-scheduler-' . bin2hex(random_bytes(8));
        foreach (['kis.kovopraha.cz', 'data/.kis-deploy', 'CRON.1minuta'] as $path) {
            mkdir($this->directory . '/' . $path, 0700, true);
        }
        $this->paths = \trainerResetSchedulerPaths($this->directory . '/kis.kovopraha.cz', $this->directory . '/data/.kis-deploy');
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
        self::assertFalse(\trainerResetSchedulerStatus($this->paths)['installed_expected_source']);
        self::assertSame(['installed' => true, 'changed' => true], \trainerResetSchedulerInstall($this->paths));
        self::assertSame(['installed' => true, 'changed' => false], \trainerResetSchedulerInstall($this->paths));
        $source = (string)file_get_contents($this->paths['cron']);
        self::assertStringContainsString('TRAINER_RESET_TRIGGER=hosting_cron', $source);
        self::assertStringContainsString("PHP_SAPI !== 'cli'", $source);
        self::assertStringContainsString('TRAINER_RESET_ACTION=process', $source);
        self::assertFalse(\trainerResetSchedulerStatus($this->paths)['recent_successful_hosting_run']);
        \trainerResetSchedulerHeartbeat($this->paths, ['processed' => 0, 'sent' => 0], true);
        self::assertTrue(\trainerResetSchedulerStatus($this->paths)['recent_successful_hosting_run']);
        \trainerResetSchedulerHeartbeat($this->paths, ['error' => 'worker_failed'], false);
        self::assertFalse(\trainerResetSchedulerStatus($this->paths)['recent_successful_hosting_run']);
    }

    public function testUnrelatedCronIsNeverOverwritten(): void
    {
        file_put_contents($this->paths['cron'], 'Existing unrelated job');
        try {
            \trainerResetSchedulerInstall($this->paths);
            self::fail('Existing different job must be preserved');
        } catch (\RuntimeException) {
            self::assertSame('Existing unrelated job', file_get_contents($this->paths['cron']));
        }
    }

    public function testStatusDoesNotWriteAndOldHeartbeatIsNotCurrent(): void
    {
        $before = scandir($this->paths['operations']);
        \trainerResetSchedulerStatus($this->paths);
        self::assertSame($before, scandir($this->paths['operations']));
        \trainerResetSchedulerInstall($this->paths);
        file_put_contents($this->paths['heartbeat'], json_encode([
            'trigger' => 'hosting_cron', 'ok' => true, 'completed_at' => gmdate(DATE_ATOM, time() - 600),
        ], JSON_THROW_ON_ERROR));
        self::assertFalse(\trainerResetSchedulerStatus($this->paths)['recent_successful_hosting_run']);
        file_put_contents($this->paths['heartbeat'], json_encode([
            'trigger' => 'github', 'ok' => true, 'completed_at' => gmdate(DATE_ATOM),
        ], JSON_THROW_ON_ERROR));
        self::assertFalse(\trainerResetSchedulerStatus($this->paths)['recent_successful_hosting_run']);
    }

    public function testInstallerDoesNotCreateMissingHostingDirectory(): void
    {
        rmdir(dirname($this->paths['cron']));
        $this->expectException(\RuntimeException::class);
        \trainerResetSchedulerInstall($this->paths);
    }

    public function testOperationsMustStayInTheSameHostingAccount(): void
    {
        self::assertTrue(\trainerResetSchedulerIdentity('kis.kovopraha.cz', 'https://kis.kovopraha.cz'));
        self::assertFalse(\trainerResetSchedulerIdentity('kis.kovopraha.cz', 'http://localhost'));
        self::assertFalse(\trainerResetSchedulerIdentity('velocota.com', 'https://kis.kovopraha.cz'));
        $this->expectException(\RuntimeException::class);
        \trainerResetSchedulerPaths($this->paths['root'], sys_get_temp_dir());
    }
}
