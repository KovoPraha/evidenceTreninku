<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ShopCatalogStageCliTest extends TestCase
{
    private const CLI = __DIR__ . '/../../bin/shoptet-products-stage.php';
    private const FIXTURE = __DIR__ . '/../fixtures/shoptet/products-valid.csv';

    public function testRequiresExplicitApplyBeforeLoadingDatabaseConfiguration(): void
    {
        $input = realpath(self::FIXTURE);
        self::assertIsString($input);

        $run = $this->runCli(['--input=' . $input]);

        self::assertSame(64, $run['exit']);
        self::assertStringContainsString('explicitni --apply', $run['stderr']);
    }

    public function testRejectsRemoteInputAndMissingExplicitHost(): void
    {
        $remote = $this->runCli(['--input=https://example.invalid/products.xml', '--apply']);
        self::assertSame(64, $remote['exit']);
        self::assertStringContainsString('lokalni regularni', $remote['stderr']);

        $input = realpath(self::FIXTURE);
        self::assertIsString($input);
        $missingHost = $this->runCli(['--input=' . $input, '--apply'], ['APP_HOST' => '']);
        self::assertSame(64, $missingHost['exit']);
        self::assertStringContainsString('APP_HOST', $missingHost['stderr']);
    }

    /**
     * @param list<string> $arguments
     * @param array<string,string>|null $environment
     * @return array{exit:int,stdout:string,stderr:string}
     */
    private function runCli(array $arguments, ?array $environment = null): array
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
            null,
            $environment
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
