<?php
declare(strict_types=1);

$database = getenv('EVIDENCE_CHILD_SMOKE_DB') ?: 'evidence_m18_child_test';
if (preg_match('/\Aevidence_m18_child_test_[a-z0-9_]+\z/', $database) !== 1
    && $database !== 'evidence_m18_child_test'
) {
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
    ]);
    $pdo->exec(
        'CREATE TABLE sportovci ('
        . 'id INT NOT NULL PRIMARY KEY,jmeno VARCHAR(100),prijmeni VARCHAR(100),'
        . 'narozeni DATE NULL,stav_clenstvi VARCHAR(30) NULL) ENGINE=InnoDB'
    );
    $pdo->exec(
        'CREATE TABLE treneri ('
        . 'id INT NOT NULL PRIMARY KEY,jmeno VARCHAR(100),aktivni TINYINT NOT NULL) ENGINE=InnoDB'
    );
    $pdo->exec("INSERT INTO sportovci VALUES(10,'Anna','Testovací','2012-01-01','aktivni')");
    $pdo->exec("INSERT INTO treneri VALUES(1,'Admin',1)");

    $migration = require dirname(__DIR__, 2) . '/migrations/20260804190000_child_access_accounts.php';
    $migration['up']($pdo);
    if (!$migration['verify']($pdo)) {
        throw new RuntimeException('MariaDB migration verification failed.');
    }

    require_once dirname(__DIR__, 2) . '/includes/child_access.php';
    childAccessCreate($pdo, 10, 'anna.test', 'BezpecneHeslo123!', 1, 'MariaDB smoke.');
    if (childAccessAuthenticate($pdo, 'anna.test', 'BezpecneHeslo123!') === null) {
        throw new RuntimeException('MariaDB authentication smoke failed.');
    }
    childAccessSetActive($pdo, 1, false, 1, 'MariaDB revocation smoke.');
    if (childAccessAuthenticate($pdo, 'anna.test', 'BezpecneHeslo123!') !== null) {
        throw new RuntimeException('MariaDB revocation smoke failed.');
    }
    echo "MariaDB child access smoke OK\n";
} finally {
    $server->exec('DROP DATABASE IF EXISTS ' . $quotedDatabase);
}
