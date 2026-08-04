<?php
declare(strict_types=1);

$tableExists = static function (PDO $pdo, string $table): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    $statement = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $statement->execute([$table]);
    return (bool)$statement->fetchColumn();
};
$columnExists = static function (PDO $pdo, string $table, string $column) use ($tableExists): bool {
    if (!$tableExists($pdo, $table)) return false;
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $definition) {
            if ((string)$definition['name'] === $column) return true;
        }
        return false;
    }
    $statement = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $statement->execute([$table, $column]);
    return (bool)$statement->fetchColumn();
};
$indexExists = static function (PDO $pdo, string $table, string $index): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='index' AND name=?");
        $statement->execute([$index]);
        return (bool)$statement->fetchColumn();
    }
    $statement = $pdo->prepare('SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?');
    $statement->execute([$table, $index]);
    return (bool)$statement->fetchColumn();
};

return [
    'id' => '20260804234800_kis_import_field_contract',
    'up' => static function (PDO $pdo) use ($tableExists, $columnExists, $indexExists): void {
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($tableExists($pdo, 'sportovci') && !$columnExists($pdo, 'sportovci', 'kis_external_id')) {
            $pdo->exec('ALTER TABLE sportovci ADD COLUMN kis_external_id ' . ($driver === 'mysql' ? 'VARCHAR(80) NULL' : 'TEXT NULL'));
        }
        if ($tableExists($pdo, 'sportovci') && !$indexExists($pdo, 'sportovci', 'uq_sportovci_kis_external_id')) {
            $pdo->exec('CREATE UNIQUE INDEX uq_sportovci_kis_external_id ON sportovci(kis_external_id)');
        }
        if ($tableExists($pdo, 'kis_import_rows') && !$columnExists($pdo, 'kis_import_rows', 'kis_external_id')) {
            $pdo->exec('ALTER TABLE kis_import_rows ADD COLUMN kis_external_id ' . ($driver === 'mysql' ? 'VARCHAR(80) NULL' : 'TEXT NULL'));
        }
        if ($tableExists($pdo, 'kis_import_runs')) {
            $columns = [
                'field_contract_version' => $driver === 'mysql' ? 'VARCHAR(64) NULL' : 'TEXT NULL',
                'field_contract_fingerprint' => $driver === 'mysql' ? 'CHAR(64) NULL' : 'TEXT NULL',
                'field_contract_report_json' => $driver === 'mysql' ? 'LONGTEXT NULL' : 'TEXT NULL',
                'field_contract_blockers' => $driver === 'mysql' ? 'INT UNSIGNED NOT NULL DEFAULT 0' : 'INTEGER NOT NULL DEFAULT 0',
            ];
            foreach ($columns as $column => $definition) {
                if (!$columnExists($pdo, 'kis_import_runs', $column)) {
                    $pdo->exec('ALTER TABLE kis_import_runs ADD COLUMN ' . $column . ' ' . $definition);
                }
            }
        }
    },
    'verify' => static function (PDO $pdo) use ($tableExists, $columnExists, $indexExists): bool {
        if ($tableExists($pdo, 'sportovci')
            && (!$columnExists($pdo, 'sportovci', 'kis_external_id') || !$indexExists($pdo, 'sportovci', 'uq_sportovci_kis_external_id'))) return false;
        if ($tableExists($pdo, 'kis_import_rows') && !$columnExists($pdo, 'kis_import_rows', 'kis_external_id')) return false;
        if ($tableExists($pdo, 'kis_import_runs')) {
            foreach (['field_contract_version', 'field_contract_fingerprint', 'field_contract_report_json', 'field_contract_blockers'] as $column) {
                if (!$columnExists($pdo, 'kis_import_runs', $column)) return false;
            }
        }
        return true;
    },
];
