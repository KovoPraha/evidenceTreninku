<?php

declare(strict_types=1);

function w103ClubFixture(int $size, string $domain): array
{
    $s = ['period_from' => '2026-01-01', 'period_to' => '2026-01-31', 'allocations' => []];
    for ($id = 1; $id <= $size; $id++) {
        if ($domain === 'expenses') {
            $s['club_expenses'][] = ['id' => $id, 'root_id' => $id, 'state' => 'approved',
                'approved_at' => '2025-12-15', 'accepted_minor' => 10000, 'creditor_label' => 'Synthetic', 'currency' => 'CZK'];
            foreach ([['expense_claim', 1000, '2025-12-20'], ['expense_claim', 1000, '2026-01-10'],
                ['expense_claim_return', 300, '2026-01-11']] as [$type, $amount, $date]) {
                $s['allocations'][] = ['case_id' => $id, 'case_type' => $type, 'amount_minor' => $amount, 'effective_on' => $date];
            }
            $s['club_consumptions'][] = ['claim_root_id' => $id, 'advance_id' => $id, 'amount_minor' => 500, 'effective_on' => '2026-01-12'];
        } elseif ($domain === 'advances') {
            $s['club_advances'][] = ['id' => $id, 'recipient_label' => 'Synthetic', 'currency' => 'CZK'];
            foreach ([['expense_advance_out', 20000, '2025-12-20'], ['expense_advance_in', 5000, '2026-01-10']] as [$type, $amount, $date]) {
                $s['allocations'][] = ['case_id' => $id, 'case_type' => $type, 'amount_minor' => $amount, 'effective_on' => $date];
            }
            $s['club_consumptions'][] = ['claim_root_id' => $id, 'advance_id' => $id, 'amount_minor' => 500, 'effective_on' => '2026-01-12'];
            $s['club_transfers'][] = ['from_advance_id' => $id, 'to_advance_id' => $id + $size, 'amount_minor' => 1000, 'effective_on' => '2026-01-13'];
            $s['club_transfers'][] = ['from_advance_id' => $id + $size, 'to_advance_id' => $id, 'amount_minor' => 100, 'effective_on' => '2026-01-14'];
        } elseif ($domain === 'rewards') {
            $s['club_reward_claims'][] = ['id' => $id, 'kind' => 'coach', 'subject_id' => $id, 'currency' => 'CZK'];
            foreach ([[$id, 5000, '2025-12-20'], [$id + $size, 8000, '2026-01-10']] as [$version, $amount, $date]) {
                $s['club_reward_versions'][] = ['id' => $version, 'claim_id' => $id, 'amount_minor' => $amount];
                $s['club_reward_decisions'][] = ['version_id' => $version, 'outcome' => 'approved', 'decided_on' => $date];
            }
            $s['club_reward_payouts'][] = ['claim_id' => $id, 'paid_on' => '2026-01-15', 'amount_minor' => 3000];
        } elseif ($domain === 'receivables') {
            $s['club_receivables'][] = ['id' => 'shop_order:' . $id, 'case_type' => 'shop_order', 'case_id' => $id,
                'reference' => '000' . $id, 'amount_minor' => 10000, 'historical_status' => 'UNVERIFIED', 'currency' => 'CZK'];
            $s['allocations'][] = ['case_id' => $id, 'case_type' => 'shop_order', 'amount_minor' => 500, 'effective_on' => '2026-01-10'];
        } else {
            throw new RuntimeException('Unknown synthetic domain.');
        }
    }
    return $s;
}
