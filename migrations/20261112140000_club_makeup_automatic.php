<?php

declare(strict_types=1);

$makeupAutoTableExists = static function (PDO $pdo, string $table): bool {
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $query = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $query->execute([$table]);
        return (bool) $query->fetchColumn();
    }
    $query = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
    $query->execute([$table]);
    return (bool) $query->fetchColumn();
};

return [
    'id' => '20261112140000_club_makeup_automatic',
    'up' => static function (PDO $pdo) use ($makeupAutoTableExists): void {
        $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if (!$makeupAutoTableExists($pdo, 'club_makeup_auto_settings')) {
            $pdo->exec($mysql ? <<<'SQL'
CREATE TABLE club_makeup_auto_settings (
 id TINYINT UNSIGNED PRIMARY KEY,enabled TINYINT(1) NOT NULL DEFAULT 0,
 excuse_hours SMALLINT UNSIGNED NOT NULL DEFAULT 12,credit_valid_days SMALLINT UNSIGNED NOT NULL DEFAULT 365,
 max_credits SMALLINT UNSIGNED NOT NULL DEFAULT 0,cancel_hours SMALLINT UNSIGNED NOT NULL DEFAULT 12,
 updated_by_trainer_id INT NULL,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(updated_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL : <<<'SQL'
CREATE TABLE club_makeup_auto_settings (
 id INTEGER PRIMARY KEY,enabled INTEGER NOT NULL DEFAULT 0,excuse_hours INTEGER NOT NULL DEFAULT 12,
 credit_valid_days INTEGER NOT NULL DEFAULT 365,max_credits INTEGER NOT NULL DEFAULT 0,
 cancel_hours INTEGER NOT NULL DEFAULT 12,updated_by_trainer_id INTEGER NULL,
 updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(updated_by_trainer_id) REFERENCES treneri(id)
)
SQL);
        }
        $pdo->exec($mysql
            ? 'INSERT IGNORE INTO club_makeup_auto_settings(id) VALUES(1)'
            : 'INSERT OR IGNORE INTO club_makeup_auto_settings(id) VALUES(1)');
        if (!$makeupAutoTableExists($pdo, 'club_makeup_offer_auto')) {
            $pdo->exec($mysql ? <<<'SQL'
CREATE TABLE club_makeup_offer_auto (
 offer_id BIGINT UNSIGNED PRIMARY KEY,enabled TINYINT(1) NOT NULL DEFAULT 1,
 excuse_hours SMALLINT UNSIGNED NULL,credit_valid_days SMALLINT UNSIGNED NULL,
 max_credits SMALLINT UNSIGNED NULL,cancel_hours SMALLINT UNSIGNED NULL,
 updated_by_trainer_id INT NOT NULL,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(offer_id) REFERENCES club_program_offers(id) ON DELETE RESTRICT,
 FOREIGN KEY(updated_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL : <<<'SQL'
CREATE TABLE club_makeup_offer_auto (
 offer_id INTEGER PRIMARY KEY,enabled INTEGER NOT NULL DEFAULT 1,excuse_hours INTEGER NULL,
 credit_valid_days INTEGER NULL,max_credits INTEGER NULL,cancel_hours INTEGER NULL,
 updated_by_trainer_id INTEGER NOT NULL,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(offer_id) REFERENCES club_program_offers(id),FOREIGN KEY(updated_by_trainer_id) REFERENCES treneri(id)
)
SQL);
        }
        if (!$makeupAutoTableExists($pdo, 'club_makeup_route_exceptions')) {
            $pdo->exec($mysql ? <<<'SQL'
CREATE TABLE club_makeup_route_exceptions (
 source_offer_id BIGINT UNSIGNED NOT NULL,target_offer_id BIGINT UNSIGNED NOT NULL,
 allowed TINYINT(1) NOT NULL,updated_by_trainer_id INT NOT NULL,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(source_offer_id,target_offer_id),
 FOREIGN KEY(source_offer_id) REFERENCES club_program_offers(id) ON DELETE RESTRICT,
 FOREIGN KEY(target_offer_id) REFERENCES club_program_offers(id) ON DELETE RESTRICT,
 FOREIGN KEY(updated_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL : <<<'SQL'
CREATE TABLE club_makeup_route_exceptions (
 source_offer_id INTEGER NOT NULL,target_offer_id INTEGER NOT NULL,allowed INTEGER NOT NULL,
 updated_by_trainer_id INTEGER NOT NULL,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(source_offer_id,target_offer_id),
 FOREIGN KEY(source_offer_id) REFERENCES club_program_offers(id),
 FOREIGN KEY(target_offer_id) REFERENCES club_program_offers(id),
 FOREIGN KEY(updated_by_trainer_id) REFERENCES treneri(id)
)
SQL);
        }
        if (!$makeupAutoTableExists($pdo, 'club_makeup_plan_exclusions')) {
            $pdo->exec($mysql ? <<<'SQL'
CREATE TABLE club_makeup_plan_exclusions (
 plan_id INT PRIMARY KEY,updated_by_trainer_id INT NOT NULL,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(plan_id) REFERENCES planovane_treninky(id) ON DELETE CASCADE,
 FOREIGN KEY(updated_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL : <<<'SQL'
CREATE TABLE club_makeup_plan_exclusions (
 plan_id INTEGER PRIMARY KEY,updated_by_trainer_id INTEGER NOT NULL,
 updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(plan_id) REFERENCES planovane_treninky(id) ON DELETE CASCADE,
 FOREIGN KEY(updated_by_trainer_id) REFERENCES treneri(id)
)
SQL);
        }
        $columns = $mysql ? $pdo->query('SHOW COLUMNS FROM club_makeup_slots')->fetchAll(PDO::FETCH_ASSOC)
            : $pdo->query('PRAGMA table_info(club_makeup_slots)')->fetchAll(PDO::FETCH_ASSOC);
        if (!isset(array_column($columns, null, $mysql ? 'Field' : 'name')['origin'])) {
            $pdo->exec($mysql
                ? "ALTER TABLE club_makeup_slots ADD COLUMN origin VARCHAR(16) NOT NULL DEFAULT 'manual'"
                : "ALTER TABLE club_makeup_slots ADD COLUMN origin TEXT NOT NULL DEFAULT 'manual'");
        }
        $reservationColumns = $mysql
            ? $pdo->query('SHOW COLUMNS FROM club_makeup_reservations')->fetchAll(PDO::FETCH_ASSOC)
            : $pdo->query('PRAGMA table_info(club_makeup_reservations)')->fetchAll(PDO::FETCH_ASSOC);
        if (!isset(array_column($reservationColumns, null, $mysql ? 'Field' : 'name')['cancel_hours_snapshot'])) {
            $pdo->exec($mysql
                ? 'ALTER TABLE club_makeup_reservations ADD COLUMN cancel_hours_snapshot SMALLINT UNSIGNED NULL'
                : 'ALTER TABLE club_makeup_reservations ADD COLUMN cancel_hours_snapshot INTEGER NULL');
        }
    },
    'verify' => static function (PDO $pdo) use ($makeupAutoTableExists): bool {
        foreach (['club_makeup_auto_settings', 'club_makeup_offer_auto',
            'club_makeup_route_exceptions', 'club_makeup_plan_exclusions'] as $table) {
            if (!$makeupAutoTableExists($pdo, $table)) {
                return false;
            }
        }
        $columns = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? $pdo->query('SHOW COLUMNS FROM club_makeup_slots')->fetchAll(PDO::FETCH_ASSOC)
            : $pdo->query('PRAGMA table_info(club_makeup_slots)')->fetchAll(PDO::FETCH_ASSOC);
        $key = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? 'Field' : 'name';
        $reservationColumns = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? $pdo->query('SHOW COLUMNS FROM club_makeup_reservations')->fetchAll(PDO::FETCH_ASSOC)
            : $pdo->query('PRAGMA table_info(club_makeup_reservations)')->fetchAll(PDO::FETCH_ASSOC);
        return isset(array_column($columns, null, $key)['origin'])
            && isset(array_column($reservationColumns, null, $key)['cancel_hours_snapshot'])
            && $pdo->query('SELECT id FROM club_makeup_auto_settings WHERE id=1')->fetchColumn() !== false;
    },
];
