<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2).'/bin/configure-production-memberships.php';

final class ConfigureProductionMembershipsTest extends TestCase
{
    public function testEnableAndDisablePreserveOtherSettingsAndAreIdempotent(): void
    {
        $other = "define('MONTHLY_MEMBERSHIP_MAIL_ENABLED', false);\ndefine('FIO_IMPORT_ENABLED', true);\n"
            ."define('DB_PASS', 'synthetic-unit-value');\n";
        $old = "<?php\ndeclare(strict_types=1);\ndefine('MONTHLY_MEMBERSHIP_AUTOMATION_ENABLED', false);\n".$other;
        foreach ([true, false, true] as $enabled) {
            $block = \kisProductionMembershipsManagedBlock($enabled);
            $merged = \kisProductionMembershipsMergeConfig($old, $block);
            self::assertSame($merged, \kisProductionMembershipsMergeConfig($merged, $block));
            self::assertStringContainsString($other, $merged);
            self::assertSame(1, substr_count($merged, \KIS_MEMBERSHIPS_BLOCK_BEGIN));
            $this->assertRuntimeFlags($merged, $enabled);
            $old = $merged;
        }
    }

    public function testMailSwitchIsIndependentIdempotentAndSurvivesAutomationChanges(): void
    {
        $other = "define('FIO_IMPORT_ENABLED', true);\ndefine('DB_PASS', 'synthetic-unit-value');\n";
        $config = "<?php\ndeclare(strict_types=1);\ndefine('MONTHLY_MEMBERSHIP_MAIL_ENABLED', false);\n".$other;
        $config = \kisProductionMembershipsMergeConfig($config, \kisProductionMembershipsManagedBlock(true));
        foreach ([true, false, true] as $mail) {
            $block = \kisProductionMembershipsManagedMailBlock($mail);
            $config = \kisProductionMembershipsMergeConfig($config, $block, true);
            self::assertSame($config, \kisProductionMembershipsMergeConfig($config, $block, true));
            self::assertStringContainsString($other, $config);
            foreach ([false, true] as $automation) {
                $config = \kisProductionMembershipsMergeConfig($config, \kisProductionMembershipsManagedBlock($automation));
                self::assertSame(1, substr_count($config, \KIS_MEMBERSHIPS_MAIL_BLOCK_BEGIN));
                self::assertSame(1, substr_count($config, \KIS_MEMBERSHIPS_BLOCK_BEGIN));
                $this->assertRuntimeFlags($config, $automation, $mail);
            }
        }
    }

    public function testMailCanBeAddedWhenPrivateFlagIsMissing(): void
    {
        $config = "<?php\ndefine('MONTHLY_MEMBERSHIP_AUTOMATION_ENABLED', true);\ndefine('FIO_IMPORT_ENABLED', true);\n";
        $merged = \kisProductionMembershipsMergeConfig($config, \kisProductionMembershipsManagedMailBlock(true), true);
        self::assertStringNotContainsString(\KIS_MEMBERSHIPS_BLOCK_BEGIN, $merged);
        $this->assertRuntimeFlags($merged, true, true);
    }

    private function assertRuntimeFlags(string $config, bool $enabled, bool $mail = false): void
    {
        $file = tempnam(sys_get_temp_dir(), 'kis-memberships-test-');
        self::assertIsString($file);
        try {
            file_put_contents($file, $config."\necho json_encode([MONTHLY_MEMBERSHIP_AUTOMATION_ENABLED, MONTHLY_MEMBERSHIP_MAIL_ENABLED, FIO_IMPORT_ENABLED]);");
            $process = proc_open([PHP_BINARY, '-d', 'display_errors=1', $file], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            self::assertIsResource($process);
            fclose($pipes[0]);
            $body = stream_get_contents($pipes[1]);
            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            self::assertSame(0, proc_close($process));
            self::assertSame('', $errors);
            self::assertSame([$enabled, $mail, true], json_decode($body, true, flags: JSON_THROW_ON_ERROR));
        } finally {
            unlink($file);
        }
    }

    public function testBackupMatchesOriginalAndRepeatedWriteMakesNoExtraBackup(): void
    {
        $dir = sys_get_temp_dir().'/kis-memberships-'.bin2hex(random_bytes(6));
        mkdir($dir);
        $old = "<?php\ndefine('DB_PASS', 'synthetic');\n";
        file_put_contents($dir.'/config.php', $old);
        try {
            $new = \kisProductionMembershipsMergeConfig($old, \kisProductionMembershipsManagedBlock(true));
            self::assertTrue(\kisProductionMembershipsWriteConfig($dir.'/config.php', $new, $dir.'/backups'));
            self::assertSame($new, file_get_contents($dir.'/config.php'));
            self::assertFalse(\kisProductionMembershipsWriteConfig($dir.'/config.php', $new, $dir.'/backups'));
            $backups = glob($dir.'/backups/*.php');
            self::assertCount(1, $backups);
            self::assertSame($old, file_get_contents($backups[0]));
        } finally {
            foreach (glob($dir.'/backups/*.php') ?: [] as $backup) {
                unlink($backup);
            }
            rmdir($dir.'/backups');
            unlink($dir.'/config.php');
            rmdir($dir);
        }
    }

    public function testBrokenPhpIsRejectedBeforeWriting(): void
    {
        $this->expectException(\ParseError::class);
        \kisProductionMembershipsMergeConfig("<?php\ndefine('BROKEN', ;", \kisProductionMembershipsManagedBlock(true));
    }

    public function testNonPhpConfigIsRejected(): void
    {
        $this->expectException(\RuntimeException::class);
        \kisProductionMembershipsMergeConfig('not php', \kisProductionMembershipsManagedBlock(true));
    }

    public function testMissingExplicitAuthorizationIsRejectedBeforeReadingConfig(): void
    {
        $previous = getenv('CONFIGURE_PRODUCTION_MEMBERSHIPS');
        putenv('CONFIGURE_PRODUCTION_MEMBERSHIPS');
        try {
            $this->expectException(\RuntimeException::class);
            \kisProductionMembershipsMain();
        } finally {
            putenv($previous === false ? 'CONFIGURE_PRODUCTION_MEMBERSHIPS' : 'CONFIGURE_PRODUCTION_MEMBERSHIPS='.$previous);
        }
    }

    public function testWorkflowKeepsProtectionAndChecksFreshPersistedFlags(): void
    {
        $workflow = (string)file_get_contents(dirname(__DIR__, 2).'/.github/workflows/configure-memberships-production.yml');
        foreach (["github.ref == 'refs/heads/main'", 'environment: production', 'group: produkce-kis',
            'enable:ZAPNOUT-CLENSTVI', 'StrictHostKeyChecking', 'unchanged_flags', 'memberships-after.json'] as $required) {
            if ($required === 'StrictHostKeyChecking') {
                $helper = (string)file_get_contents(dirname(__DIR__, 2).'/.github/scripts/production-ssh-retry.sh');
                self::assertStringContainsString($required.'=yes', $helper);
            } else {
                self::assertStringContainsString($required, $workflow);
            }
        }
        self::assertStringNotContainsString('MONTHLY_MEMBERSHIP_MAIL_ENABLED', \kisProductionMembershipsManagedBlock(true));
        self::assertStringNotContainsString('MONTHLY_MEMBERSHIP_AUTOMATION_ENABLED', \kisProductionMembershipsManagedMailBlock(true));
        self::assertStringContainsString('mail-enable:ZAPNOUT-POSTU-CLENSTVI', $workflow);
        self::assertStringContainsString('mail-disable:VYPNOUT-POSTU-CLENSTVI', $workflow);
        self::assertStringContainsString('(.automation_enabled == $before[0].automation_enabled)', $workflow);
        self::assertStringContainsString('del(.MONTHLY_MEMBERSHIP_MAIL_ENABLED)', $workflow);
        self::assertStringNotContainsString('set -x', $workflow);
    }
}
