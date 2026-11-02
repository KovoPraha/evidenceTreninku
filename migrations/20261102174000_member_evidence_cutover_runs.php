<?php

declare(strict_types=1);

return [
    'id' => '20261102174000_member_evidence_cutover_runs',
    'up' => static function (PDO $pdo): void {
        $pdo->exec($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? <<<'SQL'
            CREATE TABLE IF NOT EXISTS member_evidence_cutover_runs (
                fingerprint CHAR(64) PRIMARY KEY,
                actor_trainer_id INT NOT NULL,
                backup_sha256 CHAR(64) NOT NULL,
                summary_json LONGTEXT NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(actor_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL : <<<'SQL'
            CREATE TABLE IF NOT EXISTS member_evidence_cutover_runs (
                fingerprint TEXT PRIMARY KEY,
                actor_trainer_id INTEGER NOT NULL REFERENCES treneri(id) ON DELETE RESTRICT,
                backup_sha256 TEXT NOT NULL,
                summary_json TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
            SQL);
    },
    'verify' => static function (PDO $pdo): bool {
        $pdo->query('SELECT fingerprint,actor_trainer_id,backup_sha256,summary_json FROM member_evidence_cutover_runs WHERE 1=0');
        return true;
    },
];
