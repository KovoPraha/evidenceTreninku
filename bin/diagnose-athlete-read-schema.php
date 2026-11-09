<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/** Fixed metadata targets only; never select a sports/person record. */
function athleteReadSchemaInventory(PDO $pdo): array
{
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $tables = [];
    foreach (['treninky', 'trenink_sportovec', 'trenink_mereni', 'mereni_zaznamy', 'sportovec_poznamka'] as $table) {
        $statement = $pdo->prepare($mysql
            ? 'SELECT ENGINE,TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?'
            : "SELECT 'SQLite' AS ENGINE,NULL AS TABLE_ROWS FROM sqlite_master WHERE type='table' AND name=?");
        $statement->execute([$table]);
        $meta = $statement->fetch(PDO::FETCH_ASSOC);
        $statement->closeCursor();
        $columns = [];
        $indexes = [];
        if ($meta !== false && $mysql) {
            foreach ($pdo->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC) as $column) {
                $columns[] = array_intersect_key($column, array_flip(['Field', 'Type', 'Null', 'Key', 'Extra']));
            }
            foreach ($pdo->query('SHOW INDEX FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC) as $index) {
                $indexes[] = ['name' => (string) $index['Key_name'], 'column' => $index['Column_name'],
                    'sequence' => (int) $index['Seq_in_index'], 'non_unique' => (int) $index['Non_unique'],
                    'sub_part' => $index['Sub_part'], 'cardinality' => $index['Cardinality'],
                    'type' => $index['Index_type']];
            }
        } elseif ($meta !== false) {
            foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $column) {
                $columns[] = ['Field' => $column['name'], 'Type' => $column['type'],
                    'Null' => (int) $column['notnull'] === 1 ? 'NO' : 'YES', 'Key' => (int) $column['pk'] > 0 ? 'PRI' : ''];
            }
            foreach ($pdo->query('PRAGMA index_list(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $index) {
                $name = str_replace("'", "''", $index['name']);
                foreach ($pdo->query("PRAGMA index_info('" . $name . "')")->fetchAll(PDO::FETCH_ASSOC) as $part) {
                    $indexes[] = ['name' => $index['name'], 'column' => $part['name'],
                        'sequence' => (int) $part['seqno'] + 1, 'non_unique' => 1 - (int) $index['unique'],
                        'sub_part' => null, 'cardinality' => null, 'type' => 'BTREE'];
                }
            }
        }
        $tables[$table] = ['exists' => $meta !== false, 'engine' => $meta['ENGINE'] ?? null,
            'rows_estimate' => isset($meta['TABLE_ROWS']) ? (int) $meta['TABLE_ROWS'] : null,
            'columns' => $columns, 'indexes' => $indexes];
    }
    return ['tables' => $tables, 'domain_rows_read' => false, 'ddl_executed' => false];
}

function diagnoseAthleteReadSchemaMain(): void
{
    $pdo = null;
    $step = 'target';
    try {
        $root = realpath((string) getenv('APP_ROOT'));
        $sha = (string) getenv('ATHLETE_SCHEMA_SOURCE_SHA');
        if (getenv('APP_HOST') !== 'kis.kovopraha.cz' || $root === false || !is_file($root . '/config.php')
            || !preg_match('/^[a-f0-9]{40}$/D', $sha)) {
            throw new RuntimeException('Unexpected metadata diagnostic target.');
        }
        $_SERVER['HTTP_HOST'] = 'kis.kovopraha.cz';
        $_SERVER['SERVER_NAME'] = 'kis.kovopraha.cz';
        $step = 'configuration';
        ob_start();
        try {
            require $root . '/config.php';
        } finally {
            ob_end_clean();
        }
        if (!defined('JE_LOKALNE') || constant('JE_LOKALNE') !== false) {
            throw new RuntimeException('Production configuration required.');
        }
        foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $name) {
            if (!defined($name)) {
                throw new RuntimeException('Incomplete configuration.');
            }
        }
        $step = 'connection';
        $pdo = new PDO(
            'mysql:host=' . constant('DB_HOST') . ';dbname=' . constant('DB_NAME') . ';charset=utf8mb4',
            (string) constant('DB_USER'),
            (string) constant('DB_PASS'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false]
        );
        $pdo->exec('START TRANSACTION READ ONLY');
        $step = 'metadata';
        $inventory = athleteReadSchemaInventory($pdo);
        $version = $pdo->query('SELECT VERSION()')->fetchColumn();
        $pdo->rollBack();
        $ok = !in_array(false, array_column($inventory['tables'], 'exists'), true);
        echo json_encode(
            ['contract' => 'athlete-read-schema-v1', 'ok' => $ok, 'read_only' => true,
            'source_sha' => $sha, 'at_utc' => gmdate('c'), 'server' => $version,
            'probe_sha256' => hash_file('sha256', __FILE__)] + $inventory,
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ) . PHP_EOL;
        if (!$ok) {
            exit(1);
        }
    } catch (Throwable $error) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // Connection exception messages can contain credentials: never print them.
        fwrite(STDERR, json_encode(['ok' => false, 'read_only' => true, 'step' => $step,
            'error_type' => get_class($error)], JSON_THROW_ON_ERROR) . PHP_EOL);
        exit(1);
    }
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    diagnoseAthleteReadSchemaMain();
}
