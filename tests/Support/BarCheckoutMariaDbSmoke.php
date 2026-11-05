<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__, 2) . '/includes/bar/reversals.php';
require_once dirname(__DIR__, 2) . '/includes/bar/stock.php';
$port = (int)(getenv('BAR_SMOKE_DB_PORT') ?: 3306);
if ($port < 1024 || $port > 65535) {
    throw new RuntimeException('Invalid loopback test port.');
}
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false];
$server = new PDO('mysql:host=127.0.0.1;port=' . $port . ';charset=utf8mb4', 'root', '', $options);
$database = 'evidence_bar_test_checkout_' . bin2hex(random_bytes(6));
$server->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$dsn = 'mysql:host=127.0.0.1;port=' . $port . ';dbname=' . $database . ';charset=utf8mb4';
$oldDsn = getenv('BAR_TARGET_DSN');
$oldUser = getenv('BAR_TARGET_USER');
$oldPassword = getenv('BAR_TARGET_PASSWORD');
putenv('BAR_TARGET_DSN=' . $dsn);
putenv('BAR_TARGET_USER=root');
putenv('BAR_TARGET_PASSWORD=');
$workers = [];
$connect = static fn (): PDO => new PDO($dsn, 'root', '', $options);
$login = static function (int $id): void {
    $_SESSION = ['trener_id' => $id, 'trener_session_version' => 1, 'staff_active_position' => $id === 3 ? 'bar_admin' : 'bar_operator'];
};
$start = static function (array $input) use (&$workers): int {
    $process = proc_open([PHP_BINARY, __DIR__ . '/BarCheckoutRaceWorker.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start isolated checkout race worker.');
    }
    fwrite($pipes[0], json_encode($input, JSON_THROW_ON_ERROR));
    fclose($pipes[0]);
    stream_set_timeout($pipes[1], 10);
    $ready = json_decode((string)fgets($pipes[1]), true, 8, JSON_THROW_ON_ERROR);
    if (!($ready['ready'] ?? false)) {
        throw new RuntimeException('Checkout worker did not become ready.');
    }
    $workers[] = ['process' => $process, 'out' => $pipes[1], 'err' => $pipes[2], 'connection_id' => $ready['connection_id']];
    return array_key_last($workers);
};
$waitBlocked = static function (array $indexes) use (&$workers, $server): void {
    $ids = array_map(static fn (int $index): int => $workers[$index]['connection_id'], $indexes);
    $deadline = microtime(true) + 8;
    do {
        $rows = $server->query('SELECT ID,INFO FROM information_schema.PROCESSLIST WHERE ID IN (' . implode(',', $ids) . ')')->fetchAll(PDO::FETCH_ASSOC);
        $waiting = array_filter($rows, static fn (array $row): bool => str_contains((string)$row['INFO'], 'kis_bar_state'));
        if (count($waiting) === count($ids)) {
            return;
        }
        usleep(20000);
    } while (microtime(true) < $deadline);
    throw new RuntimeException('Workers did not reach the real database lock barrier.');
};
$finish = static function (int $index) use (&$workers): array {
    $worker = $workers[$index];
    $output = trim((string)stream_get_contents($worker['out']));
    $error = trim((string)stream_get_contents($worker['err']));
    fclose($worker['out']);
    fclose($worker['err']);
    $code = proc_close($worker['process']);
    unset($workers[$index]);
    if ($code !== 0 || $error !== '') {
        throw new RuntimeException('Isolated worker failed: ' . $error);
    }
    return json_decode($output, true, 16, JSON_THROW_ON_ERROR);
};
try {
    $pdo = $connect();
    $writer = $connect();
    $barrier = $connect();
    $pdo->exec('CREATE TABLE treneri(id INT AUTO_INCREMENT PRIMARY KEY,jmeno VARCHAR(100),role VARCHAR(20),aktivni INT,session_version INT,email VARCHAR(255),heslo VARCHAR(100)) ENGINE=InnoDB');
    $pdo->exec("INSERT INTO treneri(id,jmeno,role,aktivni,session_version) VALUES(2,'LOCALHOST A','trener',1,1),(3,'LOCALHOST B','trener',1,1)");
    $migrationIds = ['20260821150000_staff_workspaces', '20261105150000_bar_foundation', '20261105160000_bar_transfer',
        '20261105170000_bar_stock', '20261105180000_bar_catalog_management', '20261105190000_bar_checkout'];
    foreach ($migrationIds as $migrationId) {
        $migration = require dirname(__DIR__, 2) . '/migrations/' . $migrationId . '.php';
        $migration['up']($pdo);
        $migration['up']($pdo);
        if (!$migration['verify']($pdo)) {
            throw new RuntimeException('Repeated isolated migration failed.');
        }
    }
    $pdo->exec("INSERT INTO staff_user_positions(trainer_id,position_code,is_default) VALUES(2,'bar_operator',1),(3,'bar_admin',1)");
    $pdo->exec("INSERT INTO kis_bar_categories(id,name) VALUES(1,'LOCALHOST Drinks')");
    $pdo->exec("INSERT INTO kis_bar_products(id,category_id,name,description,price_minor,purchase_minor,stock_mode,stock_stall,package_name) "
        . "VALUES(1,1,'LOCALHOST Tea','',3500,1101,'sledovat',1,'')");
    $cart = [['product_id' => 1, 'quantity' => 1]];
    $inputs = [];
    foreach ([2, 3] as $actorId) {
        $login($actorId);
        $box = barOpenCashbox($pdo, 10000, 'LOCALHOST race drawer', bin2hex(random_bytes(32)))['cashbox_id'];
        $inputs[$actorId] = ['actor_id' => $actorId, 'position' => $actorId === 3 ? 'bar_admin' : 'bar_operator', 'action' => 'sale',
            'cashbox_id' => $box, 'cart' => $cart, 'fingerprint' => barQuote($pdo, $cart)['fingerprint'], 'token' => bin2hex(random_bytes(32))];
    }
    $barrier->beginTransaction();
    barLockState($barrier);
    $a = $start($inputs[2]);
    $b = $start($inputs[3]);
    $waitBlocked([$a, $b]);
    $barrier->commit();
    $results = [$finish($a), $finish($b)];
    if (count(array_filter($results, static fn (array $result): bool => $result['ok'])) !== 1
        || (int)$pdo->query('SELECT stock_stall FROM kis_bar_products WHERE id=1')->fetchColumn() !== 0
        || (int)$pdo->query('SELECT COUNT(*) FROM kis_bar_sales')->fetchColumn() !== 1) {
        throw new RuntimeException('Concurrent last-unit sale oversold stock or created multiple receipts.');
    }
    // A queued writer must re-read an authorization revoked while it is actually blocked in MySQL.
    $writer->exec('UPDATE kis_bar_products SET stock_stall=5 WHERE id=1');
    $barrier->beginTransaction();
    barLockState($barrier);
    $input = $inputs[2];
    $input['token'] = bin2hex(random_bytes(32));
    $worker = $start($input);
    $waitBlocked([$worker]);
    $writer->exec("DELETE FROM staff_user_positions WHERE trainer_id=2 AND position_code='bar_operator'");
    $barrier->commit();
    if ($finish($worker)['ok'] || (int)$pdo->query('SELECT stock_stall FROM kis_bar_products WHERE id=1')->fetchColumn() !== 5) {
        throw new RuntimeException('Queued sale used a revoked position.');
    }
    $writer->exec("INSERT INTO staff_user_positions(trainer_id,position_code,is_default) VALUES(2,'bar_operator',1)");
    $writer->exec("INSERT INTO kis_bar_permissions(trainer_id,permission_code,granted_by,reason) VALUES(2,'own_consumption',3,'LOCALHOST race grant')");
    $login(2);
    $input = $inputs[2];
    $input['action'] = 'consumption';
    $input['fingerprint'] = barQuote($pdo, $cart, 0, 0, true)['fingerprint'];
    $input['token'] = bin2hex(random_bytes(32));
    $barrier->beginTransaction();
    barLockState($barrier);
    $worker = $start($input);
    $waitBlocked([$worker]);
    $writer->exec('DELETE FROM kis_bar_permissions WHERE trainer_id=2');
    $barrier->commit();
    if ($finish($worker)['ok'] || (int)$pdo->query('SELECT stock_stall FROM kis_bar_products WHERE id=1')->fetchColumn() !== 5) {
        throw new RuntimeException('Queued consumption used a revoked grant.');
    }
    // A closing and a new sale compete for the same immutable closing boundary.
    $saleInput = $inputs[2];
    $saleInput['token'] = bin2hex(random_bytes(32));
    $boxId = $saleInput['cashbox_id'];
    $version = (int)$pdo->query('SELECT lock_version FROM kis_bar_cashboxes WHERE id=' . $boxId)->fetchColumn();
    $closeInput = $saleInput + ['version' => $version, 'counted_minor' => barCashBalance($pdo, $boxId)];
    $closeInput['action'] = 'close';
    $closeInput['token'] = bin2hex(random_bytes(32));
    $barrier->beginTransaction();
    barLockState($barrier);
    $a = $start($saleInput);
    $b = $start($closeInput);
    $waitBlocked([$a, $b]);
    $barrier->commit();
    $race = [$finish($a), $finish($b)];
    if (count(array_filter($race, static fn (array $result): bool => $result['ok'])) !== 1) {
        throw new RuntimeException('Closing boundary did not reject the competing stale operation.');
    }
    $box = barOwnedCashbox($pdo, $boxId, 2, false);
    if ($box['closed_at'] !== null && (int)$box['expected_minor'] !== barCashBalance($pdo, $boxId)) {
        throw new RuntimeException('Closed cashbox balance changed behind its snapshot.');
    }
    $login(2);
    if ($box['closed_at'] !== null) {
        $boxId = barOpenCashbox($pdo, 10000, 'LOCALHOST next race drawer', bin2hex(random_bytes(32)))['cashbox_id'];
    }
    // Two identical requests must both succeed with one receipt and one stock/cash effect.
    $input = $inputs[2];
    $input['cashbox_id'] = $boxId;
    $input['token'] = bin2hex(random_bytes(32));
    $beforeStock = (int)$pdo->query('SELECT stock_stall FROM kis_bar_products WHERE id=1')->fetchColumn();
    $beforeSales = (int)$pdo->query('SELECT COUNT(*) FROM kis_bar_sales')->fetchColumn();
    $barrier->beginTransaction();
    barLockState($barrier);
    $a = $start($input);
    $b = $start($input);
    $waitBlocked([$a, $b]);
    $barrier->commit();
    $first = $finish($a);
    $second = $finish($b);
    if (!$first['ok'] || !$second['ok'] || $first['result']['sale_id'] !== $second['result']['sale_id']
        || $first['result']['repeated'] === $second['result']['repeated']
        || (int)$pdo->query('SELECT stock_stall FROM kis_bar_products WHERE id=1')->fetchColumn() !== $beforeStock - 1
        || (int)$pdo->query('SELECT COUNT(*) FROM kis_bar_sales')->fetchColumn() !== $beforeSales + 1) {
        throw new RuntimeException('Concurrent idempotence created duplicate effects.');
    }
    // Reversing and closing compete for the same version, just like selling and closing.
    $voidInput = $input;
    $voidInput['action'] = 'void';
    $voidInput['sale_id'] = $first['result']['sale_id'];
    $voidInput['token'] = bin2hex(random_bytes(32));
    $closeInput = $input;
    $closeInput['action'] = 'close';
    $closeInput['version'] = (int)$pdo->query('SELECT lock_version FROM kis_bar_cashboxes WHERE id=' . $boxId)->fetchColumn();
    $closeInput['counted_minor'] = barCashBalance($pdo, $boxId);
    $closeInput['token'] = bin2hex(random_bytes(32));
    $barrier->beginTransaction();
    barLockState($barrier);
    $a = $start($voidInput);
    $b = $start($closeInput);
    $waitBlocked([$a, $b]);
    $barrier->commit();
    $race = [$finish($a), $finish($b)];
    if (count(array_filter($race, static fn (array $result): bool => $result['ok'])) !== 1) {
        throw new RuntimeException('Reversal crossed the immutable closing boundary.');
    }
    $box = barOwnedCashbox($pdo, $boxId, 2, false);
    if ($box['closed_at'] === null) {
        barCloseCashbox($pdo, $boxId, (int)$box['lock_version'], barCashBalance($pdo, $boxId), 'LOCALHOST final race closure', bin2hex(random_bytes(32)));
    }
    $box = barOwnedCashbox($pdo, $boxId, 2, false);
    if ((int)$box['expected_minor'] !== barCashBalance($pdo, $boxId)) {
        throw new RuntimeException('Final closing snapshot does not match its ledger.');
    }
    $login(3);
    $quote = barQuote($pdo, $cart);
    $pending = barSubmitSale($pdo, $inputs[3]['cashbox_id'], $cart, 0, 0, $quote['fingerprint'], 'card', bin2hex(random_bytes(32)));
    $beforeInventory = (int)$pdo->query('SELECT stock_stall FROM kis_bar_products WHERE id=1')->fetchColumn();
    $productVersion = (int)$pdo->query('SELECT lock_version FROM kis_bar_products WHERE id=1')->fetchColumn();
    try {
        barStockOperation($pdo, 1, $productVersion, 'inventory_stall', $beforeInventory + 1, bin2hex(random_bytes(32)), 'LOCALHOST pending physical count');
        throw new RuntimeException('Physical inventory counted reserved stock twice.');
    } catch (DomainException $expected) {
    }
    barVoidSale($pdo, $pending['sale_id'], null, '', 'LOCALHOST release inventory reservation', bin2hex(random_bytes(32)));
    if ((int)$pdo->query('SELECT stock_stall FROM kis_bar_products WHERE id=1')->fetchColumn() !== $beforeInventory + 1) {
        throw new RuntimeException('Pending cancellation and physical inventory changed the stock incorrectly.');
    }
    echo json_encode(['ok' => true, 'mariadb' => $server->query('SELECT VERSION()')->fetchColumn(),
        'checks' => ['repeat_migrations', 'last_unit_race', 'position_revoked_during_real_wait', 'consumption_revoked_during_real_wait',
            'close_vs_sale', 'concurrent_same_token', 'close_vs_void', 'pending_inventory_guard']], JSON_THROW_ON_ERROR) . "\n";
} finally {
    if (isset($barrier) && $barrier->inTransaction()) {
        $barrier->rollBack();
    }
    foreach ($workers as $worker) {
        proc_terminate($worker['process']);
        fclose($worker['out']);
        fclose($worker['err']);
        proc_close($worker['process']);
    }
    $pdo = $writer = $barrier = null;
    $server->exec("DROP DATABASE `$database`");
    foreach (['BAR_TARGET_DSN' => $oldDsn, 'BAR_TARGET_USER' => $oldUser, 'BAR_TARGET_PASSWORD' => $oldPassword] as $key => $value) {
        putenv($value === false ? $key : $key . '=' . $value);
    }
    $_SESSION = [];
}
