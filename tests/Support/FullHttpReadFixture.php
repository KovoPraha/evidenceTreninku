<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (($argv[1] ?? '') !== '--setup' || !isset($argv[2])) {
    throw new RuntimeException('CLI --setup OWN_OUTPUT_DIRECTORY required.');
}
$output = realpath($argv[2]);
if ($output === false || file_exists(dirname(__DIR__, 2) . '/config.php')) {
    throw new RuntimeException('Owned output and an unconfigured isolated checkout required.');
}
$runtime = json_decode((string)file_get_contents($output . '/runtime.json'), true, 512, JSON_THROW_ON_ERROR);
if (($runtime['synthetic_only'] ?? false) !== true) {
    throw new RuntimeException('Synthetic runtime required.');
}
$git = proc_open(['git', 'rev-parse', 'HEAD'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2));
if (!is_resource($git)) {
    throw new RuntimeException('Cannot identify application checkout.');
}
$runtime['application_sha'] = trim((string)stream_get_contents($pipes[1]));
fclose($pipes[1]);
fclose($pipes[2]);
if (proc_close($git) !== 0 || preg_match('/^[a-f0-9]{40}$/D', $runtime['application_sha']) !== 1) {
    throw new RuntimeException('Invalid checkout identity.');
}
$argv = [__FILE__, '--library'];
require_once __DIR__ . '/SyntheticReadPagesProfile.php';
$admin = p11Connection();
$schema = 'p11_read_' . bin2hex(random_bytes(8));
$admin->exec('CREATE DATABASE `' . $schema . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$runtime['schema'] = $schema;
file_put_contents($output . '/runtime.json', json_encode($runtime, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
$pdo = p11Connection($schema);
w72Seed($pdo);
p11Prepare($pdo);
p11SeedPlanner($pdo, 52);
p11SeedClosure($pdo, 200, 52);
p11Session('registrar');
p11SeedMedical($pdo, 10000);

// Legacy fields required by the real bootstrap/login, absent from the minimal GET fixture.
w72LegacyColumns($pdo, 'verejni_uzivatele', ['heslo_hash' => 'VARCHAR(255) NULL']);
$pdo->exec('ALTER TABLE verejni_uzivatele MODIFY id INT NOT NULL AUTO_INCREMENT,
    MODIFY registrovan DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
$pdo->exec('CREATE TABLE IF NOT EXISTS opravneni(klic VARCHAR(100) PRIMARY KEY,min_role VARCHAR(30) NOT NULL)');
$pdo->exec("INSERT INTO opravneni VALUES('planovac','trener'),('oznameni','hlavni')");
$pdo->exec('DELETE FROM staff_superadmins; DELETE FROM staff_user_positions');
$credentials = [];
$password = 'Localhost-Neplatit-' . bin2hex(random_bytes(16));
$roles = array_keys(staffPositionDefinitions());
foreach ($roles as $i => $role) {
    $id = $role === 'coach' ? 7 : 17 + $i;
    $login = 'LOCALHOST_HTTP_' . $role;
    $legacy = staffPositionDefinitions()[$role]['legacy_role'];
    $q = $pdo->prepare('INSERT INTO treneri(id,jmeno,email,heslo,role,aktivni) VALUES(?,?,?,?,?,1)
        ON DUPLICATE KEY UPDATE jmeno=VALUES(jmeno),email=VALUES(email),heslo=VALUES(heslo),role=VALUES(role),aktivni=1');
    $q->execute([$id, $login, $role . '@example.invalid', password_hash($password, PASSWORD_DEFAULT), $legacy]);
    $pdo->prepare('INSERT INTO staff_user_positions(trainer_id,position_code,is_default) VALUES(?,?,1)')->execute([$id, $role]);
    $credentials[$role] = ['id' => $id, 'login' => $login, 'password' => $password];
}
$pdo->prepare('UPDATE treneri SET jmeno=?,email=?,heslo=?,role=?,aktivni=1 WHERE id=8')
    ->execute(['LOCALHOST_HTTP_other_coach', 'other-coach@example.invalid', password_hash($password, PASSWORD_DEFAULT), 'trener']);
$pdo->exec("INSERT INTO staff_user_positions(trainer_id,position_code,is_default) VALUES(8,'coach',1)");
$credentials['other_coach'] = ['id' => 8, 'login' => 'LOCALHOST_HTTP_other_coach', 'password' => $password];
$pdo->exec("UPDATE planovane_treninky SET trenink_id=101,stav='evidovany' WHERE id=50010;
    UPDATE planovane_treninky SET trenink_id=102,stav='evidovany' WHERE id=50011");
$pdo->exec("INSERT IGNORE INTO club_teams(id,season_id,code,name,discipline,age_label,status,created_by_trainer_id)
    VALUES(2,1,'HTTP-CHILD','LOCALHOST child roster','cycling','synthetic','active',7)");
$pdo->exec("INSERT INTO club_team_edges(parent_team_id,child_team_id,valid_from,actor_trainer_id,reason)
    VALUES(1,2,'2020-01-01',7,'LOCALHOST NEPLATIT HTTP hierarchy')");
$q = $pdo->prepare("INSERT IGNORE INTO club_roster_members(team_id,sportovec_id,status,source,valid_from,created_by_trainer_id)
    VALUES(2,?,'active','manual','2020-01-01',7)");
foreach (range(1001, 1020) as $person) {
    $q->execute([$person]);
}
// Mixed medical states for actual positive/empty state filters, without personal documents.
$pdo->exec("UPDATE medical_clearance_records SET state='revoked' WHERE state='verified' AND watch_id % 10 = 0");

$webUser = 'http_' . bin2hex(random_bytes(5));
$webPassword = bin2hex(random_bytes(20));
$admin->exec('CREATE USER ' . $admin->quote($webUser) . "@'127.0.0.1' IDENTIFIED BY " . $admin->quote($webPassword));
$admin->exec('GRANT SELECT,INSERT,UPDATE,DELETE ON `' . $schema . '`.* TO ' . $admin->quote($webUser) . "@'127.0.0.1'");
$config = ['DB_HOST' => '127.0.0.1;port=' . $runtime['port'], 'DB_NAME' => $schema, 'DB_USER' => $webUser, 'DB_PASS' => $webPassword,
    'APP_BASE_URL' => 'http://127.0.0.1:' . $runtime['http_port'], 'AUTH_RATE_LIMIT_PEPPER' => bin2hex(random_bytes(32)),
    'VELOCOTA_INTEGRATION' => false,
    'FIO_IMPORT_ENABLED' => false, 'FIO_AUTO_CONFIRM_ENABLED' => false, 'STRIPE_ENABLED' => false,
    'SUMUP_ENABLED' => false, 'MONTHLY_MEMBERSHIP_MAIL_ENABLED' => false];
$php = "<?php\n// OWNED SYNTHETIC LOCALHOST ONLY; generated by FullHttpReadFixture.\n";
foreach ($config as $key => $value) {
    $php .= 'define(' . var_export($key, true) . ', ' . var_export($value, true) . ");\n";
}
file_put_contents(dirname(__DIR__, 2) . '/config.php', $php);
$runtime += ['schema' => $schema, 'web_user' => $webUser, 'config_sha256' => hash('sha256', $php),
    'base_url' => $config['APP_BASE_URL'], 'external_sends_disabled' => true,
    'fixture_tables' => (int)$pdo->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')->fetchColumn(),
    'migrations' => (int)$pdo->query('SELECT COUNT(*) FROM evidence_schema_migrations')->fetchColumn()];
file_put_contents($output . '/runtime.json', json_encode($runtime, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
file_put_contents($output . '/credentials.json', json_encode($credentials, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
echo json_encode(['schema' => $schema, 'tables' => $runtime['fixture_tables'], 'migrations' => $runtime['migrations'],
    'roles' => array_keys($credentials), 'synthetic_only' => true], JSON_THROW_ON_ERROR) . "\n";
