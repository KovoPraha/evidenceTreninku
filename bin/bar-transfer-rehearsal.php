<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
umask(0077);
require_once dirname(__DIR__) . '/includes/bar/transfer_verification.php';
require_once dirname(__DIR__) . '/includes/migration_runner.php';

try {
    $pdo = barIsolatedTransferConnection('BAR_TARGET');
    if ((int)$pdo->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')->fetchColumn() !== 0) {
        throw new RuntimeException('Rehearsal requires an empty isolated database.');
    }
    $package = (string)getenv('BAR_REHEARSAL_PACKAGE');
    $file = $package . '/bundle.json';
    if (!is_file($file) || is_link($file) || filesize($file) > 32 * 1024 * 1024) {
        throw new RuntimeException('Invalid private package.');
    }
    $bundle = barValidateTransferBundle(json_decode((string)file_get_contents($file), true, 32, JSON_THROW_ON_ERROR), $package . '/images');
    // Import only the synthetic KIS schema; no production target dump is used.
    $process = proc_open(
        [(string)(getenv('BAR_MYSQL_CLIENT') ?: 'mariadb'), '--protocol=tcp', '-h', '127.0.0.1',
        '-P', (string)getenv('BAR_REHEARSAL_PORT'), '-u', (string)getenv('BAR_TARGET_USER'),
        (string)$pdo->query('SELECT DATABASE()')->fetchColumn()],
        [0 => ['file', dirname(__DIR__) . '/database/local-demo-schema.sql', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        null,
        array_merge(getenv(), ['MYSQL_PWD' => (string)getenv('BAR_TARGET_PASSWORD')])
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot import synthetic schema.');
    }
    stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('Synthetic schema import failed.');
    }
    $pdo->prepare('INSERT INTO nastaveni(klic,hodnota) VALUES(?,?) ON DUPLICATE KEY UPDATE hodnota=VALUES(hodnota)')
        ->execute(['schema_version', LEGACY_SCHEMA_VERSION]);
    $runner = new EvidenceMigrationRunner($pdo, EvidenceMigrationCatalog::load(dirname(__DIR__) . '/migrations'));
    $runner->apply();
    $pdo->prepare("INSERT INTO treneri(id,jmeno,email,heslo,role,aktivni,session_version) VALUES(7001,'LOCALHOST transfer operator','bar.rehearsal@example.invalid',?,'trener',1,1)")
        ->execute([password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT)]);
    $pdo->exec("INSERT INTO staff_user_positions(trainer_id,position_code,is_default) VALUES(7001,'system_admin',1)");
    $beforeUsers = (int)$pdo->query('SELECT COUNT(*) FROM treneri')->fetchColumn();
    $_SESSION = ['trener_id' => 7001, 'trener_session_version' => 1, 'staff_active_position' => 'system_admin'];
    $first = barImportTransfer($pdo, $bundle, $package . '/images');
    $verified = barVerifyInitialTransfer($pdo, $bundle, $package . '/images');
    if ($first['repeated'] || (int)$pdo->query('SELECT COUNT(*) FROM treneri')->fetchColumn() !== $beforeUsers + 1) {
        throw new RuntimeException('Unexpected account count.');
    }
    $repeat = barImportTransfer($pdo, $bundle, $package . '/images');
    barVerifyInitialTransfer($pdo, $bundle, $package . '/images');
    $id = (int)$pdo->query('SELECT MIN(id) FROM kis_bar_products')->fetchColumn();
    $pdo->prepare('UPDATE kis_bar_products SET price_minor=price_minor+1,stock_stall=7,stock_office=3 WHERE id=?')->execute([$id]);
    $changed = $pdo->query('SELECT * FROM kis_bar_products ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $afterChange = barImportTransfer($pdo, $bundle, $package . '/images');
    if (!$repeat['repeated'] || !$afterChange['repeated']
        || $pdo->query('SELECT * FROM kis_bar_products ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) !== $changed) {
        throw new RuntimeException('Replay overwrote later changes.');
    }
    echo json_encode(['ok' => true, 'isolated' => true, 'synthetic_target_schema' => true, 'verification' => $verified,
        'replay_preserves_changes' => true, 'source_writes' => false, 'production_target_writes' => false], JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable) {
    fwrite(STDERR, "bar_transfer_rehearsal_failed_no_production_writes\n");
    exit(1);
}
