<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__, 2) . '/includes/tv_program.php';
$port = (int)(getenv('TV_SMOKE_DB_PORT') ?: 3306);
if ($port < 1024 || $port > 65535) {
    throw new RuntimeException('Invalid local smoke database port.');
}
$server = new PDO('mysql:host=127.0.0.1;port=' . $port . ';charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database = 'evidence_tv_test_' . bin2hex(random_bytes(6));
$server->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=' . $port . ';dbname=' . $database . ';charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec('CREATE TABLE treneri(id INT PRIMARY KEY) ENGINE=InnoDB');
    $pdo->exec('INSERT INTO treneri VALUES(1)');
    $pdo->exec('CREATE TABLE staff_positions(code VARCHAR(64) PRIMARY KEY,label VARCHAR(160),sort_order INT,active INT) ENGINE=InnoDB');
    $migration = require dirname(__DIR__, 2) . '/migrations/20261104180000_tv_programs.php';
    $migration['up']($pdo);
    $migration['up']($pdo);
    if (!$migration['verify']($pdo)) {
        throw new RuntimeException('Migration verification failed.');
    }
    $id = tvCreateProgram($pdo, 'LOCALHOST TV smoke', 1);
    $document = ['name' => 'LOCALHOST TV smoke', 'slides' => [[
        'id' => 'test', 'title' => 'Zkouška', 'duration' => 3, 'enabled' => true,
        'background' => '#112233', 'fit' => 'contain', 'asset_id' => null, 'blocks' => [],
    ]]];
    tvMutateProgram($pdo, $id, 1, 1, 'publish', $document);
    if (count(tvPublicProgram($pdo, $id)['document']['slides']) !== 1) {
        throw new RuntimeException('Publication failed.');
    }
    try {
        tvMutateProgram($pdo, $id, 1, 1, 'save', $document);
        throw new RuntimeException('Stale writer was accepted.');
    } catch (TvConflictException $expected) {
        // The transaction was rolled back without replacing the public revision.
    }
    tvMutateProgram($pdo, $id, 2, 1, 'archive');
    if (tvPublicProgram($pdo, $id) !== null) {
        throw new RuntimeException('Archive leaked public playback.');
    }
    echo 'MariaDB TV programmes smoke OK (' . $server->query('SELECT VERSION()')->fetchColumn() . ")\n";
} finally {
    // Only the random database created above is owned by this smoke test.
    $server->exec("DROP DATABASE `$database`");
}
