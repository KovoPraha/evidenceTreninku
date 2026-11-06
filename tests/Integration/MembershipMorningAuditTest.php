<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/membership_morning_audit.php';

final class MembershipMorningAuditTest extends TestCase
{
    private string $directory;
    private array $paths;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/membership-observer-' . bin2hex(random_bytes(8));
        foreach (['kis.kovopraha.cz', 'data/.kis-deploy', 'CRON.1minuta'] as $name) {
            mkdir($this->directory . '/' . $name, 0700, true);
        }
        $this->paths = \membershipMorningPaths($this->directory . '/kis.kovopraha.cz', $this->directory . '/data/.kis-deploy');
    }

    protected function tearDown(): void
    {
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->directory);
    }

    private function membershipStatus(int $delay = 2): array
    {
        $attempt = strtotime('2026-11-07 05:23:00 UTC') + $delay;
        return ['installed_expected_source' => true, 'recent_hosting_tick' => true,
            'completed_today' => true, 'daily_due' => false, 'automation_enabled' => true, 'mail_enabled' => true,
            'last_success_utc' => gmdate(DATE_ATOM, $attempt + 1),
            'state' => ['trigger' => 'hosting_cron', 'last_attempt_at' => $attempt,
                'result' => ['apply' => true, 'errors' => [], 'memberships' => 0, 'charges' => 0, 'documents' => 0, 'queued' => 0, 'sent' => 0]]];
    }

    private function jobs(int $delay = 2): array
    {
        return [['started_at' => gmdate('Y-m-d H:i:s', strtotime('2026-11-07 05:23:00 UTC') + $delay),
            'finished_at' => gmdate('Y-m-d H:i:s', strtotime('2026-11-07 05:23:00 UTC') + $delay + 1), 'status' => 'success']];
    }

    public function testTimelyEmptyRunHasRealAttemptAndLedgerEvidence(): void
    {
        $result = \membershipMorningEvaluate($this->membershipStatus(), $this->jobs(), strtotime('2026-11-07 05:30:02 UTC'));
        self::assertSame('PASS', $result['verdict']);
        self::assertSame(2, $result['start_delay_seconds']);
        self::assertSame(0, $result['counts']['sent']);
        self::assertFalse($result['first_attempt_limitation']);
    }

    public function testHeartbeatOrManualCompletionCannotProveTimeliness(): void
    {
        $status = $this->membershipStatus();
        unset($status['state']['last_attempt_at']);
        $status['state']['last_tick_at'] = strtotime('2026-11-07 05:30:00 UTC');
        self::assertSame('FAIL', \membershipMorningEvaluate($status, $this->jobs(), strtotime('2026-11-07 05:30:02 UTC'))['verdict']);
        $status = $this->membershipStatus();
        $status['state']['trigger'] = 'manual';
        self::assertSame('FAIL', \membershipMorningEvaluate($status, $this->jobs(), strtotime('2026-11-07 05:30:02 UTC'))['verdict']);
        self::assertSame('FAIL', \membershipMorningEvaluate($this->membershipStatus(), [], strtotime('2026-11-07 05:30:02 UTC'))['verdict']);
    }

    public function testDelayUsesLedgerStartRatherThanEarlierGateTimestamp(): void
    {
        $status = $this->membershipStatus(59);
        $status['last_success_utc'] = '2026-11-07T05:24:02+00:00';
        $report = \membershipMorningEvaluate($status, $this->jobs(61), strtotime('2026-11-07 05:30:02 UTC'));
        self::assertSame('LATE', $report['verdict']);
        self::assertSame(61, $report['start_delay_seconds']);
    }

    public function testLateRetryFailureAndWrongDayRemainHonest(): void
    {
        $now = strtotime('2026-11-07 06:30:00 UTC');
        self::assertSame('LATE', \membershipMorningEvaluate($this->membershipStatus(61), $this->jobs(61), $now)['verdict']);
        $jobs = array_merge($this->jobs(602), [['started_at' => '2026-11-07 05:23:02', 'status' => 'failed']]);
        $report = \membershipMorningEvaluate($this->membershipStatus(602), $jobs, $now);
        self::assertSame('FAIL', $report['verdict']);
        self::assertTrue($report['first_attempt_limitation']);
        self::assertSame('BLOCKED', \membershipMorningEvaluate($this->membershipStatus(), $this->jobs(), strtotime('2026-11-08 05:30:00 UTC'))['verdict']);
        self::assertSame('BLOCKED', \membershipMorningEvaluate($this->membershipStatus(), $this->jobs(), strtotime('2026-11-07 05:29:59 UTC'))['verdict']);
    }

    public function testWaitCaptureImmutabilityAndSelfRemoval(): void
    {
        $due = strtotime('2026-11-07 05:30:00 UTC');
        $calls = 0;
        $capture = function (int $now) use (&$calls): array {
            $calls++;
            return \membershipMorningEvaluate($this->membershipStatus(), $this->jobs(), $now);
        };
        self::assertTrue(\membershipMorningOperate($this->paths, true, $capture)['installed']);
        self::assertFalse(\membershipMorningOperate($this->paths, true, $capture)['changed']);
        self::assertNull(\membershipMorningStatus($this->paths)['heartbeat']);
        self::assertSame(['phase' => 'waiting'], \membershipMorningOperate($this->paths, false, $capture, $due - 1));
        self::assertSame(0, $calls);
        self::assertSame(['phase' => 'captured'], \membershipMorningOperate($this->paths, false, $capture, $due));
        self::assertSame(1, $calls);
        self::assertFileDoesNotExist($this->paths['audit_cron']);
        self::assertFileExists($this->paths['note']);
        self::assertFileExists($this->paths['handoff']);
        $saved = file_get_contents($this->paths['report']);
        self::assertSame(['completed' => true, 'installed' => false], \membershipMorningOperate($this->paths, true, $capture, $due + 60));
        self::assertSame($saved, file_get_contents($this->paths['report']));
        self::assertSame(1, $calls);
    }

    public function testCrashAfterCaptureRepairsNotesWithoutRecapture(): void
    {
        $due = strtotime('2026-11-07 05:30:00 UTC');
        $never = static function (): array {
            self::fail('Recapture attempted');
        };
        \membershipMorningOperate($this->paths, true, $never);
        $report = json_encode(\membershipMorningEvaluate($this->membershipStatus(), $this->jobs(), $due), JSON_THROW_ON_ERROR);
        file_put_contents($this->paths['report'], $report);
        \membershipMorningOperate($this->paths, false, $never, $due + 60);
        self::assertSame($report, file_get_contents($this->paths['report']));
        self::assertFileExists($this->paths['handoff']);
        self::assertFileDoesNotExist($this->paths['audit_cron']);
    }

    public function testForeignCronAndConcurrentTickAreNotOverwritten(): void
    {
        $never = static function (): array {
            self::fail('Unexpected capture');
        };
        file_put_contents($this->paths['audit_cron'], 'foreign');
        try {
            \membershipMorningOperate($this->paths, true, $never);
            self::fail('Foreign cron overwritten');
        } catch (\RuntimeException) {
            self::assertSame('foreign', file_get_contents($this->paths['audit_cron']));
        }
        $lock = fopen($this->paths['audit_lock'], 'c');
        flock($lock, LOCK_EX);
        try {
            self::assertSame(['skipped' => 'already_running'], \membershipMorningOperate($this->paths, false, $never));
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function testCaptureFailurePersistsFailureAndNeverRetriesBusiness(): void
    {
        $capture = static function (): array {
            throw new \RuntimeException('private-provider-detail');
        };
        \membershipMorningOperate($this->paths, true, $capture);
        \membershipMorningOperate($this->paths, false, $capture, strtotime('2026-11-07 05:30:00 UTC'));
        $report = file_get_contents($this->paths['report']);
        self::assertStringNotContainsString('private-provider-detail', $report);
        self::assertSame('FAIL', json_decode($report, true)['verdict']);
        self::assertFileDoesNotExist($this->paths['audit_cron']);
    }

    public function testSelfCleanupNeverRemovesReplacementCron(): void
    {
        $capture = function (int $now): array {
            file_put_contents($this->paths['audit_cron'], 'replacement');
            return \membershipMorningEvaluate($this->membershipStatus(), $this->jobs(), $now);
        };
        \membershipMorningOperate($this->paths, true, $capture);
        try {
            \membershipMorningOperate($this->paths, false, $capture, strtotime('2026-11-07 05:30:00 UTC'));
            self::fail('Foreign cron deleted');
        } catch (\RuntimeException) {
            self::assertSame('replacement', file_get_contents($this->paths['audit_cron']));
            self::assertFileExists($this->paths['report']);
        }
    }

    public function testSymlinkEvidenceCannotOverwriteItsTarget(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            self::markTestSkipped('Unix hosting symlink boundary.');
        }
        $foreign = $this->directory . '/foreign';
        file_put_contents($foreign, 'preserved');
        symlink($foreign, $this->paths['report']);
        try {
            \membershipMorningStatus($this->paths);
            self::fail('Symlink read as audit');
        } catch (\RuntimeException) {
            self::assertSame('preserved', file_get_contents($foreign));
        }
        $this->expectException(\RuntimeException::class);
        \membershipMorningWrite($this->paths, $this->paths['report'], 'replacement');
    }
}
