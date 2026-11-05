<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__, 2) . '/includes/bar/transfer_package.php';
require_once dirname(__DIR__, 2) . '/includes/bar/invoices.php';
require_once dirname(__DIR__, 2) . '/includes/bar/accounting.php';
try {
    $input = json_decode((string)stream_get_contents(STDIN), true, 32, JSON_THROW_ON_ERROR);
    $pdo = barIsolatedTransferConnection('BAR_TARGET');
    $_SESSION = ['trener_id' => $input['actor_id'], 'trener_session_version' => 1, 'staff_active_position' => $input['position']];
    echo json_encode(['ready' => true, 'connection_id' => (int)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn()], JSON_THROW_ON_ERROR) . "\n";
    flush();
    $pdo->exec('SET SESSION innodb_lock_wait_timeout=15');
    if (isset($input['storage'])) {
        putenv('APP_PRIVATE_STORAGE_ROOT=' . $input['storage']);
    }
    $result = match ($input['action']) {
        'receive' => barReceiveInvoice($pdo, $input['invoice_id'], $input['version'], '2020-01-01', true, 'LOCALHOST concurrent receipt', $input['token']),
        'export' => barCreateAccountingExport($pdo, $input['selection'], 'LOCALHOST concurrent export', $input['token']),
        default => throw new RuntimeException('Unknown document race action.'),
    };
    echo json_encode(['ok' => true, 'result' => $result], JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $error) {
    echo json_encode(['ok' => false, 'class' => get_class($error), 'message' => $error instanceof DomainException ? $error->getMessage() : 'Isolated worker failed.'], JSON_THROW_ON_ERROR) . "\n";
}
