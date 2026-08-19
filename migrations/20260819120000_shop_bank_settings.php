<?php
declare(strict_types=1);

$shopBankSettingsTableExists = static function (PDO $pdo, string $table): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES '
            . 'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1'
        );
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1");
    $statement->execute([$table]);
    return (bool)$statement->fetchColumn();
};

$shopBankSettingsColumnExists = static function (PDO $pdo, string $table, string $column): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1'
        );
        $statement->execute([$table, $column]);
        return (bool)$statement->fetchColumn();
    }
    foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ((string)$row['name'] === $column) return true;
    }
    return false;
};

return [
    'id' => '20260819120000_shop_bank_settings',
    'up' => static function (PDO $pdo) use ($shopBankSettingsTableExists): void {
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';

        // Jeden řádek pravdy: primární klíč je pevná jednička, takže druhý
        // konkurenční záznam nemůže vzniknout ani při souběžném uložení.
        if (!$shopBankSettingsTableExists($pdo, 'shop_bank_settings')) {
            $pdo->exec($mysql ? <<<'SQL'
            CREATE TABLE shop_bank_settings (
                id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
                iban VARCHAR(34) NOT NULL,
                bic VARCHAR(11) NOT NULL DEFAULT '',
                account_label VARCHAR(255) NOT NULL,
                due_days SMALLINT UNSIGNED NOT NULL,
                updated_by_trainer_id INT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_shop_bank_settings_trainer
                    FOREIGN KEY (updated_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL : <<<'SQL'
            CREATE TABLE shop_bank_settings (
                id INTEGER NOT NULL PRIMARY KEY,
                iban TEXT NOT NULL,
                bic TEXT NOT NULL DEFAULT '',
                account_label TEXT NOT NULL,
                due_days INTEGER NOT NULL,
                updated_by_trainer_id INTEGER NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(updated_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
            )
            SQL);
        }

        if (!$shopBankSettingsTableExists($pdo, 'shop_bank_settings_events')) {
            $pdo->exec($mysql ? <<<'SQL'
            CREATE TABLE shop_bank_settings_events (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                actor_type VARCHAR(16) NOT NULL,
                actor_id INT NOT NULL,
                action VARCHAR(32) NOT NULL,
                before_json LONGTEXT NULL,
                after_json LONGTEXT NOT NULL,
                reason VARCHAR(1000) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_shop_bank_settings_events_created (created_at, id),
                CONSTRAINT fk_shop_bank_settings_events_trainer
                    FOREIGN KEY (actor_id) REFERENCES treneri(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL : <<<'SQL'
            CREATE TABLE shop_bank_settings_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                actor_type TEXT NOT NULL,
                actor_id INTEGER NOT NULL,
                action TEXT NOT NULL,
                before_json TEXT NULL,
                after_json TEXT NOT NULL,
                reason TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(actor_id) REFERENCES treneri(id) ON DELETE RESTRICT
            )
            SQL);
        }

        // Konfigurovatelné oprávnění stejným vzorem jako sync_evidence. Výchozí
        // je nejpřísnější role; vlastník ji smí snížit v nastaveni_opravneni.php
        // bez zásahu do kódu. Chybí-li klíč v relaci, canAccess() spadne zpět na
        // 'hlavni', takže obrazovka nikdy není otevřená trenérovi.
        if ($mysql && $shopBankSettingsTableExists($pdo, 'opravneni')) {
            $pdo->prepare(
                'INSERT IGNORE INTO opravneni (klic, nazev, popis, min_role, skupina, poradi) '
                . 'VALUES (?,?,?,?,?,?)'
            )->execute([
                'eshop_bank_settings',
                'Bankovní účet e-shopu',
                'Změna účtu, na který chodí platby klubu, a splatnosti objednávek',
                'admin',
                'E-shop',
                80,
            ]);
        }
    },
    'verify' => static function (PDO $pdo) use ($shopBankSettingsTableExists, $shopBankSettingsColumnExists): bool {
        if (!$shopBankSettingsTableExists($pdo, 'shop_bank_settings')) return false;
        foreach (['id','iban','bic','account_label','due_days','updated_by_trainer_id','created_at','updated_at'] as $column) {
            if (!$shopBankSettingsColumnExists($pdo, 'shop_bank_settings', $column)) return false;
        }
        if (!$shopBankSettingsTableExists($pdo, 'shop_bank_settings_events')) return false;
        foreach (['actor_type','actor_id','action','before_json','after_json','reason','created_at'] as $column) {
            if (!$shopBankSettingsColumnExists($pdo, 'shop_bank_settings_events', $column)) return false;
        }
        return true;
    },
];
