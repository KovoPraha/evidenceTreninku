<?php
declare(strict_types=1);

$legacySupportTableExists = static function (PDO $pdo, string $table): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1");
    $statement->execute([$table]);
    return (bool)$statement->fetchColumn();
};

$legacySupportColumnExists = static function (PDO $pdo, string $table, string $column): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1');
        $statement->execute([$table, $column]);
        return (bool)$statement->fetchColumn();
    }
    foreach ($pdo->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ((string)$row['name'] === $column) return true;
    }
    return false;
};

return [
    'id' => '20260810003000_legacy_training_support_tables',
    'up' => static function (PDO $pdo) use ($legacySupportTableExists): void {
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';

        if (!$legacySupportTableExists($pdo, 'cviky')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE cviky (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    nazev VARCHAR(150) NOT NULL,
                    popis TEXT NULL,
                    poradi INT NOT NULL DEFAULT 0,
                    aktivni TINYINT(1) NOT NULL DEFAULT 1,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_cviky_nazev (nazev),
                    KEY idx_cviky_active_order (aktivni,poradi,nazev)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE cviky (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    nazev TEXT NOT NULL UNIQUE,
                    popis TEXT NULL,
                    poradi INTEGER NOT NULL DEFAULT 0,
                    aktivni INTEGER NOT NULL DEFAULT 1,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
                )
                SQL);
        }

        if (!$legacySupportTableExists($pdo, 'gs_kategorie')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE gs_kategorie (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    nazev VARCHAR(120) NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_gs_kategorie_nazev (nazev)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE gs_kategorie (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    nazev TEXT NOT NULL UNIQUE,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
                )
                SQL);
        }

        if (!$legacySupportTableExists($pdo, 'gs_linky')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE gs_linky (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    kategorie_id INT UNSIGNED NOT NULL,
                    url TEXT NOT NULL,
                    nazev VARCHAR(255) NOT NULL,
                    popis TEXT NULL,
                    datum DATE NOT NULL,
                    viditelnost ENUM('treneri','verejny','cilene') NOT NULL DEFAULT 'treneri',
                    vlozil_trener_id INT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    KEY idx_gs_linky_category_created (kategorie_id,created_at),
                    KEY idx_gs_linky_trainer (vlozil_trener_id),
                    CONSTRAINT fk_gs_linky_category FOREIGN KEY (kategorie_id) REFERENCES gs_kategorie(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_gs_linky_trainer FOREIGN KEY (vlozil_trener_id) REFERENCES treneri(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE gs_linky (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    kategorie_id INTEGER NOT NULL,
                    url TEXT NOT NULL,
                    nazev TEXT NOT NULL,
                    popis TEXT NULL,
                    datum TEXT NOT NULL,
                    viditelnost TEXT NOT NULL DEFAULT 'treneri',
                    vlozil_trener_id INTEGER NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (kategorie_id) REFERENCES gs_kategorie(id) ON DELETE RESTRICT,
                    FOREIGN KEY (vlozil_trener_id) REFERENCES treneri(id) ON DELETE SET NULL
                )
                SQL);
            if (!$mysql) {
                $pdo->exec('CREATE INDEX idx_gs_linky_category_created ON gs_linky(kategorie_id,created_at)');
                $pdo->exec('CREATE INDEX idx_gs_linky_trainer ON gs_linky(vlozil_trener_id)');
            }
        }

        if (!$legacySupportTableExists($pdo, 'gs_link_targets')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE gs_link_targets (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    link_id INT UNSIGNED NOT NULL,
                    target_type VARCHAR(30) NOT NULL,
                    target_id INT NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_gs_link_target (link_id,target_type,target_id),
                    KEY idx_gs_link_target_lookup (target_type,target_id,link_id),
                    CONSTRAINT fk_gs_link_target_link FOREIGN KEY (link_id) REFERENCES gs_linky(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE gs_link_targets (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    link_id INTEGER NOT NULL,
                    target_type TEXT NOT NULL,
                    target_id INTEGER NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE (link_id,target_type,target_id),
                    FOREIGN KEY (link_id) REFERENCES gs_linky(id) ON DELETE CASCADE
                )
                SQL);
            if (!$mysql) $pdo->exec('CREATE INDEX idx_gs_link_target_lookup ON gs_link_targets(target_type,target_id,link_id)');
        }
    },
    'verify' => static function (PDO $pdo) use ($legacySupportTableExists, $legacySupportColumnExists): bool {
        foreach (['cviky', 'gs_kategorie', 'gs_linky', 'gs_link_targets'] as $table) {
            if (!$legacySupportTableExists($pdo, $table)) return false;
        }
        foreach ([
            ['cviky', 'aktivni'],
            ['gs_kategorie', 'nazev'],
            ['gs_linky', 'viditelnost'],
            ['gs_linky', 'vlozil_trener_id'],
            ['gs_link_targets', 'target_type'],
            ['gs_link_targets', 'target_id'],
        ] as [$table, $column]) {
            if (!$legacySupportColumnExists($pdo, $table, $column)) return false;
        }
        return true;
    },
];
