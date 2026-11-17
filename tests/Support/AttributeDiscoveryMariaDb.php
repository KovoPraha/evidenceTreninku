<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('EVIDENCE_PERFORMANCE_CONFIRM') !== 'SYNTHETIC-ONLY') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/AttributeDiscoveryBaseline.php';
$port = (int)getenv('EVIDENCE_MARIADB_SMOKE_PORT');
if ($port < 1 || $port > 65535) {
    throw new RuntimeException('Explicit synthetic MariaDB port required.');
}

final class AttributeDiscoveryProfilePdo extends PDO
{
    public int $queries = 0;

    public function query(string $query, ?int $fetchMode = null, mixed ...$args): PDOStatement|false
    {
        $this->queries++;
        return $fetchMode === null ? parent::query($query) : parent::query($query, $fetchMode, ...$args);
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->queries++;
        return parent::prepare($query, $options);
    }
}

function attributeDiscoveryConnection(int $port, string $schema = ''): AttributeDiscoveryProfilePdo
{
    $pdo = new AttributeDiscoveryProfilePdo(
        'mysql:host=127.0.0.1;port=' . $port . ';charset=utf8mb4;dbname=' . $schema,
        'root',
        '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
    $pdo->exec('SET SESSION query_cache_type=OFF');
    return $pdo;
}

if (($argv[1] ?? '') === '--worker') {
    $schema = $argv[2] ?? '';
    $mode = $argv[3] ?? '';
    if (!preg_match('/^attr_discovery_[a-f0-9]{16}$/D', $schema) || !in_array($mode, ['before', 'after'], true)) {
        throw new RuntimeException('Owned schema and mode required.');
    }
    $pdo = attributeDiscoveryConnection($port, $schema);
    if ($pdo->query('SELECT label FROM synthetic_owner')->fetchColumn() !== 'LOCALHOST NEPLATIT attribute discovery') {
        throw new RuntimeException('Synthetic owner mismatch.');
    }
    // Warm both algorithms equally, including frozen closure compilation, outside measurements.
    $mode === 'before' ? attributeDiscoveryBaseline($pdo) : shopAttributeDiscoveredKeys($pdo);
    $pdo->queries = 0;
    memory_reset_peak_usage();
    $memory = memory_get_usage();
    $start = hrtime(true);
    $keys = $mode === 'before' ? attributeDiscoveryBaseline($pdo) : shopAttributeDiscoveredKeys($pdo);
    $elapsed = (hrtime(true) - $start) / 1e6;
    echo json_encode(['ms' => $elapsed, 'peak_bytes' => memory_get_peak_usage() - $memory, 'sql' => $pdo->queries,
        'keys' => $keys, 'sha256' => hash('sha256', json_encode($keys, JSON_THROW_ON_ERROR))], JSON_THROW_ON_ERROR), "\n";
    exit;
}

$output = realpath($argv[1] ?? '');
if ($output === false) {
    throw new RuntimeException('Owned output required.');
}
$admin = attributeDiscoveryConnection($port);
$schema = 'attr_discovery_' . bin2hex(random_bytes(8));
$admin->exec('CREATE DATABASE `' . $schema . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo = attributeDiscoveryConnection($port, $schema);
$results = [];
$clean = false;
try {
    $pdo->exec('CREATE TABLE synthetic_owner(label VARCHAR(100) NOT NULL)');
    $pdo->exec("INSERT INTO synthetic_owner VALUES('LOCALHOST NEPLATIT attribute discovery')");
    // BLOB deliberately preserves malformed historical bytes for semantic verification.
    $pdo->exec('CREATE TABLE shop_variants(id INT PRIMARY KEY,attributes_json LONGBLOB NULL) ENGINE=InnoDB');
    $pdo->exec('SET SESSION query_cache_type=OFF');
    foreach (['repeated', 'unique', 'legacy'] as $shape) {
        foreach ($shape === 'legacy' ? [400] : [1000, 10000, 30000] as $count) {
            $pdo->exec('DELETE FROM shop_variants');
            $insert = $pdo->prepare('INSERT INTO shop_variants VALUES(?,?)');
            $pdo->beginTransaction();
            for ($i = 1; $i <= $count; $i++) {
                $data = [];
                for ($k = 0; $k < 16; $k++) {
                    $data['LOCALHOST_key_' . $k] = str_repeat('x', 64) . ($shape === 'unique' ? $i : $i % 8);
                }
                $raw = json_encode($data, JSON_THROW_ON_ERROR);
                if ($shape === 'legacy') {
                    $legacy = [null, '', 'null', 'false', '0', '1', '"scalar"', '{}', '[]', '["A","B"]',
                        '{invalid', "{\"bad\":\"\xFF\"}", '{" A ":1,"A":2,"a":3,"Á":4,"á":5," ":6}',
                        '{"0":1,"01":2,"-1":3,"9223372036854775808":4}',
                        str_repeat('[', 65) . '0' . str_repeat(']', 65),
                        json_encode(['padding' => str_repeat('x', 9000), 'lateA' => 1], JSON_THROW_ON_ERROR),
                        json_encode(['padding' => str_repeat('x', 9000), 'lateB' => 1], JSON_THROW_ON_ERROR)];
                    $raw = $i <= 300 ? json_encode(['LOCALHOST_' . $i => $i], JSON_THROW_ON_ERROR) : $legacy[$i % count($legacy)];
                }
                $insert->execute([$i, $raw]);
            }
            $pdo->commit();
            $checksum = $pdo->query('CHECKSUM TABLE shop_variants EXTENDED')->fetch(PDO::FETCH_ASSOC)['Checksum'];
            $samples = ['before' => [], 'after' => []];
            $expected = null;
            for ($round = -2; $round < 11; $round++) {
                foreach ($round % 2 === 0 ? ['before', 'after'] : ['after', 'before'] as $mode) {
                    $process = proc_open(
                        [PHP_BINARY, __FILE__, '--worker', $schema, $mode],
                        [1 => ['pipe', 'w'], 2 => STDERR],
                        $pipes
                    );
                    if (!is_resource($process)) {
                        throw new RuntimeException('Worker unavailable.');
                    }
                    $record = json_decode((string)stream_get_contents($pipes[1]), true, 512, JSON_THROW_ON_ERROR);
                    fclose($pipes[1]);
                    if (proc_close($process) !== 0 || $record['sql'] !== 2) {
                        throw new RuntimeException('Worker or query budget failed.');
                    }
                    $expected ??= $record['keys'];
                    if ($expected !== $record['keys']) {
                        throw new RuntimeException('Native discovery keys differ.');
                    }
                    unset($record['keys']);
                    if ($round >= 0) {
                        $samples[$mode][] = $record;
                    }
                }
            }
            if ($pdo->query('CHECKSUM TABLE shop_variants EXTENDED')->fetch(PDO::FETCH_ASSOC)['Checksum'] !== $checksum) {
                throw new RuntimeException('Read-only native benchmark changed data.');
            }
            $results[] = ['shape' => $shape, 'rows' => $count, 'keys_equal' => true, 'checksum_equal' => true, 'samples' => $samples];
            file_put_contents($output . '/native.json', json_encode($results, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        }
    }
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($pdo->query('SELECT label FROM synthetic_owner')->fetchColumn() !== 'LOCALHOST NEPLATIT attribute discovery') {
        throw new RuntimeException('Refusing cleanup without owned fixture marker.');
    }
    $pdo = null;
    $admin->exec('DROP DATABASE `' . $schema . '`');
    $clean = (int)$admin->query("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=" . $admin->quote($schema))->fetchColumn() === 0;
    file_put_contents($output . '/native-cleanup.json', json_encode(['owned_schema_removed' => $clean], JSON_THROW_ON_ERROR));
}
