<?php
declare(strict_types=1);

$categoryMetaTableExists = static function (PDO $pdo, string $table): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1'
        );
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1");
    $statement->execute([$table]);
    return (bool)$statement->fetchColumn();
};

/** @return array<string,mixed>|null */
$categoryMetaColumn = static function (PDO $pdo, string $table, string $column): ?array {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1'
        );
        $statement->execute([$table,$column]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ((string)$row['name'] === $column) return $row;
    }
    return null;
};

return [
    'id' => '20260820220000_shop_category_meta',
    'up' => static function (PDO $pdo) use ($categoryMetaTableExists): void {
        foreach (['shop_product_categories','shop_member_category_rules','treneri'] as $required) {
            if (!$categoryMetaTableExists($pdo,$required)) {
                throw new RuntimeException('Required category metadata table is missing: ' . $required);
            }
        }
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if (!$categoryMetaTableExists($pdo,'shop_category_meta')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE shop_category_meta (
                    category_path VARCHAR(500) NOT NULL PRIMARY KEY,
                    display_name VARCHAR(255) NOT NULL,
                    parent_path VARCHAR(500) NULL,
                    sort_order INT NOT NULL DEFAULT 0,
                    visible_in_menu TINYINT(1) NOT NULL DEFAULT 1,
                    description TEXT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    KEY idx_shop_category_parent (parent_path,sort_order),
                    KEY idx_shop_category_menu (visible_in_menu,sort_order)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE shop_category_meta (
                    category_path TEXT PRIMARY KEY,
                    display_name TEXT NOT NULL,
                    parent_path TEXT NULL,
                    sort_order INTEGER NOT NULL DEFAULT 0,
                    visible_in_menu INTEGER NOT NULL DEFAULT 1,
                    description TEXT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
                )
                SQL);
        }
        if (!$categoryMetaTableExists($pdo,'shop_category_meta_events')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE shop_category_meta_events (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    category_path VARCHAR(500) NOT NULL,
                    actor_type VARCHAR(24) NOT NULL,
                    actor_id BIGINT NOT NULL,
                    action VARCHAR(48) NOT NULL,
                    before_json LONGTEXT NULL,
                    after_json LONGTEXT NOT NULL,
                    reason TEXT NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_shop_category_event_path (category_path,id),
                    KEY idx_shop_category_event_created (created_at,id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE shop_category_meta_events (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    category_path TEXT NOT NULL,
                    actor_type TEXT NOT NULL,
                    actor_id INTEGER NOT NULL,
                    action TEXT NOT NULL,
                    before_json TEXT NULL,
                    after_json TEXT NOT NULL,
                    reason TEXT NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
                )
                SQL);
            if (!$mysql) {
                $pdo->exec('CREATE INDEX idx_shop_category_event_path ON shop_category_meta_events(category_path,id)');
            }
        }
    },
    'verify' => static function (PDO $pdo) use ($categoryMetaTableExists,$categoryMetaColumn): bool {
        if (!$categoryMetaTableExists($pdo,'shop_category_meta')
            || !$categoryMetaTableExists($pdo,'shop_category_meta_events')) return false;
        foreach (['category_path','display_name','parent_path','sort_order','visible_in_menu','description','created_at','updated_at'] as $column) {
            if ($categoryMetaColumn($pdo,'shop_category_meta',$column) === null) return false;
        }
        foreach (['category_path','actor_type','actor_id','action','before_json','after_json','reason','created_at'] as $column) {
            if ($categoryMetaColumn($pdo,'shop_category_meta_events',$column) === null) return false;
        }
        return true;
    },
];
