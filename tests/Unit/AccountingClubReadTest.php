<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/accounting_club_read.php';
require_once dirname(__DIR__) . '/Support/AccountingClubBalanceBaseline.php';
require_once dirname(__DIR__) . '/Support/AccountingClubReadFixture.php';

final class AccountingClubReadTest extends TestCase
{
    public function testOrderedIndexesRetainFinancialMeaningAcrossDomains(): void
    {
        foreach (['expenses' => [90, -12, 78], 'advances' => [200, -64, 136],
            'rewards' => [50, 0, 50], 'receivables' => [null, null, null]] as $domain => $expected) {
            $snapshot = \w103ClubFixture(7, $domain);
            $copy = $snapshot;
            $rows = iterator_to_array(\accountingClubBalanceIterator($snapshot), false);
            self::assertSame(\w103ClubBalanceBaseline($snapshot), $rows);
            self::assertSame($copy, $snapshot);
            self::assertCount(7, $rows);
            foreach ($rows as $row) {
                self::assertSame($expected, array_slice($row, 3, 3));
            }
        }
    }

    public function testVersionOrderBoundariesSelfTransfersAndExplicitBalancesRemainIntact(): void
    {
        $snapshot = array_replace_recursive(\w103ClubFixture(2, 'expenses'), \w103ClubFixture(2, 'rewards'));
        $snapshot['club_expenses'][0]['opening_remaining_minor'] = 77;
        $snapshot['club_expenses'][0]['remaining_minor'] = -33;
        $snapshot['club_expenses'][1]['approved_at'] = '2026-02-01';
        $snapshot['club_reward_decisions'][] = ['version_id' => 1, 'outcome' => 'approved', 'decided_on' => '2026-01-31'];
        $snapshot['club_reward_decisions'][] = ['version_id' => 3, 'outcome' => 'returned', 'decided_on' => '2026-01-31'];
        $snapshot['club_reward_versions'][] = ['id' => 3, 'claim_id' => 2, 'amount_minor' => 800];
        $snapshot['club_reward_payouts'][] = ['claim_id' => 1, 'paid_on' => '2026-02-01', 'amount_minor' => 999];
        self::assertSame(\w103ClubBalanceBaseline($snapshot), iterator_to_array(\accountingClubBalanceIterator($snapshot), false));
        $snapshot = \w103ClubFixture(2, 'advances');
        $snapshot['club_transfers'][] = ['from_advance_id' => 1, 'to_advance_id' => 1, 'amount_minor' => PHP_INT_MAX, 'effective_on' => '2026-01-31'];
        $snapshot['club_transfers'][] = ['from_advance_id' => 1, 'to_advance_id' => 2, 'amount_minor' => 100, 'effective_on' => '2026-02-01'];
        self::assertSame(\w103ClubBalanceBaseline($snapshot), iterator_to_array(\accountingClubBalanceIterator($snapshot), false));
        self::assertSame([], iterator_to_array(\accountingClubBalanceIterator(\w103ClubFixture(0, 'advances')), false));
    }
}
