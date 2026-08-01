<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/password_security.php';

$arguments = array_slice($argv, 1);
if ($arguments === []) {
    $mode = '--check';
} elseif (count($arguments) === 1 && in_array($arguments[0], ['--check', '--apply'], true)) {
    $mode = $arguments[0];
} else {
    fwrite(STDERR, "Usage: php bin/migrate-trainer-passwords.php [--check|--apply]\n");
    exit(2);
}

$configFile = dirname(__DIR__) . '/config.php';
if (!is_file($configFile)) {
    fwrite(STDERR, "Missing config.php; database connection was not attempted.\n");
    exit(2);
}

require $configFile;

foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $requiredConstant) {
    if (!defined($requiredConstant)) {
        fwrite(STDERR, "Missing required database configuration.\n");
        exit(2);
    }
}

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME),
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    if ($mode === '--check') {
        $rows = $pdo->query('SELECT heslo FROM treneri')->fetchAll();
        $legacyCount = 0;
        $modernCount = 0;

        foreach ($rows as $row) {
            if (trainer_password_is_modern_hash((string)($row['heslo'] ?? ''))) {
                $modernCount++;
            } else {
                $legacyCount++;
            }
        }

        printf(
            "Mode: check\nTrainer passwords: %d total, %d modern, %d legacy.\nNo data was changed.\n",
            count($rows),
            $modernCount,
            $legacyCount
        );
        exit($legacyCount === 0 ? 0 : 1);
    }

    $pdo->beginTransaction();
    $rows = $pdo->query('SELECT id, heslo FROM treneri FOR UPDATE')->fetchAll();
    $update = $pdo->prepare('UPDATE treneri SET heslo = ? WHERE id = ? AND heslo = ? LIMIT 1');
    $legacyCount = 0;
    $updatedCount = 0;
    $conflictCount = 0;

    foreach ($rows as $row) {
        $storedPassword = (string)($row['heslo'] ?? '');
        if (trainer_password_is_modern_hash($storedPassword)) {
            continue;
        }

        $legacyCount++;
        $update->execute([
            trainer_password_hash($storedPassword),
            (int)$row['id'],
            $storedPassword,
        ]);

        if ($update->rowCount() === 1) {
            $updatedCount++;
        } else {
            $conflictCount++;
        }
    }

    if ($conflictCount > 0) {
        $pdo->rollBack();
        fwrite(STDERR, "Migration aborted because one or more records changed concurrently. No data was changed.\n");
        exit(1);
    }

    $pdo->commit();
    printf(
        "Mode: apply\nLegacy passwords found: %d. Passwords migrated: %d.\n",
        $legacyCount,
        $updatedCount
    );
    exit(0);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, "Trainer password migration failed. No password values were printed.\n");
    exit(1);
}
