<?php

declare(strict_types=1);

return [
    'id' => '20261102172000_member_roster_operations',
    'up' => static function (PDO $pdo): void {
        $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $pdo->exec($mysql ? <<<'SQL'
            CREATE TABLE IF NOT EXISTS member_roster_operations (
                fingerprint CHAR(64) PRIMARY KEY,
                actor_trainer_id INT NOT NULL,
                request_json LONGTEXT NOT NULL,
                result_json LONGTEXT NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(actor_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL : <<<'SQL'
            CREATE TABLE IF NOT EXISTS member_roster_operations (
                fingerprint TEXT PRIMARY KEY,
                actor_trainer_id INTEGER NOT NULL REFERENCES treneri(id) ON DELETE RESTRICT,
                request_json TEXT NOT NULL,
                result_json TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
            SQL);
    },
    'verify' => static function (PDO $pdo): bool {
        $pdo->query('SELECT fingerprint,actor_trainer_id,request_json,result_json FROM member_roster_operations WHERE 1=0');
        return true;
    },
];
