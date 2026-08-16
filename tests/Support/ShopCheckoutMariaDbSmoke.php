<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/migration_runner.php';
require_once dirname(__DIR__, 2) . '/includes/shop_checkout.php';

if (getenv('APP_BASE_URL') === false || trim((string)getenv('APP_BASE_URL')) === '') {
    putenv('APP_BASE_URL=http://localhost/evidencePavel');
}

const SHOP_CHECKOUT_SMOKE_BANK = [
    'iban' => 'CZ6508000000192000145399',
    'bic' => 'GIBACZPX',
    'account_label' => 'KOVO Praha',
    'due_days' => 7,
];

function shopCheckoutSmokePdo(string $database = ''): PDO
{
    $dsn = 'mysql:host=127.0.0.1;' . ($database !== '' ? 'dbname=' . $database . ';' : '') . 'charset=utf8mb4';
    return new PDO($dsn, 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

/** @return array{process:resource,pipes:array<int,resource>} */
function shopCheckoutSmokeWorker(string $database, string $key, string $fingerprint, string $workerId): array
{
    $command = escapeshellarg(PHP_BINARY)
        . ' ' . escapeshellarg(__FILE__)
        . ' --worker ' . escapeshellarg($database)
        . ' ' . escapeshellarg($key)
        . ' ' . escapeshellarg($fingerprint)
        . ' ' . escapeshellarg($workerId);
    $pipes = [];
    $process = proc_open($command, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Cannot start checkout concurrency worker.');
    fclose($pipes[0]);
    return ['process' => $process, 'pipes' => $pipes];
}

/** @return array{process:resource,pipes:array<int,resource>} */
function shopCheckoutSmokePaymentWorker(string $database, int $paymentId, string $workerId): array
{
    $command = escapeshellarg(PHP_BINARY)
        . ' ' . escapeshellarg(__FILE__)
        . ' --payment-worker ' . escapeshellarg($database)
        . ' ' . escapeshellarg((string)$paymentId)
        . ' ' . escapeshellarg($workerId);
    $pipes = [];
    $process = proc_open($command, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Cannot start payment confirmation concurrency worker.');
    fclose($pipes[0]);
    return ['process' => $process, 'pipes' => $pipes];
}

/** @param array{process:resource,pipes:array<int,resource>} $worker @return array<string,mixed> */
function shopCheckoutSmokeFinishWorker(array $worker): array
{
    $stdout = stream_get_contents($worker['pipes'][1]);
    $stderr = stream_get_contents($worker['pipes'][2]);
    fclose($worker['pipes'][1]);
    fclose($worker['pipes'][2]);
    $exitCode = proc_close($worker['process']);
    if ($exitCode !== 0) {
        throw new RuntimeException('Checkout concurrency worker failed: ' . trim((string)$stderr . ' ' . (string)$stdout));
    }
    return json_decode((string)$stdout, true, 512, JSON_THROW_ON_ERROR);
}

if (($argv[1] ?? '') === '--worker') {
    $database = (string)($argv[2] ?? '');
    $key = (string)($argv[3] ?? '');
    $fingerprint = (string)($argv[4] ?? '');
    $workerId = (string)($argv[5] ?? '');
    if (preg_match('/\Aevidence_shop_checkout_smoke_test(?:_[a-z0-9_]+)?\z/', $database) !== 1
        || preg_match('/\A[a-f0-9]{32}\z/', $key) !== 1
        || preg_match('/\A[a-f0-9]{64}\z/', $fingerprint) !== 1
        || preg_match('/\Aworker_[12]\z/', $workerId) !== 1
    ) {
        throw new RuntimeException('Invalid checkout concurrency worker input.');
    }
    $pdo = shopCheckoutSmokePdo($database);
    $pdo->prepare('INSERT INTO smoke_worker_ready(worker_id) VALUES(?)')->execute([$workerId]);
    $order = shopCheckoutPlace($pdo, 10, $key, SHOP_CHECKOUT_SMOKE_BANK, $fingerprint);
    echo json_encode(['id' => (int)$order['id'], 'replayed' => (bool)$order['replayed']], JSON_THROW_ON_ERROR) . PHP_EOL;
    exit(0);
}

if (($argv[1] ?? '') === '--payment-worker') {
    $database = (string)($argv[2] ?? '');
    $paymentId = (int)($argv[3] ?? 0);
    $workerId = (string)($argv[4] ?? '');
    if (preg_match('/\Aevidence_shop_checkout_smoke_test(?:_[a-z0-9_]+)?\z/', $database) !== 1
        || $paymentId < 1
        || preg_match('/\Apayment_worker_[12]\z/', $workerId) !== 1
    ) {
        throw new RuntimeException('Invalid payment confirmation concurrency worker input.');
    }
    $pdo = shopCheckoutSmokePdo($database);
    $pdo->prepare('INSERT INTO smoke_payment_worker_ready(worker_id) VALUES(?)')->execute([$workerId]);
    $result = shopOrderAdminConfirmBankPayment(
        $pdo,
        $paymentId,
        7,
        'Dvouprocesové ověření idempotence přijaté platby.',
        true
    );
    echo json_encode([
        'order_id' => (int)$result['order_id'],
        'changed' => (bool)$result['changed'],
    ], JSON_THROW_ON_ERROR) . PHP_EOL;
    exit(0);
}

$database = getenv('EVIDENCE_SHOP_CHECKOUT_SMOKE_DB') ?: 'evidence_shop_checkout_smoke_test';
if (preg_match('/\Aevidence_shop_checkout_smoke_test(?:_[a-z0-9_]+)?\z/', $database) !== 1) {
    throw new RuntimeException('Refusing to use a non-test MariaDB database name.');
}
$server = shopCheckoutSmokePdo();
$quotedDatabase = '`' . str_replace('`', '``', $database) . '`';
$server->exec('DROP DATABASE IF EXISTS ' . $quotedDatabase);
$server->exec('CREATE DATABASE ' . $quotedDatabase . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

try {
    $pdo = shopCheckoutSmokePdo($database);
    $pdo->exec('CREATE TABLE nastaveni(klic VARCHAR(100) PRIMARY KEY,hodnota TEXT NOT NULL) ENGINE=InnoDB');
    $pdo->prepare('INSERT INTO nastaveni(klic,hodnota) VALUES(?,?)')->execute(['schema_version', LEGACY_SCHEMA_VERSION]);
    $pdo->exec('CREATE TABLE treneri(id INT AUTO_INCREMENT PRIMARY KEY,jmeno VARCHAR(100),email VARCHAR(255) UNIQUE,heslo VARCHAR(255),role VARCHAR(30),aktivni TINYINT NOT NULL DEFAULT 1) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE sportovci(id INT PRIMARY KEY,jmeno VARCHAR(100),prijmeni VARCHAR(160)) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE sportovist(id INT PRIMARY KEY) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE individualni_lekce(id INT PRIMARY KEY,nazev VARCHAR(255),datum DATE,cas_od TIME,cas_do TIME,public_exclusive_booking TINYINT NOT NULL DEFAULT 0,cena_kc DECIMAL(10,2)) ENGINE=InnoDB');
    $pdo->exec("CREATE TABLE planovane_treninky(id INT PRIMARY KEY,datum DATE NOT NULL,stav VARCHAR(30) NOT NULL DEFAULT 'planovany') ENGINE=InnoDB");
    $pdo->exec('CREATE TABLE treninky(id INT PRIMARY KEY) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE mereni_zaznamy(id INT PRIMARY KEY) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE zavod_sportovec(zavod_id INT NOT NULL,sportovec_id INT NULL) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE verejni_uzivatele(id INT PRIMARY KEY,jmeno VARCHAR(100),prijmeni VARCHAR(160),email VARCHAR(255),aktivni TINYINT NOT NULL DEFAULT 1,email_overeno TINYINT NOT NULL DEFAULT 0,verifikacni_token VARCHAR(255) NULL,registrovan DATETIME NOT NULL) ENGINE=InnoDB');
    $pdo->exec("CREATE TABLE verejne_rezervace(id INT PRIMARY KEY,lekce_id INT NULL,stav VARCHAR(30) NOT NULL DEFAULT 'ceka',slot_cas_od TIME NULL,potvrzovaci_token VARCHAR(255) NULL,cas_rezervace DATETIME NOT NULL) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE kis_import_runs(id INT AUTO_INCREMENT PRIMARY KEY,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,created_by INT NULL,status ENUM('preview','applied','failed','cancelled') NOT NULL DEFAULT 'preview',source_users VARCHAR(255),source_payments VARCHAR(255),source_rosters VARCHAR(255),stats_json LONGTEXT,warnings_json LONGTEXT,applied_at DATETIME,note TEXT) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE kis_import_rows(id INT AUTO_INCREMENT PRIMARY KEY,run_id INT NOT NULL,person_key VARCHAR(180) NOT NULL,jmeno VARCHAR(100) NOT NULL,prijmeni VARCHAR(160) NOT NULL,narozeni DATE,email VARCHAR(255),uciid VARCHAR(80),oddil VARCHAR(160),kis_aktivni TINYINT NOT NULL DEFAULT 0,kis_platebne_aktivni TINYINT NOT NULL DEFAULT 0,kis_neuhrazeno DECIMAL(10,2) NOT NULL DEFAULT 0,kis_posledni_uhrada DATE,kis_soupisky TEXT,raw_json LONGTEXT) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE kis_import_matches(id INT AUTO_INCREMENT PRIMARY KEY,run_id INT NOT NULL,row_id INT NOT NULL,sportovec_id INT,match_status ENUM('new','matched','ambiguous','conflict','ignored') NOT NULL,confidence TINYINT UNSIGNED NOT NULL DEFAULT 0,reason VARCHAR(255),candidate_json LONGTEXT,resolved_by INT,resolved_at DATETIME,resolved_action ENUM('create','update','link','ignore','manual')) ENGINE=InnoDB");

    $catalog = EvidenceMigrationCatalog::load(dirname(__DIR__, 2) . '/migrations');
    $result = (new EvidenceMigrationRunner($pdo, $catalog))->apply();
    if (!$result['current']) throw new RuntimeException('MariaDB migration catalog is not current before checkout smoke.');

    $pdo->exec("INSERT INTO treneri(id,jmeno,email,heslo,role,aktivni) VALUES(7,'Smoke Admin','smoke-admin@example.test','not-a-login-secret','admin',1)");
    $pdo->exec("INSERT INTO verejni_uzivatele(id,jmeno,prijmeni,email,aktivni,email_overeno,registrovan) VALUES(10,'Race','Tester','race@example.test',1,1,CURRENT_TIMESTAMP)");
    $pdo->exec("INSERT INTO shop_catalog_import_runs(id,source_sha256,source_filename,contract_version,status,product_count,variant_count,warning_count,manual_review_count) VALUES(1,'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa','smoke.csv','smoke-v1','promoted',1,1,0,0)");
    $pdo->exec("INSERT INTO shop_catalog_product_candidates(id,run_id,external_product_key,name,offer_type,classification_confidence,needs_manual_review,payload_json) VALUES(501,1,'race-product','Race item','goods','high',0,'{}')");
    $pdo->exec("INSERT INTO shop_catalog_variant_candidates(id,run_id,product_candidate_id,sku,price_mode,amount_minor,currency,stock_quantity_decimal,payload_json) VALUES(601,1,501,'RACE-ONE','fixed',12500,'CZK','1.000000','{}')");
    $pdo->exec("INSERT INTO shop_products(id,source_candidate_id,source_run_id,external_product_key,name,offer_type,catalog_status) VALUES(501,501,1,'race-product','Race item','goods','active')");
    $pdo->exec("INSERT INTO shop_variants(id,product_id,source_candidate_id,sku,attributes_json,price_mode,amount_minor,currency,includes_vat,vat_rate_basis_points,stock_quantity_decimal,visible,catalog_status) VALUES(601,501,601,'RACE-ONE','{}','fixed',12500,'CZK',1,2100,'1.000000',1,'active')");
    $pdo->exec("INSERT INTO shop_product_publications(product_id,status,public_name,public_summary,decision_note) VALUES(501,'active','Race item','Concurrency smoke','Approved smoke fixture')");
    $pdo->exec('CREATE TABLE smoke_worker_ready(worker_id VARCHAR(20) PRIMARY KEY) ENGINE=InnoDB');

    shopCartSetQuantity($pdo, 10, 601, 1);
    $fingerprint = (string)shopCartDetail($pdo, 10)['fingerprint'];
    $key = bin2hex(random_bytes(16));

    $pdo->beginTransaction();
    $pdo->query("SELECT id FROM shop_carts WHERE account_id=10 AND status='active' FOR UPDATE")->fetchColumn();
    $workers = [
        shopCheckoutSmokeWorker($database, $key, $fingerprint, 'worker_1'),
        shopCheckoutSmokeWorker($database, $key, $fingerprint, 'worker_2'),
    ];
    $observer = shopCheckoutSmokePdo($database);

    $deadline = microtime(true) + 8.0;
    do {
        usleep(100000);
        $ready = (int)$observer->query('SELECT COUNT(*) FROM smoke_worker_ready')->fetchColumn();
        $active = $observer->prepare("SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE DB=? AND ID<>CONNECTION_ID() AND COMMAND<>'Sleep'");
        $active->execute([$database]);
        $activeWorkers = (int)$active->fetchColumn();
    } while (($ready < 2 || $activeWorkers < 2) && microtime(true) < $deadline);
    if ($ready !== 2 || $activeWorkers < 2) throw new RuntimeException('Checkout workers did not reach the locked race window.');
    $pdo->commit();

    $outcomes = array_map('shopCheckoutSmokeFinishWorker', $workers);
    usort($outcomes, static fn(array $a, array $b): int => (int)$a['replayed'] <=> (int)$b['replayed']);
    if ($outcomes[0]['id'] !== $outcomes[1]['id'] || array_column($outcomes, 'replayed') !== [false, true]) {
        throw new RuntimeException('Concurrent duplicate checkout was not returned as one order plus one replay.');
    }
    if ((int)$pdo->query('SELECT COUNT(*) FROM shop_orders')->fetchColumn() !== 1
        || (int)$pdo->query('SELECT COUNT(*) FROM payments')->fetchColumn() !== 1
        || (int)$pdo->query('SELECT COUNT(*) FROM shop_inventory_movements')->fetchColumn() !== 1
        || (float)$pdo->query('SELECT stock_quantity_decimal FROM shop_variants WHERE id=601')->fetchColumn() !== 0.0
    ) {
        throw new RuntimeException('Concurrent duplicate checkout changed persisted order or stock counts.');
    }

    $paymentId = (int)$pdo->query('SELECT id FROM payments LIMIT 1')->fetchColumn();
    $orderId = (int)$pdo->query('SELECT id FROM shop_orders LIMIT 1')->fetchColumn();
    $pdo->exec('CREATE TABLE smoke_payment_worker_ready(worker_id VARCHAR(30) PRIMARY KEY) ENGINE=InnoDB');
    $pdo->beginTransaction();
    $pdo->prepare('SELECT id FROM payments WHERE id=? FOR UPDATE')->execute([$paymentId]);
    $paymentWorkers = [
        shopCheckoutSmokePaymentWorker($database, $paymentId, 'payment_worker_1'),
        shopCheckoutSmokePaymentWorker($database, $paymentId, 'payment_worker_2'),
    ];
    $deadline = microtime(true) + 8.0;
    do {
        usleep(100000);
        $ready = (int)$observer->query('SELECT COUNT(*) FROM smoke_payment_worker_ready')->fetchColumn();
        $active = $observer->prepare("SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE DB=? AND ID<>CONNECTION_ID() AND COMMAND<>'Sleep'");
        $active->execute([$database]);
        $activeWorkers = (int)$active->fetchColumn();
    } while (($ready < 2 || $activeWorkers < 2) && microtime(true) < $deadline);
    if ($ready !== 2 || $activeWorkers < 2) throw new RuntimeException('Payment workers did not reach the locked race window.');
    $pdo->commit();

    $paymentOutcomes = array_map('shopCheckoutSmokeFinishWorker', $paymentWorkers);
    usort($paymentOutcomes, static fn(array $a, array $b): int => (int)$b['changed'] <=> (int)$a['changed']);
    if (array_column($paymentOutcomes, 'changed') !== [true, false]
        || array_unique(array_column($paymentOutcomes, 'order_id')) !== [$orderId]
        || (int)$pdo->query("SELECT COUNT(*) FROM club_event_notifications WHERE order_id={$orderId} AND notification_type='shop_payment_received'")->fetchColumn() !== 1
        || $pdo->query('SELECT status FROM payments WHERE id=' . $paymentId)->fetchColumn() !== 'paid'
        || $pdo->query('SELECT payment_status FROM shop_orders WHERE id=' . $orderId)->fetchColumn() !== 'paid'
    ) {
        throw new RuntimeException('Concurrent payment confirmation did not persist one paid order and one notification.');
    }
    echo "MariaDB concurrent checkout and payment-notification idempotency smoke OK\n";
} finally {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    $server->exec('DROP DATABASE IF EXISTS ' . $quotedDatabase);
}
