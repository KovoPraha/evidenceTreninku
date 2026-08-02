<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class KisParityCliTest extends TestCase
{
    public function testValidFixtureExitsZero(): void
    {
        [$exitCode, $output] = $this->runCli([
            '--input', $this->fixture('parity-valid.json'), '--json',
        ]);

        self::assertSame(0, $exitCode, $output);
        $decoded = json_decode($output, true, 64, JSON_THROW_ON_ERROR);
        self::assertSame('valid', $decoded['status']);
    }

    public function testBlockerFixtureExitsTwo(): void
    {
        [$exitCode, $output] = $this->runCli([
            '--input', $this->fixture('parity-invalid.json'), '--json',
        ]);

        self::assertSame(2, $exitCode, $output);
        $decoded = json_decode($output, true, 64, JSON_THROW_ON_ERROR);
        self::assertSame('blocked', $decoded['status']);
        self::assertGreaterThan(0, $decoded['summary']['blocker_rows']);
    }

    public function testUsageErrorExitsSixtyFour(): void
    {
        [$exitCode, $output] = $this->runCli(['--json']);

        self::assertSame(64, $exitCode, $output);
        $decoded = json_decode($output, true, 64, JSON_THROW_ON_ERROR);
        self::assertSame('usage_error', $decoded['status']);
    }

    public function testSchemaValidationErrorExitsTwoWithoutEchoingInputValue(): void
    {
        [$exitCode, $output] = $this->runCli([
            '--input', $this->fixture('parity-schema-invalid.json'), '--json',
        ]);

        self::assertSame(2, $exitCode, $output);
        $decoded = json_decode($output, true, 64, JSON_THROW_ON_ERROR);
        self::assertSame('blocked', $decoded['status']);
        self::assertSame('validation', $decoded['error_type']);
        self::assertStringNotContainsString('must-not-be-echoed', $output);
    }

    /** @param list<string> $arguments @return array{int, string} */
    private function runCli(array $arguments): array
    {
        $script = dirname(__DIR__, 2) . '/bin/kis-parity-dry-run.php';
        $parts = [escapeshellarg(PHP_BINARY), escapeshellarg($script)];
        foreach ($arguments as $argument) {
            $parts[] = escapeshellarg($argument);
        }

        $lines = [];
        $exitCode = -1;
        exec(implode(' ', $parts) . ' 2>&1', $lines, $exitCode);
        return [$exitCode, implode(PHP_EOL, $lines)];
    }

    private function fixture(string $name): string
    {
        return dirname(__DIR__) . '/fixtures/kis/' . $name;
    }
}
