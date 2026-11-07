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
require_once __DIR__ . '/AttendanceReadFixture.php';
require_once dirname(__DIR__, 2) . '/includes/coach_training_access.php';

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
$schema = 'p11_attendance_' . bin2hex(random_bytes(8));
$report = ['complete' => false, 'synthetic_only' => true, 'server' => $identity['version'],
    'parity_cases' => 0, 'fresh_data' => false, 'transaction_locks_preserved' => false];
$admin->exec('CREATE DATABASE `' . $schema . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
try {
    $pdo = w72Connection($schema, true);
    Tests\Support\AttendanceReadFixture::seed($pdo);
    $pdo->beginTransaction();
    $pdo->profiling = true;
    foreach ([[], [2, 1, 2], [1, 1201, 2], [0, 1]] as $ids) {
        $pdo->profile = [];
        $old = Tests\Support\AttendanceReadFixture::outcome(fn () => Tests\Support\AttendanceReadFixture::original($pdo, $ids));
        $sql = array_map(static fn (array $q): array => [$q['sql'], $q['parameters']], $pdo->profile);
        $pdo->profile = [];
        $new = Tests\Support\AttendanceReadFixture::outcome(fn () => coachTrainingAssertPeople($pdo, $ids));
        $newSql = array_map(static fn (array $q): array => [$q['sql'], $q['parameters']], $pdo->profile);
        if ($old !== $new || $sql !== $newSql || !str_contains(json_encode($newSql, JSON_THROW_ON_ERROR), 'FOR UPDATE')) {
            throw new RuntimeException('Transaction validation or lock order changed.');
        }
    }
    $report['transaction_locks_preserved'] = true;
    $pdo->profiling = false;
    $pdo->rollBack();
    coachTrainingAssertPeople($pdo, [1]);
    $pdo->exec('DELETE FROM sportovci WHERE id=1');
    if (Tests\Support\AttendanceReadFixture::outcome(fn () => coachTrainingAssertPeople($pdo, [1])) !== [RuntimeException::class, 'Vybraný sportovec neexistuje.']) {
        throw new RuntimeException('Participant existence was cached.');
    }
    $pdo->exec('INSERT INTO sportovci VALUES(1)');
    $pdo->exec('DELETE FROM staff_user_positions WHERE trainer_id=7');
    if (Tests\Support\AttendanceReadFixture::outcome(fn () => coachTrainingAssertPeople($pdo, [1])) !== [RuntimeException::class, 'Pracovní oprávnění trenéra již neplatí.']) {
        throw new RuntimeException('Actor authorization was cached.');
    }
    $pdo->exec("INSERT INTO staff_user_positions VALUES(7,'coach'),(7,'sports_lead')");
    $report['fresh_data'] = true;
    $pdo->exec('SET SESSION TRANSACTION READ ONLY');
    foreach ([[7, 'coach'], [7, 'sports_lead'], [9, 'coach'], [8, 'coach'], [7, 'registrar'], [0, 'coach']] as [$actor, $position]) {
        Tests\Support\AttendanceReadFixture::session($actor, $position);
        foreach (Tests\Support\AttendanceReadFixture::inputs() as $ids) {
            $old = Tests\Support\AttendanceReadFixture::outcome(fn () => Tests\Support\AttendanceReadFixture::original($pdo, $ids));
            $new = Tests\Support\AttendanceReadFixture::outcome(fn () => coachTrainingAssertPeople($pdo, $ids));
            if ($old !== $new) {
                throw new RuntimeException('Participant validation parity failed.');
            }
            $report['parity_cases']++;
        }
    }
    Tests\Support\AttendanceReadFixture::session();
    $pdo->profiling = true;
    foreach ([200 => 3, 1200 => 5] as $size => $budget) {
        $pdo->profile = [];
        coachTrainingAssertPeople($pdo, range(1, $size));
        if (count($pdo->profile) !== $budget) {
            throw new RuntimeException('Participant batch budget changed.');
        }
        $report['sql_' . $size] = $pdo->profile;
    }
    $report['read_only_parity_session'] = true;
    $report['complete'] = true;
} finally {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $admin->exec('DROP DATABASE `' . $schema . '`');
    $report['schema_removed'] = true;
    $_SESSION = [];
}
echo json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
