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
        'SELECT id FROM verejni_uzivatele WHERE (email REGEXP ? OR LOWER(email) IN (?,?)) AND aktivni=1 FOR UPDATE'
    );
    $ids->execute([$pattern, 'tester.karel@velocota.com', 'tester.petra@velocota.com']);
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

    $children = $pdo->prepare(
        "UPDATE child_access_accounts a JOIN sportovci s ON s.id=a.sportovec_id "
        . "SET a.active=0,a.session_version=a.session_version+1,a.updated_at=CURRENT_TIMESTAMP "
        . "WHERE a.active=1 AND LOWER(s.prijmeni)='tester' AND LOWER(s.jmeno) IN ('ema','adam')"
    );
    $children->execute();

    $events = $pdo->prepare(
        "UPDATE club_events SET status='closed',planning_status='cancelled',visibility='staff',updated_at=CURRENT_TIMESTAMP "
        . "WHERE name LIKE 'TEST -%' AND status<>'archived'"
    );
    $events->execute();

    $lessons = $pdo->prepare(
        "UPDATE individualni_lekce SET stav='zrusena' WHERE nazev LIKE 'TEST -%' AND stav='aktivni'"
    );
    $lessons->execute();

    $trainings = $pdo->prepare(
        "UPDATE planovane_treninky SET stav='zruseny',je_verejny=0 WHERE nazev LIKE 'TEST -%' AND stav='planovany'"
    );
    $trainings->execute();

    $products = $pdo->prepare(
        "UPDATE shop_products p JOIN shop_product_publications pub ON pub.product_id=p.id "
        . "SET p.catalog_status='inactive',pub.status='inactive',pub.deactivated_at=CURRENT_TIMESTAMP,p.updated_at=CURRENT_TIMESTAMP,pub.updated_at=CURRENT_TIMESTAMP "
        . "WHERE pub.public_name LIKE 'TEST -%'"
    );
    $products->execute();
    $pdo->exec(
        "UPDATE shop_variants v JOIN shop_products p ON p.id=v.product_id JOIN shop_product_publications pub ON pub.product_id=p.id "
        . "SET v.catalog_status='inactive',v.updated_at=CURRENT_TIMESTAMP WHERE pub.public_name LIKE 'TEST -%'"
    );
    $eventVariants = $pdo->prepare(
        "UPDATE shop_variants SET catalog_status='inactive',updated_at=CURRENT_TIMESTAMP WHERE sku=? AND catalog_status<>'inactive'"
    );
    $eventVariants->execute(['KP-TEST-UAT-PRIMESTSKY-DEN']);
    $eventProducts = $pdo->prepare(
        "UPDATE shop_products p JOIN shop_variants v ON v.product_id=p.id SET p.catalog_status='inactive',p.updated_at=CURRENT_TIMESTAMP "
        . "WHERE v.sku=? AND p.catalog_status<>'inactive'"
    );
    $eventProducts->execute(['KP-TEST-UAT-PRIMESTSKY-DEN']);
    $pdo->commit();
    echo json_encode([
        'ok' => true,
        'matched' => count($accountIds),
        'deactivated' => $deactivated,
        'tokens_consumed' => $tokensConsumed,
        'children_deactivated' => $children->rowCount(),
        'events_closed' => $events->rowCount(),
        'lessons_cancelled' => $lessons->rowCount(),
        'trainings_cancelled' => $trainings->rowCount(),
        'products_deactivated' => $products->rowCount(),
        'event_products_deactivated' => $eventProducts->rowCount(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "production_test_cleanup_failed\n");
    exit(1);
}
