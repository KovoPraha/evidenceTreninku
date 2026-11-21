<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/parent_auth_mail_scheduler.php';

final class ParentAuthMailSchedulerTest extends TestCase
{
    public function testOwnedCronIsInstalledOnceAndForeignFileIsRefused(): void
    {
        $base = sys_get_temp_dir() . '/parent-mail-scheduler-' . bin2hex(random_bytes(8));
        $root = $base . '/kis.kovopraha.cz';
        $operations = $base . '/data/.kis-deploy';
        $cronDirectory = $base . '/CRON.1minuta';
        mkdir($root, 0700, true);
        mkdir($operations, 0700, true);
        mkdir($cronDirectory, 0700, true);
        try {
            $paths = \parentAuthMailPaths($root, $operations);
            self::assertFalse(\parentAuthMailSchedulerStatus($paths)['installed_expected_source']);
            self::assertSame(['installed' => true, 'changed' => true], \parentAuthMailInstall($paths));
            self::assertSame(['installed' => true, 'changed' => false], \parentAuthMailInstall($paths));
            self::assertTrue(\parentAuthMailSchedulerStatus($paths)['installed_expected_source']);
            \parentAuthMailHeartbeat($paths, ['processed' => 0], true);
            self::assertTrue(\parentAuthMailSchedulerStatus($paths)['recent_successful_hosting_run']);
            file_put_contents($paths['cron'], '<?php // foreign scheduler');
            $this->expectException(\RuntimeException::class);
            \parentAuthMailInstall($paths);
        } finally {
            foreach ([$cronDirectory . '/evidence-parent-auth-mail.php', $operations . '/parent-auth-mail-heartbeat.json',
                $operations . '/parent-auth-mail-install.lock'] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($cronDirectory);
            rmdir($operations);
            rmdir(dirname($operations));
            rmdir($root);
            rmdir($base);
        }
    }
}
