<?php
declare(strict_types=1);

return [
    'id' => '20261020170000_fio_sync_state',
    'up' => static function (PDO $pdo): void {
        $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $pdo->exec('CREATE TABLE IF NOT EXISTS fio_sync_state (
            source_account_iban VARCHAR(34) PRIMARY KEY,
            start_date DATE NOT NULL,
            covered_through DATE NULL,
            last_attempt_at BIGINT NULL,
            last_success_at BIGINT NULL,
            last_error VARCHAR(64) NULL
        )'.($mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : ''));
    },
    'verify' => static function (PDO $pdo): bool {
        try {
            $pdo->query('SELECT source_account_iban,start_date,covered_through,last_attempt_at,last_success_at,last_error FROM fio_sync_state LIMIT 0');
            return true;
        } catch (PDOException) {
            return false;
        }
    },
];
