<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/accounting_export_read.php';
require_once dirname(__DIR__) . '/Support/AccountingBalanceBaseline.php';

final class AccountingExportReadTest extends TestCase
{
    public function testOpeningTurnoverAllocationAndCancellationKeepTheirFinancialMeaning(): void
    {
        $snapshot = $this->fixture();
        $rows = \accountingExportFinancialBalanceRows($snapshot);
        self::assertSame(\w10BalanceRowsBaseline($snapshot), $rows);
        self::assertSame([150, 103.45, 253.45], array_slice($rows[0], 3, 3));
        self::assertSame([null, -5, 195], array_slice($rows[1], 3, 3));
        self::assertSame(78.45, $rows[3][5]);
        self::assertSame(15, $rows[4][5]);
        self::assertSame(0, $rows[5][5]);
        self::assertSame(\w10FinancialExceptionsBaseline($snapshot, '2026-01-31'), \accountingExportFinancialExceptions($snapshot, '2026-01-31'));
        self::assertCount(1, \accountingExportFinancialExceptions($snapshot, '2026-01-31'));
        $snapshot['reconciliations'][] = ['account_id' => '01', 'as_of' => '2026-01-31', 'is_closed' => 0, 'difference_minor' => 0];
        self::assertCount(2, \accountingExportFinancialExceptions($snapshot, '2026-01-31'), 'Last reconciliation in snapshot order wins.');
    }

    public function testLegacyZeroIdentifiersDuplicateKeysAndArithmeticOrderMatchFrozenProjection(): void
    {
        $snapshot = $this->fixture();
        foreach ([0, 1, 2, 3] as $id) {
            $snapshot['movements'][] = ['id' => $id, 'account_id' => '01', 'kind' => 'income', 'amount_minor' => PHP_INT_MAX,
                'currency' => 'CZK', 'effective_on' => '2025-12-01', 'reversal_of_id' => null];
            $snapshot['allocations'][] = ['movement_id' => $id, 'amount_minor' => PHP_INT_MAX];
            $snapshot['allocations'][] = ['movement_id' => $id, 'amount_minor' => -1];
        }
        $snapshot['refund_decisions'][] = ['id' => 0, 'original_movement_id' => 0, 'amount_minor' => 200, 'currency' => 'CZK'];
        $snapshot['adjustment_events'][] = ['decision_id' => null, 'event_type' => 'decision_cancelled'];
        self::assertSame(\w10BalanceRowsBaseline($snapshot), \accountingExportFinancialBalanceRows($snapshot));
        self::assertSame(\w10FinancialExceptionsBaseline($snapshot, '2026-01-31'), \accountingExportFinancialExceptions($snapshot, '2026-01-31'));
        foreach (['accounts', 'movements', 'allocations', 'refund_decisions', 'adjustment_events', 'refund_payments', 'classifications', 'reconciliations'] as $key) {
            $snapshot[$key] = [];
        }
        self::assertSame([], \accountingExportFinancialBalanceRows($snapshot));
        self::assertSame([], \accountingExportFinancialExceptions($snapshot, '2026-01-31'));
    }

    private function fixture(): array
    {
        return ['period_from' => '2026-01-01',
            'accounts' => [
                ['id' => 1, 'code' => 'A', 'name' => 'Cash', 'opening_minor' => 10000, 'opened_on' => '2025-01-01', 'currency' => 'CZK'],
                ['id' => 2, 'code' => 'B', 'name' => 'Late', 'opening_minor' => 20000, 'opened_on' => '2026-01-10', 'currency' => 'CZK'],
            ],
            'movements' => [
                ['id' => 1, 'account_id' => 1, 'kind' => 'income', 'amount_minor' => 5000, 'currency' => 'CZK', 'effective_on' => '2025-12-31'],
                ['id' => 2, 'account_id' => 1, 'kind' => 'income', 'amount_minor' => 12345, 'currency' => 'CZK', 'effective_on' => '2026-01-01'],
                ['id' => 3, 'account_id' => 1, 'kind' => 'refund', 'amount_minor' => -2000, 'currency' => 'CZK', 'effective_on' => '2026-01-02', 'reversal_of_id' => 2],
                ['id' => 4, 'account_id' => 2, 'kind' => 'transfer', 'amount_minor' => -500, 'currency' => 'CZK', 'effective_on' => '2026-01-10'],
            ],
            'allocations' => [['movement_id' => 2, 'amount_minor' => 3000], ['movement_id' => 2, 'amount_minor' => -500]],
            'refund_decisions' => [
                ['id' => 9, 'original_movement_id' => 2, 'amount_minor' => 2000, 'currency' => 'CZK'],
                ['id' => 10, 'original_movement_id' => 2, 'correction_document_id' => '0007', 'amount_minor' => 1000, 'currency' => 'CZK'],
            ],
            'adjustment_events' => [['decision_id' => 10, 'event_type' => 'decision_cancelled']],
            'refund_payments' => [['decision_id' => 9, 'amount_minor' => 500]],
            'classifications' => [['movement_id' => 1], ['movement_id' => 2], ['movement_id' => '02']],
            'reconciliations' => [
                ['account_id' => 1, 'as_of' => '2026-01-31', 'is_closed' => 1, 'difference_minor' => 0],
                ['account_id' => 2, 'as_of' => '2026-01-31', 'is_closed' => 1, 'difference_minor' => 0],
            ]];
    }
}
