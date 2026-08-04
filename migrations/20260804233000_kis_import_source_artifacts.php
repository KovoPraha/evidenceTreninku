<?php
declare(strict_types=1);

$kisArtifactTableExists = static function (PDO $pdo, string $table): bool {
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

$kisArtifactColumnExists = static function (PDO $pdo, string $table, string $column) use ($kisArtifactTableExists): bool {
    if (!$kisArtifactTableExists($pdo, $table)) {
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
    'id' => '20260804233000_kis_import_source_artifacts',
    'up' => static function (PDO $pdo) use ($kisArtifactTableExists, $kisArtifactColumnExists): void {
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if (!$kisArtifactTableExists($pdo, 'kis_import_source_artifacts')) {
            $pdo->exec($driver === 'mysql' ? <<<'SQL'
                CREATE TABLE kis_import_source_artifacts (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    source_kind VARCHAR(24) NOT NULL,
                    contract_version VARCHAR(64) NOT NULL,
                    sha256 CHAR(64) NOT NULL,
                    byte_size BIGINT UNSIGNED NOT NULL,
                    original_filename VARCHAR(255) NOT NULL,
                    storage_key VARCHAR(96) NOT NULL,
                    archived_by INT NULL,
                    archived_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_kis_source_artifact (source_kind,contract_version,sha256),
                    UNIQUE KEY uq_kis_source_storage (storage_key),
                    INDEX idx_kis_source_archived_at (archived_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE kis_import_source_artifacts (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    source_kind TEXT NOT NULL,
                    contract_version TEXT NOT NULL,
                    sha256 TEXT NOT NULL,
                    byte_size INTEGER NOT NULL,
                    original_filename TEXT NOT NULL,
                    storage_key TEXT NOT NULL UNIQUE,
                    archived_by INTEGER NULL,
                    archived_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(source_kind,contract_version,sha256)
                )
                SQL);
        }
        if ($kisArtifactTableExists($pdo, 'kis_import_runs')
            && !$kisArtifactColumnExists($pdo, 'kis_import_runs', 'source_manifest_json')) {
            $pdo->exec(
                'ALTER TABLE kis_import_runs ADD COLUMN source_manifest_json '
                . ($driver === 'mysql' ? 'LONGTEXT NULL' : 'TEXT NULL')
            );
        }
    },
    'verify' => static function (PDO $pdo) use ($kisArtifactTableExists, $kisArtifactColumnExists): bool {
        if (!$kisArtifactTableExists($pdo, 'kis_import_source_artifacts')) {
            return false;
        }
        return !$kisArtifactTableExists($pdo, 'kis_import_runs')
            || $kisArtifactColumnExists($pdo, 'kis_import_runs', 'source_manifest_json');
    },
];
