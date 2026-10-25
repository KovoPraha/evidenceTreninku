<?php
declare(strict_types=1);

// This separate CI runner never reads application credentials or the local B1 config.
// Refuse before connecting unless invoked by the Linux GitHub Actions test job.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (getenv('CI') !== 'true' || getenv('GITHUB_ACTIONS') !== 'true'
    || getenv('RUNNER_OS') !== 'Linux' || PHP_OS_FAMILY !== 'Linux'
) {
    throw new RuntimeException('Payment symbol smoke requires the isolated Linux GitHub Actions service.');
}

$root = dirname(__DIR__, 2);
require_once $root . '/includes/migration_runner.php';

// Fixed loopback service, no configurable production host, database or credentials.
$connect = static fn(string $database = ''): PDO => new PDO(
    'mysql:host=127.0.0.1;port=3306;' . ($database === '' ? '' : 'dbname=' . $database . ';') . 'charset=utf8mb4',
    'root',
    '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false]
);
$database = 'evidence_payment_vs_smoke_' . bin2hex(random_bytes(8));
if (preg_match('/\Aevidence_payment_vs_smoke_[a-f0-9]{16}\z/', $database) !== 1) {
    throw new RuntimeException('Unsafe smoke database name.');
}
$server = $connect();
$quotedDatabase = '`' . $database . '`';
// A collision must fail. Never drop or reuse an existing database.
$server->exec('CREATE DATABASE ' . $quotedDatabase . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo = null;
$checks = [];
$check = static function(bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException($label);
    $checks[] = $label;
};
set_error_handler(static function(int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
try {
    $pdo = $connect($database);
    $pdo->exec('CREATE TABLE nastaveni(klic VARCHAR(100) PRIMARY KEY,hodnota TEXT NOT NULL) ENGINE=InnoDB');
    $pdo->prepare('INSERT INTO nastaveni VALUES(?,?)')->execute(['schema_version', LEGACY_SCHEMA_VERSION]);
    $pdo->exec('CREATE TABLE sportovci(id INT PRIMARY KEY) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE member_fee_plans(id BIGINT UNSIGNED PRIMARY KEY) ENGINE=InnoDB');
    $pdo->exec('INSERT INTO sportovci VALUES(1)');
    $pdo->exec('INSERT INTO member_fee_plans VALUES(1)');
    $pdo->exec('CREATE TABLE payments(
        id INT PRIMARY KEY,payable_type VARCHAR(30),payable_id INT,variable_symbol VARCHAR(10),method VARCHAR(30),
        UNIQUE KEY aaa_symbol(variable_symbol),UNIQUE KEY zzz_symbol(variable_symbol),
        UNIQUE KEY uq_payable(payable_type,payable_id),UNIQUE KEY uq_composite(variable_symbol,method)
    ) ENGINE=InnoDB');
    $pdo->exec("INSERT INTO payments VALUES(1,'member_charge',11,'001234','bank'),(2,'member_charge',12,'009999','cash')");
    $paymentsBefore = $pdo->query('SELECT * FROM payments ORDER BY id')->fetchAll();
    $catalog = EvidenceMigrationCatalog::load($root . '/migrations');
    $legacyId = '20260922190000_member_fee_standing_orders';
    $forwardId = '20261024200000_payment_variable_symbol_index_repair';
    $legacy = $catalog[$legacyId];
    $forward = $catalog[$forwardId];
    $check($legacy['checksum'] === hash_file('sha256', $root . '/migrations/' . $legacyId . '.php'),
        'Historical migration file checksum remains exact');
    $check(paymentVariableSymbolUniqueIndexes($pdo) === ['aaa_symbol', 'zzz_symbol'],
        'Both single-column unique indexes detected regardless of index ordering');

    // Actual catalog compatibility shim through the migration runner, not a copied repair.
    $freshRunner = new EvidenceMigrationRunner($pdo, [$legacyId => $legacy]);
    $check($freshRunner->apply()['current'], 'Fresh standing-order migration applies without warnings');
    $check($legacy['verify']($pdo), 'Fresh schema satisfies the corrected legacy postcondition');
    $check($paymentsBefore === $pdo->query('SELECT * FROM payments ORDER BY id')->fetchAll(),
        'Fresh migration preserves payment identity and leading-zero symbols');
    $pdo->exec("INSERT INTO member_fee_standing_orders(plan_id,sportovec_id,variable_symbol) VALUES(1,1,'001234')");
    $standingBefore = $pdo->query('SELECT * FROM member_fee_standing_orders ORDER BY id')->fetchAll();

    $assertPaymentConstraints = static function() use ($pdo, $check): void {
        $pdo->exec("INSERT INTO payments VALUES(3,'member_charge',13,'001234','cash')");
        $check((int)$pdo->query("SELECT COUNT(*) FROM payments WHERE variable_symbol='001234'")->fetchColumn() === 2,
            'A second charge can reuse the existing payer symbol');
        foreach ([
            'Canonical payable identity remains unique' => "INSERT INTO payments VALUES(4,'member_charge',11,'005555','cash')",
            'Unrelated composite constraint remains unique' => "INSERT INTO payments VALUES(4,'member_charge',14,'001234','cash')",
            'Payment primary key remains unique' => "INSERT INTO payments VALUES(1,'member_charge',15,'005555','cash')",
        ] as $label => $sql) {
            $rejected = false;
            try { $pdo->exec($sql); }
            catch (PDOException $e) { $rejected = $e->getCode() === '23000'; }
            $check($rejected, $label);
        }
        $pdo->exec('DELETE FROM payments WHERE id=3');
    };
    $assertPaymentConstraints();

    // Reproduce a migrated database whose original MySQL index reader missed an index.
    // Its historical ledger entry must stay untouched when the forward repair applies.
    $ledgerBefore = $pdo->query('SELECT * FROM evidence_schema_migrations ORDER BY id')->fetchAll();
    $pdo->exec('ALTER TABLE payments ADD UNIQUE INDEX legacy_symbol(variable_symbol)');
    $check(!$forward['verify']($pdo), 'Forward repair detects the missed legacy symbol index');
    $upgradeRunner = new EvidenceMigrationRunner($pdo, [$legacyId => $legacy, $forwardId => $forward]);
    $check($upgradeRunner->check()['pending'] === [$forwardId], 'Only the forward repair is pending on upgrade');
    $check($upgradeRunner->apply()['current'], 'Forward repair applies through the actual migration runner');
    $check($forward['verify']($pdo), 'Forward repair removes every single-column symbol uniqueness constraint');
    $ledgerAfter = $pdo->query('SELECT * FROM evidence_schema_migrations ORDER BY id')->fetchAll();
    $oldLedgerAfter = array_values(array_filter($ledgerAfter, static fn(array $row): bool => $row['id'] !== $forwardId));
    $check($ledgerBefore === $oldLedgerAfter, 'Upgrade preserves historical ledger checksums and timestamps');
    $check($upgradeRunner->apply()['current'], 'A repeated migration runner apply is current');
    $check($ledgerAfter === $pdo->query('SELECT * FROM evidence_schema_migrations ORDER BY id')->fetchAll(),
        'Repeated apply does not rewrite migration ledger entries');
    $forward['up']($pdo);
    $forward['up']($pdo);
    $legacy['up']($pdo);
    $check($forward['verify']($pdo) && $legacy['verify']($pdo), 'Repeated repair callbacks remain idempotent');
    $assertPaymentConstraints();
    $check($paymentsBefore === $pdo->query('SELECT * FROM payments ORDER BY id')->fetchAll(),
        'Upgrade and repeated repair preserve all historical payment values');
    $check($standingBefore === $pdo->query('SELECT * FROM member_fee_standing_orders ORDER BY id')->fetchAll(),
        'Upgrade preserves standing-order identifiers and leading-zero symbols');
    $result = ['status' => 'PASS', 'server_version' => $pdo->query('SELECT VERSION()')->fetchColumn(),
        'checks' => count($checks), 'scenarios' => $checks, 'synthetic_only' => true, 'production_mutations' => false];
} finally {
    restore_error_handler();
    $pdo = null;
    // This name was created successfully by this process and is never supplied by a caller.
    $server->exec('DROP DATABASE ' . $quotedDatabase);
}
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
