<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/BarNativeHarness.php';
$harness = new BarNativeHarness(__DIR__ . '/BarRecoveryRaceWorker.php');
$login = static function (int $id, string $position): void {
    $_SESSION = ['trener_id' => $id, 'trener_session_version' => 1, 'staff_active_position' => $position];
};
$token = static fn (): string => bin2hex(random_bytes(32));
try {
    $pdo = $harness->pdo;
    $pdo->exec("SET SESSION time_zone='+09:00'");
    $login(2, 'bar_operator');
    $box = barOpenCashbox($pdo, 100000, 'LOCALHOST UTC drawer', $token())['cashbox_id'];
    $cart = [['product_id' => 1, 'quantity' => 1]];
    $quote = barQuote($pdo, $cart);
    $request = [$box, $cart, 0, 0, $quote['fingerprint'], 'cash', $token()];
    $worker = $harness->start(['actor_id' => 2, 'position' => 'bar_operator', 'action' => 'drop_after_commit', 'sale_request' => $request]);
    if (!($harness->finish($worker)['response_lost'] ?? false)) {
        throw new RuntimeException('Post-commit response was not lost.');
    }
    $replay = barSubmitSale($pdo, ...$request);
    if (!$replay['repeated'] || (int)$pdo->query('SELECT COUNT(*) FROM kis_bar_sales')->fetchColumn() !== 1
        || barCashBalance($pdo, $box) !== 103500 || (int)$pdo->query('SELECT stock_stall FROM kis_bar_products WHERE id=1')->fetchColumn() !== 19) {
        throw new RuntimeException('Lost response replay duplicated cash, sale or stock.');
    }
    $request[6] = $token();
    $pdo->exec('CREATE TRIGGER bar_test_drop BEFORE INSERT ON kis_bar_requests FOR EACH ROW BEGIN IF NEW.token='
        . $pdo->quote($request[6]) . ' THEN DO SLEEP(12); END IF; END');
    $worker = $harness->start(['actor_id' => 2, 'position' => 'bar_operator', 'action' => 'sale', 'sale_request' => $request]);
    // MariaDB exposes the currently executing trigger statement in PROCESSLIST.
    $harness->waitForSql($worker, 'SLEEP(12)');
    $harness->killConnection($worker);
    $failed = $harness->finish($worker);
    $pdo->exec('DROP TRIGGER bar_test_drop');
    if (($failed['ok'] ?? true) !== false || $failed['class'] !== PDOException::class
        || (int)$pdo->query('SELECT COUNT(*) FROM kis_bar_sales')->fetchColumn() !== 1 || barCashBalance($pdo, $box) !== 103500) {
        throw new RuntimeException('Connection loss before commit left partial financial data.');
    }
    $retry = barSubmitSale($pdo, ...$request);
    if ($retry['repeated'] || (int)$pdo->query('SELECT COUNT(*) FROM kis_bar_sales')->fetchColumn() !== 2
        || barCashBalance($pdo, $box) !== 107000 || (int)$pdo->query('SELECT stock_stall FROM kis_bar_products WHERE id=1')->fetchColumn() !== 18) {
        throw new RuntimeException('Pre-commit rollback did not permit exactly one later retry.');
    }
    if ($pdo->query('SELECT @@session.time_zone')->fetchColumn() !== '+09:00'
        || (int)$pdo->query('SELECT ABS(TIMESTAMPDIFF(SECOND,opened_at,UTC_TIMESTAMP())) FROM kis_bar_cashboxes WHERE id=' . $box)->fetchColumn() > 10) {
        throw new RuntimeException('BAR changed the shared timezone or stored a non-UTC financial timestamp.');
    }
    $pdo->exec('UPDATE treneri SET aktivni=0 WHERE id=2');
    $login(3, 'bar_admin');
    $version = (int)$pdo->query('SELECT lock_version FROM kis_bar_cashboxes WHERE id=' . $box)->fetchColumn();
    $input = ['action' => 'recover', 'actor_id' => 3, 'position' => 'bar_admin', 'cashbox_id' => $box,
        'version' => $version, 'counted_minor' => 107000, 'token' => $token()];
    $harness->barrier->beginTransaction();
    barLockState($harness->barrier);
    $worker = $harness->start($input);
    $harness->waitForSql($worker, 'kis_bar_state');
    $harness->writer->exec('UPDATE treneri SET aktivni=1 WHERE id=2');
    $harness->barrier->commit();
    if ($harness->finish($worker)['ok']) {
        throw new RuntimeException('Administrator recovered a reactivated owner after a real SQL wait.');
    }
    $pdo->exec('UPDATE treneri SET aktivni=0 WHERE id=2');
    $input['token'] = $token();
    $harness->barrier->beginTransaction();
    barLockState($harness->barrier);
    $worker = $harness->start($input);
    $harness->waitForSql($worker, 'kis_bar_state');
    $harness->writer->exec("DELETE FROM staff_user_positions WHERE trainer_id=3 AND position_code='bar_admin'");
    $harness->barrier->commit();
    if ($harness->finish($worker)['ok']) {
        throw new RuntimeException('Revoked administrator recovered a drawer after a real SQL wait.');
    }
    $pdo->exec("INSERT INTO staff_user_positions(trainer_id,position_code,is_default) VALUES(3,'bar_admin',1)");
    $harness->barrier->beginTransaction();
    barLockState($harness->barrier);
    $input['token'] = $token();
    $a = $harness->start($input);
    $input['token'] = $token();
    $b = $harness->start($input);
    $harness->waitForSql($a, 'kis_bar_state');
    $harness->waitForSql($b, 'kis_bar_state');
    $harness->barrier->commit();
    $race = [$harness->finish($a), $harness->finish($b)];
    $closed = barRecoveryCashbox($pdo, $box);
    if (count(array_filter($race, static fn (array $result): bool => $result['ok'])) !== 1
        || (int)$closed['owner_id'] !== 2 || (int)$closed['closed_by'] !== 3 || $closed['closure_kind'] !== 'recovery'
        || barCashBalance($pdo, $box) !== 107000) {
        throw new RuntimeException('Concurrent recovery changed ownership, cash or closed twice.');
    }
    echo json_encode(['ok' => true, 'mariadb' => $harness->server->query('SELECT VERSION()')->fetchColumn(),
        'checks' => ['lost_post_commit_response', 'killed_before_commit_rollback', 'single_retry_after_reconnect', 'utc_with_shared_offset_unchanged',
            'owner_reactivated_during_real_wait', 'administrator_revoked_during_real_wait', 'concurrent_recovery_single_winner']], JSON_THROW_ON_ERROR) . "\n";
} finally {
    $harness->close();
}
