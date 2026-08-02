<?php
declare(strict_types=1);

const KIS_PARITY_CONTRACT = 'kis-parity-v1';
const KIS_PARITY_MAX_ROWS = 10000;

/**
 * Validate and deterministically summarize a synthetic, already-classified KIS
 * parity payload. This function is deliberately pure: it has no DB, session,
 * config, network or filesystem dependency.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function kisParityContractEvaluate(array $input): array
{
    kisParityAssertObjectKeys($input, ['contract', 'run_ref', 'missing_in_run_count', 'rows'], 'root');

    if (($input['contract'] ?? null) !== KIS_PARITY_CONTRACT) {
        throw new InvalidArgumentException('root.contract must be kis-parity-v1');
    }
    $runRef = kisParityValidateRef($input['run_ref'] ?? null, 'root.run_ref');
    $missingInRun = $input['missing_in_run_count'] ?? null;
    if (!is_int($missingInRun) || $missingInRun < 0) {
        throw new InvalidArgumentException('root.missing_in_run_count must be a non-negative integer');
    }

    $rows = $input['rows'] ?? null;
    if (!is_array($rows) || !array_is_list($rows)) {
        throw new InvalidArgumentException('root.rows must be a JSON array');
    }
    if (count($rows) > KIS_PARITY_MAX_ROWS) {
        throw new InvalidArgumentException('root.rows exceeds the 10000 row limit');
    }

    $categories = [
        'matched_same',
        'matched_different',
        'new',
        'ambiguous',
        'conflict',
        'invalid',
        'ignored',
        'unexplained',
    ];
    $reasonByCategory = [
        'matched_same' => 'signals_equal',
        'matched_different' => 'signals_differ',
        'new' => 'no_candidate',
        'ambiguous' => 'multiple_candidates',
        'conflict' => 'strong_signal_conflict',
        'invalid' => 'invalid_input',
        'ignored' => 'explicitly_ignored',
        'unexplained' => 'missing_match_result',
    ];

    $canonicalRows = [];
    $seenSourceRefs = [];
    $targetRows = [];
    foreach ($rows as $index => $row) {
        $path = 'root.rows[' . $index . ']';
        if (!is_array($row) || array_is_list($row)) {
            throw new InvalidArgumentException($path . ' must be a JSON object');
        }
        kisParityAssertObjectKeys($row, ['source_ref', 'category', 'target_ref', 'reason'], $path);

        $sourceRef = kisParityValidateRef($row['source_ref'] ?? null, $path . '.source_ref');
        if (isset($seenSourceRefs[$sourceRef])) {
            throw new InvalidArgumentException($path . '.source_ref must be unique');
        }
        $seenSourceRefs[$sourceRef] = true;

        $category = $row['category'] ?? null;
        if (!is_string($category) || !in_array($category, $categories, true)) {
            throw new InvalidArgumentException($path . '.category is not canonical');
        }
        $reason = $row['reason'] ?? null;
        if (!is_string($reason) || $reason !== $reasonByCategory[$category]) {
            throw new InvalidArgumentException($path . '.reason does not match its category');
        }

        $needsTarget = in_array($category, ['matched_same', 'matched_different'], true);
        if ($needsTarget) {
            $targetRef = kisParityValidateRef($row['target_ref'] ?? null, $path . '.target_ref');
        } else {
            if (array_key_exists('target_ref', $row)) {
                throw new InvalidArgumentException($path . '.target_ref is allowed only for matched rows');
            }
            $targetRef = null;
        }

        $canonical = [
            'source_ref' => $sourceRef,
            'category' => $category,
            'reason' => $reason,
        ];
        if ($targetRef !== null) {
            $canonical['target_ref'] = $targetRef;
            $targetRows[$targetRef][] = count($canonicalRows);
        }
        $canonicalRows[] = $canonical;
    }

    // Více zdrojových řádků nesmí tiše směřovat na stejný kanonický cíl.
    foreach ($targetRows as $indexes) {
        if (count($indexes) < 2) {
            continue;
        }
        foreach ($indexes as $index) {
            $canonicalRows[$index]['category'] = 'conflict';
            $canonicalRows[$index]['reason'] = 'duplicate_target';
        }
    }

    usort(
        $canonicalRows,
        static fn(array $a, array $b): int => strcmp($a['source_ref'], $b['source_ref'])
    );

    $counts = array_fill_keys($categories, 0);
    foreach ($canonicalRows as $row) {
        $counts[$row['category']]++;
    }

    $blockerCategories = ['matched_different', 'new', 'ambiguous', 'conflict', 'invalid', 'unexplained'];
    $blockerRows = 0;
    foreach ($blockerCategories as $category) {
        $blockerRows += $counts[$category];
    }

    return [
        'contract' => KIS_PARITY_CONTRACT,
        'run_ref' => $runRef,
        'status' => $blockerRows === 0 ? 'valid' : 'blocked',
        'summary' => [
            'total_rows' => count($canonicalRows),
            'classified_rows' => count($canonicalRows),
            'blocker_rows' => $blockerRows,
            'counts' => $counts,
            'missing_in_run' => [
                'count' => $missingInRun,
                'informational_only' => true,
                'archive_action' => 'never',
            ],
        ],
        'rows' => $canonicalRows,
    ];
}

/** @param array<string, mixed> $value */
function kisParityAssertObjectKeys(array $value, array $allowedKeys, string $path): void
{
    foreach (array_keys($value) as $key) {
        if (!is_string($key) || !in_array($key, $allowedKeys, true)) {
            throw new InvalidArgumentException($path . ' contains an unsupported field');
        }
    }
}

function kisParityValidateRef(mixed $value, string $path): string
{
    if (!is_string($value) || preg_match('/^[A-Za-z0-9][A-Za-z0-9._:\/-]{0,127}$/D', $value) !== 1) {
        throw new InvalidArgumentException($path . ' must be an opaque non-PII reference');
    }
    return $value;
}
