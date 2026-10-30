<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/tools/check-phpstan-baseline-budget.php';

final class PhpstanBaselineBudgetTest extends TestCase
{
    public function testReductionAllowedButEqualTotalCannotHideNewFinding(): void
    {
        baselineBudgetAssert(['a' => 2, 'b' => 3], ['a' => 1]);
        $this->addToAssertionCount(1);
        $this->expectException(\RuntimeException::class);
        baselineBudgetAssert(['a' => 2, 'b' => 3], ['a' => 1, 'b' => 4]);
    }

    public function testNewEntryRejectedEvenWhenTotalFalls(): void
    {
        $this->expectException(\RuntimeException::class);
        baselineBudgetAssert(['a' => 100], ['new' => 1]);
    }

    public function testCurrentGeneratedBaselineAndMalformedInput(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/phpstan-baseline.neon');
        self::assertGreaterThan(0, count(baselineBudgetEntries($source)));
        self::assertSame(baselineBudgetEntries($source), baselineBudgetEntries(str_replace("\r\n", "\n", $source)));
        self::assertSame([], baselineBudgetEntries("parameters:\n\tignoreErrors: []\n"));
        $this->expectException(\RuntimeException::class);
        baselineBudgetEntries($source . "unexpected: silently-ignored\n");
    }
}
