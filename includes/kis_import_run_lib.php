<?php
declare(strict_types=1);

require_once __DIR__ . '/kis_match_lib.php';
require_once __DIR__ . '/kis_source_archive.php';

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
    $rowStmt = $pdo->prepare("
        INSERT INTO kis_import_rows
            (run_id, person_key, jmeno, prijmeni, narozeni, email, uciid, oddil,
             kis_aktivni, kis_platebne_aktivni, kis_neuhrazeno, kis_posledni_uhrada, kis_soupisky, raw_json)
        VALUES
            (:run_id, :person_key, :jmeno, :prijmeni, :narozeni, :email, :uciid, :oddil,
             :kis_aktivni, :kis_platebne_aktivni, :kis_neuhrazeno, :kis_posledni_uhrada, :kis_soupisky, :raw_json)
    ");
    $matchStmt = $pdo->prepare("
        INSERT INTO kis_import_matches
            (run_id, row_id, sportovec_id, match_status, confidence, reason, candidate_json)
        VALUES
            (:run_id, :row_id, :sportovec_id, :match_status, :confidence, :reason, :candidate_json)
    ");

    foreach ($people as $person) {
        $personKey = kisMatchPersonKey($person);
        $rowStmt->execute([
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
        ]);
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
