<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__, 2) . '/includes/member_evidence_cutover.php';
require_once dirname(__DIR__, 2) . '/includes/member_roster_admin.php';
require_once dirname(__DIR__, 2) . '/includes/staff_workspaces.php';

$port = getenv('EVIDENCE_MARIADB_SMOKE_PORT') ?: '3306';
if (!ctype_digit($port) || (int)$port < 1 || (int)$port > 65535) {
    throw new RuntimeException('Invalid loopback test port.');
}
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false];
$dsn = 'mysql:host=127.0.0.1;port=' . $port . ';charset=utf8mb4';
$server = new PDO($dsn, 'root', '', $options);
$database = 'evidence_member_evidence_smoke_' . bin2hex(random_bytes(5));
$server->exec('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo = new PDO($dsn . ';dbname=' . $database, 'root', '', $options);
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks++;
};
try {
    $pdo->exec('CREATE TABLE treneri(id INT PRIMARY KEY,jmeno VARCHAR(100),aktivni INT) ENGINE=InnoDB');
    $pdo->exec("INSERT INTO treneri VALUES(7,'LOCALHOST registrář',1)");
    $pdo->exec('CREATE TABLE sportovci(id INT PRIMARY KEY,jmeno VARCHAR(100),prijmeni VARCHAR(100),stav_clenstvi VARCHAR(24)) ENGINE=InnoDB');
    $pdo->exec("INSERT INTO sportovci VALUES(10,'LOCALHOST','NEPLATIT','aktivni')");
    $pdo->exec('CREATE TABLE staff_user_positions(trainer_id INT,position_code VARCHAR(40)) ENGINE=InnoDB');
    $pdo->exec("INSERT INTO staff_user_positions VALUES(7,'registrar')");
    $pdo->exec('CREATE TABLE staff_superadmins(trainer_id INT) ENGINE=InnoDB');
    foreach (['20260804090000_kis_teams_rosters', '20260804110000_kis_roster_policies',
        '20260822170000_club_roster_structure_events', '20261021090000_roster_hierarchy_temporal',
        '20261102170000_roster_activity_kind', '20261102172000_member_roster_operations',
        '20261102173000_member_roster_age_rules', '20261102174000_member_evidence_cutover_runs'] as $name) {
        $migration = require dirname(__DIR__, 2) . '/migrations/' . $name . '.php';
        $migration['up']($pdo);
        $migration['up']($pdo);
        $check($migration['verify']($pdo), 'Migration verify: ' . $name);
    }
    $season = kisRosterCreateSeason($pdo, 7, 'LEGACY', 'LOCALHOST původní', '2020-01-01', '2020-12-31', 'calendar_year');
    $team = kisRosterCreateTeam($pdo, $season['id'], 7, 'LEGACY', 'LOCALHOST původní', 'kolo', 'vše', 'LOCALHOST fixture');
    $member = kisRosterGrantPeriod($pdo, $team['id'], 10, 7, 'manual', '2020-01-01', null, 'LOCALHOST zachovaný nárok');
    $pdo->exec('CREATE TABLE retained_finance(id INT PRIMARY KEY,amount_minor INT) ENGINE=InnoDB');
    $pdo->exec('INSERT INTO retained_finance VALUES(1,12345)');
    $config = ['cutover_on' => date('Y-m-d'), 'reason' => 'LOCALHOST nácvik převodu', 'delete' => [],
        'teams' => [['id' => $team['id'], 'standing_code' => 'STANDING', 'name' => 'LOCALHOST trvalá', 'activity_kind' => 'competitive']]];
    $before = memberEvidenceSnapshot($pdo);
    $pdo->exec('START TRANSACTION READ ONLY');
    $readOnlyConfig = $config + ['groups' => [['standing_code' => 'GROUP', 'name' => 'LOCALHOST skupina',
        'activity_kind' => 'competitive', 'child_ids' => [$team['id']]]]];
    $readOnlyManifest = memberEvidencePreview($pdo, $readOnlyConfig);
    $check($readOnlyManifest['groups'][0]['membership'][date('Y-m-d')]['count'] === 1, 'Read-only group preview failed.');
    $pdo->exec('ROLLBACK');
    $manifest = memberEvidencePreview($pdo, $config);
    $check($before === memberEvidenceSnapshot($pdo), 'Preview changed data.');
    $pdo->exec('UPDATE retained_finance SET amount_minor=12346 WHERE id=1');
    try {
        memberEvidenceApply($pdo, $manifest, $manifest['fingerprint'], 7, str_repeat('a', 64), true);
        throw new LogicException('Stale manifest accepted.');
    } catch (RuntimeException $error) {
        $check(str_contains($error->getMessage(), 'Databáze se od náhledu změnila'), 'Unexpected rejection.');
    }
    $pdo->exec('UPDATE retained_finance SET amount_minor=12345 WHERE id=1');
    $manifest = memberEvidencePreview($pdo, $config);
    $result = memberEvidenceApply($pdo, $manifest, $manifest['fingerprint'], 7, str_repeat('a', 64), true);
    $check($result['preserved_parity'], 'Parity failed.');
    $check(memberEvidenceApply($pdo, $manifest, $manifest['fingerprint'], 7, str_repeat('a', 64), true)['idempotent'], 'Retry duplicated cutover.');
    $check($before['retained_finance'] === memberEvidenceSnapshot($pdo)['retained_finance'], 'Financial data changed.');
    $check(kisRosterEffectiveMemberIds($pdo, $team['id'], date('Y-m-d')) === [10], 'Membership changed.');
    $_SESSION = ['trener_id' => 7, 'staff_active_position' => 'registrar', 'staff_positions' => ['registrar']];
    $target = memberRosterMutate($pdo, ['action' => 'create', 'code' => 'TARGET', 'name' => 'LOCALHOST cíl',
        'kind' => 'roster', 'activity_kind' => 'competitive', 'reason' => 'LOCALHOST nový tým']);
    $request = ['action' => 'transfer', 'team_id' => $team['id'], 'target_team_id' => $target['id'],
        'member_id' => $member['id'], 'valid_from' => date('Y-m-d'), 'reason' => 'LOCALHOST přestup'];
    $request['fingerprint'] = memberRosterPreview($pdo, $request)['fingerprint'];
    memberRosterMutate($pdo, $request);
    $check(memberRosterMutate($pdo, $request)['idempotent'], 'Transfer retry duplicated membership.');
    $check(kisRosterEffectiveMemberIds($pdo, $target['id'], date('Y-m-d')) === [10], 'Target membership missing.');
    echo 'Member evidence MariaDB smoke OK (' . $checks . ' checks)' . PHP_EOL;
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $pdo = null;
    $server->exec('DROP DATABASE `' . $database . '`');
}
