<?php
declare(strict_types=1);

$tableExists = static function (PDO $pdo, string $table): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    $statement = $pdo->prepare(
        'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?'
    );
    $statement->execute([$table]);
    return (bool)$statement->fetchColumn();
};

$columnExists = static function (PDO $pdo, string $table, string $column) use ($tableExists): bool {
    if (!$tableExists($pdo, $table)) {
        return false;
    }
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $definition) {
            if ((string)$definition['name'] === $column) {
                return true;
            }
        }
        return false;
    }
    $statement = $pdo->prepare(
        'SELECT 1 FROM information_schema.COLUMNS '
        . 'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?'
    );
    $statement->execute([$table, $column]);
    return (bool)$statement->fetchColumn();
};

return [
    'id' => '20260804234500_kis_import_preview_integrity',
    'up' => static function (PDO $pdo) use ($tableExists, $columnExists): void {
        if (!$tableExists($pdo, 'kis_import_runs')) {
            return;
        }
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $columns = [
            'preview_contract_version' => $driver === 'mysql' ? 'VARCHAR(64) NULL' : 'TEXT NULL',
            'preview_fingerprint' => $driver === 'mysql' ? 'CHAR(64) NULL' : 'TEXT NULL',
            'preview_report_json' => $driver === 'mysql' ? 'LONGTEXT NULL' : 'TEXT NULL',
            'classified_rows' => $driver === 'mysql' ? 'INT UNSIGNED NOT NULL DEFAULT 0' : 'INTEGER NOT NULL DEFAULT 0',
            'blocker_rows' => $driver === 'mysql' ? 'INT UNSIGNED NOT NULL DEFAULT 0' : 'INTEGER NOT NULL DEFAULT 0',
        ];
        foreach ($columns as $name => $definition) {
            if (!$columnExists($pdo, 'kis_import_runs', $name)) {
                $pdo->exec('ALTER TABLE kis_import_runs ADD COLUMN ' . $name . ' ' . $definition);
            }
        }
        if ($driver === 'mysql') {
            $statement = $pdo->prepare(
                'SELECT 1 FROM information_schema.STATISTICS '
                . 'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?'
            );
            $statement->execute(['kis_import_runs', 'idx_kis_preview_fingerprint']);
            if (!$statement->fetchColumn()) {
                $pdo->exec('CREATE INDEX idx_kis_preview_fingerprint ON kis_import_runs(preview_fingerprint)');
            }
        }
    },
    'verify' => static function (PDO $pdo) use ($tableExists, $columnExists): bool {
        if (!$tableExists($pdo, 'kis_import_runs')) {
            return true;
        }
        foreach (['preview_contract_version', 'preview_fingerprint', 'preview_report_json', 'classified_rows', 'blocker_rows'] as $column) {
            if (!$columnExists($pdo, 'kis_import_runs', $column)) {
                return false;
            }
        }
        return true;
    },
];
