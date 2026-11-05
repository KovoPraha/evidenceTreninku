<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__, 2) . '/includes/bar/transfer_package.php';
require_once dirname(__DIR__, 2) . '/includes/bar/reversals.php';
try {
    $input = json_decode((string)stream_get_contents(STDIN), true, 32, JSON_THROW_ON_ERROR);
    $pdo = barIsolatedTransferConnection('BAR_TARGET');
    $_SESSION = ['trener_id' => $input['actor_id'], 'trener_session_version' => 1, 'staff_active_position' => $input['position']];
    echo json_encode(['ready' => true, 'connection_id' => (int)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn()], JSON_THROW_ON_ERROR) . "\n";
    flush();
    $pdo->exec('SET SESSION innodb_lock_wait_timeout=15');
    $result = match ($input['action']) {
        'sale' => barSubmitSale($pdo, $input['cashbox_id'], $input['cart'], 0, 0, $input['fingerprint'], 'cash', $input['token']),
        'consumption' => barSubmitConsumption($pdo, $input['cart'], 'stall', $input['fingerprint'], 'LOCALHOST race consumption', $input['token']),
        'close' => barCloseCashbox($pdo, $input['cashbox_id'], $input['version'], $input['counted_minor'], 'LOCALHOST race closing', $input['token']),
        'void' => barVoidSale($pdo, $input['sale_id'], $input['cashbox_id'], '', 'LOCALHOST race reversal', $input['token']),
        default => throw new RuntimeException('Unknown isolated race action.'),
    };
    echo json_encode(['ok' => true, 'result' => $result], JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $error) {
    echo json_encode(['ok' => false, 'class' => get_class($error), 'message' => $error instanceof DomainException ? $error->getMessage() : 'Isolated worker failed.'], JSON_THROW_ON_ERROR) . "\n";
}
