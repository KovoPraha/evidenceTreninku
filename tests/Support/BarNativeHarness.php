<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bar/recovery.php';

final class BarNativeHarness
{
    public PDO $server;
    public PDO $pdo;
    public PDO $writer;
    public PDO $barrier;
    private string $database;
    private string $dsn;
    private array $previousEnvironment = [];
    private array $workers = [];

    public function __construct(private string $workerFile)
    {
        $port = (int)(getenv('BAR_SMOKE_DB_PORT') ?: 3306);
        if ($port < 1024 || $port > 65535) {
            throw new RuntimeException('Invalid loopback test port.');
        }
        $this->server = new PDO('mysql:host=127.0.0.1;port=' . $port . ';charset=utf8mb4', 'root', '', $this->options());
        $this->database = 'evidence_bar_test_recovery_' . bin2hex(random_bytes(6));
        $this->server->exec('CREATE DATABASE `' . $this->database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->dsn = 'mysql:host=127.0.0.1;port=' . $port . ';dbname=' . $this->database . ';charset=utf8mb4';
        foreach (['BAR_TARGET_DSN' => $this->dsn, 'BAR_TARGET_USER' => 'root', 'BAR_TARGET_PASSWORD' => ''] as $key => $value) {
            $this->previousEnvironment[$key] = getenv($key);
            putenv($key . '=' . $value);
        }
        $this->pdo = $this->connect();
        $this->writer = $this->connect();
        $this->barrier = $this->connect();
        $this->pdo->exec('CREATE TABLE treneri(id INT AUTO_INCREMENT PRIMARY KEY,jmeno VARCHAR(100),role VARCHAR(20),'
            . 'aktivni INT,session_version INT,email VARCHAR(255),heslo VARCHAR(100)) ENGINE=InnoDB');
        $this->pdo->exec("INSERT INTO treneri(id,jmeno,role,aktivni,session_version) VALUES(2,'LOCALHOST Owner','trener',1,1),(3,'LOCALHOST Admin','trener',1,1)");
        foreach (['20260821150000_staff_workspaces', '20261105150000_bar_foundation', '20261105160000_bar_transfer',
            '20261105170000_bar_stock', '20261105180000_bar_catalog_management', '20261105190000_bar_checkout',
            '20261105200000_bar_documents', '20261105210000_bar_recovery'] as $id) {
            $migration = require dirname(__DIR__, 2) . '/migrations/' . $id . '.php';
            $migration['up']($this->pdo);
            $migration['up']($this->pdo);
            if (!$migration['verify']($this->pdo)) {
                throw new RuntimeException('Native migration verification failed: ' . $id);
            }
        }
        $this->pdo->exec("INSERT INTO staff_user_positions(trainer_id,position_code,is_default) VALUES(2,'bar_operator',1),(3,'bar_admin',1)");
        $this->pdo->exec("INSERT INTO kis_bar_categories(id,name) VALUES(1,'LOCALHOST Drinks')");
        $this->pdo->exec("INSERT INTO kis_bar_products(id,category_id,name,description,price_minor,purchase_minor,stock_mode,stock_stall,package_name)
            VALUES(1,1,'LOCALHOST Tea','',3500,1101,'sledovat',20,'')");
    }

    private function options(): array
    {
        return [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false];
    }

    public function connect(): PDO
    {
        return new PDO($this->dsn, 'root', '', $this->options());
    }

    public function start(array $request): int
    {
        $process = proc_open([PHP_BINARY, $this->workerFile], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Cannot start isolated BAR recovery worker.');
        }
        fwrite($pipes[0], json_encode($request, JSON_THROW_ON_ERROR));
        fclose($pipes[0]);
        stream_set_timeout($pipes[1], 20);
        $ready = json_decode((string)fgets($pipes[1]), true, 8, JSON_THROW_ON_ERROR);
        if (!($ready['ready'] ?? false)) {
            throw new RuntimeException('Recovery worker did not become ready.');
        }
        $this->workers[] = ['process' => $process, 'out' => $pipes[1], 'err' => $pipes[2], 'connection_id' => (int)$ready['connection_id']];
        return array_key_last($this->workers);
    }

    public function waitForSql(int $worker, string $needle): void
    {
        $deadline = microtime(true) + 8;
        do {
            $query = $this->server->prepare('SELECT INFO FROM information_schema.PROCESSLIST WHERE ID=?');
            $query->execute([$this->workers[$worker]['connection_id']]);
            $info = (string)$query->fetchColumn();
            if (str_contains($info, $needle)) {
                return;
            }
            usleep(20000);
        } while (microtime(true) < $deadline);
        throw new RuntimeException('Worker did not reach its real SQL barrier.');
    }

    public function killConnection(int $worker): void
    {
        $this->server->exec('KILL CONNECTION ' . $this->workers[$worker]['connection_id']);
    }

    public function finish(int $id): array
    {
        $worker = $this->workers[$id];
        $out = trim((string)stream_get_contents($worker['out']));
        $err = trim((string)stream_get_contents($worker['err']));
        fclose($worker['out']);
        fclose($worker['err']);
        $code = proc_close($worker['process']);
        unset($this->workers[$id]);
        if ($code !== 0 || $err !== '') {
            throw new RuntimeException('Recovery worker failed: ' . $err);
        }
        return $out === '' ? ['response_lost' => true] : json_decode($out, true, 16, JSON_THROW_ON_ERROR);
    }

    public function close(): void
    {
        if ($this->barrier->inTransaction()) {
            $this->barrier->rollBack();
        }
        foreach ($this->workers as $worker) {
            proc_terminate($worker['process']);
            fclose($worker['out']);
            fclose($worker['err']);
            proc_close($worker['process']);
        }
        $this->workers = [];
        $this->server->exec('DROP DATABASE `' . $this->database . '`');
        foreach ($this->previousEnvironment as $key => $value) {
            putenv($value === false ? $key : $key . '=' . $value);
        }
        $_SESSION = [];
    }
}
