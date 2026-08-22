<?php
declare(strict_types=1);

$venueEventTableExists = static function (PDO $pdo): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        return (bool)$pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='venue_operation_events' LIMIT 1")->fetchColumn();
    }
    return (bool)$pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='venue_operation_events' LIMIT 1")->fetchColumn();
};
$venueEventIndexExists = static function (PDO $pdo): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        return (bool)$pdo->query("SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='venue_operation_events' AND INDEX_NAME='idx_venue_operation_target' LIMIT 1")->fetchColumn();
    }
    return (bool)$pdo->query("SELECT 1 FROM sqlite_master WHERE type='index' AND name='idx_venue_operation_target' LIMIT 1")->fetchColumn();
};

return [
    'id' => '20260822150000_venue_operation_events',
    'up' => static function (PDO $pdo) use ($venueEventTableExists, $venueEventIndexExists): void {
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if (!$venueEventTableExists($pdo)) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE venue_operation_events (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    target_type VARCHAR(24) NOT NULL,
                    target_id BIGINT UNSIGNED NOT NULL,
                    actor_trainer_id INT NOT NULL,
                    action VARCHAR(32) NOT NULL,
                    reason VARCHAR(1000) NOT NULL,
                    payload_json LONGTEXT NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_venue_operation_target (target_type,target_id,id),
                    CONSTRAINT fk_venue_operation_actor FOREIGN KEY (actor_trainer_id)
                        REFERENCES treneri(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE venue_operation_events (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    target_type TEXT NOT NULL,
                    target_id INTEGER NOT NULL,
                    actor_trainer_id INTEGER NOT NULL,
                    action TEXT NOT NULL,
                    reason TEXT NOT NULL,
                    payload_json TEXT NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (actor_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
                )
                SQL);
        }
        if (!$venueEventIndexExists($pdo)) {
            $pdo->exec('CREATE INDEX idx_venue_operation_target ON venue_operation_events(target_type,target_id,id)');
        }
    },
    'verify' => static fn(PDO $pdo): bool => $venueEventTableExists($pdo) && $venueEventIndexExists($pdo),
];
