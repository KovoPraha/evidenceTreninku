<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PrivateStorageReadinessTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = str_replace('\\', '/', sys_get_temp_dir()) . '/kis-storage-readiness-test-' . bin2hex(random_bytes(8));
        mkdir($this->root, 0700);
    }

    private function probe(string $configured, string $documentRoot = '', bool $restricted = false): array
    {
        $source = dirname(__DIR__, 2) . '/includes/private_storage_readiness.php';
        $code = "define('JE_LOKALNE',false); \$_SERVER['DOCUMENT_ROOT']=" . var_export($documentRoot, true) . '; require ' . var_export($source, true) . '; echo json_encode(privateStorageReadiness());';
        $command = [PHP_BINARY];
        if ($restricted) {
            $command[] = '-d';
            $command[] = 'open_basedir=' . dirname(__DIR__, 2);
        }
        array_push($command, '-r', $code);
        $process = proc_open($command, [['pipe','r'], ['pipe','w'], ['pipe','w']], $pipes, null,
            array_merge(getenv(), ['APP_PRIVATE_STORAGE_ROOT'=>$configured]));
        self::assertIsResource($process);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        self::assertSame(0, proc_close($process));
        self::assertSame('', $errors);
        self::assertStringNotContainsString($this->root, $output);
        $report = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['configured','outside_webroot','directory_exists','writable'], array_keys($report));
        foreach ($report as $value) self::assertIsBool($value);
        return $report;
    }

    public function testUnconfiguredProductionHasNoLocalFallback(): void
    {
        self::assertSame(['configured'=>false,'outside_webroot'=>false,'directory_exists'=>false,'writable'=>false], $this->probe(''));
    }

    public function testExistingPrivateDirectoryIsCheckedWithoutWriting(): void
    {
        self::assertSame(['configured'=>true,'outside_webroot'=>true,'directory_exists'=>true,'writable'=>true], $this->probe($this->root));
        self::assertSame(['.','..'], scandir($this->root));
    }

    public function testMissingDirectoryIsNeverCreated(): void
    {
        $report = $this->probe($this->root . '/missing');
        self::assertTrue($report['configured']);
        self::assertFalse($report['directory_exists']);
        self::assertFalse($report['writable']);
        self::assertDirectoryDoesNotExist($this->root . '/missing');
    }

    public function testDocumentRootCannotBeUsedForPrivateStorage(): void
    {
        $report = $this->probe($this->root, $this->root);
        self::assertFalse($report['outside_webroot']);
        self::assertFalse($report['writable']);
    }

    public function testWebOpenBasedirDenialIsReportedWithoutWarningsOrPaths(): void
    {
        $report = $this->probe($this->root, '', true);
        self::assertTrue($report['configured']);
        self::assertFalse($report['directory_exists']);
        self::assertFalse($report['writable']);
    }

    protected function tearDown(): void
    {
        rmdir($this->root);
    }
}
