<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__, 2) . '/includes/bar/access.php';
$port = (int)(getenv('BAR_SMOKE_DB_PORT') ?: 3306);
if ($port < 1024 || $port > 65535) {
    throw new RuntimeException('Invalid isolated database port.');
}
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false];
$server = new PDO('mysql:host=127.0.0.1;port=' . $port . ';charset=utf8mb4', 'root', '', $options);
$database = 'evidence_bar_test_' . bin2hex(random_bytes(6));
$server->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try {
    $dsn = 'mysql:host=127.0.0.1;port=' . $port . ';dbname=' . $database . ';charset=utf8mb4';
    $pdo = new PDO($dsn, 'root', '', $options);
    $writer = new PDO($dsn, 'root', '', $options);
    $pdo->exec('CREATE TABLE treneri(id INT PRIMARY KEY,jmeno VARCHAR(100),role VARCHAR(20),aktivni INT,session_version INT) ENGINE=InnoDB');
    $pdo->exec("INSERT INTO treneri VALUES(1,'LOCALHOST System','trener',1,1),(2,'LOCALHOST Bar','trener',1,1)");
    $staff = require dirname(__DIR__, 2) . '/migrations/20260821150000_staff_workspaces.php';
    $staff['up']($pdo);
    $pdo->exec('DELETE FROM staff_user_positions');
    $migration = require dirname(__DIR__, 2) . '/migrations/20261105150000_bar_foundation.php';
    $migration['up']($pdo);
    $migration['up']($pdo);
    if (!$migration['verify']($pdo)) {
        throw new RuntimeException('Bar schema verification failed.');
    }
    $pdo->exec("INSERT INTO staff_user_positions(trainer_id,position_code,is_default) VALUES(1,'system_admin',1),(2,'bar_operator',1)");
    $_SESSION = ['trener_id' => 1, 'trener_session_version' => 1, 'staff_active_position' => 'system_admin'];
    if (!barSetConsumptionPermission($pdo, 2, true, 'LOCALHOST isolated grant')
        || barSetConsumptionPermission($pdo, 2, true, 'LOCALHOST repeated grant')) {
        throw new RuntimeException('Permission idempotence failed.');
    }
    $_SESSION = ['trener_id' => 2, 'trener_session_version' => 1, 'staff_active_position' => 'bar_operator'];
    barActor($pdo, 'own_consumption');
    // Establish a stale REPEATABLE READ snapshot before the other connection revokes the grant.
    $pdo->beginTransaction();
    $pdo->query('SELECT * FROM kis_bar_permissions')->fetchAll();
    $writer->exec('DELETE FROM kis_bar_permissions WHERE trainer_id=2');
    $denied = false;
    try {
        barActor($pdo, 'own_consumption', true);
    } catch (DomainException $expected) {
        $denied = true;
    } finally {
        $pdo->rollBack();
    }
    if (!$denied) {
        throw new RuntimeException('A stale repeatable-read snapshot bypassed permission revocation.');
    }
    // The same current-read rule must protect revoked workplace membership.
    $pdo->beginTransaction();
    $pdo->query('SELECT * FROM staff_user_positions')->fetchAll();
    $writer->exec("DELETE FROM staff_user_positions WHERE trainer_id=2 AND position_code='bar_operator'");
    $denied = false;
    try {
        barActor($pdo, 'sell', true);
    } catch (DomainException $expected) {
        $denied = true;
    } finally {
        $pdo->rollBack();
    }
    if (!$denied) {
        throw new RuntimeException('A stale snapshot bypassed workspace revocation.');
    }
    if ((int)$pdo->query('SELECT COUNT(*) FROM kis_bar_events')->fetchColumn() !== 1) {
        throw new RuntimeException('Permission audit is not idempotent.');
    }
    echo 'MariaDB bar foundation: repeatable migration, audited grants and current locking reads PASS ('
        . $server->query('SELECT VERSION()')->fetchColumn() . ")\n";
} finally {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Only the random database created by this test is ever removed.
    $server->exec("DROP DATABASE `$database`");
    $_SESSION = [];
}
