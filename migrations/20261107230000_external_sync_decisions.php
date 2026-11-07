<?php

declare(strict_types=1);

return [
    'id' => '20261107230000_external_sync_decisions',
    'up' => static function (PDO $pdo): void {
        $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $id = $mysql ? 'BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $pdo->exec('CREATE TABLE IF NOT EXISTS external_sync_decisions (
            id ' . $id . ', run_id VARCHAR(36) NOT NULL, ordinal_no INT NOT NULL,
            revision INT NOT NULL, state VARCHAR(20) NOT NULL, reason TEXT NOT NULL,
            actor_id INT NOT NULL, recorded_at BIGINT NOT NULL, basis_hash VARCHAR(64) NOT NULL,
            UNIQUE(run_id,ordinal_no,revision),
            FOREIGN KEY(run_id,ordinal_no) REFERENCES external_sync_records(run_id,ordinal_no)
        )' . ($mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin' : ''));
    },
    'verify' => static function (PDO $pdo): bool {
        try {
            $pdo->query('SELECT id,run_id,ordinal_no,revision,state,reason,actor_id,recorded_at,basis_hash FROM external_sync_decisions LIMIT 0');
            return true;
        } catch (PDOException) {
            return false;
        }
    },
];
