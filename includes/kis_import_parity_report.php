<?php
declare(strict_types=1);

require_once __DIR__ . '/kis_parity_contract.php';

const KIS_IMPORT_PARITY_CONTRACT = 'kis-import-parity-v1';

/** @return list<string> */
function kisImportParityRosterNames(?string $value): array
{
    $items = array_values(array_filter(array_map('trim', explode(',', (string)$value)), static fn(string $item): bool => $item !== ''));
    sort($items, SORT_NATURAL | SORT_FLAG_CASE);
    return $items;
}

function kisImportParityMoneyMinor(mixed $value): int
{
    return (int)round((float)$value * 100);
}

/** @return array<string,mixed> */
function kisImportBuildParityReport(PDO $pdo, int $runId): array
{
    $statement = $pdo->prepare('SELECT * FROM kis_import_runs WHERE id=?');
    $statement->execute([$runId]);
    $run = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$run) {
        throw new InvalidArgumentException('KIS import run nebyl nalezen.');
    }
    $preview = kisImportStoredPreviewReport($run);
    $field = kisFieldContractStoredReport($run);
    if ($preview === null || $field === null) {
        throw new RuntimeException('KIS parity vyzaduje platny preview i field kontrakt.');
    }

    $rows = $pdo->prepare(
        'SELECT ir.*,im.match_status,im.sportovec_id,'
        . 's.narozeni AS target_narozeni,s.kis_external_id AS target_kis_external_id,'
        . 's.kis_aktivni AS target_kis_aktivni,s.kis_platebne_aktivni AS target_kis_platebne_aktivni,'
        . 's.kis_neuhrazeno AS target_kis_neuhrazeno,s.kis_posledni_uhrada AS target_kis_posledni_uhrada,'
        . 's.kis_soupisky AS target_kis_soupisky '
        . 'FROM kis_import_rows ir LEFT JOIN kis_import_matches im ON im.row_id=ir.id AND im.run_id=ir.run_id '
        . 'LEFT JOIN sportovci s ON s.id=im.sportovec_id WHERE ir.run_id=? ORDER BY ir.id'
    );
    $rows->execute([$runId]);

    $parityRows = [];
    $matchedTargetIds = [];
    $domains = [
        'persons' => ['source_rows' => 0, 'exact_same' => 0, 'creates' => 0],
        'memberships' => ['active' => 0, 'inactive' => 0],
        'rosters' => ['people_with_rosters' => 0, 'assignment_count' => 0],
        'payment_signals' => ['active_people' => 0, 'people_with_debt' => 0, 'paid_rows' => 0, 'open_rows' => 0],
    ];
    foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $index => $row) {
        $domains['persons']['source_rows']++;
        ((int)$row['kis_aktivni'] === 1) ? $domains['memberships']['active']++ : $domains['memberships']['inactive']++;
        $rosterCount = max(0, (int)($row['kis_roster_count'] ?? 0));
        if ($rosterCount > 0) $domains['rosters']['people_with_rosters']++;
        $domains['rosters']['assignment_count'] += $rosterCount;
        if ((int)$row['kis_platebne_aktivni'] === 1) $domains['payment_signals']['active_people']++;
        if (kisImportParityMoneyMinor($row['kis_neuhrazeno']) > 0) $domains['payment_signals']['people_with_debt']++;
        $domains['payment_signals']['paid_rows'] += max(0, (int)($row['kis_payment_paid_count'] ?? 0));
        $domains['payment_signals']['open_rows'] += max(0, (int)($row['kis_payment_open_count'] ?? 0));

        $sourceRef = 'source:' . ($index + 1);
        $status = (string)($row['match_status'] ?? '');
        if ($status === 'new') {
            $parityRows[] = ['source_ref' => $sourceRef, 'category' => 'new', 'reason' => 'no_candidate'];
            $domains['persons']['creates']++;
            continue;
        }
        if ($status !== 'matched' || (int)($row['sportovec_id'] ?? 0) < 1) {
            $category = in_array($status, ['ambiguous', 'conflict', 'ignored'], true) ? $status : 'unexplained';
            $reason = ['ambiguous' => 'multiple_candidates', 'conflict' => 'strong_signal_conflict', 'ignored' => 'explicitly_ignored'][$category] ?? 'missing_match_result';
            $parityRows[] = ['source_ref' => $sourceRef, 'category' => $category, 'reason' => $reason];
            continue;
        }

        $targetId = (int)$row['sportovec_id'];
        $matchedTargetIds[$targetId] = true;
        $same = kisFieldNormalizeExternalId($row['kis_external_id'] ?? '') === kisFieldNormalizeExternalId($row['target_kis_external_id'] ?? '')
            && trim((string)($row['narozeni'] ?? '')) === trim((string)($row['target_narozeni'] ?? ''))
            && (int)$row['kis_aktivni'] === (int)$row['target_kis_aktivni']
            && (int)$row['kis_platebne_aktivni'] === (int)$row['target_kis_platebne_aktivni']
            && kisImportParityMoneyMinor($row['kis_neuhrazeno']) === kisImportParityMoneyMinor($row['target_kis_neuhrazeno'])
            && trim((string)($row['kis_posledni_uhrada'] ?? '')) === trim((string)($row['target_kis_posledni_uhrada'] ?? ''))
            && kisImportParityRosterNames($row['kis_soupisky'] ?? '') === kisImportParityRosterNames($row['target_kis_soupisky'] ?? '');
        $category = $same ? 'matched_same' : 'matched_different';
        $parityRows[] = [
            'source_ref' => $sourceRef,
            'category' => $category,
            'reason' => $same ? 'signals_equal' : 'signals_differ',
            'target_ref' => 'sportovec:' . $targetId,
        ];
        if ($same) $domains['persons']['exact_same']++;
    }

    $missingStatement = $pdo->query("SELECT id FROM sportovci WHERE kis_external_id IS NOT NULL AND kis_external_id<>''");
    $missing = 0;
    foreach ($missingStatement->fetchAll(PDO::FETCH_COLUMN) as $targetId) {
        if (!isset($matchedTargetIds[(int)$targetId])) $missing++;
    }
    $base = kisParityContractEvaluate([
        'contract' => KIS_PARITY_CONTRACT,
        'run_ref' => 'run:' . $runId,
        'missing_in_run_count' => $missing,
        'rows' => $parityRows,
    ]);

    $coverageBlockers = [];
    if (($domains['payment_signals']['paid_rows'] + $domains['payment_signals']['open_rows']) > 0) {
        $coverageBlockers[] = 'payment_prescription_target_contract_missing';
    }
    $canonical = [
        'contract' => KIS_IMPORT_PARITY_CONTRACT,
        'status' => $base['status'] === 'valid' && $coverageBlockers === [] ? 'cutover_ready' : 'blocked',
        'cutover_ready' => $base['status'] === 'valid' && $coverageBlockers === [],
        'source_fingerprints' => [
            'preview' => $preview['fingerprint'],
            'field' => $field['fingerprint'],
        ],
        'summary' => [
            'total_rows' => $base['summary']['total_rows'],
            'row_blockers' => $base['summary']['blocker_rows'],
            'coverage_blockers' => count($coverageBlockers),
            'total_blockers' => $base['summary']['blocker_rows'] + count($coverageBlockers),
            'missing_in_run' => $base['summary']['missing_in_run'],
        ],
        'domains' => $domains,
        'coverage' => [
            'persons' => 'compared',
            'membership_status' => 'compared',
            'roster_snapshot' => 'compared',
            'payment_aggregates' => 'compared',
            'payment_prescriptions' => $coverageBlockers === [] ? 'not_present' : 'source_projection_only',
        ],
        'coverage_blockers' => $coverageBlockers,
        'rows' => $base['rows'],
    ];
    $canonical['fingerprint'] = hash('sha256', json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    return $canonical;
}

/** @return array<string,mixed> */
function kisImportFinalizeParityReport(PDO $pdo, int $runId): array
{
    $report = kisImportBuildParityReport($pdo, $runId);
    $statement = $pdo->prepare('UPDATE kis_import_runs SET parity_contract_version=?,parity_fingerprint=?,parity_report_json=?,parity_blockers=?,parity_generated_at=CURRENT_TIMESTAMP WHERE id=?');
    $statement->execute([KIS_IMPORT_PARITY_CONTRACT, $report['fingerprint'], kisImportJson($report), $report['summary']['total_blockers'], $runId]);
    return $report;
}

/** @return array<string,mixed>|null */
function kisImportStoredParityReport(array $run): ?array
{
    if (!is_string($run['parity_report_json'] ?? null) || trim((string)$run['parity_report_json']) === '') return null;
    try {
        $report = json_decode((string)$run['parity_report_json'], true, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return null;
    }
    if (!is_array($report) || ($report['contract'] ?? null) !== KIS_IMPORT_PARITY_CONTRACT
        || !is_string($report['fingerprint'] ?? null)
        || !hash_equals((string)($run['parity_fingerprint'] ?? ''), (string)$report['fingerprint'])) return null;
    return $report;
}
