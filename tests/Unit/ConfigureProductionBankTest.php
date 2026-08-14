<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/bin/configure-production-bank.php';

final class ConfigureProductionBankTest extends TestCase
{
    /** @return array{iban:string,bic:string,account_label:string,due_days:int} */
    private function settings(): array
    {
        return \kisProductionBankValidate([
            'iban' => 'CZ1455000000001527393001',
            'bic' => '',
            'account_label' => 'TJ Kovo Praha z.s.',
            'due_days' => 7,
        ]);
    }

    public function testProvidedCzechIbanPassesChecksum(): void
    {
        self::assertTrue(\kisBankIbanChecksumIsValid('CZ14 5500 0000 0015 2739 3001'));
        self::assertFalse(\kisBankIbanChecksumIsValid('CZ1555000000001527393001'));
    }

    public function testManagedBlockIsInsertedAndIdempotentlyReplaced(): void
    {
        $block = \kisProductionBankManagedBlock($this->settings());
        $once = \kisProductionBankMergeConfig("<?php\ndeclare(strict_types=1);\ndefine('DB_HOST', '127.0.0.1');\n", $block);
        $twice = \kisProductionBankMergeConfig($once, $block);

        self::assertStringStartsWith("<?php\ndeclare(strict_types=1);\n\n// BEGIN KIS MANAGED BANK ACCOUNT", $once);
        self::assertStringContainsString('CZ1455000000001527393001', $once);
        self::assertStringContainsString('TJ Kovo Praha z.s.', $once);
        self::assertSame(1, substr_count($twice, \KIS_BANK_BLOCK_BEGIN));
        self::assertSame($once, $twice);
    }

    public function testInvalidDueDaysAreRejected(): void
    {
        $this->expectException(\RuntimeException::class);
        \kisProductionBankValidate([
            'iban' => 'CZ1455000000001527393001',
            'bic' => '',
            'account_label' => 'TJ Kovo Praha z.s.',
            'due_days' => 0,
        ]);
    }
}
