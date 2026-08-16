<?php
declare(strict_types=1);

$columnInfo = static function (PDO $pdo, string $column): ?array {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare(
            'SELECT IS_NULLABLE AS nullable FROM information_schema.COLUMNS '
            . "WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='club_event_notifications' AND COLUMN_NAME=? LIMIT 1"
        );
        $statement->execute([$column]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
    foreach ($pdo->query('PRAGMA table_info(club_event_notifications)')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ((string)$row['name'] === $column) {
            return ['nullable' => (int)$row['notnull'] === 0 ? 'YES' : 'NO'];
        }
    }
    return null;
};

$indexExists = static function (PDO $pdo, string $index): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
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
    'id' => '20260816170000_shop_payment_received_notification',
    'up' => static function (PDO $pdo) use ($columnInfo, $indexExists): void {
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if ($mysql) {
            if ($columnInfo($pdo, 'order_id') === null) {
                $pdo->exec('ALTER TABLE club_event_notifications ADD COLUMN order_id BIGINT UNSIGNED NULL AFTER registration_event_id');
            }
            if (($columnInfo($pdo, 'registration_id')['nullable'] ?? '') !== 'YES') {
                $pdo->exec('ALTER TABLE club_event_notifications MODIFY registration_id BIGINT UNSIGNED NULL');
            }
            if (($columnInfo($pdo, 'registration_event_id')['nullable'] ?? '') !== 'YES') {
                $pdo->exec('ALTER TABLE club_event_notifications MODIFY registration_event_id BIGINT UNSIGNED NULL');
            }
            if (!$indexExists($pdo, 'uq_shop_payment_notification')) {
                $pdo->exec('ALTER TABLE club_event_notifications ADD UNIQUE KEY uq_shop_payment_notification (order_id,notification_type)');
            }
            if (!$indexExists($pdo, 'idx_club_notification_order')) {
                $pdo->exec('ALTER TABLE club_event_notifications ADD KEY idx_club_notification_order (order_id,id)');
            }
            $constraint = $pdo->query(
                "SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() "
                . "AND TABLE_NAME='club_event_notifications' AND CONSTRAINT_NAME='fk_club_notification_order' LIMIT 1"
            )->fetchColumn();
            if (!$constraint) {
                $pdo->exec('ALTER TABLE club_event_notifications ADD CONSTRAINT fk_club_notification_order '
                    . 'FOREIGN KEY (order_id) REFERENCES shop_orders(id) ON DELETE RESTRICT');
            }
            return;
        }
        $ready = $columnInfo($pdo, 'order_id') !== null
            && ($columnInfo($pdo, 'registration_id')['nullable'] ?? '') === 'YES'
            && ($columnInfo($pdo, 'registration_event_id')['nullable'] ?? '') === 'YES';
        if (!$ready) {
            if ($pdo->inTransaction()) {
                throw new RuntimeException('SQLite změna společného outboxu vyžaduje stav mimo transakci.');
            }
            $pdo->exec('PRAGMA foreign_keys=OFF');
            try {
                $pdo->exec(<<<'SQL'
                    CREATE TABLE club_event_notifications_next (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        registration_id INTEGER NULL,
                        registration_event_id INTEGER NULL,
                        order_id INTEGER NULL,
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
                        FOREIGN KEY (registration_event_id) REFERENCES club_event_registration_events(id) ON DELETE RESTRICT,
                        FOREIGN KEY (order_id) REFERENCES shop_orders(id) ON DELETE RESTRICT
                    )
                    SQL);
                $pdo->exec(<<<'SQL'
                    INSERT INTO club_event_notifications_next
                    (id,registration_id,registration_event_id,order_id,notification_type,recipient_email,
                     recipient_name,subject_plain,body_plain,status,attempts,available_at,claimed_at,
                     claim_token,sent_at,last_error,created_at,updated_at)
                    SELECT id,registration_id,registration_event_id,NULL,notification_type,recipient_email,
                     recipient_name,subject_plain,body_plain,status,attempts,available_at,claimed_at,
                     claim_token,sent_at,last_error,created_at,updated_at FROM club_event_notifications
                    SQL);
                $pdo->exec('DROP TABLE club_event_notifications');
                $pdo->exec('ALTER TABLE club_event_notifications_next RENAME TO club_event_notifications');
            } finally {
                $pdo->exec('PRAGMA foreign_keys=ON');
            }
        }
        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS uq_club_event_notification ON club_event_notifications(registration_event_id,notification_type)');
        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS uq_shop_payment_notification ON club_event_notifications(order_id,notification_type)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_club_event_notification_queue ON club_event_notifications(status,available_at,id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_club_notification_order ON club_event_notifications(order_id,id)');
    },
    'verify' => static function (PDO $pdo) use ($columnInfo, $indexExists): bool {
        return $columnInfo($pdo, 'order_id') !== null
            && ($columnInfo($pdo, 'registration_id')['nullable'] ?? '') === 'YES'
            && ($columnInfo($pdo, 'registration_event_id')['nullable'] ?? '') === 'YES'
            && $indexExists($pdo, 'uq_shop_payment_notification')
            && $indexExists($pdo, 'idx_club_notification_order');
    },
];
