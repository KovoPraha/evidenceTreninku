<?php
declare(strict_types=1);

$financeTableExists = static function (PDO $pdo, string $table): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
    $statement->execute([$table]);
    return (bool)$statement->fetchColumn();
};
$financeColumnExists = static function (PDO $pdo, string $table, string $column): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $statement->execute([$table, $column]);
        return (bool)$statement->fetchColumn();
    }
    foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ($row['name'] === $column) return true;
    }
    return false;
};

return [
    'id' => '20261015130000_club_financial_balance',
    'up' => static function (PDO $pdo) use ($financeTableExists, $financeColumnExists): void {
        foreach (['club_member_charges', 'member_fee_plans', 'shop_orders', 'payments', 'sportovci', 'treneri'] as $table) {
            if (!$financeTableExists($pdo, $table)) throw new RuntimeException('Chybí finanční tabulka: ' . $table);
        }
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if (!$financeTableExists($pdo, 'club_finance_settings')) {
            $pdo->exec($mysql ? <<<'SQL'
CREATE TABLE club_finance_settings (
    id TINYINT UNSIGNED PRIMARY KEY,
    enforcement_enabled TINYINT(1) NOT NULL DEFAULT 0,
    grace_days SMALLINT UNSIGNED NOT NULL DEFAULT 14,
    registration_limit_minor BIGINT UNSIGNED NOT NULL DEFAULT 200000,
    training_notice_minor BIGINT UNSIGNED NOT NULL DEFAULT 500000,
    updated_by_trainer_id INT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_club_finance_settings_actor FOREIGN KEY (updated_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL : <<<'SQL'
CREATE TABLE club_finance_settings (
    id INTEGER PRIMARY KEY,
    enforcement_enabled INTEGER NOT NULL DEFAULT 0,
    grace_days INTEGER NOT NULL DEFAULT 14,
    registration_limit_minor INTEGER NOT NULL DEFAULT 200000,
    training_notice_minor INTEGER NOT NULL DEFAULT 500000,
    updated_by_trainer_id INTEGER NULL,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL);
        }
        $pdo->exec($mysql
            ? 'INSERT IGNORE INTO club_finance_settings(id,enforcement_enabled,grace_days,registration_limit_minor,training_notice_minor) VALUES(1,0,14,200000,500000)'
            : 'INSERT OR IGNORE INTO club_finance_settings(id,enforcement_enabled,grace_days,registration_limit_minor,training_notice_minor) VALUES(1,0,14,200000,500000)');
        if (!$financeTableExists($pdo, 'club_finance_setting_events')) {
            $pdo->exec($mysql ? <<<'SQL'
CREATE TABLE club_finance_setting_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_trainer_id INT NOT NULL,
    before_json LONGTEXT NOT NULL,
    after_json LONGTEXT NOT NULL,
    reason VARCHAR(1000) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_club_finance_setting_event_actor FOREIGN KEY (actor_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL : <<<'SQL'
CREATE TABLE club_finance_setting_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    actor_trainer_id INTEGER NOT NULL,
    before_json TEXT NOT NULL,
    after_json TEXT NOT NULL,
    reason TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL);
        }
        if (!$financeTableExists($pdo, 'club_finance_receipts')) {
            $pdo->exec($mysql ? <<<'SQL'
CREATE TABLE club_finance_receipts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sportovec_id INT NOT NULL,
    plan_id BIGINT UNSIGNED NULL,
    period_start CHAR(7) NULL,
    kind VARCHAR(24) NOT NULL,
    reference VARCHAR(100) NOT NULL,
    amount_minor BIGINT UNSIGNED NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'CZK',
    paid_on DATE NOT NULL,
    confirmed_by_trainer_id INT NOT NULL,
    reason VARCHAR(1000) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_club_finance_receipt_reference(reference),
    KEY idx_club_finance_receipt_athlete(sportovec_id,id),
    CONSTRAINT fk_club_finance_receipt_athlete FOREIGN KEY (sportovec_id) REFERENCES sportovci(id) ON DELETE RESTRICT,
    CONSTRAINT fk_club_finance_receipt_plan FOREIGN KEY (plan_id) REFERENCES member_fee_plans(id) ON DELETE RESTRICT,
    CONSTRAINT fk_club_finance_receipt_actor FOREIGN KEY (confirmed_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL : <<<'SQL'
CREATE TABLE club_finance_receipts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sportovec_id INTEGER NOT NULL,
    plan_id INTEGER NULL,
    period_start TEXT NULL,
    kind TEXT NOT NULL,
    reference TEXT NOT NULL UNIQUE,
    amount_minor INTEGER NOT NULL,
    currency TEXT NOT NULL DEFAULT 'CZK',
    paid_on TEXT NOT NULL,
    confirmed_by_trainer_id INTEGER NOT NULL,
    reason TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL);
            if (!$mysql) $pdo->exec('CREATE INDEX idx_club_finance_receipt_athlete ON club_finance_receipts(sportovec_id,id)');
        }
        if (!$financeTableExists($pdo, 'club_finance_allocations')) {
            $pdo->exec($mysql ? <<<'SQL'
CREATE TABLE club_finance_allocations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    receipt_id BIGINT UNSIGNED NOT NULL,
    charge_id BIGINT UNSIGNED NULL,
    order_id BIGINT UNSIGNED NULL,
    amount_minor BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_club_finance_allocation(receipt_id,charge_id),
    UNIQUE KEY uq_club_finance_allocation_order(receipt_id,order_id),
    KEY idx_club_finance_allocation_charge(charge_id),
    KEY idx_club_finance_allocation_order(order_id),
    CONSTRAINT fk_club_finance_allocation_receipt FOREIGN KEY (receipt_id) REFERENCES club_finance_receipts(id) ON DELETE RESTRICT,
    CONSTRAINT fk_club_finance_allocation_charge FOREIGN KEY (charge_id) REFERENCES club_member_charges(id) ON DELETE RESTRICT,
    CONSTRAINT fk_club_finance_allocation_order FOREIGN KEY (order_id) REFERENCES shop_orders(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL : <<<'SQL'
CREATE TABLE club_finance_allocations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    receipt_id INTEGER NOT NULL,
    charge_id INTEGER NULL,
    order_id INTEGER NULL,
    amount_minor INTEGER NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(receipt_id,charge_id),
    UNIQUE(receipt_id,order_id),
    FOREIGN KEY (receipt_id) REFERENCES club_finance_receipts(id) ON DELETE RESTRICT,
    FOREIGN KEY (charge_id) REFERENCES club_member_charges(id) ON DELETE RESTRICT,
    FOREIGN KEY (order_id) REFERENCES shop_orders(id) ON DELETE RESTRICT
)
SQL);
            if (!$mysql) $pdo->exec('CREATE INDEX idx_club_finance_allocation_charge ON club_finance_allocations(charge_id)');
            if (!$mysql) $pdo->exec('CREATE INDEX idx_club_finance_allocation_order ON club_finance_allocations(order_id)');
        }
        if (!$financeColumnExists($pdo, 'shop_orders', 'finance_sportovec_id')) {
            $pdo->exec('ALTER TABLE shop_orders ADD COLUMN finance_sportovec_id ' . ($mysql ? 'INT' : 'INTEGER') . ' NULL');
        }
        if ($mysql) {
            $index = $pdo->query("SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='shop_orders' AND INDEX_NAME='idx_shop_order_finance_athlete' LIMIT 1")->fetchColumn();
            if (!$index) $pdo->exec('ALTER TABLE shop_orders ADD KEY idx_shop_order_finance_athlete(finance_sportovec_id,payment_status,id)');
            $fk = $pdo->query("SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='shop_orders' AND CONSTRAINT_NAME='fk_shop_order_finance_athlete' LIMIT 1")->fetchColumn();
            if (!$fk) $pdo->exec('ALTER TABLE shop_orders ADD CONSTRAINT fk_shop_order_finance_athlete FOREIGN KEY (finance_sportovec_id) REFERENCES sportovci(id) ON DELETE RESTRICT');
        } else {
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_shop_order_finance_athlete ON shop_orders(finance_sportovec_id,payment_status,id)');
        }
    },
    'verify' => static function (PDO $pdo) use ($financeTableExists, $financeColumnExists): bool {
        foreach (['club_finance_settings', 'club_finance_setting_events', 'club_finance_receipts', 'club_finance_allocations'] as $table) {
            if (!$financeTableExists($pdo, $table)) return false;
        }
        return $financeColumnExists($pdo, 'shop_orders', 'finance_sportovec_id');
    },
];
