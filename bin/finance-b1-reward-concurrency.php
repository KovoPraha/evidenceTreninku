<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/tests/Support/finance_b1_reward_concurrency_worker.php';

$_SESSION = ['trener_id' => 1, 'role' => 'admin', 'opravneni' => ['kreditni_obdobi' => 'hlavni']];
$pdo = new PDO(B1_REWARD_RACE_DSN, 'b1_app', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'evidence_b1_rewards') throw new RuntimeException('Wrong test database.');
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$owned = ['finance_b1_reward_test_marker', 'sportovci', 'sportovec_obdobi', 'treninky', 'trenink_sportovec', 'ucto_audit_log'];
if ($tables && (!in_array('finance_b1_reward_test_marker', $tables, true) || array_diff($tables, $owned))) {
    throw new RuntimeException('Database is not the dedicated synthetic reward test fixture.');
}
$pdo->exec('CREATE TABLE IF NOT EXISTS finance_b1_reward_test_marker (id INT PRIMARY KEY) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE IF NOT EXISTS sportovci (id INT PRIMARY KEY) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE IF NOT EXISTS sportovec_obdobi (id INT AUTO_INCREMENT PRIMARY KEY, sportovec_id INT NOT NULL, datum_od DATE NOT NULL, datum_do DATE NULL, sazba_kc DECIMAL(10,2), pocet_treninku INT NULL, castka_celkem DECIMAL(10,2) NULL, vyplaceno INT NOT NULL DEFAULT 0, INDEX (sportovec_id)) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE IF NOT EXISTS treninky (id INT PRIMARY KEY, datum DATE) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE IF NOT EXISTS trenink_sportovec (trenink_id INT, sportovec_id INT) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE IF NOT EXISTS ucto_audit_log (id INT AUTO_INCREMENT PRIMARY KEY, uzivatel_id INT, akce VARCHAR(100), tabulka VARCHAR(100), zaznam_id INT, detail TEXT, ip_adresa VARCHAR(100), user_agent TEXT) ENGINE=InnoDB');

$checks = 0;
function b1RaceAssert(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
function b1WaitFile(string $path): void
{
    $deadline = microtime(true) + 10;
    while (!is_file($path)) {
        if (microtime(true) > $deadline) throw new RuntimeException('Worker readiness timeout: ' . basename($path));
        usleep(10000);
    }
}
function b1Worker(string $operation, int $id, string $barrier, bool $hold): array
{
    $prefix = $barrier . ($hold ? '.first' : '.second');
    $process = proc_open([PHP_BINARY, dirname(__DIR__) . '/tests/Support/finance_b1_reward_concurrency_worker.php',
        $operation, (string)$id, $barrier, $hold ? 'hold' : ''],
        [0 => ['pipe', 'r'], 1 => ['file', $prefix . '.out', 'w'], 2 => ['file', $prefix . '.err', 'w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Cannot start test worker.');
    fclose($pipes[0]);
    return [$process, $prefix];
}
function b1Result(array $worker): array
{
    [$process, $prefix] = $worker;
    $code = proc_close($process);
    if ($code !== 0) throw new RuntimeException('Worker failed: ' . file_get_contents($prefix . '.err'));
    return json_decode((string)file_get_contents($prefix . '.out'), true, 512, JSON_THROW_ON_ERROR);
}

$directory = sys_get_temp_dir() . '/finance-b1-reward-race-' . bin2hex(random_bytes(6));
if (!mkdir($directory, 0700)) throw new RuntimeException('Cannot create test barriers.');
$results = [];
try {
    foreach (['save', 'close', 'bulk'] as $contender) {
        foreach (['ucto_audit_log', 'sportovec_obdobi', 'trenink_sportovec', 'treninky', 'sportovci'] as $table) $pdo->exec('DELETE FROM ' . $table);
        $pdo->exec('INSERT INTO sportovci VALUES (1)');
        $pdo->exec("INSERT INTO treninky VALUES (1, '2026-10-01'), (2, '2026-10-10')");
        $pdo->exec('INSERT INTO trenink_sportovec VALUES (1,1), (2,1)');
        $id = athleteRewardSave($pdo, 1, 0, 10, '2026-10-01', null);
        $barrier = $directory . '/' . $contender;
        $first = b1Worker('close', $id, $barrier, true);
        b1WaitFile($barrier . '.locked'); // First real service mutation has reached commit with row locks held.
        $second = b1Worker($contender, $id, $barrier, false);
        b1WaitFile($barrier . '.second-started');
        usleep(250000);
        b1RaceAssert(proc_get_status($second[0])['running'], $contender . ' did not wait for the locked period.');
        file_put_contents($barrier . '.release', 'commit now');
        $winner = b1Result($first);
        $loser = b1Result($second);
        b1RaceAssert($winner['status'] === 'ok', 'First close failed.');
        b1RaceAssert($loser['status'] === ($contender === 'bulk' ? 'ok' : 'blocked'), 'Wrong contender outcome.');
        $row = $pdo->query('SELECT * FROM sportovec_obdobi WHERE id = ' . $id)->fetch(PDO::FETCH_ASSOC);
        b1RaceAssert($row['datum_do'] === '2026-10-20' && (int)$row['pocet_treninku'] === 2 && (float)$row['castka_celkem'] === 20.0 && (float)$row['sazba_kc'] === 10.0,
            'Protected snapshot changed under concurrency.');
        b1RaceAssert((int)$pdo->query("SELECT COUNT(*) FROM ucto_audit_log WHERE akce = 'reward_period_close'")->fetchColumn() === 1, 'Close audit was duplicated.');
        if ($contender === 'bulk') {
            b1RaceAssert($loser['result'] === ['updated' => 0, 'inserted' => 1], 'Bulk should create a new open period after closure.');
            b1RaceAssert((int)$pdo->query('SELECT COUNT(*) FROM sportovec_obdobi WHERE datum_do IS NULL AND sazba_kc = 77')->fetchColumn() === 1, 'Missing new open period.');
        }
        $results[] = ['race' => 'close_vs_' . $contender, 'status' => 'PASS', 'contender' => $loser['status']];
    }
    echo json_encode(['status' => 'PASS', 'checks' => $checks, 'database' => 'evidence_b1_rewards', 'races' => $results], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
} finally {
    // Only files created by this run in a uniquely named temp directory.
    foreach (glob($directory . '/*') ?: [] as $file) if (is_file($file)) unlink($file);
    rmdir($directory);
}
