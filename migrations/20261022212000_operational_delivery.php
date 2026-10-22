<?php
declare(strict_types=1);

return [
    'id' => '20261022212000_operational_delivery',
    'up' => static function (PDO $pdo): void {
        $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $suffix = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        $pdo->exec('CREATE TABLE IF NOT EXISTS booking_notification_context ('
            . 'notification_id BIGINT PRIMARY KEY,reservation_id BIGINT NOT NULL,recipient_type VARCHAR(16) NOT NULL,'
            . 'recipient_id BIGINT NOT NULL,expected_state VARCHAR(32) NOT NULL,expires_at DATETIME NOT NULL)'.$suffix);
        $pdo->exec('CREATE TABLE IF NOT EXISTS operational_job_runs ('
            . 'id '.($mysql ? 'BIGINT UNSIGNED AUTO_INCREMENT' : 'INTEGER').' PRIMARY KEY,'
            . 'job_key VARCHAR(64) NOT NULL,started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,'
            . 'finished_at DATETIME NULL,status VARCHAR(16) NOT NULL,summary_code VARCHAR(64) NULL)'.$suffix);
        $pdo->exec('CREATE TABLE IF NOT EXISTS notification_authorization_snapshots ('
            . 'queue_key VARCHAR(64) NOT NULL,message_id BIGINT NOT NULL,authorization_hash CHAR(64) NOT NULL,'
            . 'PRIMARY KEY(queue_key,message_id))'.$suffix);
    },
    'verify' => static function (PDO $pdo): bool {
        try {
            $pdo->query('SELECT notification_id,reservation_id,recipient_type,recipient_id,expected_state,expires_at FROM booking_notification_context WHERE 1=0');
            $pdo->query('SELECT id,job_key,started_at,finished_at,status,summary_code FROM operational_job_runs WHERE 1=0');
            $pdo->query('SELECT queue_key,message_id,authorization_hash FROM notification_authorization_snapshots WHERE 1=0');
            return true;
        } catch (PDOException) { return false; }
    },
];
