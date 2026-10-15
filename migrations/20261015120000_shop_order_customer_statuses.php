<?php
declare(strict_types=1);

$columnExists = static function (PDO $pdo, string $table, string $column): bool {
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
$tableExists = static function (PDO $pdo, string $table): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
    $statement->execute([$table]);
    return (bool)$statement->fetchColumn();
};

return [
    'id' => '20261015120000_shop_order_customer_statuses',
    'up' => static function (PDO $pdo) use ($columnExists, $tableExists): void {
        foreach (['shop_orders', 'shop_products', 'shop_order_events', 'club_event_notifications'] as $required) {
            if (!$tableExists($pdo, $required)) throw new RuntimeException('Chybí tabulka e-shopu: ' . $required);
        }
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if (!$tableExists($pdo, 'shop_order_status_definitions')) {
            $pdo->exec($mysql ? <<<'SQL'
CREATE TABLE shop_order_status_definitions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(80) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    email_enabled TINYINT(1) NOT NULL DEFAULT 0,
    email_subject VARCHAR(180) NULL,
    email_body TEXT NULL,
    created_by_trainer_id INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_shop_order_status_label (label),
    CONSTRAINT fk_shop_order_status_creator FOREIGN KEY (created_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL : <<<'SQL'
CREATE TABLE shop_order_status_definitions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    label TEXT NOT NULL UNIQUE,
    active INTEGER NOT NULL DEFAULT 1,
    email_enabled INTEGER NOT NULL DEFAULT 0,
    email_subject TEXT NULL,
    email_body TEXT NULL,
    created_by_trainer_id INTEGER NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL);
        }
        if (!$tableExists($pdo, 'shop_order_status_definition_events')) {
            $pdo->exec($mysql ? <<<'SQL'
CREATE TABLE shop_order_status_definition_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    definition_id BIGINT UNSIGNED NOT NULL,
    actor_trainer_id INT NOT NULL,
    action VARCHAR(24) NOT NULL,
    before_json LONGTEXT NULL,
    after_json LONGTEXT NOT NULL,
    reason VARCHAR(1000) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_shop_status_event_definition (definition_id,id),
    CONSTRAINT fk_shop_status_event_definition FOREIGN KEY (definition_id) REFERENCES shop_order_status_definitions(id) ON DELETE RESTRICT,
    CONSTRAINT fk_shop_status_event_actor FOREIGN KEY (actor_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL : <<<'SQL'
CREATE TABLE shop_order_status_definition_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    definition_id INTEGER NOT NULL,
    actor_trainer_id INTEGER NOT NULL,
    action TEXT NOT NULL,
    before_json TEXT NULL,
    after_json TEXT NOT NULL,
    reason TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (definition_id) REFERENCES shop_order_status_definitions(id) ON DELETE RESTRICT
)
SQL);
            if (!$mysql) $pdo->exec('CREATE INDEX idx_shop_status_event_definition ON shop_order_status_definition_events(definition_id,id)');
        }
        if (!$columnExists($pdo, 'shop_orders', 'customer_status_id')) {
            $pdo->exec('ALTER TABLE shop_orders ADD COLUMN customer_status_id ' . ($mysql ? 'BIGINT UNSIGNED' : 'INTEGER') . ' NULL');
        }
        if (!$columnExists($pdo, 'shop_products', 'delivery_mode')) {
            $pdo->exec("ALTER TABLE shop_products ADD COLUMN delivery_mode " . ($mysql ? "VARCHAR(24)" : "TEXT") . " NOT NULL DEFAULT 'pickup_only'");
        }
        if ($mysql) {
            $index = $pdo->query("SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='shop_orders' AND INDEX_NAME='idx_shop_order_customer_status' LIMIT 1")->fetchColumn();
            if (!$index) $pdo->exec('ALTER TABLE shop_orders ADD KEY idx_shop_order_customer_status (customer_status_id)');
            $fk = $pdo->query("SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='shop_orders' AND CONSTRAINT_NAME='fk_shop_order_customer_status' LIMIT 1")->fetchColumn();
            if (!$fk) $pdo->exec('ALTER TABLE shop_orders ADD CONSTRAINT fk_shop_order_customer_status FOREIGN KEY (customer_status_id) REFERENCES shop_order_status_definitions(id) ON DELETE RESTRICT');
        } else {
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_shop_order_customer_status ON shop_orders(customer_status_id)');
        }
    },
    'verify' => static function (PDO $pdo) use ($columnExists, $tableExists): bool {
        return $tableExists($pdo, 'shop_order_status_definitions')
            && $tableExists($pdo, 'shop_order_status_definition_events')
            && $columnExists($pdo, 'shop_orders', 'customer_status_id')
            && $columnExists($pdo, 'shop_products', 'delivery_mode');
    },
];
