<?php

declare(strict_types=1);
function w103ClubBalanceBaseline(array $s): array
{
    $rows = [];
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
            $s
        ): int {
            $v = $approved <= $date ? (int)$r['accepted_minor'] : 0;
            foreach ($s['allocations'] as $a) {
                if ((int)$a['case_id'] === $root &&
                  $a['effective_on'] <= $date) {
                    if ($a['case_type'] === 'expense_claim') {
                        $v -= (int)$a['amount_minor'];
                    }if ($a['case_type'] === 'expense_claim_return') {
                        $v += (int)$a['amount_minor'];
                    }
                }
            }foreach ($s['club_consumptions'] ?? [] as $c) {
                if ((int)$c['claim_root_id'] === $root &&
                  $c['effective_on'] <= $date) {
                    $v -= (int)$c['amount_minor'];
                }
            }return $v;
        };
        $opening = isset($r['opening_remaining_minor']) ? (int)$r['opening_remaining_minor'] : $at((new DateTimeImmutable($from))->modify('-1 day')->format('Y-m-d'));
        $closing = isset($r['remaining_minor']) ? (int)$r['remaining_minor'] : $at($to);
        $rows[] = [($closing < 0 ? 'Pohledávka vratky výdaje' : 'Závazek výdaje'),
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
            $s
        ): int {
            $v = 0;
            foreach ($s['allocations'] as $a) {
                if ((int)$a['case_id'] === $id &&
                   $a['effective_on'] <= $date) {
                    if ($a['case_type'] === 'expense_advance_out') {
                        $v += (int)$a['amount_minor'];
                    }if ($a['case_type'] === 'expense_advance_in') {
                        $v -= (int)$a['amount_minor'];
                    }
                }
            }foreach ($s['club_consumptions'] ?? [] as $c) {
                if ((int)$c['advance_id'] === $id &&
                   $c['effective_on'] <= $date) {
                    $v -= (int)$c['amount_minor'];
                }
            }foreach ($s['club_transfers'] ?? [] as $t) {
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
        $rows[] = ['Nevyúčtovaná záloha',
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
    foreach ($s['club_reward_claims'] ?? [] as $c) {
        $at = static function (string$date) use (
            $approved,
            $versions,
            $s,
            $c
        ): int {
            $amount = 0;
            $latest = 0;
            foreach ($approved as $id => $d) {
                if ($d['decided_on'] <= $date &&
                   isset($versions[$id]) &&
                   (int)$versions[$id]['claim_id'] === (int)$c['id'] &&
                   $id > $latest) {
                    $latest = $id;
                    $amount = (int)$versions[$id]['amount_minor'];
                }
            }foreach ($s['club_reward_payouts'] ?? [] as $p) {
                if ((int)$p['claim_id'] === (int)$c['id'] &&
                   $p['paid_on'] <= $date) {
                    $amount -= (int)$p['amount_minor'];
                }
            }return$amount;
        };
        $opening = $at((new DateTimeImmutable($from))->modify('-1 day')->format('Y-m-d'));
        $closing = $at($to);
        $rows[] = ['Závazek odměny',
        (string)$c['id'],
        ($c['kind'] === 'coach' ? 'Trenér' : 'Sportovec').' #'.$c['subject_id'],
        $opening / 100,
        ($closing - $opening) / 100,
        $closing / 100,
        $c['currency']];
    }
    foreach ($s['club_receivables'] ?? [] as $r) {
        $allocated = 0;
        foreach ($s['allocations'] as $a) {
            if ($a['case_type'] === $r['case_type'] &&
               (int)$a['case_id'] === (int)$r['case_id']) {
                $allocated += (int)$a['amount_minor'];
            }
        }$rows[] = ['Pohledávka k ověření',
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
    return$rows;
}
