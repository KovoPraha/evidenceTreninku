<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$configFile = $root . '/config.php';

if (!is_file($configFile)) {
    fwrite(STDERR, "CHYBA: Na serveru chybí config.php.\n");
    exit(2);
}

require_once $configFile;
require_once $root . '/includes/schema_version.php';

foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $constant) {
    if (!defined($constant)) {
        fwrite(STDERR, "CHYBA: V config.php chybí konstanta {$constant}.\n");
        exit(2);
    }
}

$checkOnly = in_array('--check', $argv, true);

try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    if (!$checkOnly) {
        require $root . '/includes/auto_migrace.php';
    }

    $stmt = $pdo->query(
        "SELECT hodnota FROM nastaveni WHERE klic = 'schema_version'"
    );
    $actualVersion = $stmt ? (string) $stmt->fetchColumn() : '';
} catch (Throwable $e) {
    fwrite(STDERR, "CHYBA DB: {$e->getMessage()}\n");
    exit(1);
}

if ($actualVersion !== SCHEMA_VERSION) {
    $shownVersion = $actualVersion !== '' ? $actualVersion : '(nenastavena)';
    fwrite(
        STDERR,
        "CHYBA: DB schéma je {$shownVersion}, očekává se " . SCHEMA_VERSION . ".\n"
    );
    exit(1);
}

fwrite(STDOUT, "DB schéma OK: {$actualVersion}\n");
