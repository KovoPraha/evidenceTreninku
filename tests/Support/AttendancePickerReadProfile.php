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
require_once __DIR__ . '/AttendancePersonPickerFixture.php';
require_once dirname(__DIR__, 2) . '/includes/training_closure_people.php';

$admin = w72Connection();
$identity = $admin->query('SELECT @@port port,@@datadir directory,VERSION() version')->fetch(PDO::FETCH_ASSOC);
$directory = strtolower(rtrim(str_replace('\\', '/', $identity['directory']), '/'));
$expected = strtolower(rtrim(str_replace('\\', '/', (string)getenv('EVIDENCE_PERFORMANCE_DATADIR')), '/'));
if ($directory !== $expected || (int)$identity['port'] !== (int)getenv('EVIDENCE_MARIADB_SMOKE_PORT')) {
    throw new RuntimeException('Wrong owned database server.');
}
$schema = 'p11_picker_' . bin2hex(random_bytes(8));
$admin->exec('CREATE DATABASE `' . $schema . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$report = ['complete' => false, 'synthetic_only' => true, 'server' => $identity['version'], 'cases' => []];
try {
    $pdo = w72Connection($schema, true);
    Tests\Support\AttendancePersonPickerFixture::seed($pdo);
    $pdo->exec("UPDATE sportovci SET jmeno='Jiří',prijmeni='Novák 100%_!' WHERE id=3");
    $pdo->exec('SET SESSION TRANSACTION READ ONLY');
    foreach (['coach', 'sports_lead'] as $role) {
        Tests\Support\AttendanceReadFixture::session(7, $role);
        $original = $pdo->query('SELECT s.id,s.jmeno,s.prijmeni FROM sportovci s WHERE ' . coachTrainingPersonPredicate($pdo)
            . ' ORDER BY CASE WHEN (' . coachTrainingPreferredPersonPredicate($pdo) . ') THEN 0 ELSE 1 END,s.prijmeni,s.jmeno,s.id')->fetchAll(PDO::FETCH_ASSOC);
        $rows = [];
        $pdo->profile = [];
        $pdo->profiling = true;
        for ($page = 1; $page <= 25; $page++) {
            $result = trainingClosurePersonPage($pdo, ['page' => (string)$page]);
            if (count($result['rows']) > 50) {
                throw new RuntimeException('Unbounded person page.');
            }
            array_push($rows, ...$result['rows']);
            if (!$result['has_more']) {
                break;
            }
        }
        foreach ($pdo->profile as $query) {
            if (str_contains($query['sql'], 'FROM sportovci s') && $query['fetched_rows'] > 51) {
                throw new RuntimeException('Unbounded fetched projection.');
            }
        }
        $pdo->profiling = false;
        if ($original !== $rows || count($rows) !== 1200) {
            throw new RuntimeException('People, preference or collation ordering changed.');
        }
        foreach (['Jiří Novák' => 3, 'novak jiri' => 3, '100%_!' => 3, '1200' => 1200, 'NOT FOUND' => null] as $term => $id) {
            $found = array_map('intval', array_column(trainingClosurePersonPage($pdo, ['q' => $term])['rows'], 'id'));
            if ($found !== ($id === null ? [] : [$id])) {
                throw new RuntimeException('Search wildcard/word/collation mismatch.');
            }
        }
        $pinned = trainingClosureSelectedPeople($pdo, [...range(1, 1200), 0, 999999]);
        if (count($pinned) !== 1200 || isset($pinned[999999])
            || trainingClosurePersonOptions(trainingClosurePersonPage($pdo, ['q' => 'NOT FOUND']), $pinned) !== $pinned) {
            throw new RuntimeException('Selection lost on another search page.');
        }
        $report['cases'][] = ['role' => $role, 'original_people' => count($original), 'pages' => $page,
            'data_sha256' => hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR)), 'bounded_projection' => true,
            'all_people_and_order_equal' => true, 'literal_and_accent_search' => true, 'pinned_people' => count($pinned)];
    }
    $pdo->exec('SET SESSION TRANSACTION READ WRITE');
    Tests\Support\AttendanceReadFixture::session();
    $pdo->exec("UPDATE sportovci SET jmeno='Changed now' WHERE id=1");
    if (trainingClosureSelectedPeople($pdo, [1])[1]['jmeno'] !== 'Changed now') {
        throw new RuntimeException('Person data cached.');
    }
    $pdo->exec('DELETE FROM staff_user_positions WHERE trainer_id=7');
    foreach ([fn () => trainingClosurePersonPage($pdo, []), fn () => trainingClosureSelectedPeople($pdo, [1])] as $operation) {
        if (Tests\Support\AttendanceReadFixture::outcome($operation) !== [RuntimeException::class, 'Pracovní oprávnění trenéra již neplatí.']) {
            throw new RuntimeException('Revoked actor accepted.');
        }
    }
    $report['fresh_data_and_revocation'] = true;
    $report['read_only_parity_session'] = true;
    $report['complete'] = true;
} finally {
    $admin->exec('DROP DATABASE `' . $schema . '`');
    $report['schema_removed'] = true;
    $_SESSION = [];
}
echo json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
