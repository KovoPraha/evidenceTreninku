<?php

declare(strict_types=1);
/** Ordered indexes over a single immutable accounting snapshot. */
function accountingClubBalanceIterator(array $s): Generator
{

    $allocationsByCase = [];
    foreach ($s['allocations'] as $row) {
        $allocationsByCase[(int)$row['case_id']][] = $row;
    }
    $expenseRoots = [];
    foreach ($s['club_expenses'] ?? [] as $expense) {
        if ($expense['state'] === 'approved') {
            $expenseRoots[(int) $expense['root_id']] = true;
        }
    }
    $advanceIds = [];
    foreach ($s['club_advances'] ?? [] as $advance) {
        $advanceIds[(int) $advance['id']] = true;
    }
    $consumptionsByClaim = [];
    $consumptionsByAdvance = [];
    foreach ($s['club_consumptions'] ?? [] as $row) {
        if (isset($expenseRoots[(int) $row['claim_root_id']])) {
            $consumptionsByClaim[(int)$row['claim_root_id']][] = $row;
        }
        if (isset($advanceIds[(int) $row['advance_id']])) {
            $consumptionsByAdvance[(int)$row['advance_id']][] = $row;
        }
    }
    $transfersByAdvance = [];
    foreach ($s['club_transfers'] ?? [] as $row) {
        $source = (int)$row['from_advance_id'];
        $target = (int)$row['to_advance_id'];
        if (isset($advanceIds[$source])) {
            $transfersByAdvance[$source][] = $row;
        }
        if ($target !== $source && isset($advanceIds[$target])) {
            $transfersByAdvance[$target][] = $row;
        }
    }
    $payoutsByClaim = [];
    foreach ($s['club_reward_payouts'] ?? [] as $row) {
        $payoutsByClaim[(int)$row['claim_id']][] = $row;
    }
    $from = $s['period_from'];
    $to = $s['period_to'];
    foreach ($s['club_expenses'] ?? [] as $r) {
        if ($r['state'] !== 'approved') {
            continue;
        }$root = (int)$r['root_id'];
        $approved = substr((string)$r['approved_at'], 0, 10);
        if ($approved === '' || $approved > $to) {
            continue;
        }
        $at = static function (string $date) use (
            $r,
            $root,
            $approved,
            $allocationsByCase,
            $consumptionsByClaim
        ): int {
            $v = $approved <= $date ? (int)$r['accepted_minor'] : 0;
            foreach ($allocationsByCase[$root] ?? [] as $a) {
                if ((int)$a['case_id'] === $root &&
                  $a['effective_on'] <= $date) {
                    if ($a['case_type'] === 'expense_claim') {
                        $v -= (int)$a['amount_minor'];
                    }if ($a['case_type'] === 'expense_claim_return') {
                        $v += (int)$a['amount_minor'];
                    }
                }
            }foreach ($consumptionsByClaim[$root] ?? [] as $c) {
                if ((int)$c['claim_root_id'] === $root &&
                  $c['effective_on'] <= $date) {
                    $v -= (int)$c['amount_minor'];
                }
            }return $v;
        };
        $opening = isset($r['opening_remaining_minor']) ? (int)$r['opening_remaining_minor'] : $at((new DateTimeImmutable($from))->modify('-1 day')->format('Y-m-d'));
        $closing = isset($r['remaining_minor']) ? (int)$r['remaining_minor'] : $at($to);
        yield [($closing < 0 ? 'Pohledávka vratky výdaje' : 'Závazek výdaje'),
          (string)$r['id'],
          $r['creditor_label'].'; schválená verze #'.$r['id'],
          $opening / 100,
          ($closing - $opening) / 100,
          $closing / 100,
          $r['currency']];
    }
    foreach ($s['club_advances'] ?? [] as $r) {
        $id = (int)$r['id'];
        $at = static function (string $date) use (
            $id,
            $allocationsByCase,
            $consumptionsByAdvance,
            $transfersByAdvance
        ): int {
            $v = 0;
            foreach ($allocationsByCase[$id] ?? [] as $a) {
                if ((int)$a['case_id'] === $id &&
                   $a['effective_on'] <= $date) {
                    if ($a['case_type'] === 'expense_advance_out') {
                        $v += (int)$a['amount_minor'];
                    }if ($a['case_type'] === 'expense_advance_in') {
                        $v -= (int)$a['amount_minor'];
                    }
                }
            }foreach ($consumptionsByAdvance[$id] ?? [] as $c) {
                if ((int)$c['advance_id'] === $id &&
                   $c['effective_on'] <= $date) {
                    $v -= (int)$c['amount_minor'];
                }
            }foreach ($transfersByAdvance[$id] ?? [] as $t) {
                if ($t['effective_on'] <= $date) {
                    if ((int)$t['from_advance_id'] === $id) {
                        $v -= (int)$t['amount_minor'];
                    }if ((int)$t['to_advance_id'] === $id) {
                        $v += (int)$t['amount_minor'];
                    }
                }
            }return $v;
        };
        $opening = $at((new DateTimeImmutable($from))->modify('-1 day')->format('Y-m-d'));
        $closing = $at($to);
        yield ['Nevyúčtovaná záloha',
        (string)$id,
        $r['recipient_label'],
        $opening / 100,
        ($closing - $opening) / 100,
        $closing / 100,
        $r['currency']];
    }
    $approved = [];
    foreach ($s['club_reward_decisions'] ?? [] as $d) {
        if ($d['outcome'] === 'approved' && $d['decided_on'] <= $to) {
            $approved[(int)$d['version_id']] = $d;
        }
    }
    $versions = [];
    foreach ($s['club_reward_versions'] ?? [] as $v) {
        $versions[(int)$v['id']] = $v;
    }
    $approvedByClaim = [];
    foreach ($approved as $id => $decision) {
        if (isset($versions[$id])) {
            $approvedByClaim[(int)$versions[$id]['claim_id']][$id] = $decision;
        }
    }
    foreach ($s['club_reward_claims'] ?? [] as $c) {
        $at = static function (string$date) use (
            $approvedByClaim,
            $versions,
            $payoutsByClaim,
            $c
        ): int {
            $amount = 0;
            $latest = 0;
            foreach ($approvedByClaim[(int)$c['id']] ?? [] as $id => $d) {
                if ($d['decided_on'] <= $date &&
                   isset($versions[$id]) &&
                   (int)$versions[$id]['claim_id'] === (int)$c['id'] &&
                   $id > $latest) {
                    $latest = $id;
                    $amount = (int)$versions[$id]['amount_minor'];
                }
            }foreach ($payoutsByClaim[(int)$c['id']] ?? [] as $p) {
                if ((int)$p['claim_id'] === (int)$c['id'] &&
                   $p['paid_on'] <= $date) {
                    $amount -= (int)$p['amount_minor'];
                }
            }return$amount;
        };
        $opening = $at((new DateTimeImmutable($from))->modify('-1 day')->format('Y-m-d'));
        $closing = $at($to);
        yield ['Závazek odměny',
        (string)$c['id'],
        ($c['kind'] === 'coach' ? 'Trenér' : 'Sportovec').' #'.$c['subject_id'],
        $opening / 100,
        ($closing - $opening) / 100,
        $closing / 100,
        $c['currency']];
    }
    foreach ($s['club_receivables'] ?? [] as $r) {
        $allocated = 0;
        foreach ($allocationsByCase[(int)$r['case_id']] ?? [] as $a) {
            if ($a['case_type'] === $r['case_type'] &&
               (int)$a['case_id'] === (int)$r['case_id']) {
                $allocated += (int)$a['amount_minor'];
            }
        }yield ['Pohledávka k ověření',
           $r['id'],
           $r['reference'].'; nominální '.number_format(
               (int)$r['amount_minor'] / 100,
               2,
               ',',
               ' '
           ).'; příděly '.number_format(
               $allocated / 100,
               2,
               ',',
               ' '
           ).'; '.$r['historical_status'],
           null,
           null,
           null,
           $r['currency']];
    }

}
