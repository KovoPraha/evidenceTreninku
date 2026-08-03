<?php
declare(strict_types=1);

$notificationAdminTableExists = static function (PDO $pdo): bool {
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        return (bool)$pdo->query(
            "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() "
            . "AND TABLE_NAME='club_event_notification_events' LIMIT 1"
        )->fetchColumn();
    }
    $statement = $pdo->query(
        "SELECT 1 FROM sqlite_master WHERE type='table' AND name='club_event_notification_events' LIMIT 1"
    );
    return (bool)$statement->fetchColumn();
};

return [
    'id' => '20260803210000_club_event_notification_admin',
    'up' => static function (PDO $pdo) use ($notificationAdminTableExists): void {
        if ($notificationAdminTableExists($pdo)) {
            return;
        }
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $pdo->exec($mysql ? <<<'SQL'
            CREATE TABLE club_event_notification_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                notification_id BIGINT UNSIGNED NOT NULL,
                actor_trainer_id INT NOT NULL,
                action VARCHAR(64) NOT NULL,
                from_status VARCHAR(32) NOT NULL,
                attempts_before TINYINT UNSIGNED NOT NULL,
                reason VARCHAR(1000) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_club_notification_event_notification (notification_id, id),
                CONSTRAINT fk_club_notification_event_notification FOREIGN KEY (notification_id)
                    REFERENCES club_event_notifications (id) ON DELETE RESTRICT,
                CONSTRAINT fk_club_notification_event_actor FOREIGN KEY (actor_trainer_id)
                    REFERENCES treneri (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL : <<<'SQL'
            CREATE TABLE club_event_notification_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                notification_id INTEGER NOT NULL,
                actor_trainer_id INTEGER NOT NULL,
                action TEXT NOT NULL,
                from_status TEXT NOT NULL,
                attempts_before INTEGER NOT NULL,
                reason TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (notification_id) REFERENCES club_event_notifications(id) ON DELETE RESTRICT,
                FOREIGN KEY (actor_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
            )
            SQL);
        if (!$mysql) {
            $pdo->exec(
                'CREATE INDEX idx_club_notification_event_notification '
                . 'ON club_event_notification_events(notification_id,id)'
            );
        }
    },
    'verify' => static function (PDO $pdo) use ($notificationAdminTableExists): bool {
        return $notificationAdminTableExists($pdo);
    },
];
