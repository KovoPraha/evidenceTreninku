<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/bin/provision-production-private-storage.php';

/** Deterministic stream faults after bytes have already been appended. */
final class PrivateStorageFaultStream
{
    public mixed $context;
    public static string $bytes = '';
    public static string $mode = '';
    public static int $truncations = 0;
    public static int $flushes = 0;
    private int $position = 0;
    private bool $failed = false;

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool { return true; }
    public function stream_read(int $count): string
    {
        $read = substr(self::$bytes, $this->position, $count);
        $this->position += strlen($read);
        return $read;
    }
    public function stream_eof(): bool { return $this->position >= strlen(self::$bytes); }
    public function stream_tell(): int { return $this->position; }
    public function stream_stat(): array { return ['size'=>strlen(self::$bytes)]; }
    public function stream_seek(int $offset, int $whence): bool
    {
        $this->position = match ($whence) { SEEK_SET=>$offset, SEEK_CUR=>$this->position+$offset, SEEK_END=>strlen(self::$bytes)+$offset };
        return $this->position >= 0;
    }
    public function stream_write(string $data): int
    {
        $written = self::$mode === 'partial_write_warning' ? substr($data, 0, 3) : $data;
        if (self::$mode === 'corrupt_readback') $written[0] = '!';
        self::$bytes = substr(self::$bytes, 0, $this->position) . $written;
        $this->position += strlen($written);
        if (self::$mode === 'partial_write_warning' && !$this->failed) {
            $this->failed = true;
            trigger_error('Injected warning after partial append.', E_USER_WARNING);
        }
        return strlen($written);
    }
    public function stream_flush(): bool
    {
        self::$flushes++;
        if (in_array(self::$mode, ['flush_warning','rollback_flush_warning'], true) && !$this->failed) {
            $this->failed = true;
            trigger_error('Injected flush warning after append.', E_USER_WARNING);
        }
        if (self::$mode === 'rollback_flush_warning' && self::$truncations > 0) {
            trigger_error('Injected warning during rollback flush.', E_USER_WARNING);
            return false;
        }
        return true;
    }
    public function stream_truncate(int $size): bool
    {
        self::$truncations++;
        self::$bytes = substr(self::$bytes, 0, $size);
        return true;
    }
}

final class ProductionPrivateStorageProvisionTest extends TestCase
{
    private string $root;
    private string $app;

    protected function setUp(): void
    {
        $this->root = str_replace('\\', '/', sys_get_temp_dir()) . '/kis-private-provision-test-' . bin2hex(random_bytes(8));
        $this->app = $this->root . '/web';
        mkdir($this->app, 0700, true);
        $this->config();
    }

    private function config(string $extra = '', bool $local = false): void
    {
        file_put_contents($this->app . '/config.php', "<?php\ndefine('JE_LOKALNE', " . ($local ? 'true' : 'false') . ");\ndefine('APP_BASE_URL', 'https://kis.kovopraha.cz');\n// SYNTHETIC SECRET MUST NOT APPEAR IN OUTPUT\n" . $extra);
    }

    private function runProvision(array $overrides = []): array
    {
        $env = array_merge(getenv(), ['APP_ROOT'=>$this->app, 'APP_HOST'=>'kis.kovopraha.cz',
            'KIS_PRIVATE_STORAGE_CONFIRM'=>'PRIPRAVIT-PRIVATNI-ULOZISTE', 'APP_PRIVATE_STORAGE_ROOT'=>''], $overrides);
        $process = proc_open([PHP_BINARY, dirname(__DIR__, 2) . '/bin/provision-production-private-storage.php'],
            [['pipe','r'], ['pipe','w'], ['pipe','w']], $pipes, null, $env);
        self::assertIsResource($process);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $exit = proc_close($process);
        self::assertStringNotContainsString('SYNTHETIC SECRET', $stdout . $stderr);
        self::assertStringNotContainsString($this->root, $stdout . $stderr);
        return [$exit, json_decode($stdout, true, 512, JSON_THROW_ON_ERROR)];
    }

    public function testCreatesPrivateStorageBacksUpConfigAndIsIdempotent(): void
    {
        $original = file_get_contents($this->app . '/config.php');
        [$exit, $report] = $this->runProvision();
        self::assertSame(0, $exit);
        self::assertTrue($report['config_appended']);
        self::assertTrue($report['private_config_backup_verified']);
        self::assertTrue($report['cli_write_read_delete_probe']);
        self::assertFalse($report['web_runtime_verified']);
        $config = file_get_contents($this->app . '/config.php');
        self::assertStringStartsWith($original, $config);
        self::assertStringContainsString("if (!defined('APP_PRIVATE_STORAGE_ROOT'))", $config);
        $backups = glob($this->root . '/data/.kis-backups/private-storage/config-*.php');
        self::assertCount(1, $backups);
        self::assertSame($original, file_get_contents($backups[0]));
        self::assertSame([], glob($this->root . '/data/kis-private/.provision-probe-*'));
        [$exit, $second] = $this->runProvision();
        self::assertSame(0, $exit);
        self::assertFalse($second['config_appended']);
        self::assertFalse($second['storage_created']);
        self::assertSame($config, file_get_contents($this->app . '/config.php'));
        self::assertCount(1, glob($this->root . '/data/.kis-backups/private-storage/config-*.php'));
        if (DIRECTORY_SEPARATOR === '/') {
            self::assertSame(0700, fileperms($this->root . '/data/kis-private') & 0777);
            self::assertSame(0600, fileperms($backups[0]) & 0777);
        }
    }

    public function testPreservesExistingAllowlistedStorageAndAttachments(): void
    {
        $existing = $this->root . '/data/existing-private';
        mkdir($existing, 0700, true);
        file_put_contents($existing . '/synthetic.txt', 'unchanged');
        $this->config("define('APP_PRIVATE_STORAGE_ROOT', " . var_export($existing, true) . ");\n");
        $original = file_get_contents($this->app . '/config.php');
        [$exit, $report] = $this->runProvision();
        self::assertSame(0, $exit);
        self::assertTrue($report['existing_configuration_preserved']);
        self::assertSame($original, file_get_contents($this->app . '/config.php'));
        self::assertSame('unchanged', file_get_contents($existing . '/synthetic.txt'));
        self::assertDirectoryDoesNotExist($this->root . '/data/kis-private');
        self::assertDirectoryDoesNotExist($this->root . '/data/.kis-backups');
    }

    public function testRejectsUnknownPathBeforeChanges(): void
    {
        $this->config("define('APP_PRIVATE_STORAGE_ROOT', " . var_export($this->root . '/other', true) . ");");
        $original = file_get_contents($this->app . '/config.php');
        self::assertSame(2, $this->runProvision()[0]);
        self::assertSame($original, file_get_contents($this->app . '/config.php'));
        self::assertDirectoryDoesNotExist($this->root . '/data');
        self::assertDirectoryDoesNotExist($this->root . '/other');
    }

    public function testFailsClosedOnHostConfirmationAndLocalConfig(): void
    {
        foreach ([['APP_HOST'=>'localhost'], ['KIS_PRIVATE_STORAGE_CONFIRM'=>''], ['APP_ROOT'=>'']] as $env) {
            self::assertSame(2, $this->runProvision($env)[0]);
            self::assertDirectoryDoesNotExist($this->root . '/data');
        }
        $this->config('', true);
        self::assertSame(2, $this->runProvision()[0]);
        self::assertDirectoryDoesNotExist($this->root . '/data');
    }

    public function testRejectsEmptyConstantAndUnreachableAppend(): void
    {
        foreach (["define('APP_PRIVATE_STORAGE_ROOT', '');", 'return;'] as $extra) {
            $this->config($extra);
            $original = file_get_contents($this->app . '/config.php');
            self::assertSame(2, $this->runProvision()[0]);
            self::assertSame($original, file_get_contents($this->app . '/config.php'));
            self::assertDirectoryDoesNotExist($this->root . '/data');
        }
    }

    public function testSupportsConfigWithClosingPhpTag(): void
    {
        $this->config('?>');
        self::assertSame(0, $this->runProvision()[0]);
        self::assertSame(0, $this->runProvision()[0]);
    }

    public function testPartialWriteAndFlushWarningsAlwaysAttemptVerifiedRollback(): void
    {
        self::assertTrue(stream_wrapper_register('kisfault', PrivateStorageFaultStream::class));
        $original = "<?php\n// original synthetic configuration\n";
        try {
            foreach (['partial_write_warning','flush_warning','corrupt_readback','rollback_flush_warning'] as $mode) {
                PrivateStorageFaultStream::$bytes = $original;
                PrivateStorageFaultStream::$mode = $mode;
                PrivateStorageFaultStream::$truncations = PrivateStorageFaultStream::$flushes = 0;
                $handle = fopen('kisfault://config', 'r+b');
                set_error_handler(static function (): never { throw new \RuntimeException('same_throwing_handler_as_cli'); });
                try {
                    \productionPrivateStorageAppend($handle, $original, "// appended setting\n");
                    self::fail('Injected append failure was accepted: ' . $mode);
                } catch (\RuntimeException $exception) {
                    self::assertSame($mode === 'rollback_flush_warning' ? 'config_append_failed_restore_required' : 'config_append_failed_restored', $exception->getMessage(), $mode);
                    self::assertSame($original, PrivateStorageFaultStream::$bytes, $mode);
                    self::assertSame(1, PrivateStorageFaultStream::$truncations, $mode);
                    self::assertGreaterThanOrEqual(1, PrivateStorageFaultStream::$flushes, $mode);
                } finally {
                    restore_error_handler();
                    PrivateStorageFaultStream::$mode = '';
                    fclose($handle);
                }
            }
        } finally { stream_wrapper_unregister('kisfault'); }
    }

    public function testRefusesSymlinkedDataBeforeChanges(): void
    {
        if (DIRECTORY_SEPARATOR !== '/') self::markTestSkipped('Symlink creation requires Windows elevated privilege; verified in Linux CI.');
        mkdir($this->root . '/outside', 0700);
        self::assertTrue(symlink($this->root . '/outside', $this->root . '/data'));
        self::assertSame(2, $this->runProvision()[0]);
        self::assertSame([], array_values(array_diff(scandir($this->root . '/outside'), ['.','..'])));
    }

    protected function tearDown(): void
    {
        $remove = static function (string $path) use (&$remove): void {
            if (is_link($path) || is_file($path)) { unlink($path); return; }
            if (!is_dir($path)) return;
            foreach (new \FilesystemIterator($path) as $item) $remove($item->getPathname());
            rmdir($path);
        };
        $remove($this->root);
    }
}
