<?php

declare(strict_types=1);

require_once __DIR__ . '/RefactoringFixtureRuntime.php';
require_once dirname(__DIR__, 2) . '/includes/kis_import_run_lib.php';

$runtime = new RefactoringFixtureRuntime();
try {
    $pdo = $runtime->create('', true);
    w72Seed($pdo);
    if (in_array('--legacy-indexes', $argv, true)) {
        $pdo->exec('DROP INDEX idx_kis_match_run_row ON kis_import_matches');
    }
    w72LegacyColumns($pdo, 'sportovci', ['kis_aktivni' => 'INT DEFAULT 0', 'kis_platebne_aktivni' => 'INT DEFAULT 0',
        'kis_neuhrazeno' => 'DECIMAL(12,2) DEFAULT 0', 'kis_posledni_uhrada' => 'DATE NULL', 'kis_soupisky' => 'TEXT NULL']);
    // These indexes belong to the legacy bootstrap, not the numbered catalog.
    // Match auto_migrace.php and local-demo-schema.sql instead of profiling a heap fixture.
    foreach (['kis_import_rows' => ['run_id', 'person_key'],
        'kis_import_matches' => ['run_id', 'row_id', 'sportovec_id', 'match_status']] as $table => $columns) {
        foreach ($columns as $column) {
            $pdo->exec('CREATE INDEX idx_' . $column . ' ON ' . $table . '(' . $column . ')');
        }
    }
    $source = $runtime->directory . '/source.json';
    file_put_contents($source, '{"synthetic":true}');
    mkdir($runtime->directory . '/archive', 0700);
    $archive = kisSourceArchive($pdo, $source, 'users', 'synthetic-v1', $runtime->directory . '/archive', 7);
    $beforePeople = $pdo->query('SELECT COUNT(*) FROM sportovci')->fetchColumn();
    $results = [];
    foreach ([100, 1000, 10000] as $size) {
        $people = [];
        for ($i = 1; $i <= $size; $i++) {
            $people[] = ['jmeno' => 'Synthetic', 'prijmeni' => 'New ' . $i, 'narozeni' => '2010-01-01',
                'kis_external_id' => 'W12-' . $i];
        }
        $pdo->profile = [];
        $pdo->profiling = true;
        memory_reset_peak_usage();
        $memory = memory_get_usage();
        $start = hrtime(true);
        $run = kisImportCreateRun($pdo, $people, [], [], ['users' => 'source.json'], 7, ['users' => $archive['id']]);
        $ms = (hrtime(true) - $start) / 1e6;
        $peak = memory_get_peak_usage() - $memory;
        $pdo->profiling = false;
        $counts = ['prepared_sql' => count($pdo->profile), 'fetched_rows' => array_sum(array_column($pdo->profile, 'fetched_rows'))];
        $sqlTimings = [];
        foreach ($pdo->profile as $entry) {
            $sql = $entry['sql'];
            $sqlTimings[$sql] = ($sqlTimings[$sql] ?? 0.0) + $entry['execute_ms'] + $entry['fetch_ms'];
        }
        arsort($sqlTimings);
        $counts['sql_ms'] = array_sum($sqlTimings);
        $counts['top_sql_ms'] = array_slice($sqlTimings, 0, 5, true);
        $statement = $pdo->prepare('SELECT * FROM kis_import_runs WHERE id=?');
        $statement->execute([$run]);
        $stored = $statement->fetch();
        $explain = $pdo->query('EXPLAIN SELECT ir.id,im.match_status FROM kis_import_rows ir '
            . 'LEFT JOIN kis_import_matches im ON im.row_id=ir.id AND im.run_id=ir.run_id '
            . 'WHERE ir.run_id=' . $run . ' ORDER BY ir.id')->fetchAll();
        $report = kisImportStoredPreviewReport($stored);
        $again = kisImportFinalizePreview($pdo, $run);
        if (!in_array('--legacy-indexes', $argv, true) && $size === 10000
            && ($explain[1]['key'] !== 'idx_kis_match_run_row' || (int) $explain[1]['key_len'] !== 8)) {
            throw new RuntimeException('Preview join must use both indexed identity columns.');
        }
        if ($report['summary']['classified_rows'] !== $size || $report['fingerprint'] !== $again['fingerprint']
            || $pdo->query('SELECT COUNT(*) FROM sportovci')->fetchColumn() !== $beforePeople) {
            throw new RuntimeException('Preview identity, fingerprint or no-promotion contract failed.');
        }
        $results[] = ['input_rows' => $size, 'instrumented_single_ms' => $ms, 'extra_peak_bytes' => $peak,
            'report_bytes' => strlen($stored['preview_report_json']), 'classified_rows' => $report['summary']['classified_rows'],
            'fingerprint' => $report['fingerprint'], 'explain' => $explain] + $counts;
        unset($people, $report, $again, $stored);
    }
    // A failure after partial inserts must roll back the complete preview transaction.
    $before = $pdo->query('SELECT COUNT(*) FROM kis_import_runs')->fetchColumn();
    $pdo->exec("CREATE TRIGGER w12_injected_failure BEFORE INSERT ON kis_import_matches FOR EACH ROW "
        . "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic rollback'");
    try {
        kisImportCreateRun($pdo, [['jmeno' => 'Failure', 'prijmeni' => 'Synthetic', 'narozeni' => '2010-01-01']], [], [], [], 7);
        throw new LogicException('Injected import failure was ignored.');
    } catch (PDOException $exception) {
        if ((string) $exception->getCode() !== '45000' || $pdo->query('SELECT COUNT(*) FROM kis_import_runs')->fetchColumn() !== $before) {
            throw $exception;
        }
    }
    echo json_encode(['schema' => 'full migrations, FK enabled, random localhost DB', 'samples' => 1,
        'transport' => 'none', 'promotion' => false, 'rollback' => 'pass', 'results' => $results], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
} finally {
    $runtime->close();
}
