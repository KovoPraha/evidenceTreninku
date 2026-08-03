<?php
declare(strict_types=1);

$clubTableExists = static function (PDO $pdo, string $table): bool {
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    if ($driver === 'sqlite') {
        $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1");
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    throw new RuntimeException('Unsupported database driver for club events migration.');
};

return [
    'id' => '20260803110000_club_events',
    'up' => static function (PDO $pdo) use ($clubTableExists): void {
        foreach (['shop_products', 'shop_variants', 'treneri'] as $required) {
            if (!$clubTableExists($pdo, $required)) {
                throw new RuntimeException('Required club event table is missing: ' . $required);
            }
        }
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if (!$clubTableExists($pdo, 'club_events')) {
            $pdo->exec($driver === 'mysql' ? <<<'SQL'
                CREATE TABLE club_events (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    code VARCHAR(64) NOT NULL,
                    event_type VARCHAR(24) NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    description_plain TEXT NOT NULL,
                    audience_label VARCHAR(255) NOT NULL,
                    min_age SMALLINT UNSIGNED NULL,
                    max_age SMALLINT UNSIGNED NULL,
                    capacity INT UNSIGNED NOT NULL,
                    pricing_policy VARCHAR(24) NOT NULL,
                    currency CHAR(3) NOT NULL,
                    registration_starts_at DATETIME NULL,
                    registration_ends_at DATETIME NULL,
                    status VARCHAR(24) NOT NULL DEFAULT 'draft',
                    created_by_trainer_id INT NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_club_event_code (code),
                    INDEX idx_club_event_status (status, event_type),
                    CONSTRAINT fk_club_event_creator FOREIGN KEY (created_by_trainer_id)
                        REFERENCES treneri (id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE club_events (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    code TEXT NOT NULL UNIQUE,
                    event_type TEXT NOT NULL,
                    name TEXT NOT NULL,
                    description_plain TEXT NOT NULL,
                    audience_label TEXT NOT NULL,
                    min_age INTEGER NULL,
                    max_age INTEGER NULL,
                    capacity INTEGER NOT NULL,
                    pricing_policy TEXT NOT NULL,
                    currency TEXT NOT NULL,
                    registration_starts_at TEXT NULL,
                    registration_ends_at TEXT NULL,
                    status TEXT NOT NULL DEFAULT 'draft',
                    created_by_trainer_id INTEGER NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (created_by_trainer_id) REFERENCES treneri (id) ON DELETE RESTRICT
                )
                SQL);
        }
        if (!$clubTableExists($pdo, 'club_event_sessions')) {
            $pdo->exec($driver === 'mysql' ? <<<'SQL'
                CREATE TABLE club_event_sessions (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    event_id BIGINT UNSIGNED NOT NULL,
                    starts_at DATETIME NOT NULL,
                    ends_at DATETIME NOT NULL,
                    location VARCHAR(255) NOT NULL,
                    capacity_override INT UNSIGNED NULL,
                    status VARCHAR(24) NOT NULL DEFAULT 'scheduled',
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_club_session_event_time (event_id, starts_at),
                    CONSTRAINT fk_club_session_event FOREIGN KEY (event_id)
                        REFERENCES club_events (id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE club_event_sessions (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    event_id INTEGER NOT NULL,
                    starts_at TEXT NOT NULL,
                    ends_at TEXT NOT NULL,
                    location TEXT NOT NULL,
                    capacity_override INTEGER NULL,
                    status TEXT NOT NULL DEFAULT 'scheduled',
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (event_id) REFERENCES club_events (id) ON DELETE RESTRICT
                )
                SQL);
        }
        if (!$clubTableExists($pdo, 'shop_product_event_links')) {
            $pdo->exec($driver === 'mysql' ? <<<'SQL'
                CREATE TABLE shop_product_event_links (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    product_id BIGINT UNSIGNED NOT NULL,
                    event_id BIGINT UNSIGNED NOT NULL,
                    actor_trainer_id INT NOT NULL,
                    decision_note TEXT NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_shop_event_product (product_id),
                    INDEX idx_shop_event_event (event_id),
                    CONSTRAINT fk_shop_event_product FOREIGN KEY (product_id)
                        REFERENCES shop_products (id) ON DELETE RESTRICT,
                    CONSTRAINT fk_shop_event_event FOREIGN KEY (event_id)
                        REFERENCES club_events (id) ON DELETE RESTRICT,
                    CONSTRAINT fk_shop_event_actor FOREIGN KEY (actor_trainer_id)
                        REFERENCES treneri (id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE shop_product_event_links (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    product_id INTEGER NOT NULL UNIQUE,
                    event_id INTEGER NOT NULL,
                    actor_trainer_id INTEGER NOT NULL,
                    decision_note TEXT NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (product_id) REFERENCES shop_products (id) ON DELETE RESTRICT,
                    FOREIGN KEY (event_id) REFERENCES club_events (id) ON DELETE RESTRICT,
                    FOREIGN KEY (actor_trainer_id) REFERENCES treneri (id) ON DELETE RESTRICT
                )
                SQL);
        }
        if (!$clubTableExists($pdo, 'club_event_admin_events')) {
            $pdo->exec($driver === 'mysql' ? <<<'SQL'
                CREATE TABLE club_event_admin_events (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    event_id BIGINT UNSIGNED NOT NULL,
                    actor_trainer_id INT NOT NULL,
                    action VARCHAR(32) NOT NULL,
                    subject_type VARCHAR(24) NOT NULL,
                    subject_id BIGINT UNSIGNED NULL,
                    note TEXT NOT NULL,
                    payload_json LONGTEXT NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_club_admin_event (event_id, created_at),
                    CONSTRAINT fk_club_admin_event_event FOREIGN KEY (event_id)
                        REFERENCES club_events (id) ON DELETE RESTRICT,
                    CONSTRAINT fk_club_admin_event_actor FOREIGN KEY (actor_trainer_id)
                        REFERENCES treneri (id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE club_event_admin_events (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    event_id INTEGER NOT NULL,
                    actor_trainer_id INTEGER NOT NULL,
                    action TEXT NOT NULL,
                    subject_type TEXT NOT NULL,
                    subject_id INTEGER NULL,
                    note TEXT NOT NULL,
                    payload_json TEXT NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (event_id) REFERENCES club_events (id) ON DELETE RESTRICT,
                    FOREIGN KEY (actor_trainer_id) REFERENCES treneri (id) ON DELETE RESTRICT
                )
                SQL);
        }
    },
    'verify' => static function (PDO $pdo) use ($clubTableExists): bool {
        foreach (['club_events', 'club_event_sessions', 'shop_product_event_links', 'club_event_admin_events'] as $table) {
            if (!$clubTableExists($pdo, $table)) {
                return false;
            }
        }
        return true;
    },
];
