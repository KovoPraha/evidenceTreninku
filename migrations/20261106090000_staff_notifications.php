<?php

declare(strict_types=1);

return [
    'id' => '20261106090000_staff_notifications',
    'up' => static function (PDO $pdo): void {
        $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $id = $mysql ? 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $big = $mysql ? 'BIGINT UNSIGNED' : 'INTEGER';
        $tail = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        if ($mysql) {
            $pdo->exec("ALTER TABLE club_event_notifications MODIFY status ENUM('pending','processing','sent','failed','cancelled') NOT NULL DEFAULT 'pending'");
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS staff_notification_events (
            id $id,
            event_type VARCHAR(64) NOT NULL,
            source_id $big NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            resolved_at DATETIME NULL,
            UNIQUE (event_type, source_id)
        )$tail");
        $pdo->exec("CREATE TABLE IF NOT EXISTS staff_notifications (
            id $id,
            event_id $big NOT NULL,
            trainer_id INT NOT NULL,
            read_at DATETIME NULL,
            mail_id $big NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (event_id, trainer_id),
            FOREIGN KEY (event_id) REFERENCES staff_notification_events(id) ON DELETE RESTRICT,
            FOREIGN KEY (trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
        )$tail");
        // Index detection keeps re-running an interrupted migration safe.
        foreach (['staff_notifications' => ['idx_staff_notification_inbox', 'trainer_id,read_at,id'],
            'staff_notification_events' => ['idx_staff_notification_open', 'resolved_at,id']] as $table => [$name, $columns]) {
            $rows = $pdo->query($mysql ? "SHOW INDEX FROM $table" : "PRAGMA index_list($table)")->fetchAll(PDO::FETCH_ASSOC);
            if (!in_array($name, array_column($rows, $mysql ? 'Key_name' : 'name'), true)) {
                $pdo->exec("CREATE INDEX $name ON $table ($columns)");
            }
        }
    },
    'verify' => static function (PDO $pdo): bool {
        try {
            $pdo->query('SELECT id,event_type,source_id,created_at,resolved_at FROM staff_notification_events WHERE 1=0');
            $pdo->query('SELECT id,event_id,trainer_id,read_at,mail_id,created_at FROM staff_notifications WHERE 1=0');
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
                $column = $pdo->query("SHOW COLUMNS FROM club_event_notifications LIKE 'status'")->fetch(PDO::FETCH_ASSOC);
                if (!str_contains((string)($column['Type'] ?? ''), "'cancelled'")) {
                    return false;
                }
            }
            return true;
        } catch (PDOException) {
            return false;
        }
    },
];
