<?php

declare(strict_types=1);

/** Portable localhost-only runtime for the historical sports race contracts. */
function sportRaceRuntime(string $prefix, string $schema, bool $worker): array
{
    if (!in_array($prefix, ['evidence_training_race_', 'evidence_training_auth_', 'evidence_medical_race_'], true)
        || !preg_match('/^' . preg_quote($prefix, '/') . '[a-f0-9]{8}$/D', $schema)) {
        throw new RuntimeException('Invalid owned race schema.');
    }
    $port = getenv('EVIDENCE_MARIADB_SMOKE_PORT') ?: '3306';
    if (!ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) {
        throw new RuntimeException('Invalid localhost port.');
    }
    $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false];
    $dsn = 'mysql:host=127.0.0.1;port=' . $port . ';charset=utf8mb4';
    $root = new PDO($dsn, 'root', '', $options);
    $runtime = sys_get_temp_dir() . '/' . $schema;
    if (!$worker) {
        $root->exec('CREATE DATABASE `' . $schema . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        if (!mkdir($runtime, 0700)) {
            $root->exec('DROP DATABASE `' . $schema . '`');
            throw new RuntimeException('Cannot create owned race directory.');
        }
        register_shutdown_function(static function () use ($root, $schema, $runtime): void {
            $pdo = $GLOBALS['pdo'] ?? null;
            if ($pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            foreach (array_merge($GLOBALS['processes'] ?? [], [$GLOBALS['process'] ?? null]) as $process) {
                if (is_resource($process)) {
                    proc_terminate($process);
                    proc_close($process);
                }
            }
            $root->exec('DROP DATABASE `' . $schema . '`');
            foreach (glob($runtime . '/*') ?: [] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
            rmdir($runtime);
        });
    } elseif (!is_dir($runtime)) {
        throw new RuntimeException('Parent race runtime missing.');
    }
    $pdo = new PDO($dsn . ';dbname=' . $schema, 'root', '', $options);
    $pdo->exec('SET SESSION innodb_lock_wait_timeout=20');
    return [$root, $pdo, $runtime];
}
