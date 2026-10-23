<?php
declare(strict_types=1);

// Dedicated B1 sandbox only: never imports app config or uses the default DB port.
require_once dirname(__DIR__, 2) . '/includes/financial_event_guard.php';

function financialRaceConnection(): PDO
{
    $base = getenv('LOCALAPPDATA');
    $manifest = $base . '/EvidencePavelB1/finance-b1-20261022/environment.json';
    if (PHP_SAPI !== 'cli' || !$base || !is_file($manifest)) {
        throw new RuntimeException('Dedicated B1 sandbox descriptor is required.');
    }
    $pdo = new PDO('mysql:host=127.0.0.1;port=33426;dbname=evidence_b1;charset=utf8mb4', 'b1_app', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    if ((int)$pdo->query('SELECT @@port')->fetchColumn() !== 33426
        || $pdo->query('SELECT DATABASE()')->fetchColumn() !== 'evidence_b1') {
        throw new RuntimeException('Refusing a non-sandbox database.');
    }
    $pdo->exec('SET SESSION innodb_lock_wait_timeout=5');
    return $pdo;
}

function financialRaceAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

if (($argv[1] ?? '') === '--worker') {
    $pdo = financialRaceConnection();
    $operation = $argv[2];
    $eventId = (int)$argv[3];
    $receiptId = (int)$argv[4];
    $pdo->beginTransaction();
    try {
        if ($operation === 'receipt') {
            $receipt = financialReceiptLock($pdo, $receiptId);
            echo "READY\n";
            flush();
            financialReceiptAssertMutable($pdo, $receipt, $eventId);
            // Represents the first possible SQL/file side effect after the guard.
            $pdo->prepare('UPDATE ucto_uctenky SET castka=999 WHERE id=?')->execute([$receiptId]);
        } else {
            echo "READY\n";
            flush();
            financialEventAssertOpen(financialEventLock($pdo, $eventId));
            $pdo->prepare("UPDATE ucto_udalosti SET stav='uzavrena' WHERE id=?")->execute([$eventId]);
        }
        $pdo->commit();
        echo "COMMITTED\n";
    } catch (FinancialEventConflict) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo "BLOCKED\n";
    }
    exit;
}

function financialRaceWorker(string $operation, int $eventId, int $receiptId): array
{
    // Windows anonymous pipes do not reliably support nonblocking stream reads.
    $outputPath = tempnam(sys_get_temp_dir(), 'b1-race-out-');
    $errorPath = tempnam(sys_get_temp_dir(), 'b1-race-err-');
    $process = proc_open([PHP_BINARY, __FILE__, '--worker', $operation, (string)$eventId, (string)$receiptId],
        [0 => ['pipe', 'r'], 1 => ['file', $outputPath, 'w'], 2 => ['file', $errorPath, 'w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Cannot start race worker.');
    fclose($pipes[0]);
    return [$process, [1 => $outputPath, 2 => $errorPath]];
}

function financialRaceWaitReady($process, array $pipes): void
{
    $output = '';
    $deadline = microtime(true) + 5;
    do {
        $output = (string)file_get_contents($pipes[1]);
        if (str_contains($output, "READY\n")) return;
        if (!proc_get_status($process)['running']) break;
        usleep(20000);
    } while (microtime(true) < $deadline);
    throw new RuntimeException('Race worker did not reach the lock: ' . file_get_contents($pipes[2]));
}

function financialRaceFinish($process, array $pipes): string
{
    $output = '';
    $errors = '';
    $deadline = microtime(true) + 7;
    do {
        if (!proc_get_status($process)['running']) break;
        usleep(20000);
    } while (microtime(true) < $deadline);
    $running = proc_get_status($process)['running'];
    if ($running) proc_terminate($process);
    proc_close($process);
    $output = (string)file_get_contents($pipes[1]);
    $errors = (string)file_get_contents($pipes[2]);
    unlink($pipes[1]);
    unlink($pipes[2]);
    financialRaceAssert(!$running && $errors === '', 'Race worker failed: ' . $errors);
    return trim(str_replace("READY\n", '', $output));
}

$pdo = financialRaceConnection();
$eventId = 0;
$receiptId = 0;
$process = null;
$pipes = [];
try {
    $pdo->exec("INSERT INTO ucto_udalosti(nazev,datum_od,datum_do,typ,vytvoril_id,stav) VALUES('LOCALHOST NEPLATIT B1 race', '2026-10-22', '2026-10-22', 'jine', 0, 'otevrena')");
    $eventId = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO ucto_uctenky(udalost_id,castka,datum,kategorie,obrazek_path) VALUES(?,100,'2026-10-22','ostatni','LOCALHOST-race-proof')")->execute([$eventId]);
    $receiptId = (int)$pdo->lastInsertId();

    // Close wins: a previously started receipt request must see the committed close.
    $pdo->beginTransaction();
    financialEventAssertOpen(financialEventLock($pdo, $eventId));
    $pdo->prepare("UPDATE ucto_udalosti SET stav='uzavrena' WHERE id=?")->execute([$eventId]);
    [$process, $pipes] = financialRaceWorker('receipt', $eventId, $receiptId);
    financialRaceWaitReady($process, $pipes);
    usleep(150000);
    financialRaceAssert(proc_get_status($process)['running'], 'Receipt worker must wait for close lock.');
    $pdo->commit();
    financialRaceAssert(financialRaceFinish($process, $pipes) === 'BLOCKED', 'Receipt must reject committed close.');
    $stmt = $pdo->prepare('SELECT castka,obrazek_path FROM ucto_uctenky WHERE id=?');
    $stmt->execute([$receiptId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    financialRaceAssert((float)$row['castka'] === 100.0 && $row['obrazek_path'] === 'LOCALHOST-race-proof', 'Rejected write must preserve amount and attachment.');

    // Fixture-only reset; the application deliberately has no reopen action.
    $pdo->prepare("UPDATE ucto_udalosti SET stav='otevrena' WHERE id=?")->execute([$eventId]);
    $pdo->beginTransaction();
    financialReceiptAssertMutable($pdo, financialReceiptLock($pdo, $receiptId), $eventId);
    $pdo->prepare('UPDATE ucto_uctenky SET castka=125 WHERE id=?')->execute([$receiptId]);
    [$process, $pipes] = financialRaceWorker('close', $eventId, $receiptId);
    financialRaceWaitReady($process, $pipes);
    usleep(150000);
    financialRaceAssert(proc_get_status($process)['running'], 'Close worker must wait for receipt mutation.');
    $pdo->commit();
    financialRaceAssert(financialRaceFinish($process, $pipes) === 'COMMITTED', 'Close should succeed after open receipt commits.');
    $stmt = $pdo->prepare('SELECT e.stav,r.castka FROM ucto_udalosti e JOIN ucto_uctenky r ON r.udalost_id=e.id WHERE r.id=?');
    $stmt->execute([$receiptId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    financialRaceAssert($row['stav'] === 'uzavrena' && (float)$row['castka'] === 125.0, 'Serialized close must retain committed receipt.');
    echo json_encode(['status' => 'PASS', 'scenarios' => ['close_before_receipt_blocks', 'receipt_before_close_serializes'], 'production_touched' => false], JSON_PRETTY_PRINT) . "\n";
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if (is_resource($process)) {
        if (proc_get_status($process)['running']) proc_terminate($process);
        proc_close($process);
    }
    foreach ($pipes as $path) {
        if (is_file($path)) unlink($path);
    }
    if ($receiptId > 0) $pdo->prepare('DELETE FROM ucto_uctenky WHERE id=?')->execute([$receiptId]);
    if ($eventId > 0) $pdo->prepare('DELETE FROM ucto_udalosti WHERE id=?')->execute([$eventId]);
}
