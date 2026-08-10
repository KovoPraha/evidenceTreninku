<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$confirm = (string)getenv('KIS_INVARIANT_CONFIRM');
$appRoot = realpath((string)getenv('APP_ROOT'));
if ($confirm !== 'OVERIT' || $appRoot === false || !is_file($appRoot . '/config.php')) {
    fwrite(STDERR, "production_invariants_refused\n");
    exit(2);
}

require $appRoot . '/config.php';
foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $constant) {
    if (!defined($constant)) {
        fwrite(STDERR, "production_invariants_missing_database_configuration\n");
        exit(2);
    }
}

try {
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

    $tableExists = static function (string $table) use ($pdo): bool {
        $statement = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?'
        );
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    };
    $columnsExist = static function (string $table, array $columns) use ($pdo): bool {
        $statement = $pdo->prepare(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?'
        );
        $statement->execute([$table]);
        $available = array_fill_keys($statement->fetchAll(PDO::FETCH_COLUMN), true);
        foreach ($columns as $column) {
            if (!isset($available[$column])) return false;
        }
        return true;
    };

    $checks = [
        ['orphan_shop_order_items', ['shop_order_items', 'shop_orders'], [],
            'SELECT COUNT(*) FROM shop_order_items i LEFT JOIN shop_orders o ON o.id=i.order_id WHERE o.id IS NULL'],
        ['invalid_shop_order_totals', ['shop_orders'], ['shop_orders' => ['subtotal_minor', 'discount_minor', 'total_minor']],
            'SELECT COUNT(*) FROM shop_orders WHERE discount_minor>subtotal_minor OR total_minor<>subtotal_minor-discount_minor'],
        ['invalid_shop_order_lines', ['shop_order_items'], ['shop_order_items' => ['quantity', 'unit_amount_minor', 'line_amount_minor']],
            'SELECT COUNT(*) FROM shop_order_items WHERE quantity<1 OR line_amount_minor<>quantity*unit_amount_minor'],
        ['orphan_shop_order_payments', ['payments', 'shop_orders'], ['payments' => ['payable_type', 'payable_id']],
            "SELECT COUNT(*) FROM payments p LEFT JOIN shop_orders o ON o.id=p.payable_id WHERE p.payable_type='shop_order' AND o.id IS NULL"],
        ['duplicate_shop_order_payments', ['payments'], ['payments' => ['payable_type', 'payable_id']],
            "SELECT COUNT(*) FROM (SELECT payable_id FROM payments WHERE payable_type='shop_order' GROUP BY payable_id HAVING COUNT(*)>1) duplicates"],
        ['mismatched_shop_order_payments', ['payments', 'shop_orders'], ['payments' => ['payable_type', 'payable_id', 'amount_minor', 'currency'], 'shop_orders' => ['total_minor', 'currency']],
            "SELECT COUNT(*) FROM payments p JOIN shop_orders o ON o.id=p.payable_id WHERE p.payable_type='shop_order' AND (p.amount_minor<>o.total_minor OR p.currency<>o.currency)"],
        ['orphan_account_person_roles_accounts', ['account_person_roles', 'verejni_uzivatele'], ['account_person_roles' => ['account_id']],
            'SELECT COUNT(*) FROM account_person_roles r LEFT JOIN verejni_uzivatele a ON a.id=r.account_id WHERE a.id IS NULL'],
        ['orphan_account_person_roles_people', ['account_person_roles', 'sportovci'], ['account_person_roles' => ['sportovec_id']],
            'SELECT COUNT(*) FROM account_person_roles r LEFT JOIN sportovci s ON s.id=r.sportovec_id WHERE s.id IS NULL'],
        ['duplicate_active_account_person_roles', ['account_person_roles'], ['account_person_roles' => ['account_id', 'sportovec_id', 'status', 'valid_to']],
            "SELECT COUNT(*) FROM (SELECT account_id,sportovec_id FROM account_person_roles WHERE status='approved' AND valid_to IS NULL GROUP BY account_id,sportovec_id HAVING COUNT(*)>1) duplicates"],
        ['orphan_child_access_accounts', ['child_access_accounts', 'sportovci'], ['child_access_accounts' => ['sportovec_id']],
            'SELECT COUNT(*) FROM child_access_accounts a LEFT JOIN sportovci s ON s.id=a.sportovec_id WHERE s.id IS NULL'],
        ['invalid_child_session_versions', ['child_access_accounts'], ['child_access_accounts' => ['session_version']],
            'SELECT COUNT(*) FROM child_access_accounts WHERE session_version<1'],
        ['orphan_training_assignments_trainings', ['trenink_sportovec', 'treninky'], ['trenink_sportovec' => ['trenink_id']],
            'SELECT COUNT(*) FROM trenink_sportovec x LEFT JOIN treninky t ON t.id=x.trenink_id WHERE t.id IS NULL'],
        ['orphan_training_assignments_people', ['trenink_sportovec', 'sportovci'], ['trenink_sportovec' => ['sportovec_id']],
            'SELECT COUNT(*) FROM trenink_sportovec x LEFT JOIN sportovci s ON s.id=x.sportovec_id WHERE s.id IS NULL'],
        ['invalid_active_lesson_windows', ['individualni_lekce'], ['individualni_lekce' => ['cas_od', 'cas_do', 'stav']],
            "SELECT COUNT(*) FROM individualni_lekce WHERE stav='aktivni' AND cas_do<=cas_od"],
        ['orphan_club_event_registrations_accounts', ['club_event_registrations', 'verejni_uzivatele'], ['club_event_registrations' => ['account_id']],
            'SELECT COUNT(*) FROM club_event_registrations r LEFT JOIN verejni_uzivatele a ON a.id=r.account_id WHERE a.id IS NULL'],
        ['orphan_club_event_registrations_people', ['club_event_registrations', 'sportovci'], ['club_event_registrations' => ['sportovec_id']],
            'SELECT COUNT(*) FROM club_event_registrations r LEFT JOIN sportovci s ON s.id=r.sportovec_id WHERE s.id IS NULL'],
    ];

    $results = [];
    $violations = [];
    foreach ($checks as [$name, $tables, $requiredColumns, $sql]) {
        $supported = true;
        foreach ($tables as $table) {
            if (!$tableExists($table)) $supported = false;
        }
        foreach ($requiredColumns as $table => $columns) {
            if (!$columnsExist($table, $columns)) $supported = false;
        }
        if (!$supported) {
            $results[$name] = ['status' => 'skipped_missing_schema', 'count' => null];
            continue;
        }
        $count = (int)$pdo->query($sql)->fetchColumn();
        $results[$name] = ['status' => $count === 0 ? 'ok' : 'violation', 'count' => $count];
        if ($count > 0) $violations[$name] = $count;
    }

    echo json_encode([
        'ok' => $violations === [],
        'checked' => count(array_filter($results, static fn (array $result): bool => $result['status'] !== 'skipped_missing_schema')),
        'checks' => $results,
        'violations' => $violations,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit($violations === [] ? 0 : 1);
} catch (Throwable) {
    fwrite(STDERR, "production_invariants_failed\n");
    exit(1);
}
