<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (getenv('EVIDENCE_PERFORMANCE_CONFIRM') !== 'SYNTHETIC-ONLY'
    || !ctype_digit((string)getenv('EVIDENCE_MARIADB_SMOKE_PORT'))
    || trim((string)getenv('EVIDENCE_PERFORMANCE_DATADIR')) === '') {
    throw new RuntimeException('Explicit owned synthetic server required.');
}
require_once __DIR__ . '/FamilyOverviewPageFixture.php';
require_once __DIR__ . '/MedicalClearanceListBaseline.php';
require_once __DIR__ . '/MedicalClearanceReadFixture.php';
require_once dirname(__DIR__, 2) . '/includes/medical_clearance.php';

$admin = w72Connection();
$identity = $admin->query('SELECT @@port port,@@datadir directory,VERSION() version')->fetch(PDO::FETCH_ASSOC);
$directory = rtrim(str_replace('\\', '/', $identity['directory']), '/');
$expected = rtrim(str_replace('\\', '/', (string)getenv('EVIDENCE_PERFORMANCE_DATADIR')), '/');
if (PHP_OS_FAMILY === 'Windows') {
    $directory = strtolower($directory);
    $expected = strtolower($expected);
}
if ($directory !== $expected || (int)$identity['port'] !== (int)getenv('EVIDENCE_MARIADB_SMOKE_PORT')) {
    throw new RuntimeException('Wrong owned database server.');
}
$schema = 'p11_medread_' . bin2hex(random_bytes(8));
$report = ['complete' => false, 'synthetic_only' => true, 'server' => $identity['version'],
    'fixture_watches' => 431, 'fixture_people' => 57, 'parity_cases' => 0, 'lookup_all_people' => false];
$admin->exec('CREATE DATABASE `' . $schema . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
try {
    $pdo = w72Connection($schema, true);
    Tests\Support\MedicalClearanceReadFixture::seed($pdo);
    $pdo->exec('SET SESSION TRANSACTION READ ONLY');
    foreach (Tests\Support\MedicalClearanceReadFixture::inputs() as $input) {
        $expectedList = Tests\Support\MedicalClearanceListBaseline::read($pdo, $input);
        if ($expectedList !== medicalClearanceList($pdo, $input)) {
            throw new RuntimeException('List parity failed: ' . json_encode($input, JSON_THROW_ON_ERROR));
        }
        $report['parity_cases']++;
    }
    $all = [];
    for ($page = 1; $page <= 3; $page++) {
        $choices = medicalClearancePersonChoices($pdo, ['medical_person_page' => $page], 0);
        $all = [...$all, ...array_map('intval', array_column($choices['rows'], 'id'))];
    }
    sort($all);
    if ($all !== range(1, 57)) {
        throw new RuntimeException('Person picker lost people.');
    }
    $report['lookup_all_people'] = true;
    $selected = medicalClearancePersonChoices($pdo, ['medical_person_q' => 'No such name'], 57);
    if ((int)$selected['selected']['id'] !== 57 || count($selected['rows']) > 26) {
        throw new RuntimeException('Person context lost.');
    }
    $pdo->profile = [];
    $pdo->profiling = true;
    medicalClearanceList($pdo, []);
    $report['page_read'] = $pdo->profile;
    $pdo->profile = [];
    $bufferedBefore = $pdo->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY);
    medicalClearanceList($pdo, ['medical_state' => 'valid']);
    if ($pdo->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY) !== $bufferedBefore) {
        throw new RuntimeException('PDO buffering state not restored.');
    }
    $report['buffering_restored'] = true;
    $report['state_read'] = $pdo->profile;
    if (count($report['state_read']) > 4 || array_sum(array_column($pdo->profile, 'fetched_rows')) >= 431 * 3
        || $report['state_read'][array_key_last($report['state_read'])]['fetched_rows'] > 25) {
        throw new RuntimeException('State filter exceeded its bounded projection budget.');
    }
    $report['read_only_session'] = true;
    $report['complete'] = true;
} finally {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $admin->exec('DROP DATABASE `' . $schema . '`');
    $report['schema_removed'] = true;
}
echo json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
