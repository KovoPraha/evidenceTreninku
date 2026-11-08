<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/** Metadata only: never select a person, watch, record value, document or credential. */
function medicalReadIndexInventory(PDO $pdo): array
{
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $query = $pdo->query($mysql ? "SELECT ENGINE FROM information_schema.TABLES
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='medical_clearance_records'"
        : "SELECT 'SQLite' FROM sqlite_master WHERE type='table' AND name='medical_clearance_records'");
    try {
        $engine = $query->fetchColumn();
    } finally {
        $query->closeCursor();
    }
    $indexes = $engine !== false ? MedicalClearanceReadIndex::definitions($pdo) : [];
    return ['table' => 'medical_clearance_records', 'exists' => $engine !== false,
        'engine' => $engine === false ? null : (string) $engine, 'indexes' => $indexes,
        'covering_index' => MedicalClearanceReadIndex::coveringName($indexes),
        'clinical_rows_read' => false, 'ddl_executed' => false];
}

function diagnoseMedicalReadIndexesMain(): void
{
    $pdo = null;
    $step = 'target';
    try {
        $root = realpath((string) getenv('APP_ROOT'));
        $phase = (string) getenv('MEDICAL_INDEX_PHASE');
        $sha = (string) getenv('MEDICAL_INDEX_SOURCE_SHA');
        if (getenv('APP_HOST') !== 'kis.kovopraha.cz' || $root === false || !is_file($root . '/config.php')
            || !in_array($phase, ['before', 'after'], true) || !preg_match('/^[a-f0-9]{40}$/D', $sha)) {
            throw new RuntimeException('Unexpected medical index diagnostic target.');
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
        foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $constant) {
            if (!defined($constant)) {
                throw new RuntimeException('Incomplete database configuration.');
            }
        }
        $step = 'index_definition';
        $helper = realpath(getenv('MEDICAL_READ_INDEX_HELPER') ?: dirname(__DIR__) . '/includes/medical_clearance_read_index.php');
        if ($helper === false) {
            throw new RuntimeException('Missing index definition.');
        }
        require_once $helper;
        $step = 'connection';
        $pdo = new PDO(
            'mysql:host=' . constant('DB_HOST') . ';dbname=' . constant('DB_NAME') . ';charset=utf8mb4',
            (string) constant('DB_USER'),
            (string) constant('DB_PASS'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
        );
        $pdo->exec('START TRANSACTION READ ONLY');
        $step = 'metadata';
        $inventory = medicalReadIndexInventory($pdo);
        $version = $pdo->query('SELECT VERSION()')->fetchColumn();
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $report = ['contract' => 'medical-read-index-inventory-v1', 'read_only' => true, 'source_sha' => $sha,
            'phase' => $phase, 'at_utc' => gmdate('c'), 'server' => $version,
            'definition_sha256' => hash_file('sha256', $helper), 'inventory' => $inventory,
            'ok' => $phase === 'before' || $inventory['covering_index'] !== null];
        echo json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        if (!$report['ok']) {
            exit(1);
        }
    } catch (Throwable $error) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // Exception messages may contain connection details; emit only a phase and type.
        fwrite(STDERR, json_encode(['ok' => false, 'read_only' => true, 'step' => $step, 'error_type' => get_class($error)], JSON_THROW_ON_ERROR) . PHP_EOL);
        exit(1);
    }
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    diagnoseMedicalReadIndexesMain();
}
