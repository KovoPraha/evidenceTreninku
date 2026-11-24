<?php

declare(strict_types=1);

return [
    'id' => '20261124161000_monthly_overdue_alerts',
    'up' => static function (PDO $pdo): void {
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $pdo->exec($mysql ? <<<'SQL'
            CREATE TABLE IF NOT EXISTS monthly_membership_overdue_alerts (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                charge_id BIGINT UNSIGNED NOT NULL UNIQUE,
                status VARCHAR(16) NOT NULL DEFAULT 'pending',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                resolved_at DATETIME NULL,
                CONSTRAINT fk_monthly_overdue_charge FOREIGN KEY (charge_id) REFERENCES club_member_charges(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL : <<<'SQL'
            CREATE TABLE IF NOT EXISTS monthly_membership_overdue_alerts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                charge_id INTEGER NOT NULL UNIQUE,
                status TEXT NOT NULL DEFAULT 'pending',
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                resolved_at TEXT NULL,
                FOREIGN KEY (charge_id) REFERENCES club_member_charges(id) ON DELETE RESTRICT
            )
            SQL);
    },
    'verify' => static function (PDO $pdo): bool {
        try {
            $pdo->query('SELECT id,charge_id,status,created_at,resolved_at FROM monthly_membership_overdue_alerts LIMIT 0');
            return true;
        } catch (PDOException) {
            return false;
        }
    },
];
