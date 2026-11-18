<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('EVIDENCE_PERFORMANCE_CONFIRM') !== 'SYNTHETIC-ONLY') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/CatalogTopCountsBaseline.php';
require_once __DIR__ . '/CatalogTopCountsFixture.php';
$port = (int)getenv('EVIDENCE_MARIADB_SMOKE_PORT');
if ($port < 1 || $port > 65535) {
    throw new RuntimeException('Explicit synthetic MariaDB port required.');
}

final class CatalogTopCountsProfilePdo extends PDO
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

function catalogTopCountsConnection(int $port, string $schema = ''): CatalogTopCountsProfilePdo
{
    $pdo = new CatalogTopCountsProfilePdo(
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
    if (!preg_match('/^cat_top_[a-f0-9]{16}$/D', $schema) || !in_array($mode, ['before', 'after'], true)) {
        throw new RuntimeException('Owned schema and mode required.');
    }
    $pdo = catalogTopCountsConnection($port, $schema);
    if ($pdo->query('SELECT label FROM synthetic_owner')->fetchColumn() !== 'LOCALHOST NEPLATIT category menu') {
        throw new RuntimeException('Synthetic owner mismatch.');
    }
    $products = json_decode((string)$pdo->query('SELECT products FROM synthetic_owner')->fetchColumn(), true, 512, JSON_THROW_ON_ERROR);
    // Warm both algorithms equally, including frozen closure compilation, outside measurements.
    $mode === 'before' ? catalogTopCountsBaseline($pdo, $products) : shopCatalogPageSelect($pdo, $products, []);
    $pdo->queries = 0;
    memory_reset_peak_usage();
    $memory = memory_get_usage();
    $start = hrtime(true);
    $keys = $mode === 'before' ? catalogTopCountsBaseline($pdo, $products) : shopCatalogPageSelect($pdo, $products, []);
    $elapsed = (hrtime(true) - $start) / 1e6;
    echo json_encode(['ms' => $elapsed, 'peak_bytes' => memory_get_peak_usage() - $memory, 'sql' => $pdo->queries,
        'keys' => $keys, 'sha256' => hash('sha256', serialize($keys))], JSON_THROW_ON_ERROR), "\n";
    exit;
}

$output = realpath($argv[1] ?? '');
if ($output === false) {
    throw new RuntimeException('Owned output required.');
}
$admin = catalogTopCountsConnection($port);
$schema = 'cat_top_' . bin2hex(random_bytes(8));
$admin->exec('CREATE DATABASE `' . $schema . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo = catalogTopCountsConnection($port, $schema);
$results = [];
$clean = false;
try {
    $pdo->exec('CREATE TABLE synthetic_owner(label VARCHAR(100) NOT NULL,products LONGTEXT NULL)');
    $pdo->exec("INSERT INTO synthetic_owner(label) VALUES('LOCALHOST NEPLATIT category menu')");
    $pdo->exec('CREATE TABLE shop_product_categories(product_id INT,category_path VARCHAR(500)) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE shop_member_category_rules(category_path VARCHAR(500)) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE shop_category_meta(category_path VARCHAR(500) PRIMARY KEY,display_name TEXT,parent_path VARCHAR(500) NULL,'
        . 'sort_order INT,visible_in_menu INT,description TEXT NULL) ENGINE=InnoDB');
    $checksum = static function () use ($pdo): array {
        $out = [];
        foreach (['shop_product_categories', 'shop_member_category_rules', 'shop_category_meta'] as $table) {
            $out[$table] = $pdo->query('CHECKSUM TABLE `' . $table . '` EXTENDED')->fetch(PDO::FETCH_ASSOC)['Checksum'];
        }
        return $out;
    };
    foreach ([[12, 6], [1000, 100], [5000, 500], [10000, 1000], [10000, 1], [1000, 0]] as [$count, $roots]) {
        $products = catalogTopCountsFixture($pdo, $count, $roots);
        $pdo->prepare('UPDATE synthetic_owner SET products=?')->execute([json_encode($products, JSON_THROW_ON_ERROR)]);
        $initial = $checksum();
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
                if (proc_close($process) !== 0 || $record['sql'] !== 6) {
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
        if ($checksum() !== $initial) {
            throw new RuntimeException('Read-only native benchmark changed data.');
        }
        $results[] = ['products' => $count, 'roots' => $roots, 'target' => $count >= 5000 && $roots >= 500, 'state_equal' => true, 'checksum_equal' => true, 'samples' => $samples];
        file_put_contents($output . '/native.json', json_encode($results, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($pdo->query('SELECT label FROM synthetic_owner')->fetchColumn() !== 'LOCALHOST NEPLATIT category menu') {
        throw new RuntimeException('Refusing cleanup without owned fixture marker.');
    }
    $pdo = null;
    $admin->exec('DROP DATABASE `' . $schema . '`');
    $clean = (int)$admin->query("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=" . $admin->quote($schema))->fetchColumn() === 0;
    file_put_contents($output . '/native-cleanup.json', json_encode(['owned_schema_removed' => $clean], JSON_THROW_ON_ERROR));
}
