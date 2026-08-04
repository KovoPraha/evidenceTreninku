<?php
declare(strict_types=1);

require_once __DIR__ . '/kis_match_lib.php';
require_once __DIR__ . '/kis_source_archive.php';
require_once __DIR__ . '/kis_import_field_contract.php';
require_once __DIR__ . '/kis_import_parity_report.php';

const KIS_IMPORT_PREVIEW_CONTRACT = 'kis-import-preview-v2';

function kisImportJson(array $value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
}

function kisImportCreateRun(PDO $pdo, array $people, array $meta, array $warnings, array $sourceNames, ?int $userId, array $sourceArtifactIds = []): int
{
    $stats = [
        'rows' => count($people),
        'warnings' => count($warnings),
        'created_from' => $meta,
    ];
    $ownsTransaction = !$pdo->inTransaction();
    $savepoint = 'kis_import_create_run';

    if ($ownsTransaction) {
        $pdo->beginTransaction();
    } else {
        $pdo->exec('SAVEPOINT ' . $savepoint);
    }

    try {
        $manifest = $sourceArtifactIds === [] ? null : kisSourceManifest($pdo, $sourceArtifactIds);
        $hasManifestColumn = kisImportColumnExists($pdo, 'kis_import_runs', 'source_manifest_json');
        if ($manifest !== null && !$hasManifestColumn) {
            throw new RuntimeException('KIS import source manifest migration is missing.');
        }
        $columns = 'created_by,status,source_users,source_payments,source_rosters,stats_json,warnings_json';
        $values = ":created_by,'preview',:source_users,:source_payments,:source_rosters,:stats_json,:warnings_json";
        if ($hasManifestColumn) {
            $columns .= ',source_manifest_json';
            $values .= ',:source_manifest_json';
        }
        $stmt = $pdo->prepare('INSERT INTO kis_import_runs (' . $columns . ') VALUES (' . $values . ')');
        $parameters = [
            ':created_by' => $userId,
            ':source_users' => $sourceNames['users'] ?? null,
            ':source_payments' => $sourceNames['payments'] ?? null,
            ':source_rosters' => $sourceNames['rosters'] ?? null,
            ':stats_json' => kisImportJson($stats),
            ':warnings_json' => kisImportJson($warnings),
        ];
        if ($hasManifestColumn) {
            $parameters[':source_manifest_json'] = $manifest === null ? null : kisImportJson($manifest);
        }
        $stmt->execute($parameters);
        $runId = (int)$pdo->lastInsertId();

        kisImportStoreRowsAndMatches($pdo, $runId, $people, $stats);
        if (kisImportColumnExists($pdo, 'kis_import_runs', 'preview_report_json')) {
            kisImportFinalizePreview($pdo, $runId);
        }
        if (kisImportColumnExists($pdo, 'kis_import_runs', 'field_contract_report_json')) {
            kisImportFinalizeFieldContract($pdo, $runId, $people, $meta, $warnings);
        }
        if (kisImportColumnExists($pdo, 'kis_import_runs', 'parity_report_json')) {
            kisImportFinalizeParityReport($pdo, $runId);
        }

        if ($ownsTransaction) {
            $pdo->commit();
        } else {
            $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
        }
        return $runId;
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        } elseif (!$ownsTransaction && $pdo->inTransaction()) {
            $pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
            $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
        }
        throw $e;
    }
}

/** @return array<string,mixed> */
function kisImportFinalizeFieldContract(PDO $pdo, int $runId, array $people, array $meta, array $warnings): array
{
    $report = kisFieldContractEvaluate($people, $meta, $warnings);
    $statement = $pdo->prepare(
        'UPDATE kis_import_runs SET field_contract_version=?,field_contract_fingerprint=?,field_contract_report_json=?,field_contract_blockers=? WHERE id=?'
    );
    $statement->execute([
        KIS_IMPORT_FIELD_CONTRACT,
        $report['fingerprint'],
        kisImportJson($report),
        $report['summary']['total_blockers'],
        $runId,
    ]);
    if ($statement->rowCount() !== 1) {
        $check = $pdo->prepare('SELECT field_contract_fingerprint FROM kis_import_runs WHERE id=?');
        $check->execute([$runId]);
        if (!hash_equals((string)$report['fingerprint'], (string)$check->fetchColumn())) {
            throw new RuntimeException('Datovy kontrakt KIS importu se nepodarilo ulozit.');
        }
    }
    return $report;
}

/**
 * Build a deterministic, non-PII report for one stored preview. It deliberately
 * uses opaque row/database references and fixed reason codes only.
 *
 * @return array<string,mixed>
 */
function kisImportBuildPreviewReport(PDO $pdo, int $runId): array
{
    $runStatement = $pdo->prepare('SELECT source_manifest_json FROM kis_import_runs WHERE id=?');
    $runStatement->execute([$runId]);
    $run = $runStatement->fetch(PDO::FETCH_ASSOC);
    if (!$run) {
        throw new InvalidArgumentException('KIS preview run nebyl nalezen.');
    }

    $manifest = null;
    if (is_string($run['source_manifest_json'] ?? null) && trim((string)$run['source_manifest_json']) !== '') {
        try {
            $decoded = json_decode((string)$run['source_manifest_json'], true, 32, JSON_THROW_ON_ERROR);
            if (is_array($decoded)
                && ($decoded['contract'] ?? null) === KIS_SOURCE_MANIFEST_CONTRACT
                && is_string($decoded['fingerprint'] ?? null)
                && preg_match('/^[a-f0-9]{64}$/D', (string)$decoded['fingerprint']) === 1
                && is_array($decoded['sources'] ?? null)
                && $decoded['sources'] !== []) {
                $manifest = $decoded;
            }
        } catch (JsonException) {
            $manifest = null;
        }
    }

    $statement = $pdo->prepare(
        'SELECT ir.id,ir.jmeno,ir.prijmeni,ir.uciid,im.match_status,im.sportovec_id '
        . 'FROM kis_import_rows ir LEFT JOIN kis_import_matches im '
        . 'ON im.row_id=ir.id AND im.run_id=ir.run_id WHERE ir.run_id=? ORDER BY ir.id'
    );
    $statement->execute([$runId]);
    $rows = [];
    $targets = [];
    $sourceOrdinal = 0;
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $stored) {
        $sourceOrdinal++;
        $action = 'invalid';
        $reason = 'missing_match_result';
        $targetRef = null;
        if ($manifest === null) {
            $action = 'missing_without_archive';
            $reason = 'archived_source_required';
        } elseif (trim((string)$stored['jmeno']) === '' || trim((string)$stored['prijmeni']) === '') {
            $reason = 'row_missing_identity';
        } else {
            switch ((string)($stored['match_status'] ?? '')) {
                case 'matched':
                    if ((int)($stored['sportovec_id'] ?? 0) > 0) {
                        $action = 'exact_match';
                        $reason = 'strong_identity_match';
                        $targetRef = 'sportovec:' . (int)$stored['sportovec_id'];
                    }
                    break;
                case 'new':
                    $action = 'create';
                    $reason = 'no_existing_candidate';
                    break;
                case 'ambiguous':
                    $action = 'ambiguous';
                    $reason = 'multiple_candidates';
                    break;
                case 'conflict':
                    $action = 'conflict';
                    $reason = 'identity_conflict';
                    break;
                case 'ignored':
                    $reason = 'explicitly_ignored';
                    break;
            }
        }
        $row = [
            'source_ref' => 'source:' . $sourceOrdinal,
            'action' => $action,
            'reason' => $reason,
        ];
        if ($targetRef !== null) {
            $row['target_ref'] = $targetRef;
            $targets[$targetRef][] = count($rows);
        }
        $rows[] = $row;
    }

    foreach ($targets as $indexes) {
        if (count($indexes) < 2) {
            continue;
        }
        foreach ($indexes as $index) {
            $rows[$index]['action'] = 'conflict';
            $rows[$index]['reason'] = 'duplicate_target';
        }
    }

    $actions = ['create', 'exact_match', 'conflict', 'ambiguous', 'invalid', 'missing_without_archive'];
    $counts = array_fill_keys($actions, 0);
    foreach ($rows as $row) {
        $counts[$row['action']]++;
    }
    $blockerRows = $counts['conflict'] + $counts['ambiguous'] + $counts['invalid'] + $counts['missing_without_archive'];
    $canonical = [
        'contract' => KIS_IMPORT_PREVIEW_CONTRACT,
        'run_ref' => 'run:' . $runId,
        'source_manifest_fingerprint' => $manifest['fingerprint'] ?? null,
        'status' => $blockerRows === 0 ? 'ready_for_test_review' : 'blocked',
        'summary' => [
            'total_rows' => count($rows),
            'classified_rows' => count($rows),
            'blocker_rows' => $blockerRows,
            'counts' => $counts,
        ],
        'rows' => $rows,
    ];
    $fingerprintPayload = $canonical;
    unset($fingerprintPayload['run_ref']);
    $canonical['fingerprint'] = hash('sha256', kisImportJson($fingerprintPayload));
    return $canonical;
}

/** @return array<string,mixed> */
function kisImportFinalizePreview(PDO $pdo, int $runId): array
{
    $report = kisImportBuildPreviewReport($pdo, $runId);
    $statement = $pdo->prepare(
        'UPDATE kis_import_runs SET preview_contract_version=?,preview_fingerprint=?,preview_report_json=?,classified_rows=?,blocker_rows=? WHERE id=?'
    );
    $statement->execute([
        KIS_IMPORT_PREVIEW_CONTRACT,
        $report['fingerprint'],
        kisImportJson($report),
        $report['summary']['classified_rows'],
        $report['summary']['blocker_rows'],
        $runId,
    ]);
    if ($statement->rowCount() !== 1) {
        $check = $pdo->prepare('SELECT preview_fingerprint FROM kis_import_runs WHERE id=?');
        $check->execute([$runId]);
        if (!hash_equals((string)$report['fingerprint'], (string)$check->fetchColumn())) {
            throw new RuntimeException('KIS preview report se nepodařilo uložit.');
        }
    }
    return $report;
}

/** @return array<string,mixed>|null */
function kisImportStoredPreviewReport(array $run): ?array
{
    $json = $run['preview_report_json'] ?? null;
    if (!is_string($json) || trim($json) === '') {
        return null;
    }
    try {
        $report = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return null;
    }
    if (!is_array($report)
        || ($report['contract'] ?? null) !== KIS_IMPORT_PREVIEW_CONTRACT
        || !is_string($report['fingerprint'] ?? null)
        || !hash_equals((string)($run['preview_fingerprint'] ?? ''), (string)$report['fingerprint'])) {
        return null;
    }
    return $report;
}

function kisImportColumnExists(PDO $pdo, string $table, string $column): bool
{
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $definition) {
            if ((string)$definition['name'] === $column) {
                return true;
            }
        }
        return false;
    }
    $statement = $pdo->prepare(
        'SELECT 1 FROM information_schema.COLUMNS '
        . 'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?'
    );
    $statement->execute([$table, $column]);
    return (bool)$statement->fetchColumn();
}

function kisImportStoreRowsAndMatches(PDO $pdo, int $runId, array $people, array $baseStats = []): array
{
    $counts = ['new' => 0, 'matched' => 0, 'ambiguous' => 0, 'conflict' => 0, 'ignored' => 0];
    $hasExternalIdColumn = kisImportColumnExists($pdo, 'kis_import_rows', 'kis_external_id');
    $columns = [
        'run_id', 'person_key', 'jmeno', 'prijmeni', 'narozeni', 'email', 'uciid', 'oddil',
        'kis_aktivni', 'kis_platebne_aktivni', 'kis_neuhrazeno', 'kis_posledni_uhrada', 'kis_soupisky', 'raw_json',
    ];
    if ($hasExternalIdColumn) {
        $columns[] = 'kis_external_id';
    }
    $optionalSnapshots = [
        'kis_roster_count' => static fn(array $person): int => count((array)($person['_soupisky_parsed'] ?? [])),
        'kis_payment_paid_count' => static fn(array $person): int => max(0, (int)($person['_kis_payment']['paid_rows'] ?? 0)),
        'kis_payment_open_count' => static fn(array $person): int => max(0, (int)($person['_kis_payment']['open_rows'] ?? 0)),
    ];
    foreach (array_keys($optionalSnapshots) as $optionalColumn) {
        if (kisImportColumnExists($pdo, 'kis_import_rows', $optionalColumn)) $columns[] = $optionalColumn;
    }
    $rowStmt = $pdo->prepare(
        'INSERT INTO kis_import_rows (' . implode(',', $columns) . ') VALUES ('
        . implode(',', array_map(static fn(string $column): string => ':' . $column, $columns)) . ')'
    );
    $matchStmt = $pdo->prepare("
        INSERT INTO kis_import_matches
            (run_id, row_id, sportovec_id, match_status, confidence, reason, candidate_json)
        VALUES
            (:run_id, :row_id, :sportovec_id, :match_status, :confidence, :reason, :candidate_json)
    ");

    foreach ($people as $person) {
        $personKey = kisMatchPersonKey($person);
        $parameters = [
            ':run_id' => $runId,
            ':person_key' => $personKey,
            ':jmeno' => trim((string)($person['jmeno'] ?? '')),
            ':prijmeni' => trim((string)($person['prijmeni'] ?? '')),
            ':narozeni' => $person['narozeni'] ?: null,
            ':email' => trim((string)($person['email'] ?? '')),
            ':uciid' => trim((string)($person['uciid'] ?? '')),
            ':oddil' => trim((string)($person['oddil'] ?? '')),
            ':kis_aktivni' => (int)($person['kis_aktivni'] ?? 0),
            ':kis_platebne_aktivni' => (int)($person['kis_platebne_aktivni'] ?? 0),
            ':kis_neuhrazeno' => (float)($person['kis_neuhrazeno'] ?? 0),
            ':kis_posledni_uhrada' => ($person['kis_posledni_uhrada'] ?? null) ?: null,
            ':kis_soupisky' => (string)($person['kis_soupisky'] ?? ''),
            // Celý zdrojový řádek obsahuje kontakty, adresu nebo rodné číslo.
            // Preview tabulky je pro současný matching nepotřebují.
            ':raw_json' => '{}',
        ];
        if ($hasExternalIdColumn) {
            $parameters[':kis_external_id'] = kisFieldNormalizeExternalId($person['kis_external_id'] ?? '') ?: null;
        }
        foreach ($optionalSnapshots as $optionalColumn => $resolver) {
            if (in_array($optionalColumn, $columns, true)) $parameters[':' . $optionalColumn] = $resolver($person);
        }
        $rowStmt->execute($parameters);
        $rowId = (int)$pdo->lastInsertId();
        $match = kisMatchResolve($pdo, $person);
        $status = (string)$match['status'];
        $counts[$status] = ($counts[$status] ?? 0) + 1;
        $matchStmt->execute([
            ':run_id' => $runId,
            ':row_id' => $rowId,
            ':sportovec_id' => $match['sportovec_id'],
            ':match_status' => $status,
            ':confidence' => (int)$match['confidence'],
            ':reason' => $match['reason'],
            ':candidate_json' => kisMatchJson($match['candidates'] ?? []),
        ]);
    }

    $stats = array_merge($baseStats, ['rows' => count($people), 'matches' => $counts]);
    $stmt = $pdo->prepare("UPDATE kis_import_runs SET stats_json = :stats WHERE id = :id");
    $stmt->execute([':stats' => kisImportJson($stats), ':id' => $runId]);
    return $counts;
}

function kisImportLatestRuns(PDO $pdo, int $limit = 20): array
{
    $stmt = $pdo->query("
        SELECT r.*, t.jmeno AS created_by_name,
               (SELECT COUNT(*) FROM kis_import_matches m WHERE m.run_id = r.id AND m.match_status = 'ambiguous') AS ambiguous_count,
               (SELECT COUNT(*) FROM kis_import_matches m WHERE m.run_id = r.id AND m.match_status = 'conflict') AS conflict_count,
               (SELECT COUNT(*) FROM kis_import_rows rr WHERE rr.run_id = r.id) AS row_count
        FROM kis_import_runs r
        LEFT JOIN treneri t ON t.id = r.created_by
        ORDER BY r.created_at DESC, r.id DESC
        LIMIT " . max(1, min(100, $limit))
    );
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function kisImportRunDetail(PDO $pdo, int $runId): array
{
    $runStmt = $pdo->prepare("SELECT * FROM kis_import_runs WHERE id = ?");
    $runStmt->execute([$runId]);
    $run = $runStmt->fetch(PDO::FETCH_ASSOC);
    if (!$run) {
        return ['run' => null, 'rows' => []];
    }
    $rowsStmt = $pdo->prepare("
        SELECT ir.*, im.match_status, im.confidence, im.reason, im.sportovec_id, im.candidate_json,
               s.jmeno AS db_jmeno, s.prijmeni AS db_prijmeni
        FROM kis_import_rows ir
        LEFT JOIN kis_import_matches im ON im.row_id = ir.id
        LEFT JOIN sportovci s ON s.id = im.sportovec_id
        WHERE ir.run_id = ?
        ORDER BY FIELD(im.match_status, 'ambiguous','conflict','new','matched','ignored'), ir.prijmeni, ir.jmeno
    ");
    $rowsStmt->execute([$runId]);
    return ['run' => $run, 'rows' => $rowsStmt->fetchAll(PDO::FETCH_ASSOC)];
}
