<?php

declare(strict_types=1);

require_once __DIR__ . '/ProgramCapacityMigratedFixture.php';
require_once dirname(__DIR__, 2) . '/includes/trainer_pilot.php';

$database = 'evidence_trainer_pilot_test_' . bin2hex(random_bytes(6));
$port = getenv('EVIDENCE_MARIADB_SMOKE_PORT') ?: '3306';
if (!ctype_digit($port) || (int)$port < 1 || (int)$port > 65535) {
    throw new RuntimeException('Invalid isolated test port.');
}
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false];
$server = new PDO('mysql:host=127.0.0.1;port=' . $port . ';charset=utf8mb4', 'root', '', $options);
$server->exec('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=' . $port . ';dbname=' . $database . ';charset=utf8mb4', 'root', '', $options);
    Tests\Support\programCapacityMigratedSchema($pdo, static function (PDO $pdo): void {
        $pdo->exec('ALTER TABLE sportovci MODIFY id INT NOT NULL AUTO_INCREMENT,ADD COLUMN email VARCHAR(255),ADD COLUMN telefon VARCHAR(40),'
            . 'ADD COLUMN hash VARCHAR(64),ADD COLUMN uci TINYINT DEFAULT 0,ADD COLUMN stav_clenstvi VARCHAR(30)');
        $pdo->exec("INSERT INTO treneri(jmeno,email,heslo,role,aktivni) VALUES('Fixture Owner','owner@example.test','unchanged-owner-hash','admin',1)");
    });
    $first = trainerPilotCreate($pdo, 'Fixture Owner', 'pilot@example.test');
    $second = trainerPilotCreate($pdo, 'Fixture Owner', 'pilot@example.test');
    if (!$first['changed'] || $second['changed'] || !$second['scope_ready'] || $second['visible_test_people'] !== 2
        || $first['valid_to'] !== $second['valid_to'] || $second['outside_scope_visible'] || !$second['reset_eligible']) {
        throw new RuntimeException('Isolated trainer pilot contract failed.');
    }
    $pdo->exec('START TRANSACTION READ ONLY');
    $status = trainerPilotStatus($pdo, 'Fixture Owner');
    $pdo->rollBack();
    echo json_encode(['ok' => true, 'database' => 'isolated synthetic database', 'created' => $first['changed'],
        'idempotent' => !$second['changed'], 'visible_test_people' => $status['visible_test_people'],
        'outside_scope_visible' => $status['outside_scope_visible'], 'reset_eligible' => $status['reset_eligible']], JSON_THROW_ON_ERROR) . PHP_EOL;
} finally {
    $server->exec('DROP DATABASE `' . $database . '`');
}
