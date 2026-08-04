<?php
declare(strict_types=1);

$familyCalendarTableExists = static function (PDO $pdo, string $table): bool {
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    if ($driver === 'sqlite') {
        $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    throw new RuntimeException('Unsupported database driver for family calendar migration.');
};

return [
    'id' => '20260805010000_family_calendar_feeds',
    'up' => static function (PDO $pdo) use ($familyCalendarTableExists): void {
        if (!$familyCalendarTableExists($pdo, 'verejni_uzivatele')) {
            throw new RuntimeException('Public accounts must exist before family calendar feeds.');
        }
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if (!$familyCalendarTableExists($pdo, 'family_calendar_feeds')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE family_calendar_feeds (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    account_id INT NOT NULL,
                    token_hash CHAR(64) NOT NULL,
                    token_hint CHAR(8) NOT NULL,
                    active TINYINT(1) NOT NULL DEFAULT 1,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    rotated_at DATETIME NOT NULL,
                    revoked_at DATETIME NULL,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_family_calendar_account(account_id),
                    UNIQUE KEY uq_family_calendar_token(token_hash),
                    CONSTRAINT fk_family_calendar_account FOREIGN KEY(account_id)
                        REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE family_calendar_feeds (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    account_id INTEGER NOT NULL UNIQUE,
                    token_hash TEXT NOT NULL UNIQUE,
                    token_hint TEXT NOT NULL,
                    active INTEGER NOT NULL DEFAULT 1,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    rotated_at TEXT NOT NULL,
                    revoked_at TEXT NULL,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY(account_id) REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT
                )
                SQL);
        }
        if (!$familyCalendarTableExists($pdo, 'family_calendar_feed_events')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE family_calendar_feed_events (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    feed_id BIGINT UNSIGNED NOT NULL,
                    actor_account_id INT NOT NULL,
                    action VARCHAR(24) NOT NULL,
                    token_hint_snapshot CHAR(8) NOT NULL,
                    note VARCHAR(255) NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_family_calendar_event(feed_id,id),
                    CONSTRAINT fk_family_calendar_event_feed FOREIGN KEY(feed_id)
                        REFERENCES family_calendar_feeds(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_family_calendar_event_actor FOREIGN KEY(actor_account_id)
                        REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE family_calendar_feed_events (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    feed_id INTEGER NOT NULL,
                    actor_account_id INTEGER NOT NULL,
                    action TEXT NOT NULL,
                    token_hint_snapshot TEXT NOT NULL,
                    note TEXT NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY(feed_id) REFERENCES family_calendar_feeds(id) ON DELETE RESTRICT,
                    FOREIGN KEY(actor_account_id) REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT
                )
                SQL);
            if (!$mysql) $pdo->exec('CREATE INDEX idx_family_calendar_event ON family_calendar_feed_events(feed_id,id)');
        }
    },
    'verify' => static fn (PDO $pdo): bool => $familyCalendarTableExists($pdo, 'family_calendar_feeds')
        && $familyCalendarTableExists($pdo, 'family_calendar_feed_events'),
];
