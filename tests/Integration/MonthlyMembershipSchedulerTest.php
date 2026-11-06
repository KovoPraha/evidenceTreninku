<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/monthly_membership_scheduler.php';

final class MonthlyMembershipSchedulerTest extends TestCase
{
    private string $directory;
    private array $paths;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/membership-scheduler-' . bin2hex(random_bytes(8));
        foreach (['kis.kovopraha.cz', 'data/.kis-deploy', 'CRON.1minuta'] as $path) {
            mkdir($this->directory . '/' . $path, 0700, true);
        }
        $this->paths = \membershipSchedulerPaths($this->directory . '/kis.kovopraha.cz', $this->directory . '/data/.kis-deploy');
    }

    protected function tearDown(): void
    {
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->directory);
    }

    public function testUtcBoundaryAndDstPreserveConfiguredTime(): void
    {
        foreach (['2026-11-06', '2026-03-29', '2026-10-25'] as $day) {
            $due = strtotime($day . ' 05:23:00 UTC');
            self::assertFalse(\membershipSchedulerDue([], $due - 1, null));
            self::assertTrue(\membershipSchedulerDue([], $due, null));
            self::assertFalse(\membershipSchedulerDue([], $due + 60, $due));
            self::assertTrue(\membershipSchedulerDue([], $due + 3600, $due - 86400));
            self::assertFalse(\membershipSchedulerDue([], $due + 86400 - 1, $due));
            self::assertTrue(\membershipSchedulerDue([], $due + 86400, $due));
        }
    }

    public function testOnlyExplicitProductionIdentityIsAccepted(): void
    {
        self::assertTrue(\membershipSchedulerIdentity('https://kis.kovopraha.cz/', false));
        foreach ([null, '', 'http://kis.kovopraha.cz', 'https://kis.kovopraha.cz.evil', 'http://localhost/evidencePavel'] as $url) {
            self::assertFalse(\membershipSchedulerIdentity($url, false));
        }
        self::assertFalse(\membershipSchedulerIdentity('https://kis.kovopraha.cz', true));
        self::assertFalse(\membershipSchedulerIdentity('https://kis.kovopraha.cz', null));
    }

    public function testFutureSuccessIsRejected(): void
    {
        $this->expectException(\RuntimeException::class);
        \membershipSchedulerDue([], 1000, 1001);
    }

    public function testInstallIsIdempotentAndDoesNotManufactureHeartbeat(): void
    {
        $before = \membershipSchedulerStatus($this->paths, null);
        self::assertFalse($before['installed_expected_source']);
        self::assertSame(['installed' => true, 'changed' => true], \membershipSchedulerInstall($this->paths));
        self::assertSame(['installed' => true, 'changed' => false], \membershipSchedulerInstall($this->paths));
        $after = \membershipSchedulerStatus($this->paths, null);
        self::assertTrue($after['installed_expected_source']);
        self::assertFalse($after['recent_hosting_tick']);
        self::assertFalse(is_file($this->paths['state']));
        $source = file_get_contents($this->paths['cron']);
        self::assertStringContainsString("MEMBERSHIP_SCHEDULER_TRIGGER=hosting_cron", $source);
        self::assertStringNotContainsString('MONTHLY_MEMBERSHIP_MAIL_ENABLED', $source);
    }

    public function testForeignCronAndMissingDirectoryAreNotReplaced(): void
    {
        file_put_contents($this->paths['cron'], 'foreign');
        try {
            \membershipSchedulerInstall($this->paths);
            self::fail('Foreign job replaced');
        } catch (\RuntimeException) {
            self::assertSame('foreign', file_get_contents($this->paths['cron']));
        }
        unlink($this->paths['cron']);
        rmdir(dirname($this->paths['cron']));
        $this->expectException(\RuntimeException::class);
        \membershipSchedulerInstall($this->paths);
    }

    public function testActualTickOncePerDayAndFreshnessWithoutStatusMutation(): void
    {
        \membershipSchedulerInstall($this->paths);
        $due = strtotime('2026-11-06 05:23:00 UTC');
        $success = null;
        $calls = 0;
        $last = static function () use (&$success): ?int {
            return $success;
        };
        $run = static function () use (&$success, &$calls, $due): array {
            $calls++;
            $success = $due;
            return ['errors' => [], 'sent' => 0];
        };
        self::assertSame(['outcome' => 'not_due'], \membershipSchedulerTick($this->paths, $last, $run, $due - 1));
        self::assertSame(['outcome' => 'completed'], \membershipSchedulerTick($this->paths, $last, $run, $due));
        self::assertSame(['outcome' => 'not_due'], \membershipSchedulerTick($this->paths, $last, $run, $due + 60));
        self::assertSame(1, $calls);
        $before = file_get_contents($this->paths['state']);
        self::assertTrue(\membershipSchedulerStatus($this->paths, $success, $due + 60)['recent_hosting_tick']);
        self::assertFalse(\membershipSchedulerStatus($this->paths, $success, $due + 241)['recent_hosting_tick']);
        self::assertSame($before, file_get_contents($this->paths['state']));
    }

    public function testFailureCrashGateAndDisabledFlags(): void
    {
        $due = strtotime('2026-11-06 05:23:00 UTC');
        $last = static fn (): ?int => null;
        $run = static function (): array {
            throw new \RuntimeException('secret-provider-body');
        };
        self::assertSame(['outcome' => 'failed'], \membershipSchedulerTick($this->paths, $last, $run, $due));
        self::assertSame(['outcome' => 'not_due'], \membershipSchedulerTick($this->paths, $last, $run, $due + 599));
        self::assertSame(['outcome' => 'failed'], \membershipSchedulerTick($this->paths, $last, $run, $due + 600));
        self::assertStringNotContainsString('secret-provider-body', file_get_contents($this->paths['state']));
        $state = \membershipSchedulerRead($this->paths);
        $state['outcome'] = 'running';
        $state['last_attempt_at'] = $due + 1200;
        \membershipSchedulerWrite($this->paths, $state);
        self::assertFalse(\membershipSchedulerDue($state, $due + 1799, null));
        self::assertTrue(\membershipSchedulerDue($state, $due + 1800, null));
        self::assertSame(['outcome' => 'disabled'], \membershipSchedulerTick($this->paths, $last, $run, $due + 1800, false));
    }

    public function testExistingManualSuccessAndConcurrencyDoNotRunAgain(): void
    {
        $due = strtotime('2026-11-06 05:23:00 UTC');
        $run = static function (): array {
            self::fail('Unexpected processing');
        };
        self::assertSame(['outcome' => 'not_due'], \membershipSchedulerTick($this->paths, static fn (): int => $due + 60, $run, $due + 120));
        $lock = fopen($this->paths['lock'], 'c');
        flock($lock, LOCK_EX);
        try {
            self::assertSame(['skipped' => 'already_running'], \membershipSchedulerTick($this->paths, static fn (): ?int => null, $run, $due));
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function testCorruptStateAndUnexpectedScopeFailClosed(): void
    {
        file_put_contents($this->paths['state'], '{}');
        try {
            \membershipSchedulerRead($this->paths);
            self::fail('Invalid state accepted');
        } catch (\RuntimeException) {
            self::assertSame('{}', file_get_contents($this->paths['state']));
        }
        $this->expectException(\RuntimeException::class);
        \membershipSchedulerPaths($this->directory . '/wrong', $this->paths['operations']);
    }

    public function testSymlinkTargetsAreNeverOverwritten(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            self::markTestSkipped('Unix hosting symlink boundary; Windows developer mode is not required.');
        }
        $foreign = $this->directory . '/foreign';
        file_put_contents($foreign, 'preserve');
        foreach (['cron', 'state', 'lock'] as $key) {
            if (file_exists($this->paths[$key])) {
                unlink($this->paths[$key]);
            }
            symlink($foreign, $this->paths[$key]);
            try {
                if ($key === 'state') {
                    \membershipSchedulerWrite($this->paths, []);
                } else {
                    \membershipSchedulerInstall($this->paths);
                }
                self::fail('Symlink accepted');
            } catch (\RuntimeException) {
                self::assertSame('preserve', file_get_contents($foreign));
            } finally {
                unlink($this->paths[$key]);
            }
        }
    }
}
