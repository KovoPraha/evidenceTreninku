<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__, 2) . '/includes/bar/transfer_package.php';
require_once dirname(__DIR__, 2) . '/includes/bar/recovery.php';
try {
    $input = json_decode((string)stream_get_contents(STDIN), true, 32, JSON_THROW_ON_ERROR);
    $pdo = barIsolatedTransferConnection('BAR_TARGET');
    $_SESSION = ['trener_id' => $input['actor_id'], 'trener_session_version' => 1, 'staff_active_position' => $input['position']];
    echo json_encode(['ready' => true, 'connection_id' => (int)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn()], JSON_THROW_ON_ERROR) . "\n";
    flush();
    $pdo->exec('SET SESSION innodb_lock_wait_timeout=15');
    if ($input['action'] === 'recover') {
        $result = barRecoverCashbox(
            $pdo,
            $input['cashbox_id'],
            $input['version'],
            'close',
            0,
            $input['counted_minor'],
            '',
            true,
            'LOCALHOST native recovery',
            $input['token']
        );
    } else {
        $result = barSubmitSale($pdo, ...$input['sale_request']);
        if ($input['action'] === 'drop_after_commit') {
            // Simulate a worker disappearing after the transaction committed, before any result reaches its caller.
            exit;
        }
    }
    echo json_encode(['ok' => true, 'result' => $result], JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $error) {
    echo json_encode(['ok' => false, 'class' => get_class($error), 'code' => (string)$error->getCode(),
        'message' => $error instanceof DomainException ? $error->getMessage() : 'Isolated database operation failed.'], JSON_THROW_ON_ERROR) . "\n";
}
