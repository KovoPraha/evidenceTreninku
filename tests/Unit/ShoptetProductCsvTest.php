<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/shoptet_product_csv.php';

final class ShoptetProductCsvTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../fixtures/shoptet/products-valid.csv';
    private const CLI = __DIR__ . '/../../bin/shoptet-products-dry-run.php';

    public function testReadsUtf8SemicolonCsvWithinLimits(): void
    {
        $parsed = \ShoptetProductCsv::read(self::FIXTURE);

        self::assertSame('UTF-8', $parsed['source']['encoding']);
        self::assertSame('semicolon', $parsed['source']['delimiter']);
        self::assertSame(3, $parsed['source']['rows']);
        self::assertSame('$000123', $parsed['rows'][0]['values']['code']);
        self::assertSame(hash('sha256', (string)file_get_contents(self::FIXTURE)), $parsed['source']['sha256']);
    }

    public function testParserAndHashUseOneInMemorySnapshot(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/includes/shoptet_product_csv.php');

        self::assertIsString($source);
        self::assertStringContainsString("fopen('php://memory', 'w+b')", $source);
        self::assertStringContainsString("hash('sha256', \$sample)", $source);
        self::assertStringNotContainsString("fopen(\$input, 'rb')", $source);
        self::assertStringNotContainsString("hash_file('sha256', \$input)", $source);
    }

    public function testReadsUtf8BomAndWindows1250(): void
    {
        $utf8Base = tempnam(sys_get_temp_dir(), 'shop-utf8-');
        $cp1250Base = tempnam(sys_get_temp_dir(), 'shop-cp-');
        self::assertIsString($utf8Base);
        self::assertIsString($cp1250Base);
        $utf8 = $utf8Base . '.csv';
        $cp1250 = $cp1250Base . '.csv';
        $content = "code;pairCode;name;price;currency\nTEST-1;;Žluťoučký;100;CZK\n";
        file_put_contents($utf8, "\xEF\xBB\xBF" . $content);
        file_put_contents($cp1250, iconv('UTF-8', 'WINDOWS-1250', $content));

        try {
            self::assertSame('code', \ShoptetProductCsv::read($utf8)['headers'][0]);
            $parsedCp = \ShoptetProductCsv::read($cp1250);
            self::assertSame('Windows-1250', $parsedCp['source']['encoding']);
            self::assertSame('Žluťoučký', $parsedCp['rows'][0]['values']['name']);
        } finally {
            @unlink($utf8);
            @unlink($cp1250);
            @unlink($utf8Base);
            @unlink($cp1250Base);
        }
    }

    public function testRejectsOversizedFieldAndRemoteUrl(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'shop-limit-');
        self::assertIsString($path);
        $csv = $path . '.csv';
        file_put_contents($csv, "code;pairCode;name;price\nA;;" . str_repeat('x', 65537) . ";10\n");

        try {
            try {
                \ShoptetProductCsv::read($csv);
                self::fail('Oversized field must fail.');
            } catch (\ShoptetProductCsvException $exception) {
                self::assertStringContainsString('64 KiB', $exception->getMessage());
            }
            $this->expectException(\ShoptetProductCsvException::class);
            \ShoptetProductCsv::read('https://example.invalid/products.csv');
        } finally {
            @unlink($csv);
            @unlink($path);
        }
    }

    public function testCliJsonIsDeterministicAndMakesNoFiles(): void
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'shop-cli-' . bin2hex(random_bytes(5));
        self::assertTrue(mkdir($directory, 0700));
        $before = scandir($directory);

        try {
            $first = $this->runCli(['--input=' . realpath(self::FIXTURE), '--json'], $directory);
            $second = $this->runCli(['--input=' . realpath(self::FIXTURE), '--json'], $directory);
            self::assertSame(0, $first['exit']);
            self::assertSame($first['stdout'], $second['stdout']);
            self::assertSame('', $first['stderr']);
            self::assertSame($before, scandir($directory));
            $decoded = json_decode($first['stdout'], true, 512, JSON_THROW_ON_ERROR);
            self::assertSame(2, $decoded['summary']['products']);
            self::assertSame(3, $decoded['summary']['variants']);
            self::assertSame(0, $decoded['summary']['database_writes']);
        } finally {
            rmdir($directory);
        }
    }

    public function testCliRejectsValidationErrorsApplyAndReportWriteOptions(): void
    {
        $invalid = realpath(__DIR__ . '/../fixtures/shoptet/products-invalid.csv');
        self::assertIsString($invalid);
        self::assertSame(2, $this->runCli(['--input=' . $invalid, '--json'])['exit']);
        self::assertSame(64, $this->runCli(['--input=' . $invalid, '--apply'])['exit']);
        self::assertSame(64, $this->runCli(['--input=' . $invalid, '--report=out.json'])['exit']);
    }

    public function testCliRejectsDuplicateInputAndInvalidLocalPathsAsUsage(): void
    {
        $valid = realpath(self::FIXTURE);
        self::assertIsString($valid);
        $duplicate = $this->runCli(['--input=' . $valid, '--input', $valid, '--json']);
        self::assertSame(64, $duplicate['exit']);
        self::assertStringContainsString('pouze jednou', $duplicate['stderr']);

        $missing = $this->runCli(['--input=' . sys_get_temp_dir() . '/shop-does-not-exist.csv', '--json']);
        self::assertSame(64, $missing['exit']);
        self::assertStringContainsString('regularni .csv', $missing['stderr']);

        $base = tempnam(sys_get_temp_dir(), 'shop-wrong-ext-');
        self::assertIsString($base);
        $text = $base . '.txt';
        file_put_contents($text, "code;pairCode;name;price\nTEST-1;;Test;100\n");
        try {
            $wrongExtension = $this->runCli(['--input=' . $text, '--json']);
            self::assertSame(64, $wrongExtension['exit']);
            self::assertStringContainsString('regularni .csv', $wrongExtension['stderr']);
        } finally {
            @unlink($text);
            @unlink($base);
        }
    }

    /** @param list<string> $arguments @return array{exit:int,stdout:string,stderr:string} */
    private function runCli(array $arguments, ?string $workingDirectory = null): array
    {
        $command = [PHP_BINARY, realpath(self::CLI), ...$arguments];
        $pipes = [];
        $process = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $workingDirectory
        );
        self::assertIsResource($process);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        return ['exit' => $exit, 'stdout' => (string)$stdout, 'stderr' => (string)$stderr];
    }
}
