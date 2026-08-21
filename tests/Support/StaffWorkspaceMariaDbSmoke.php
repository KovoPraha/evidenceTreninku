<?php
declare(strict_types=1);

$database = getenv('EVIDENCE_STAFF_WORKSPACE_SMOKE_DB') ?: 'evidence_staff_workspace_test';
if (preg_match('/\Aevidence_staff_workspace_test(?:_[a-z0-9_]+)?\z/', $database) !== 1) {
    throw new RuntimeException('Refusing to use a non-test MariaDB database name.');
}

$server = new PDO('mysql:host=127.0.0.1;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$quotedDatabase = '`' . str_replace('`', '``', $database) . '`';
$server->exec('DROP DATABASE IF EXISTS ' . $quotedDatabase);
$server->exec('CREATE DATABASE ' . $quotedDatabase . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=' . $database . ';charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec(
        "CREATE TABLE treneri(id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,jmeno VARCHAR(160) NOT NULL,"
        . "role ENUM('trener','hlavni','admin') NOT NULL,aktivni TINYINT(1) NOT NULL DEFAULT 1) "
        . 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $pdo->exec("INSERT INTO treneri(jmeno,role,aktivni) VALUES('Coach','trener',1),('Lead','hlavni',1),('Admin','admin',1),('Off','admin',0)");
    $migration = require dirname(__DIR__, 2) . '/migrations/20260821150000_staff_workspaces.php';
    $migration['up']($pdo);
    $migration['up']($pdo);
    if (!$migration['verify']($pdo)) throw new RuntimeException('Staff workspace migration verify failed.');
    if ((int)$pdo->query('SELECT COUNT(*) FROM staff_positions')->fetchColumn() !== 8) throw new RuntimeException('Position seed count mismatch.');
    if ((int)$pdo->query("SELECT COUNT(*) FROM staff_user_positions WHERE trainer_id=3")->fetchColumn() !== 8) throw new RuntimeException('Admin compatibility assignment mismatch.');
    if ((int)$pdo->query('SELECT COUNT(*) FROM staff_superadmins WHERE trainer_id=3')->fetchColumn() !== 1) throw new RuntimeException('Admin superadmin backfill mismatch.');
    $pdo->exec(
        "INSERT INTO staff_position_switch_events(trainer_id,from_position_code,to_position_code,used_superadmin,reason) "
        . "VALUES(3,'system_admin','finance_manager',1,'MariaDB smoke')"
    );
    if ((int)$pdo->query('SELECT COUNT(*) FROM staff_position_switch_events')->fetchColumn() !== 1) throw new RuntimeException('Switch audit insert failed.');
    echo 'MariaDB staff workspace smoke OK (' . (string)$pdo->query('SELECT VERSION()')->fetchColumn() . ")\n";
} finally {
    $server->exec('DROP DATABASE IF EXISTS ' . $quotedDatabase);
}
