<?php
declare(strict_types=1);

$columnExists = static function (PDO $pdo, string $column): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $query=$pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='shop_orders' AND COLUMN_NAME=?");
        $query->execute([$column]);
        return (bool)$query->fetchColumn();
    }
    foreach ($pdo->query('PRAGMA table_info(shop_orders)')->fetchAll(PDO::FETCH_ASSOC) as $row) if ($row['name']===$column) return true;
    return false;
};
$tableExists = static function (PDO $pdo, string $table): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $query=$pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $query->execute([$table]);return (bool)$query->fetchColumn();
    }
    $query=$pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
    $query->execute([$table]);return (bool)$query->fetchColumn();
};

return [
    'id'=>'20261019110000_shop_ppl_box',
    'up'=>static function (PDO $pdo) use ($columnExists,$tableExists): void {
        if (!$tableExists($pdo,'shop_orders')) throw new RuntimeException('Chybí tabulka objednávek.');
        $mysql=(string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
        if (!$tableExists($pdo,'shop_ppl_settings')) $pdo->exec($mysql ? <<<'SQL'
CREATE TABLE shop_ppl_settings (
    id TINYINT UNSIGNED PRIMARY KEY,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    price_minor BIGINT UNSIGNED NOT NULL DEFAULT 0,
    widget_api_key VARCHAR(255) NOT NULL DEFAULT '',
    updated_by_trainer_id INT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL : <<<'SQL'
CREATE TABLE shop_ppl_settings (
    id INTEGER PRIMARY KEY,
    enabled INTEGER NOT NULL DEFAULT 0,
    price_minor INTEGER NOT NULL DEFAULT 0,
    widget_api_key TEXT NOT NULL DEFAULT '',
    updated_by_trainer_id INTEGER NULL,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL);
        if (!$tableExists($pdo,'shop_ppl_settings_events')) $pdo->exec($mysql ? <<<'SQL'
CREATE TABLE shop_ppl_settings_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_trainer_id INT NOT NULL,
    before_json LONGTEXT NOT NULL,
    after_json LONGTEXT NOT NULL,
    reason VARCHAR(1000) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL : <<<'SQL'
CREATE TABLE shop_ppl_settings_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    actor_trainer_id INTEGER NOT NULL,
    before_json TEXT NOT NULL,
    after_json TEXT NOT NULL,
    reason TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL);
        $columns=[
            'shipping_amount_minor'=>$mysql?'BIGINT UNSIGNED NOT NULL DEFAULT 0':'INTEGER NOT NULL DEFAULT 0',
            'ppl_access_point_code'=>$mysql?'VARCHAR(24) NULL':'TEXT NULL',
            'ppl_access_point_name'=>$mysql?'VARCHAR(255) NULL':'TEXT NULL',
            'ppl_access_point_address'=>$mysql?'VARCHAR(500) NULL':'TEXT NULL',
            'ppl_tracking_number'=>$mysql?'VARCHAR(64) NULL':'TEXT NULL',
            'ppl_dispatched_at'=>$mysql?'DATETIME NULL':'TEXT NULL',
        ];
        foreach ($columns as $name=>$definition) if (!$columnExists($pdo,$name)) $pdo->exec('ALTER TABLE shop_orders ADD COLUMN '.$name.' '.$definition);
    },
    'verify'=>static function (PDO $pdo) use ($columnExists,$tableExists): bool {
        if (!$tableExists($pdo,'shop_ppl_settings')||!$tableExists($pdo,'shop_ppl_settings_events')) return false;
        foreach (['shipping_amount_minor','ppl_access_point_code','ppl_access_point_name','ppl_access_point_address','ppl_tracking_number','ppl_dispatched_at'] as $column) if (!$columnExists($pdo,$column)) return false;
        return true;
    },
];
