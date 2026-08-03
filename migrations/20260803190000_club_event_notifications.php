<?php
declare(strict_types=1);

$notificationTableExists = static function (PDO $pdo): bool {
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        return (bool)$pdo->query(
            "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() "
            . "AND TABLE_NAME='club_event_notifications' LIMIT 1"
        )->fetchColumn();
    }
    if ($driver === 'sqlite') {
        return (bool)$pdo->query(
            "SELECT 1 FROM sqlite_master WHERE type='table' AND name='club_event_notifications' LIMIT 1"
        )->fetchColumn();
    }
    throw new RuntimeException('Unsupported database driver for club event notifications migration.');
};

$notificationIndexExists = static function (PDO $pdo, string $index): bool {
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $statement = $pdo->prepare(
            'SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() '
            . "AND TABLE_NAME='club_event_notifications' AND INDEX_NAME=? LIMIT 1"
        );
        $statement->execute([$index]);
        return (bool)$statement->fetchColumn();
    }
    $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='index' AND name=? LIMIT 1");
    $statement->execute([$index]);
    return (bool)$statement->fetchColumn();
};

return [
    'id' => '20260803190000_club_event_notifications',
    'up' => static function (PDO $pdo) use ($notificationTableExists): void {
        if ($notificationTableExists($pdo)) {
            return;
        }
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $pdo->exec($driver === 'mysql' ? <<<'SQL'
            CREATE TABLE club_event_notifications (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                registration_id BIGINT UNSIGNED NOT NULL,
                registration_event_id BIGINT UNSIGNED NOT NULL,
                notification_type VARCHAR(64) NOT NULL,
                recipient_email VARCHAR(254) NOT NULL,
                recipient_name VARCHAR(255) NOT NULL,
                subject_plain VARCHAR(255) NOT NULL,
                body_plain TEXT NOT NULL,
                status ENUM('pending','processing','sent','failed') NOT NULL DEFAULT 'pending',
                attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
                available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                claimed_at DATETIME NULL,
                claim_token CHAR(32) NULL,
                sent_at DATETIME NULL,
                last_error VARCHAR(500) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_club_event_notification (registration_event_id, notification_type),
                KEY idx_club_event_notification_queue (status, available_at, id),
                CONSTRAINT fk_club_event_notification_registration FOREIGN KEY (registration_id)
                    REFERENCES club_event_registrations (id) ON DELETE RESTRICT,
                CONSTRAINT fk_club_event_notification_event FOREIGN KEY (registration_event_id)
                    REFERENCES club_event_registration_events (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL : <<<'SQL'
            CREATE TABLE club_event_notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                registration_id INTEGER NOT NULL,
                registration_event_id INTEGER NOT NULL,
                notification_type TEXT NOT NULL,
                recipient_email TEXT NOT NULL,
                recipient_name TEXT NOT NULL,
                subject_plain TEXT NOT NULL,
                body_plain TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','processing','sent','failed')),
                attempts INTEGER NOT NULL DEFAULT 0,
                available_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                claimed_at TEXT NULL,
                claim_token TEXT NULL,
                sent_at TEXT NULL,
                last_error TEXT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (registration_id) REFERENCES club_event_registrations(id) ON DELETE RESTRICT,
                FOREIGN KEY (registration_event_id) REFERENCES club_event_registration_events(id) ON DELETE RESTRICT
            )
            SQL);
        if ($driver === 'sqlite') {
            $pdo->exec(
                'CREATE UNIQUE INDEX uq_club_event_notification ON club_event_notifications '
                . '(registration_event_id, notification_type)'
            );
            $pdo->exec(
                'CREATE INDEX idx_club_event_notification_queue ON club_event_notifications '
                . '(status, available_at, id)'
            );
        }
    },
    'verify' => static function (PDO $pdo) use ($notificationTableExists, $notificationIndexExists): bool {
        return $notificationTableExists($pdo)
            && $notificationIndexExists($pdo, 'uq_club_event_notification')
            && $notificationIndexExists($pdo, 'idx_club_event_notification_queue');
    },
];
