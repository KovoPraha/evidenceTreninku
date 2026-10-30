<?php

declare(strict_types=1);

require_once __DIR__ . '/FamilyOverviewPageFixture.php';
require_once __DIR__ . '/AccountingLedgerReadBaseline.php';
require_once dirname(__DIR__, 2) . '/includes/accounting_ledger.php';
require_once dirname(__DIR__, 2) . '/includes/sportovec_editor_details.php';

$schema = 'w103_finance_' . bin2hex(random_bytes(8));
$admin = w72Connection();
$admin->exec('CREATE DATABASE `' . $schema . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
try {
    $pdo = w72Connection($schema, true);
    w72Seed($pdo);
    $plain = w72Connection($schema);
    $pdo->exec("INSERT INTO accounting_accounts(id,code,name,kind,currency,opened_on,opening_minor,evidence,reason,actor_trainer_id,recorded_at) "
        . "VALUES(1,'W103','Synthetic cash','cash','CZK','2025-01-01',100,'Synthetic','Synthetic',7,'2025-01-01'),"
        . "(2,'W103-FOREIGN','Other account','cash','CZK','2025-01-01',999,'Synthetic','Synthetic',7,'2025-01-01')");
    $movement = $pdo->prepare("INSERT INTO accounting_movements(id,account_id,kind,amount_minor,currency,effective_on,recorded_at,source_context,"
        . "source_reference,request_key,fingerprint,evidence,reason,actor_trainer_id) VALUES(?,?,'income',100,'CZK',?,'2026-01-10','synthetic',?,?,?,'Synthetic','Synthetic',7)");
    $receipt = $pdo->prepare("INSERT INTO club_finance_receipts(id,sportovec_id,kind,reference,amount_minor,paid_on,confirmed_by_trainer_id,reason) "
        . "VALUES(?,?,'manual_bank',?,100,'2026-01-10',7,'Synthetic')");
    $charge = $pdo->prepare("INSERT INTO club_member_charges(id,sportovec_id,public_code,charge_type,title_snapshot,amount_minor,currency,status,source_system) "
        . "VALUES(?,?,?,'member_fee','Synthetic',100,'CZK','unpaid','synthetic')");
    $allocation = $pdo->prepare('INSERT INTO club_finance_allocations(receipt_id,charge_id,amount_minor) VALUES(?,?,100)');
    $median = static function (array $values): float {
        sort($values);
        $middle = intdiv(count($values), 2);
        return ($values[$middle - 1] + $values[$middle]) / 2;
    };
    $measure = static function (string $label, callable $call) use ($pdo, $plain, $median): array {
        $pdo->profile = [];
        $pdo->profiling = true;
        $value = $call($pdo);
        $pdo->profiling = false;
        $counts = ['sql' => count($pdo->profile), 'rows' => array_sum(array_column($pdo->profile, 'fetched_rows')),
            'metadata_sql' => count(array_filter($pdo->profile, static fn (array $q): bool => str_starts_with($q['sql'], 'SELECT 1 FROM ')))];
        $samples = [];
        for ($sample = -2; $sample < 10; $sample++) {
            memory_reset_peak_usage();
            $memory = memory_get_usage();
            $start = hrtime(true);
            $again = $call($plain);
            $ms = (hrtime(true) - $start) / 1e6;
            if ($again !== $value) {
                throw new RuntimeException('Unstable financial read.');
            }
            if ($sample >= 0) {
                $samples[] = ['ms' => $ms, 'peak_bytes' => memory_get_peak_usage() - $memory];
            }
            unset($again);
        }
        return ['label' => $label] + $counts + ['bytes' => strlen(json_encode($value, JSON_THROW_ON_ERROR)),
            'median_ms' => $median(array_column($samples, 'ms')), 'median_peak_bytes' => $median(array_column($samples, 'peak_bytes')),
            'warmups' => 2, 'samples' => $samples];
    };
    $results = [];
    $previous = 0;
    foreach ([1000, 10000, 100000] as $size) {
        $pdo->beginTransaction();
        for ($id = $previous + 1; $id <= $size; $id++) {
            $movement->execute([$id, $id % 2 ? 1 : 2, $id % 4 === 1 ? '2025-12-15' : '2026-01-10', 'W103-' . $id, 'W103-' . $id, hash('sha256', (string) $id)]);
            if ($id <= 10000) {
                $receipt->execute([$id, $id % 2 ? 1 : 999, 'W103-' . $id]);
                $charge->execute([$id, $id % 2 ? 1 : 999, 'W103-' . $id]);
                $allocation->execute([$id, $id]);
            }
        }
        $pdo->commit();
        $balance = accountingLedgerBalance($pdo, 1, '2026-01-31');
        if ($balance['closing_minor'] !== 100 + $size / 2 * 100
            || accountingLedgerBalance($pdo, 1, '2025-12-31')['movements_minor'] !== $size / 4 * 100
            || accountingLedgerBalance($pdo, 1, '2026-01-31', '2026-01-01 00:00:00')['movements_minor'] !== 0) {
            throw new RuntimeException('Ledger account/date/known-at isolation failed.');
        }
        $results[] = ['size' => $size] + $measure('ledger', static fn (PDO $db): array => accountingLedgerBalance($db, 1, '2026-01-31'));
        $results[] = ['size' => $size] + $measure('ledger_known_at', static fn (PDO $db): array => accountingLedgerBalance($db, 1, '2026-01-31', '2026-01-31 23:59:59'));
        if ($size <= 10000) {
            $history = sportovecEditorPayments($pdo, 1);
            foreach (['charges', 'receipts', 'allocations'] as $section) {
                if (count($history[$section]) !== $size / 2) {
                    throw new RuntimeException('Person history count changed.');
                }
                foreach ($history[$section] as $row) {
                    if ((int) ($row['id'] ?? $row['receipt_id']) % 2 !== 1) {
                        throw new RuntimeException('Foreign person leaked into history.');
                    }
                }
            }
            $results[] = ['size' => $size, 'scope' => 'charges, receipts, allocations; empty orders']
                + $measure('person_history', static fn (PDO $db): array => sportovecEditorPayments($db, 1));
            // This is a finance-only read, never source adoption or provider activation.
            if (w104PendingSourcesBaseline($pdo, 200) !== accountingLedgerPendingSources($pdo, 200)) {
                throw new RuntimeException('Frozen pending-source projection differs.');
            }
            $results[] = ['size' => $size, 'limit' => 200]
                + $measure('pending_sources_baseline', static fn (PDO $db): array => w104PendingSourcesBaseline($db, 200));
            $current = ['size' => $size, 'limit' => 200]
                + $measure('pending_sources', static fn (PDO $db): array => accountingLedgerPendingSources($db, 200));
            if ($current['metadata_sql'] > 7) {
                throw new RuntimeException('Pending-source metadata budget exceeded.');
            }
            $results[] = $current;
        }
        $previous = $size;
    }
    $explain = $pdo->query("EXPLAIN SELECT COALESCE(SUM(amount_minor),0) FROM accounting_movements WHERE account_id=1 AND effective_on<='2026-01-31'")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['production_mutations' => false, 'schema' => 'complete migration catalog; random localhost schema; FK enabled',
        'results' => $results, 'ledger_explain_100000' => $explain], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
} finally {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $admin->exec('DROP DATABASE `' . $schema . '`');
}
