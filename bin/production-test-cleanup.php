<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$confirm = (string)getenv('KIS_TEST_CLEANUP_CONFIRM');
$appRoot = realpath((string)getenv('APP_ROOT'));
if ($confirm !== 'DEAKTIVOVAT' || $appRoot === false || !is_file($appRoot . '/config.php')) {
    fwrite(STDERR, "production_test_cleanup_refused\n");
    exit(2);
}

require $appRoot . '/config.php';
foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $constant) {
    if (!defined($constant)) {
        fwrite(STDERR, "production_test_cleanup_missing_database_configuration\n");
        exit(2);
    }
}

$pdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER,
    DB_PASS,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

$pattern = '^kis-e2e-[0-9]+@velocota[.]com$';
$pdo->beginTransaction();
try {
    $ids = $pdo->prepare(
        'SELECT id FROM verejni_uzivatele WHERE email REGEXP ? AND aktivni=1 FOR UPDATE'
    );
    $ids->execute([$pattern]);
    $accountIds = array_map('intval', $ids->fetchAll(PDO::FETCH_COLUMN));

    $deactivated = 0;
    $tokensConsumed = 0;
    if ($accountIds !== []) {
        $placeholders = implode(',', array_fill(0, count($accountIds), '?'));
        $tokens = $pdo->prepare(
            'UPDATE password_reset_tokens SET consumed_at=COALESCE(consumed_at,CURRENT_TIMESTAMP) '
            . 'WHERE delivery_account_id IN (' . $placeholders . ')'
        );
        $tokens->execute($accountIds);
        $tokensConsumed = $tokens->rowCount();

        $accounts = $pdo->prepare(
            'UPDATE verejni_uzivatele SET aktivni=0,session_version=session_version+1 '
            . 'WHERE id IN (' . $placeholders . ') AND aktivni=1'
        );
        $accounts->execute($accountIds);
        $deactivated = $accounts->rowCount();
    }
    $pdo->commit();
    echo json_encode([
        'ok' => true,
        'matched' => count($accountIds),
        'deactivated' => $deactivated,
        'tokens_consumed' => $tokensConsumed,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "production_test_cleanup_failed\n");
    exit(1);
}
