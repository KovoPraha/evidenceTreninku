<?php
declare(strict_types=1);

$sportsContractTableExists = static function (PDO $pdo, string $table): bool {
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    if ($driver === 'sqlite') {
        $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    throw new RuntimeException('Unsupported database driver for sports measurement contract migration.');
};

$sportsContractColumnExists = static function (PDO $pdo, string $table, string $column) use ($sportsContractTableExists): bool {
    if (!$sportsContractTableExists($pdo, $table)) return false;
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $statement->execute([$table, $column]);
        return (bool)$statement->fetchColumn();
    }
    foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $definition) {
        if (($definition['name'] ?? null) === $column) return true;
    }
    return false;
};

return [
    'id' => '20260805050000_sports_measurement_contract',
    'up' => static function (PDO $pdo) use ($sportsContractTableExists, $sportsContractColumnExists): void {
        foreach (['mereni_zaznamy', 'zavod_sportovec'] as $required) {
            if (!$sportsContractTableExists($pdo, $required)) {
                throw new RuntimeException('Sports measurement contract prerequisite is missing: ' . $required . '.');
            }
        }
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $measurementColumns = $mysql ? [
            'contract_version' => 'VARCHAR(40) NULL',
            'distance_unit' => 'VARCHAR(4) NULL',
            'distance_meters' => 'DECIMAL(12,2) NULL',
            'duration_ms' => 'BIGINT UNSIGNED NULL',
            'rpe_value' => 'DECIMAL(3,1) NULL',
        ] : [
            'contract_version' => 'TEXT NULL',
            'distance_unit' => 'TEXT NULL',
            'distance_meters' => 'REAL NULL',
            'duration_ms' => 'INTEGER NULL',
            'rpe_value' => 'REAL NULL',
        ];
        foreach ($measurementColumns as $column => $definition) {
            if (!$sportsContractColumnExists($pdo, 'mereni_zaznamy', $column)) {
                $pdo->exec('ALTER TABLE mereni_zaznamy ADD COLUMN ' . $column . ' ' . $definition);
            }
        }

        $resultColumns = $mysql ? [
            'result_contract_version' => 'VARCHAR(40) NULL',
            'result_status' => 'VARCHAR(16) NULL',
            'result_time_ms' => 'BIGINT UNSIGNED NULL',
        ] : [
            'result_contract_version' => 'TEXT NULL',
            'result_status' => 'TEXT NULL',
            'result_time_ms' => 'INTEGER NULL',
        ];
        foreach ($resultColumns as $column => $definition) {
            if (!$sportsContractColumnExists($pdo, 'zavod_sportovec', $column)) {
                $pdo->exec('ALTER TABLE zavod_sportovec ADD COLUMN ' . $column . ' ' . $definition);
            }
        }
    },
    'verify' => static function (PDO $pdo) use ($sportsContractColumnExists): bool {
        foreach (['contract_version', 'distance_unit', 'distance_meters', 'duration_ms', 'rpe_value'] as $column) {
            if (!$sportsContractColumnExists($pdo, 'mereni_zaznamy', $column)) return false;
        }
        foreach (['result_contract_version', 'result_status', 'result_time_ms'] as $column) {
            if (!$sportsContractColumnExists($pdo, 'zavod_sportovec', $column)) return false;
        }
        return true;
    },
];
